<?php

// Config.php - PAY_KEY PAY_SANDBOX PAY_TESTMODE
require_once("inc/config.php");
require_once("inc/usaepay.php");
require_once("inc/ss_pdo.php");
require_once("inc/ss_mail.php");
require_once("inc/ss_api.php");

$db = new MyPDO();
$mail = new SSMailer();
$api = new AnchorAPI();
$tran = new umTransaction();

function processPayment() {
	global $db, $mail, $api, $tran, $user, $support, $upgrade, $plan;


	if($user['paid'] || $tran->Process()){
		$db->setUserPlan($user['id'], $plan);
		if (isset($upgrade)){
			// Broken API - Commented Out
			// $api->upgradeOrganization($user['organization_id'], $plan);
			$mail->setEmailFile("upgrade", array(
				"firstname" => $user['firstname'],
				"lastname" => $user['lastname']
			));
			$mail->sendTo($user['email']);
			$success = "<h3>Success!</h3>Your account has been upgraded! You may now log in <a href=\"http://ss.kotter.net/\">here</a>.<br><br>Redirecting in 8 seconds...";
		} else {
			$pass = MyPDO::genRandPassword();
			$apiorg = $api->createOrganization($user['agency'], $user['email'], $plan);
			if ($apiorg){
				$apiuser = $api->createUser($apiorg['result']['id'], $user['firstname'], $user['lastname'], $user['email'], $pass, $user['mobile_provider_id'], $user['mobile']);
				if ($apiuser){
					$db->exec('UPDATE users SET auth=NULL, created=NOW(), lastemailed=NOW(), user_id='.$apiuser['result']['id'].', organization_id='.$apiorg['result']['id'].' WHERE id='.$user['id']);

					$mail->setEmailFile("new", array(
						"firstname" => $user['firstname'],
						"lastname" => $user['lastname'],
						"password" => $pass
					));
					$mail->sendTo($user['email']);
					$success = "<h3>Success!</h3>Your account has been created and password has been emailed to you. Please log in <a href=\"http://ss.kotter.net/\">here</a>.<br><br>Redirecting in 8 seconds...";
				} else {
					$error = "<h3>Sorry.</h3>There was a problem creating your user: \"".$api->errorMsg()."\" ".$support;
				}
			} else {
				$error = "<h3>Sorry.</h3>There was a problem creating your agency: \"".$api->errorMsg()."\"<br><br>If your agency is already registered, contact your administrator to create your account. If not, ".$support;
			}
		}
		// Check if errors from creating Anchor account
		if (empty($error)){
			$json = array(
				"success" => TRUE,
				"authcode" => $tran->authcode,
				"message" => $success,
				"redirect" => "http://ss.kotter.net/",
				"redirectdelay" => 8000
			);
		} else {
			$json = array(
				"success" => FALSE,
				"error" => $error
			);
		}
	} else {
		$json = array(
			"success" => FALSE,
			"error" => '<h3>Sorry!</h3>There was an error while processing payment information: "'.$tran->error.'"',
		);
		// if($tran->curlerror) echo "<b>Curl Error:</b> " . $tran->curlerror . "<br>";	
	}
	die(json_encode($json));
}

extract($_POST, EXTR_SKIP);

if (isset($id, $verify) && $db->verifyUser($id, $verify)){

	$user = $db->getUser($id);
	$plan = (int)$plan;

	$tran->key        = PAY_KEY;
	
	$tran->ip         = $_SERVER['REMOTE_ADDR'];
	$tran->usesandbox = PAY_SANDBOX;
	$tran->testmode   = PAY_TESTMODE;
	$tran->cardauth   = 1;

	$amount = array(
		1 => "20.00",
		2 => "50.00",
		3 => "99.00",
	);
	$tran->amount= $amount[$plan];
	$tran->invoice= $user['id'];

	$tran->custemail  = $user['email'];
	$tran->email  = $user['email'];
	$tran->custid     = $user['id']; // MySQL user id

	$tran->schedule   = "monthly";
	$tran->start      = "next";
	$tran->custreceipt= TRUE;

	$tran->description= "Online Store & Share Order";

	if (isset($paymenttype, $card, $cardholder, $street, $zip, $cvv2, $exp_month, $exp_year)
			&& $paymenttype == "cc"){
		$tran->command    = "cc:sale";
		$tran->card       = $card;
		$tran->exp        = $exp_month.$exp_year;
		$tran->cardholder = $cardholder;
		$tran->street     = $street;
		$tran->zip        = $zip;
		$tran->cvv2       = $cvv2;
		processPayment($tran);
	} else if (isset($paymenttype, $routing, $account, $ssn)
			&& $paymenttype == "eft") {
		$tran->command = "check:sale";
		$tran->routing = $routing;
		$tran->account = $account;
		$tran->ssn = $ssn;
		$tran->cardholder = $user['firstname']." ".$user['lastname'];
		processPayment($tran);
	}

} else {
	$error = "<h3>Failed to verify email.</h3>";
}

?>