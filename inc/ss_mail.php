<?php

// Config.php - MAIL_HOST MAIL_PORT MAIL_USER MAIL_PASS
require_once(dirname(__FILE__)."/config.php");
require_once(dirname(__FILE__).'/class.phpmailer.php');

class SSMailer extends PHPMailer {

	public function __construct() {
		parent::__construct();

		$this->IsSMTP();
		$this->CharSet    = 'UTF-8';
		$this->Host       = MAIL_HOST;
		$this->SMTPDebug  = 0; 
		$this->SMTPAuth   = true;
		$this->Port       = MAIL_PORT;
		$this->Username   = MAIL_USER;
		$this->Password   = MAIL_PASS;
		$this->SMTPSecure = MAIL_SECURITY;

		$this->From       = 'storeandshare@kotter.net';
		$this->FromName   = 'Kotter Group';
		$this->IsHTML(true);

		return $this;
	}

	private function sendAndClear(){
		if (!$this->Send()){
			// Clear addresses here?
			$this->ClearAddresses();
			$this->ClearAttachments();
			echo 'Mailer Error: ' . $this->ErrorInfo;
			return false;
		}
		$this->ClearAddresses();
		$this->ClearAttachments();
		return true;
	}

	public function sendTo($to){
		$this->AddAddress($to);
		return $this->sendAndClear();
	}

	public function bulkSend(array $to){
		$this->AddAddress($this->From);
		foreach ($to as $email)
			$this->AddBCC($email);
		return $this->sendAndClear();
	}

	public function setEmailFile($emailname, $variables){
		extract($variables, EXTR_SKIP);
		ob_start();
		include dirname(__FILE__)."/../emails/$emailname/email.php";
		$this->Subject = $subject;
		$this->Body = ob_get_clean();
		foreach(glob("emails/$emailname/images/*.*") as $filename){
			$path = pathinfo($filename);
			$this->AddEmbeddedImage($filename, $path['basename']);
		}
	}

}

?>