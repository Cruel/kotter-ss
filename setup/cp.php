<?php

require_once('../inc/ss_pdo.php');
require_once('../inc/ss_mail.php');
require_once('../inc/ss_api.php');
$db = new MyPDO();
$mail = new SSMailer();
$ssurl = "https://ss.kotter.net:510";

extract($_GET, EXTR_SKIP);
extract($_POST, EXTR_SKIP);
if (isset($id)) {
	$user = $db->getUser($id);
}

if (isset($agency, $email, $firstname, $lastname, $mobile, $mobileprovider, $plan)) {
	// Assume inputs are good (they're from admin).
	$api = new AnchorAPI();
	$id = $db->createUser($agency, $firstname, $lastname, $email, "", $mobile, $mobileprovider);
	if ($id){
		$pass = MyPDO::genRandPassword();
		$apiorg = $api->createOrganization($agency, $email, $plan);
		if ($apiorg){
			$apiuser = $api->createUser($apiorg['result']['id'], $firstname, $lastname, $email, $pass, $mobileprovider, $mobile);
			if ($apiuser){
				$db->setUserPlan($id, $plan);
				$db->exec('UPDATE users SET auth=NULL, paid=1, created=NOW(), lastemailed=NOW(), user_id='.$apiuser['result']['id'].', organization_id='.$apiorg['result']['id'].' WHERE id='.$id);

				$mail->setEmailFile("new", array(
					"firstname" => $firstname,
					"lastname" => $lastname,
					"password" => $pass
				));
				$mail->sendTo($email);
				$success = "<h3>Success!</h3>The account has been created and password has been emailed to '$email'.";
			} else {
				$error = "<h3>Sorry.</h3>There was a problem creating this user: \"".$api->errorMsg()."\"";
			}
		} else {
			$error = "<h3>Sorry.</h3>There was a problem creating this agency: \"".$api->errorMsg()."\"";
		}
	} else {
		$error = "<h3>Sorry.</h3>There was a problem creating this user: \"".$db->errorMsg()."\"";
	}
	$json = array(
        "success" => empty($error),
        "message" => empty($error) ? $success : $error,
        "redirect" => $_SERVER['SCRIPT_NAME'],
        "redirectdelay" => 2000
    );
    die(json_encode($json));
}

?>

<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
<title>Store &amp; Share Control Panel</title>

<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>

<script src="cp.js"></script>
<link href="cp.css" rel='stylesheet' type='text/css' />

</head>
<body>
	<div id="wrapper">
	<?php 
		if (isset($user)) {

			if ($action == "new") {
	?>
				NEW!
	<?php
			} else if ($action == "stop_emails") {
				$db->exec("UPDATE users SET get_emails=0 WHERE id=$id");
				echo "Marketing emails will not be sent to {$user['firstname']} {$user['lastname']} &lt;{$user['email']}&gt;";
			} else if ($action == "start_emails") {
				$db->exec("UPDATE users SET get_emails=1 WHERE id=$id");
				echo "Marketing emails will be sent to {$user['firstname']} {$user['lastname']} &lt;{$user['email']}&gt;";
	?>


	<?php
			} else {

				echo "<h1>{$user['firstname']}</h1>";
				if ($user['get_emails']){
					echo "<a href=\"?id=$id&action=stop_emails\"><button>Stop Emails</button></a>";
				} else {
					echo "<a href=\"?id=$id&action=start_emails\"><button>Start Emails</button></a>";
				}

				if (!empty($user['auth'])) {
					echo "<h3>Email not confirmed.</h3>";
					$authlink = "../signup.php?trial&id=$id&verify=".$user['auth'];
					echo "<a href=\"$authlink\"><button>Confirm Email for Trial</button></a>";
				}
	?>

	<?php 
			}
			if (isset($action))
				echo "<button onclick=\"document.location='cp.php?id=$id';\">Back</button>";
		} else {
	?>
		<a href="<?php echo "../signup.php?target=".$_SERVER['SCRIPT_NAME'] ?>"><button>New Paid</button></a>
		<table>
			<thead>
				<tr>
					<th></th>
					<th>Agency</th>
					<th>Owner Name</th>
					<th>Email</th>
					<th>User Count</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
			<?php
				foreach($db->query('SELECT *, TIME_TO_SEC(TIMEDIFF(NOW(), created)) as diff FROM users') as $user) {
					$days = round($user['diff'] / 60 / 60 / 24, 1);
					if ($user['user_id']) {
						if ($user['paid']) {
							$status = "Paid";
						} else {
							$status = "Trial - Day $days";
						}
					} else {
						$status = "Unverified";
					}
					echo "<tr>";
					echo "<td><a href=\"?id={$user['id']}\"><button>Edit</button></a></td>";
					if ($user['organization_id'])
						echo "<td><a href=\"$ssurl/sites/{$user['organization_id']}/dashboard/\">{$user['agency']}</a></td>";
					else
						echo "<td>{$user['agency']}</td>";
					if ($user['user_id'])
						echo "<td><a href=\"$ssurl/accounts/{$user['user_id']}/\">{$user['firstname']} {$user['lastname']}</a></td>";
					else
						echo "<td>{$user['firstname']} {$user['lastname']}</td>";
					echo "<td>{$user['email']}</td>";
					echo "<td>{$user['user_count']}</td>";
					echo "<td>$status</td>";
					echo "</tr>";
				}
			?>
			</tbody>
		</table>
	<?php 
		}
	?>
	</div>
</body>
</html>