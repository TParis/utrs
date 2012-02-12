<?php
require_once("public_html/src/unblocklib.php");

echo "Valid emails: \n";
echo "user@domain.com --> " . testWrapper("user@domain.com") . "\n";
echo "imnotasockpuppet@imasockpuppet.org --> " . testWrapper("imnotasockpuppet@imasockpuppet.org") . "\n";
echo "email.with.dots@dots.email.com --> " . testWrapper("email.with.dots@dots.email.com") . "\n";
echo "foo@[127.0.0.1] --> " . testWrapper("foo@[127.0.0.1]") . "\n";
echo "\"user\"@domain.com --> " . testWrapper("\"user\"@domain.com") . "\n";
echo "0@a --> " . testWrapper("0@a") . "\n";
echo "!#$%&'*+-/=?^_`{}|~@example.org --> " . testWrapper("!#$%&'*+-/=?^_`{}|~@example.org") . "\n";

echo "Invalid emails:\n";
echo "not.an.email --> " . testWrapper("not.an.email") . "\n";
echo "Invalid.@email.com --> " . testWrapper("Invalid.@email.com") . "\n";
echo "Invalid..123@email.com --> " . testWrapper("Invalid..123@email.com") . "\n";
echo "invalid@123@email.com --> " . testWrapper("invalid@123@email.com") . "\n";
echo "invalid<email>addy@email.com --> " . testWrapper("invalid<email>addy@email.com") . "\n";
echo "invalid\"addy@email.com --> " . testWrapper("invalid\"addy@email.com") . "\n";
echo "invalid\"addy@email.com --> " . testWrapper("invalid\"addy@email.com") . "\n";
echo "invalid\\\"addy@email.com --> " . testWrapper("invalid\\\"addy@email.com") . "\n";


function testWrapper($email){
	if(newValidEmail($email)){
		return "VALID";
	}
	return "NO";
}

function newValidEmail($email){
	// if does not contain an @ or @ is first character
	if(strpos($email, "@") === false || strpos($email, "@") === 0){
		return false;
	}
	// get the domain and user parts, assumed to be separated
	// at the last @ in the email addy
	$user = substr($email, 0, strrpos($email, "@"));
	$domain = substr($email, strrpos($email, "@") + 1);
	// validate user side
	$userArray = str_split($email, 1);
	$length = sizeof($userArray);
	// local part may only be 64 characters long
	if($length > 64){
		return false;
	}
	$inQuotes = false;
	$inComment = false;
	$escapeNext = false;
	// this is a somewhat slow way of doing things, but avoids potential
	// for errors.
	for($i = 0; $i < $length; $i++){
		$char = $userArray[$i];
		// normal stuff
		if(preg_match("/^[a-zA-Z0-9!#$%&'*+\-\/=?^+`{}|~]$/", $char)){
			$escapeNext = false; // don't need to escape these, but don't see why you can't
			continue; // nothing to worry about
		}
		// special characters not including ., (, ), ", \
		if(preg_match("/^[ ,:;<>@\[\]]$/", $char)){
			if($escapeNext){
				$escapeNext = false;
				continue; // character properly escaped, move on
			}
			if($inQuotes || $inComment){
				continue; // nobody cares, move on
			}
			return false; // illegal character
		}
		// dot
		if(preg_match("/^[.]$/", $char)){
			if($inQuotes || $inComment){
				continue; // nobody cares, move on
			}
			// if first, last, or next character is also a dot
			if($i == 0 || $i == ($length - 1) || preg_match("/^[.]$/", $userArray[$i+1])){
				return false;
			}
			$escapeNext = false;
			continue; // otherwise we don't care
		}
		// quote
		if(preg_match("/^[\"]$/", $char)){
			if($inComment){
				continue; // nobody cares, move on
			}
			if(!$inQuotes){
				// if first or previous character is a dot
				if($i == 0 || preg_match("/^[.]$/", $userArray[$i-1])){
					$inQuotes = true;
					continue; // start of valid quoted string, carry on
				}
			}
			else{
				// if last or next character is a dot
				if($i == ($length - 1) || preg_match("/^[.]$/", $userArray[$i+1])){
					$inQuotes = false;
					continue; // end of valid quoted string, carry on
				}
			}
			if($escapeNext){
				$escapeNext = false;
				continue; // escaped, carry on
			}
			return false; // otherwise invalid character
		}
		// backslash
		if(preg_match("/^[\\\\]$/", $char)){
			if($escapeNext){
				$escapeNext = false;
				continue; // escaped, carry on
			}
			// if last
			else if($i == ($length - 1)){
				return false; // can't be last, as that escapes the @, 
				              // making the address not actually have an @
			}
			else{
				$escapeNext = true;
				continue; // escape whoever's next, carry on
			}
		}
		// open paren
		if(preg_match("/^[(]$/", $char)){
			if($escapeNext){
				return false; // not a valid character by itself
			}
			$inComment = true;
			continue; // keep going
		}
		// close paren
		if(preg_match("/^[)]$/", $char)){
			if($inComment){
				if($escapeNext){
					$escapeNext = false;
					continue; // escaped, carry on
				}
				$inComment = false;
				continue;
			}
			return false; // otherwise invalid character
		}
		return false; // if not told to continue by now, the character is invalid
	}
	if($inQuotes || $inComment || $escapeNext){
		return false;
	}
	// end user validation
	// begin domain validation
	// remove comments
	$domain = preg_replace("/\(.*?\)/", "", $domain);
	// IP address wrapped in [] (e.g. [127.0.0.1])
	if(preg_match("/^\[((2[0-4][0-9]|25[0-5]|1[0-9][0-9]|[1-9][0-9]|[0-9])\.){3,3}".
	   "(2[0-4][0-9]|25[0-5]|1[0-9][0-9]|[1-9][0-9]|[0-9])\]$/", $domain)){
		// that's ok
	}
	else if(strlen($domain) > 255){
		return false;
	}
	// apparently valid domain
	else if(preg_match("/^[a-zA-Z0-9-]+?(\.[a-zA-Z0-9-]+?){0,}$/", $domain)){
		// that's ok
	}
	
	return true; // yay!
}
?>