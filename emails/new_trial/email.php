<?php
	$subject = "Your New Account is Ready!";
?>

<h1>Welcome to Store and Share</h1>

<p>Congratulations <?php echo "$firstname $lastname" ?>, your account is ready to log in.</p>

<p>Your temporary password is: <?php echo $password ?></p>

<p>You can log in with this password <a href="http://ss.kotter.net/">here</a>.

<p>You can upgrade your account from trial to full status at any time! <a href="<?php echo $buylink ?>">Buy Now</a></p>