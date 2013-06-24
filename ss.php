<?php

// chdir(dirname(__FILE__));

// Config.php - CRON_INTERVAL TRIAL_LENGTH SIGNUP_PAGE REPORT_SUBJECT REPORT_RECIPIENT
require_once("inc/config.php");

require_once('inc/ss_pdo.php');
require_once('inc/ss_api.php');
require_once('inc/ss_mail.php');

$db = new MyPDO();
$mail = new SSMailer();
$api = new AnchorAPI();

echo time()."\n";

foreach($db->query('SELECT * FROM emails') as $email) {
	$users = $db->getUsersEmailLapse($email['timelapse'], CRON_INTERVAL);
	foreach($users as $user){
		if (!$user['paid']) {
			echo "Emailing ".$user['email']." with '".$email['title']."'\n";
			$buylink = SIGNUP_PAGE."?upgrade&id=".$user['id'];
			$mail->setEmailFile($email['name'], array(
				"firstname" => $user['firstname'],
				"lastname" => $user['lastname'],
				"buylink" => $buylink
			));
			if ($mail->sendTo($user['email'])) {
				$db->exec('UPDATE users SET lastemailed=NOW() WHERE id='.$user['id']);
			} else {
				echo "Failed to send email to {$user['email']}\n";
			}
		}
	}
}

// Make expired trials inactive
$users = $db->getUsersLapse(TRIAL_LENGTH*24*60*60);
foreach($users as $user){
	if (!$user['paid']){
		// Delete from 
		$api->deleteOrganization($user['organization_id']);
		echo "Deleted user: '".$user['id']."': ".$user['firstname']." ".$user['lastname']."\n";
		$mail->setEmailFile("termination");
		$mail->sendTo($user['email']);
	}
}

$email_flag = FALSE;
foreach($db->query('SELECT * FROM users WHERE paid=1') as $user) {
	$count = $api->getOrganizationPersonCount($user['organization_id']);
	if ($user['user_count'] != $count){
		$db->exec("UPDATE users SET user_count=$count WHERE id=".$user['id']);
		$email_flag = TRUE;
	}
}
if ($email_flag){
	ob_start();
	include "report/report.php";
	$mail->Subject = REPORT_SUBJECT;
	$mail->Body = ob_get_clean();
	$mail->sendTo(REPORT_RECIPIENT);
}

// Check if script is run from command line
if (php_sapi_name() == 'cli') {  
	// echo time()." - ".$api->errorMsg()."\n";
}

?>