<?php

// Config.php - DB_HOST DB_PORT DB_NAME DB_USER DB_PASS
require_once("config.php");

class MyPDO extends PDO {

	private $last_error;

	public function __construct() {
		$dns = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME;
		return parent::__construct($dns, DB_USER, DB_PASS);
	}

	public function errorMsg(){
		$e = $this->last_error;
		$this->last_error = NULL;
		return $e;
	}

	public static function genRandToken(){
		return md5(mt_rand());
	}

	public static function genRandPassword(){
		$pass = "";
		for ($i = 0; $i < 3; $i++) {
			$pass .= chr(mt_rand(97,122)); // lowercase
			$pass .= chr(mt_rand(48,57)); // number
			$pass .= chr(mt_rand(65,90)); // uppercase
		}
		return $pass;
	}

	public function createUser($agency, $firstname, $lastname, $email, $auth, $mobile, $mobileprovider){
		if (empty($mobile)) $mobile = NULL;
		if (empty($mobileprovider)) $mobileprovider = NULL;
		$stmt = $this->prepare("INSERT INTO users (agency, firstname, lastname, email, auth, mobile, mobile_provider_id) VALUES(?,?,?,?,?,?,?);");
		$stmt->execute(array($agency, $firstname, $lastname, $email, $auth, $mobile, $mobileprovider));
		$error = $stmt->errorInfo();
		if ($error[1] == 1062) // MySQL duplicate code
			$this->last_error = "The email '$email' already has an account. You will need to use another, sorry.";
		return $this->lastInsertId();
	}

	public function deleteUser($id){

	}

	public function getUser($id){
		$stmt = $this->prepare("SELECT * FROM users WHERE id = ?");
		$stmt->execute(array($id));
		return $stmt->fetch();
	}

	public function verifyUser($id, $token){
		$user = $this->getUser($id);
		$result = ($user && $user['auth'] == $token);
		return $result;
	}

	public function getUsersEmailLapse($timelapse, $timeexpire){
		$stmt = $this->prepare("SELECT *, TIMESTAMPDIFF(SECOND, created, NOW()) AS lapse FROM users HAVING active=1 AND lapse > :lapse AND lapse < :lapse+:expire*2 AND TIMESTAMPDIFF(SECOND, lastemailed, NOW()) > :expire");
		$stmt->execute(array(":lapse"=>$timelapse, ":expire"=>$timeexpire));
		return $stmt->fetchAll();
	}

	public function getUsersLapse($timelapse){
		$stmt = $this->prepare("SELECT * FROM users WHERE active=1 AND TIMESTAMPDIFF(SECOND, created, NOW()) > :lapse");
		$stmt->execute(array(":lapse"=>$timelapse));
		return $stmt->fetchAll();
	}

}

?>