<?php
$msg = isset($_GET["msg"]) ? $_GET["msg"] : '';
if ($msg == 'invalid'){
	$alert = '<p align="center" style="color:#F00; margin:0px; line-height:12px; padding-bottom:0px;">The User ID or Password entered do not match our records.</p>';
}
else if ($msg == 'missing'){
	$alert = '<p align="center" style="color:#F00; margin:0px; line-height:12px; padding-bottom:0px;">The User ID or Password can not be blank.</p>';
}
else if ($msg == 'disabled'){
	$alert = '<p align="center" style="color:#F00; margin:0px; line-height:12px; padding-bottom:0px;">Maximum amount of login attempts exceeded. <img src="images/question.png" title="Please wait an hour and try again. If you have forgotten your password, please click the &ldquo;forgot password&rdquo; link below."></p>';
}
else if ($msg == 'error'){
	$alert = '<p align="center" style="color:#F00; margin:0px; line-height:12px; padding-bottom:0px;">There was an error in the password reset process. <img src="images/question.png" title="Please click the &ldquo;forgot password&rdquo; link below and try again."></p>';
}
else if ($msg == 'expired'){
	$alert = '<p align="center" style="color:#F00; margin:0px; line-height:12px; padding-bottom:0px;">The password reset link has expired. <img src="images/question.png" title="Please click the &ldquo;forgot password &rdquo;link below and try again."></p>';
}
else if ($msg == 'resetgood'){
	$alert = '<p align="center" style="color:#F00; margin:0px;">Your password been reset successfully.</p>';
}
else if ($msg == 'sent'){
	$alert = '<p align="center" style="color:#0a0; margin:0px;">If an account exists for that address, a password reset link has been sent. <img src="images/question.png" title="If you do not see the email in your inbox, check your junk mail. Please add no-reply@spinielloco.com to your safe sender list."></p>';
}
else if ($msg == 'resent'){
	$alert = '<p align="center" style="color:#0a0; margin:0px;">If an account exists for that address, a new reset link has been sent. <img src="images/question.png" title="If you do not see the email in your inbox, check your junk mail. Please add no-reply@spinielloco.com to your safe sender list."></p>';
}
else if ($msg == 'missingEmail'){
	$alert = '<p align="center" style="color:#F00; margin:0px;">Please enter your email address.</p>';
}
else{
	$alert = '';
}
?>