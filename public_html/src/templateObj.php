<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once('exceptions.php');
require_once('unblocklib.php');
require_once('userObject.php');

class Template{
	private $templateID;
	private $name;
	private $text;
	private $lastEditTime;
	private $lastEditUser;
	
	public function __construct(array $vars, $fromDB){
		debug('in constructor for MgmtLog');
		if($fromDB){
			$this->templateID = $vars['templateID'];
			$this->name = $vars['name'];
			$this->text = $vars['text'];
			$this->lastEditTime = $vars['lastEditTime'];
			$this->lastEditUser = User::getUserById($vars['lastEditUser']);
		}
		else{
			$this->name = $vars['name'];
			$this->text = $vars['text'];
			$this->lastEditUser = getCurrentUser();
			
			$this->insert();
		}
	}
	
	private function insert(){
		$db = connectToDB(true); // going to take place prior to a potential redirection
		
		$query = "INSERT INTO template (name, text, lastEditUser) VALUES ('";
		$query .= $this->name . "', '";
		$query .= $this->text . "', '";
		$query .= $this->lastEditUser->getUserId() . "')";
		
		debug($query);
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		$query = "SELECT templateID, lastEditTime FROM template WHERE name='" . $this->name . "'";
		
		debug($query);
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		$data = mysql_fetch_assoc($result);
		
		$this->templateID = $data['templateID'];
		$this->lastEditTime = $data['lastEditTime'];
	}
	
	public static function getTemplateById($id){
		$db = connectToDB();
		
		$query = 'SELECT * FROM template WHERE templateID=\'' . $id . '\'';
		
		$result = mysql_query($query, $db);
		if(!$result){
			$error = mysql_error($db);
			throw new UTRSDatabaseException($error);
		}
		if(mysql_num_rows($result) == 0){
			throw new UTRSDatabaseException('No results were returned for template ID ' . $id);
		}
		if(mysql_num_rows($result) != 1){
			throw new UTRSDatabaseException('Please contact a tool developer. More '
				. 'than one result was returned for template ID ' . $id);
		}
		
		$values = mysql_fetch_assoc($result);
		
		return new Template($values, true);
	}
	
	public function getId(){
		return $this->templateID;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function getText(){
		return $this->text;
	}
	
	public function getLastEditTime(){
		return $this->lastEditTime;
	}
	
	public function getLastEditUser(){
		return $this->lastEditUser;
	}
	
	/**
	 * The database is set to update the lastEditTime field whenever we update.
	 * This function grabs that value after we change something.
	 * @param database_reference $db
	 */
	private function updateLastEditTime($db){
		
		$query = "SELECT lastEditTime FROM template WHERE templateID='" . $this->templateID . "'";
		
		debug($query);
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		$data = mysql_fetch_assoc($result);
		
		$this->lastEditTime = $data['lastEditTime'];
	}
	
	/**
	 * When updating both name and text, call me first! Name is subject to a UNIQUE constraint,
	 * text is not. Calling it first could leave things in an inconsistent state.
	 * 
	 * @param string $newName
	 * @throws UTRSDatabaseException
	 */
	public function setName($newName){
		$user = getCurrentUser();
		$sqlName = mysql_real_escape_string($newName);
		
		$query = "UPDATE template SET name='" . $sqlName . "', lastEditUser='" . $user->getUserId() . 
					"' WHERE templateID='" . $this->templateID . "'";
		
		$db = connectToDB();
		
		debug($query);
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		$this->name = $newName;
		$this->lastEditUser = $user;
		
		$this->updateLastEditTime($db);
	}
	
	/**
	 * When updating both name and text, call me first! Name is subject to a UNIQUE constraint,
	 * text is not. Calling it first could leave things in an inconsistent state.
	 * 
	 * @param string $newName
	 * @throws UTRSDatabaseException
	 */
	public function setText($newText){		
		$user = getCurrentUser();
		$sqlText = mysql_real_escape_string($newText);
		
		$query = "UPDATE template SET text='" . $sqlText . "', lastEditUser='" . $user->getUserId() . 
					"' WHERE templateID='" . $this->templateID . "'";
		
		$db = connectToDB();
		
		debug($query);
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		$this->text = $newText;
		$this->lastEditUser = $user;
		
		$this->updateLastEditTime($db);
	}
	
	public function delete(){
		$query = "DELETE FROM template WHERE templateID='" . $this->templateID . "'";
		
		$db = connectToDB();
		
		debug($query);
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		$this->templateID = null;
		$this->name = null;
		$this->text = null;
		$this->lastEditTime = null;
		$this->lastEditUser = null;
	}
}

?>