<?php
/**
 * This script is intended to clear out private data on a daily basis.
 */

require_once('unblocklib.php');
require_once('exceptions.php');

echo "Starting to clear out private data from closed appeals.\n";

try{

	$db = connectToDB();
	
	// appeals closed more than six days ago
	$closedAppealsSubquery = "SELECT DISTINCT appealID FROM actionAppealLog WHERE " .
		"comment = 'Closed' AND timestamp < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 6 DAY)";

	// grab appeals and IPs
	$query = "SELECT appealID, ip, appealText, intendedEdits, otherInfo FROM appeal WHERE appealID = ANY (" . $closedAppealsSubquery . ")" .
				" AND email IS NOT NULL" .
				" AND ip LIKE '%.%.%.%'";
		
	echo "Running query: " . $query . "\n";

	$result = mysql_query($query, $db);

	if(!$result){
		throw new UTRSDatabaseException(mysql_error($db));
	}
	$rows = mysql_num_rows($result);
	if($rows == 0){
		echo "There are no recently closed appeals that need data removed.\n";
	}
	else{

		echo "Getting appeal IDs from " . $rows . " appeals...\n";

		echo "Starting to remove private data...\n";

		for($i = 0; $i < $rows; $i++){
			$appeal = mysql_fetch_array($result);
			echo "Processing appeal #" . $appeal['appealID'] . "\n";
			
			$query = "UPDATE appeal SET email = NULL, ip = '" . md5($appeal['ip']) . "' WHERE appealID = '" . $appeal['appealID'] . "'";
			echo "\tRunning query: " . $query . "\n";
			$update = mysql_query($query, $db);
			if(!$update){
				throw new UTRSDatabaseException(mysql_error($db));
			}
			
			//Kill comments in all text sections also UTRS-93 --DQ
			//New variables for search and replace
			$replaceText = array(
  			'appealText' => $appeal['appealText']  
  			'intendedEdits' => $appeal['intendedEdits']
  			'otherInfo' => $appeal['otherInfo']
      );
			
			//Regex seach and replace
			
			preg_replace("\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b", "*IP REMOVED*" , $replaceText);
			preg_replace("^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/(\d|[1-2]\d|3[0-2]))$", "*IP RANGE REMOVED*" , $replaceText);
			
			//Update the DB
			$query = "UPDATE appeal SET appealText = '" . $replaceText['appealText'] . "', intendedEdits = '" . $replaceText['intendedEdits'] . "', otherInfo = '" . $replaceText['otherInfo'] . "'  WHERE appealID = '" . $appeal['appealID'] . "'";
			echo "\tRunning query: " . $query . "\n";
			$update = mysql_query($query, $db);
			if(!$update){
				throw new UTRSDatabaseException(mysql_error($db));
			}
			//End UTRS-93 Fix --DQ
			
			// else
			$query = "DELETE FROM cuData WHERE appealID = '" . $appeal['appealID'] . "'";
			echo "\tRunning query: " . $query . "\n";
			$delete = mysql_query($query, $db);
			if(!$delete){
				throw new UTRSDatabaseException(mysql_error($db));
			}
			echo "Appeal #" . $appeal['appealID'] . " complete.\n";
		}
	}

	echo "Script completed successfully!\n";

}
catch(Exception $e){
	echo "ERROR - Exception thrown!\n";
	echo $e->getMessage() . "\n";
	echo "Script incomplete.\n";
	
	$body = "O Great Creators,\n\n";
	$body .= "The private data removal script has failed. The error message received was:\n";
	$body .= $e->getMessage() . "\n";
	$body .= "Please check the database to resolve this issue and ensure that private data is removed on schedule.\n\n";
	$body .= "Thanks,\nUTRS";
	$subject = "URGENT: Private data removal failed";
	
	mail('unblock@toolserver.org', $subject, $body);
}

exit;
?>