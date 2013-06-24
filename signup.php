<?php

require_once('inc/ss_pdo.php');
require_once('inc/ss_mail.php');
require_once('inc/ss_api.php');

$db = new MyPDO();
$mail = new SSMailer();

extract($_GET, EXTR_SKIP);
extract($_POST, EXTR_SKIP);

$support = 'Please provide this info for support at (770)717-1777 or fill out the <a href="/contact">Contact Form</a> and we\'ll help you immediately. Thank you.';

if (isset($agency, $email, $firstname, $lastname, $mobile, $mobileprovider)) {
    $error = "";
    if (empty($agency)) $error .= "<li>Agency name needs to be provided.</li>";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $error .= "<li>Email is not properly formatted: example@site.com</li>";
    if (empty($firstname)) $error .= "<li>First Name needs to be provided.</li>";
    if (empty($lastname)) $error .= "<li>Last Name needs to be provided.</li>";
    if (!empty($mobile)) {
        $mobile = preg_replace("/[^0-9]/", '', $mobile);
        if (strlen($mobile) == 11)
            $mobile = preg_replace("/^1/", '',$mobile);
        if (strlen($mobile) != 10)
            $error .= "<li>Mobile numbers need to be 10 digits: (eg. 123-456-7890)</li>";
        if (empty($mobileprovider))
            $error .= "<li>Need to specify a mobile provider.</li>";
    }
    if (empty($error)) {
        $auth = MyPDO::genRandToken();

        $id = $db->createUser($agency, $firstname, $lastname, $email, $auth, $mobile, $mobileprovider);
        if ($id){
            $authlink = 'http'.(empty($_SERVER['HTTPS'])?'':'s').'://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?".(isset($trial)?"trial&":"")."id=".$id."&verify=".$auth;
            $mail->setEmailFile("verification", array(
                "firstname" => $firstname,
                "lastname" => $lastname,
                "authlink" => $authlink
            ));
            $mail->sendTo($email);
            $success = "<h3>Congratulations!</h3>Your new account is being set up now. We just need to verify your email address to proceed. A verification email has been sent to '".$email."'. Please click the link in your email to verify and continue.";
        } else {
            $error = "<h3>Sorry.</h3>There was a problem creating your user: \"".$db->errorMsg()."\" ".$support;
        }
    } else {
        $error = "<h3>Errors were found:</h3><ul>".$error."</ul>";
    }
} else if (isset($id, $verify)){
    if ($db->verifyUser($id, $verify)){
        if (isset($trial)) {
            $api = new AnchorAPI();
            $user = $db->getUser($id);
            $pass = MyPDO::genRandPassword();
            $apiorg = $api->createOrganization($user['agency'], $user['email']);
            if ($apiorg){
                $apiuser = $api->createUser($apiorg['result']['id'], $user['firstname'], $user['lastname'], $user['email'], $pass, $user['mobile_provider_id'], $user['mobile']);
                if ($apiuser){
                    $db->exec('UPDATE users SET auth=NULL, created=NOW(), lastemailed=NOW(), user_id='.$apiuser['result']['id'].', organization_id='.$apiorg['result']['id'].' WHERE id='.$id);
                    $buylink = 'http'.(empty($_SERVER['HTTPS'])?'':'s').'://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?upgrade&id=".$id;
                    $mail->setEmailFile("new_trial", array(
                        "firstname" => $user['firstname'],
                        "lastname" => $user['lastname'],
                        "password" => $pass,
                        "buylink" => $buylink
                    ));
                    $mail->sendTo($user['email']);
                    $success = "<h3>Success!</h3>Your email has been verified and account created. Your account password has been emailed to you. Please log in and change your password <a href=\"http://ss.kotter.net/\">here</a>.<br><br>Redirecting in 8 seconds...";
                    $redirect = TRUE;
                } else {
                    $error = "<h3>Sorry.</h3>There was a problem creating your user: \"".$api->errorMsg()."\" ".$support;
                }
            } else {
                $error = "<h3>Sorry.</h3>There was a problem creating your agency: \"".$api->errorMsg()."\"<br><br>If your agency is already registered, contact your administrator to create your account. If not, ".$support;
            }
        } else {
            $success = "<h3>Success!</h3>Your email has been verified and the last step is to process payment below.";
            if (isset($type)) {
                require_once("buy.php");
                $success = "<h3>Success!</h3>You won.";
            }
            $paymentRequired = TRUE;
        }
    } else {
        $error = "<h3>Sorry.</h3>You are either already verified or there was a problem verifying you. ".$support;
    }
} else if (!empty($id)) {
    $user = $db->getUser($id);
    if ($user) {
        $paymentRequired = TRUE;
    } else {
        $error = "<h3>Error!</h3>Payment was requested for a user that doesn't exist. ".$support;
    }
}

if (isset($json)) {
    $json = array(
        "success" => empty($error),
        "message" => empty($error) ? $success : $error,
        "redirect" => "http://ss.kotter.net/",
        "redirectdelay" => 8000
    );
    die(json_encode($json));
}

?>

<!DOCTYPE html>
<html>
<head>
<?php
    if ($redirect)
        echo '<meta http-equiv="refresh" content="8; url=http://ss.kotter.net/">';
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
<title>Sign Up</title>

<link href="/templates/yoo_infinite/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon" />
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js"></script>
<script src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/additional-methods.min.js"></script>

<script src="static/main.js"></script>

<link href='//fonts.googleapis.com/css?family=Cuprum' rel='stylesheet' type='text/css' />
<link href='static/main.css' rel='stylesheet' type='text/css' />

</head>

<body>

<!-- Content wrapper -->
<div class="wrapper">

    <!-- Content -->

<div class="loginWrapper">

    <img class="loginLogo" src="static/logo.png">

    <noscript>
        <div class="errorlist">
            <h3>Warning!</h3>
            Javascript is not detected. You need a javascript enabled browser to sign up for Store &amp; Share.
        </div>
    </noscript>
    
    <?php
        if (!empty($error))
            echo '<div class="errorlist">'.$error.'</div>';
        if (!empty($success))
            echo '<div class="success">'.$success.'</div>';

        if (!$success || $paymentRequired) {
    ?>
    
    <div class="loginPanel">
        <?php
            if ($paymentRequired) {
        ?>



        

        <form action="" id="payform" class="mainForm" method="post">
            <div class="formhead">
                <h5 class="iUser">Pay</h5>
                <!--select id="type" name="type">
                    <option value="eft">eTransfer (Recommended)</option>
                    <option value="cc">Credit Card</option>
                </select-->
                <input id="type" name="type" type="hidden" value="cc" />
            </div>
            <input name="id" type="hidden" value="<?php echo $id ?>" />
            <input name="verify" type="hidden" value="<?php echo $verify ?>" />
            <fieldset>

                <div id="cc" class="paymethod">

                    <div class="loginRow noborder">
                        <label for="card">Card Number</label>
                        <div class="loginInput"><input type="text" id="card" name="card" required /></div>
                        <div class="fix"></div>
                    </div>

                    <div class="loginRow noborder">
                        <label for="cardholder">Name as on Card</label>
                        <div class="loginInput"><input type="text" id="cardholder" name="cardholder" required /></div>
                        <div class="fix"></div>
                    </div>

                    <div class="loginRow noborder">
                        <label for="street">Billing Address</label>
                        <div class="loginInput"><input type="text" id="street" name="street" required /></div>
                        <div class="fix"></div>
                    </div>

                    <div class="loginRow noborder">
                        <label for="zip">Billing Zipcode</label>
                        <div class="loginInput"><input type="text" id="zip" name="zip" required /></div>
                        <div class="fix"></div>
                    </div>

                    <div class="loginRow noborder">
                        <label for="cvv2"><a href="https://usaepay.com/cvv.htm" target="_blank">CVV2 / CID</a></label>
                        <div class="loginInput"><input type="text" id="cvv2" name="cvv2" required /></div>
                        <div class="fix"></div>
                    </div>

                    <div class="loginRow noborder">
                        <label for="zip">Expiration:</label>
                        <div class="loginInput">
                            <select id="exp_month" name="exp_month">
                                <option>01</option><option>02</option><option>03</option><option>04</option><option>05</option><option>06</option><option>07</option><option>08</option><option>09</option><option>10</option><option>11</option><option>12</option>
                            </select>
                            <select id="exp_year" name="exp_year">
                                <?php
                                    $year = (int) date("y");
                                    for ($i = $year; $i < $year+10; $i++)
                                        echo "<option>$i</option>";
                                ?>
                            </select>
                        </div>
                        <div class="fix"></div>
                    </div>

                </div>

                <div id="eft" class="paymethod">

                    <div class="loginRow noborder">
                        <label for="routing">Routing Number</label>
                        <div class="loginInput"><input type="text" id="routing" name="routing" required /></div>
                        <div class="fix"></div>
                    </div>

                    <div class="loginRow noborder">
                        <label for="account">Account Number</label>
                        <div class="loginInput"><input type="text" id="account" name="account" required /></div>
                        <div class="fix"></div>
                    </div>

                    <div class="loginRow noborder">
                        <label for="ssn">Social Security Number</label>
                        <div class="loginInput"><input type="text" id="ssn" name="ssn" required /></div>
                        <div class="fix"></div>
                    </div>
                </div>

                <div class="loginRow">
                    <button id="submit" class="blueBtn submitForm">Pay</button>
                    <div class="fix"></div>
                </div>
            </fieldset>
        </form>

        <?php 
            } else {
        ?>

        <div class="formhead"><h5 class="iUser">Sign Up</h5></div>

        <form action="<?php echo ((isset($target)) ? $target : "") ?>" id="signupform" class="mainForm" method="post">
            <fieldset>

                <?php 
                    if (isset($trial)){
                        echo '<input name="trial" type="hidden" />';
                    }
                ?>
                
                <div class="loginRow noborder">
                    <label for="agency">Agency Name</label>
                    <div class="loginInput"><input type="agency" id="agency" name="agency" required /></div>
                    <div class="fix"></div>
                </div>

                <div class="loginRow noborder">
                    <label for="user">Username/Email</label>
                    <div class="loginInput"><input type="email" id="user" name="email" required /></div>
                    <div class="fix"></div>
                </div>

                <div class="loginRow noborder">
                    <label for="firstname">First Name</label>
                    <div class="loginInput"><input type="text" id="firstname" name="firstname" required /></div>
                    <div class="fix"></div>
                </div>

                <div class="loginRow noborder">
                    <label for="lastname">Last Name</label>
                    <div class="loginInput"><input type="text" id="lastname" name="lastname" required /></div>
                    <div class="fix"></div>
                </div>

                <div class="loginRow noborder">
                    <label for="mobile">Mobile Phone</label>
                    <div class="loginInput"><input type="phoneUS" id="mobile" name="mobile" placeholder="(Optional)" /></div>
                    <div class="fix"></div>
                </div>

                <div class="loginRow noborder">
                    <label for="mobileprovider">Mobile Provider</label>
                    <div class="loginInput">
                        <select id="mobileprovider" name="mobileprovider" placeholder="Select Provider"><option value=""></option><option value="1">AT&amp;T Wireless</option><option value="2">BellSouth</option><option value="3">Bluegrass Cellular</option><option value="4">Boost Mobile</option><option value="5">C Spire Wireless</option><option value="6">Cellcom</option><option value="7">Cellular One</option><option value="8">Cellular South</option><option value="9">Cleartalk Wireless</option><option value="10">Cricket</option><option value="11">Edge Wireless</option><option value="12">Element Mobile</option><option value="13">Esendex</option><option value="14">Fido</option><option value="15">i wireless</option><option value="16">Kajeet</option><option value="17">LongLines</option><option value="18">MetroPCS</option><option value="19">Nextel</option><option value="20">O2</option><option value="21">Orange</option><option value="22">Qwest Wireless</option><option value="23">Red Pocket Mobile</option><option value="24">Rogers Wireless</option><option value="25">Simple Mobile</option><option value="26">South Central Communications</option><option value="27">Sprint</option><option value="28">T-Mobile</option><option value="29">Teleflip</option><option value="30">Telus Mobility</option><option value="31">TracFone</option><option value="34">Unicel</option><option value="32">US Cellular</option><option value="33">USA Mobility</option><option value="35">Verizon Wireless</option><option value="36">Viaero</option><option value="37">Virgin Mobile</option></select>
                    </div>
                    <div class="fix"></div>
                </div>

                <div class="loginRow noborder">
                    <input type="checkbox" id="terms" name="terms" required />
                    <label id="termslabel" for="terms">I agree to the Kotter Store &amp; Share <a target="_blank" href="/terms">Terms and Conditions</a></label>
                    <div class="fix"></div>
                </div>

                <div class="loginRow">
                    <button id="submit" class="blueBtn submitForm">Sign Up</button>
                    <div class="fix"></div>
                </div>
            </fieldset>
        </form>

        <?php } ?>
   
    </div>  

    <?php } ?>

</div>
    <div class="fix"></div>
</div>

<div id="loading">
    <img src="static/loading.gif" />
    <h1>Loading...</h1>
</div>

</body>
</html>