<?php

require_once(dirname(__FILE__).'/../inc/ss_pdo.php');
$db = new MyPDO();

foreach($db->query('SELECT * FROM users WHERE paid=1') as $user) {
	echo "<h3>{$user['agency']}</h3>";
	echo "<p>{$user['firstname']} {$user['lastname']} - {$user['email']}</p>";
	echo "<p>Users: {$user['user_count']}</p>";
}

?>