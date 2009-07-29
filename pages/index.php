<?php

//error_reporting(E_ALL);
//ini_set("display_errors", 1);

$time = microtime( 1 );//Calculate time in microseconds to calculate time taken to execute

include( '/home/soxred93/public_html/common/header.php' );
include( '/home/soxred93/stats.php' );
require_once( '/home/soxred93/database.inc' );

$tool = 'PagesCreated';
$surl = "http://toolserver.org".$_SERVER['REQUEST_URI'];
if (isset($_GET['wiki']) && isset($_GET['lang']) && isset($_GET['name'])) {
	addStat( $tool, $surl, $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'] );//Stat checking
}
unset($tool, $surl);

//Debugging stuff
function pre( $array ) {
	echo "<pre>";
	print_r( $array );
	echo "</pre>";
}

//Output header
echo '<div id="content">
	<table class="cont_table" style="width:100%;">
	<tr>
	<td class="cont_td" style="width:75%;">
	<h2 class="table">Top Namespace Edits</h2>';
	
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

//Get array of namespaces
function getNamespaces() {
	$namespaces = getUrl( 'http://en.wikipedia.org/w/api.php?action=query&meta=siteinfo&siprop=namespaces&format=php' );
	$namespaces = unserialize( $namespaces );
	$namespaces = $namespaces['query']['namespaces'];


	unset( $namespaces[-2] );
	unset( $namespaces[-1] );

	$namespaces[0]['*'] = "Mainspace";
	
	$namespacenames = array();
	foreach ($namespaces as $value => $ns) {
		$namespacenames[$value] = $ns['*'];
	}
	return $namespacenames;
}

if( !isset( $_GET['name'] ) ) {
	$msg = 'Welcome to X!\'s create pages tool!<br /><br />
		<form action="http://toolserver.org/~soxred93/pages/index.php" method="get">
		Username: <input type="text" name="name" /><br />
		Namespace: <select name="namespace">
		<option value="0">Main</option>
		<option value="1">Talk</option>
		<option value="2">User</option>
		<option value="3">User talk</option>
		<option value="4">Wikipedia</option>
		<option value="5">Wikipedia talk</option>
		<option value="6">File</option>
		<option value="7">File talk</option>
		<option value="8">MediaWiki</option>
		<option value="9">MediaWiki talk</option>
		<option value="10">Template</option>
		<option value="11">Template talk</option>
		<option value="12">Help</option>
		<option value="13">Help talk</option>
		<option value="14">Category</option>
		<option value="15">Category talk</option>
		<option value="100">Portal</option>
		<option value="101">Portal talk</option>
		</select><br />
		Redirects: <select name="redirects">
		<option value="none">Include redirects and non-redirects</option>
		<option value="onlyredirects">Exclude non-redirects</option>
		<option value="noredirects">Exclude redirects</option>
		</select><br />
		<input type="submit" value="Submit" />
		</form><br /><hr />';
		
	toDie( $msg );
}

$oldname = ucfirst( ltrim( rtrim( str_replace( array('&#39;','%20'), array('\'',' '), $_GET['name'] ) ) ) );
$oldname = urldecode($oldname);
$oldname = str_replace('_', ' ', $oldname);
$oldname = str_replace('/', '', $oldname);
$name = mysql_escape_string( $oldname );
$namespace = mysql_escape_string( $_GET['namespace'] );
$nsnames = getNamespaces();

mysql_connect( 'sql-s1',$toolserver_username,$toolserver_password );
@mysql_select_db( 'enwiki_p' ) or toDie( "MySQL ERROR! ". mysql_error() );


function getReplag() {
	$query = "SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(rc_timestamp) as replag FROM recentchanges ORDER BY rc_timestamp DESC LIMIT 1";
		$result = mysql_query( $query );
		if( !$result ) toDie( "MySQL ERROR! ". mysql_error() );
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
		$r .= floor($secs/$week) . wfMsg( 'w' );
		$secs %= $week;
	}
	if ($secs > $day) {
		$r .= floor($secs/$day) . wfMsg( 'd' );
		$secs %= $day;
	}
	if ($secs > $hour) {
		$r .= floor($secs/$hour) . wfMsg( 'h' );
		$secs %= $hour;
	}
	if ($secs > $minute) {
		$r .= floor($secs/$minute) . wfMsg( 'm' );
		$secs %= $week;
	}
	if ($secs > $second) {
		$r .= floor(($secs/$second)/100) . wfMsg( 's' );
	}
	
	return $r;
}

$query = "SELECT user_editcount, user_id FROM user WHERE user_name = '".$name."';";
$result = mysql_query( $query );
if( !$result ) toDie( "MySQL ERROR! ". mysql_error() );
$row = mysql_fetch_assoc( $result );
$edit_count_total = $row['user_editcount'];
$uid = $row['user_id'];
unset( $row, $query, $result );

if( $uid == 0 ) {
	toDie( wfMsg('nosuchuser', $name ) );
}

if( $_GET['redirects'] == "onlyredirects" ) {
	$redirectstatus = "AND page_is_redirect = '1'";
}
elseif( $_GET['redirects'] == "noredirects" ) {
	$redirectstatus = "AND page_is_redirect = '0'";
}
else {
	$redirectstatus = null;
}

$query = "/* SLOW_OK *//* CREATED */ SELECT distinct 
page_title,page_is_redirect,page_id FROM page JOIN revision AS r on 
page_id = r.rev_page WHERE r.rev_user_text = '$name' AND page_namespace = '$namespace' $redirectstatus ORDER BY rev_timestamp DESC;";
$result = mysql_query($query);

if(!$result) Die("ERROR: No result returned.");
echo "<h2>Pages created by $oldname:</h2>\n<ol>\n";
$i = 1;
while ($row = mysql_fetch_assoc($result)) {
    $pagename = $row['page_title'];
    $first = mysql_query("SELECT rev_user_text FROM revision where rev_page = '".$row['page_id']."' order by rev_id ASC limit 1;");
    $first = mysql_fetch_assoc($first);
    if($first['rev_user_text'] != $oldname) { continue; }
    if( $nsnames[$namespace] != "Mainspace" ) {
    	echo "<li><a href=\"http://en.wikipedia.org/wiki/".$nsnames[$namespace].":$pagename\">$pagename</a>";
    }
    else {
    	echo "<li><a href=\"http://en.wikipedia.org/wiki/$pagename\">$pagename</a>";
    }
    if( $row['page_is_redirect'] == 1 ) {
    	echo " (redirect)";
    }
    echo "</li>\n";
    $i++;
    if( $i > 100 && !isset( $_GET['getall'] ) ) { break; }
}

if( $i > 100 && !isset( $_GET['getall'] ) ) { 
	echo "<br /><br /><b>Trunctuated to 100 pages</b>"; 
	echo "<br /><i>To see all results, please go to <a href=\"http://toolserver.org".$_SERVER['REQUEST_URI']."&getall=1\">this link</a>";
}
echo "</ol>\n";

//Calculate time taken to execute

$exectime = number_format(microtime( 1 ) - $time, 2, '.', '');
echo "<br /><hr><span style=\"font-size:100%;\">Excecuted in ". $exectime ." seconds.</span>";
echo "<br />Taken ".number_format((memory_get_usage() / (1024 * 1024)), 2, '.', '')." megabytes of memory to execute.";

//Output footer
include( '/home/soxred93/public_html/common/footer.php' );

?>
