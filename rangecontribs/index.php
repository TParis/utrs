<?php

$time = microtime( 1 );//Calculate time in microseconds to calculate time taken to execut

//error_reporting(E_ALL);
ini_set("display_errors", 1);

include( '/home/soxred93/public_html/common/header.php' );
include( '/home/soxred93/stats.php' );
include( '/home/soxred93/wikibot.classes.php' );
$tool = 'CidrContribs';
$surl = "http://toolserver.org".$_SERVER['REQUEST_URI'];
if (isset($_GET['wiki']) && isset($_GET['lang']) && isset($_GET['name'])) {
	addStat( $tool, $surl, $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'] );//Stat checking
}
unset($tool, $surl);

$namespaces = array(
	'0' => '',
	'1' => 'Talk:',
	'2' => 'User:',
	'3' => 'User talk:',
	'4' => 'Wikipedia:',
	'5' => 'Wikipedia talk:',
	'6' => 'File:',
	'7' => 'File talk:',
	'8' => 'MediaWiki:',
	'9' => 'MediaWiki talk:',
	'10' => 'Template:',
	'11' => 'Template talk:',
	'12' => 'Help:',
	'13' => 'Help talk:',
	'14' => 'Category:',
	'15' => 'Category talk:',
	'100' => 'Portal:',
	'101' => 'Portal talk:',
);

//Output header
echo '<div id="content">
	<table class="cont_table" style="width:100%;">
	<tr>
	<td class="cont_td" style="width:75%;">
	<h2 class="table">Range Contributions</h2>';

if( !isset( $_GET['cidr'] ) ) {
	$msg = 'Welcome to X!\'s automated edits counter!<br /><br />
		<form action="http://toolserver.org/~soxred93/rangecontribs/index.php" method="get">
		IP range (in format 127.0.0.1/16): <input type="text" name="cidr" /><br />
		<input type="submit" />
		</form><br />';
	toDie( $msg );
}

$mysql = mysql_connect( 'enwiki-p.db.toolserver.org',$toolserver_username,$toolserver_password );
@mysql_select_db( 'enwiki_p', $mysql ) or toDie( "MySQL error, please report to X! using <a href=\"http://en.wikipedia.org/wiki/User:X!/Bugs\">the bug reporter.</a> Be sure to report the following SQL error when reporting:<br /><pre>".mysql_error()."</pre>" );

$oldcidr = $_GET['cidr'];
$cidr = mysql_real_escape_string( $oldcidr, $mysql );

if( !preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $cidr ) ) {
	toDie( "Not a valid CIDR range." );
}

$replag = getReplag();
if ($replag[0] > 120) {
	echo '<h2 class="replag">'."Replag is high, change in the last {$replag[1]} will nto be shown.".'</h2>';
}
unset( $replag );

//Start the calculation

$cidr_info = calcCIDR( $cidr );
$ip_prefix = findMatch( $cidr_info['begin'], $cidr_info['end'] );

$query = "SELECT page_title,rev_id,rev_user_text,rev_timestamp,rev_minor_edit,rev_comment,page_namespace FROM revision JOIN page ON page_id = rev_page WHERE rev_user_text LIKE '{$ip_prefix}%' AND rev_user = '0' ORDER BY rev_timestamp DESC;";
$result = mysql_query( $query, $mysql );
if( !$result ) toDie( "MySQL error, please report to X! using <a href=\"http://en.wikipedia.org/wiki/User:X!/Bugs\">the bug reporter.</a> Be sure to report the following SQL error when reporting:<br /><pre>".mysql_error()."</pre>" );

echo "<h1>Result for $oldcidr</h1>\n";

echo "<b>Starting IP:</b> {$cidr_info['begin']}<br />\n";
echo "<b>Ending IP:</b> {$cidr_info['end']}<br />\n";
echo "<b>Number of possible IPs:</b> {$cidr_info['count']}<br />\n";

if( mysql_num_rows( $result ) == 0 ) {
	echo "No contribs found.";
}

echo "<ul>\n";
while( $row = mysql_fetch_assoc( $result ) ) {
	if( substr( addZero( decbin( ip2long( $row['rev_user_text'] ) ) ), 0, $cidr_info['suffix'] ) != $cidr_info['shortened'] ) continue;
	$title = $namespaces[$row['page_namespace']].$row['page_title'];
	$urltitle = $namespaces[$row['page_namespace']].urlencode($row['page_title']);
	$timestamp = $row['rev_timestamp'];
	$year = substr($timestamp, 0, 4);
    $month = substr($timestamp, 4, 2);
    $day = substr($timestamp, 6, 2);
    $hour = substr($timestamp, 8, 2);
    $minute = substr($timestamp, 10, 2);
    $second = substr($timestamp, 12, 2);
    $date = date('M d, Y H:i:s', mktime($hour, $minute, $second, $month, $day, $year));

	echo "<li>\n";
	echo '(<a href="http://en.wikipedia.org/w/index.php?title='.$urltitle.'&amp;diff=prev&amp;oldid='.urlencode($row['rev_id']).'" title="'.$title.'">diff</a>) ';

	echo '(<a href="http://en.wikipedia.org/w/index.php?title='.$urltitle.'&amp;action=history" title="'.$title.'">hist</a>) . . ';
	
	if( $row['rev_minor_edit'] == '1' ) {
		echo '<span class="minor">m</span>  ';
	}
	
	echo '<a href="http://en.wikipedia.org/wiki/'.$urltitle.'" title="'.$title.'">'.$title.'</a>‎; ';
	
	echo $date . ' . . ';
	
	echo '<a href="http://en.wikipedia.org/wiki/User:'.$row['rev_user_text'].'" title="User:'.$row['rev_user_text'].'">'.$row['rev_user_text'].'</a> ';
	
	echo '(<a href="http://en.wikipedia.org/wiki/User_talk:'.$row['rev_user_text'].'" title="User talk:'.$row['rev_user_text'].'">talk</a>) ';
	
	echo '('.$row['rev_comment'].')';
	
	echo "<hr />\n</li>\n";
}
echo "</ul>\n";

//Calculate time taken to execute
$exectime = number_format(microtime( 1 ) - $time, 2, '.', '');
echo "<br /><hr><span style=\"font-size:100%;\">Executed in $exectime seconds</span>";
echo "<br />Taken ". number_format((memory_get_usage() / (1024 * 1024)), 2, '.', '')." megabytes of memory to execute.";

//Output footer
include( '/home/soxred93/public_html/common/footer.php' );

function calcCIDR( $cidr ) {
	$cidr = explode('/', $cidr);

	$cidr_base = $cidr[0];
	$cidr_range = $cidr[1];

	$cidr_base_bin = addZero( decbin( ip2long( $cidr_base ) ) );

	$cidr_shortened = substr( $cidr_base_bin, 0, $cidr_range );
	$cidr_difference = 32 - $cidr_range;

	$cidr_begin = $cidr_shortened . str_repeat( '0', $cidr_difference );
	$cidr_end = $cidr_shortened . str_repeat( '1', $cidr_difference );

	$ip_begin = long2ip( bindec( removeZero( $cidr_begin ) ) );
	$ip_end = long2ip( bindec( removeZero( $cidr_end ) ) );
	$ip_count = bindec( $cidr_end ) - bindec( $cidr_begin ) + 1;
	
	return array( 'begin' => $ip_begin, 'end' => $ip_end, 'count' => $ip_count, 'shortened' => $cidr_shortened, 'suffix' => $cidr_range );
}

function getReplag() {
	$query = "SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(rc_timestamp) as replag FROM recentchanges ORDER BY rc_timestamp DESC LIMIT 1";
		$result = mysql_query( $query );
		if( !$result ) toDie( wfMsg('mysqlerror', mysql_error() ) );
		$row = mysql_fetch_assoc( $result );
		$replag = $row['replag'];
		
		$seconds = floor($replag);
		$text = formatReplag($seconds);
	    
	    return array($seconds,$text);
}

function formatReplag($secs) {
	$second = 1;
	$minute = $second * 60;
	$hour = $minute * 60;
	$day = $hour * 24;
	$week = $day * 7;
	
	$r = '';
	if ($secs > $week) {
		$r .= floor($secs/$week) . 'w';
		$secs %= $week;
	}
	if ($secs > $day) {
		$r .= floor($secs/$day) . 'd';
		$secs %= $day;
	}
	if ($secs > $hour) {
		$r .= floor($secs/$hour) . 'h';
		$secs %= $hour;
	}
	if ($secs > $minute) {
		$r .= floor($secs/$minute) . 'm';
		$secs %= $week;
	}
	if ($secs > $second) {
		$r .= floor(($secs/$second)/100) . 's';
	}
	
	return $r;
}

//Access to the wiki
function getUrl($url) {
	$ch = curl_init();
    curl_setopt($ch,CURLOPT_MAXCONNECTS,100);
    curl_setopt($ch,CURLOPT_CLOSEPOLICY,CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_MAXREDIRS,10);
    curl_setopt($ch,CURLOPT_HEADER,0);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_TIMEOUT,30);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
    curl_setopt($ch,CURLOPT_HTTPGET,1);
    $data = curl_exec($ch);
    curl_close($ch);
    
    return $data;
}
	
//If there is a failure, do it pretty.
function toDie( $msg ) {
	echo $msg;
	include( '/home/soxred93/public_html/common/footer.php' );
	die();
}

function addZero ( $string ) {
    $count = 32 - strlen( $string );	
    for( $i = $count; $i>0; $i-- ) {
		$string = "0" . $string;
	}
	return $string;
}

function removeZero ( $string ) {
	$string = str_split( $string, 1 );
	foreach( $string as $val => $strchar ) {
		if( $strchar == 1 ) break;
		
		unset( $string[$val] );
	}
	
	$string = implode( "", $string );
	return $string;
}

function findMatch( $ip1, $ip2 ) {
	$ip1 = str_split( $ip1, 1 );
	$ip2 = str_split( $ip2, 1 );
	
	$match = null;
	foreach ( $ip1 as $val => $char ) {
		if( $char != $ip2[$val] ) break;
		
		$match .= $char;
	}
	
	return $match;
}

//Debugging stuff
function pre( $array ) {
	echo "<pre>";
	print_r( $array );
	echo "</pre>";
}

?>
