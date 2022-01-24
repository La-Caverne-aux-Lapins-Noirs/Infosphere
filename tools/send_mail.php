<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require ("ext/phpmailer/src/Exception.php");
require ("ext/phpmailer/src/PHPMailer.php");
require ("ext/phpmailer/src/SMTP.php");

function send_mail($target, $title, $body)
{
    global $Configuration;

    if (!is_array($target))
	$target = [$target];
    try
    {
	$body = str_replace("\n", "<br />", $body);
	$body = handle_french($body);
	$mail = new PHPMailer(true);
	//$mail->SMTPDebug = SMTP::DEBUG_SERVER;
	$mail->isSMTP();
	$mail->Host = 'smtp.office365.com';
	$mail->SMTPAuth = true;

	if (!isset($Configuration->Properties["mail_password"]) ||
	    !isset($Configuration->Properties["mail_password"]))
	{
	    return (new ErrorResponse("MissingParameter"));
	}
	$mail->Username = $Configuration->Properties["mail_login"]; // infosphere@ecole-89.com
	$pass = $Configuration->Properties["mail_password"];
	$pass = openssl_decrypt($pass, "des", "this_is_the_key", 0, "azertyui");
	$mail->Password = $pass;

	// Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
	$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
	// TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
	$mail->Port = 587;

	$mail->setFrom($mail->Username, 'Albedo');
	foreach ($target as $tar)
	{
	    if (isset($tar["mail"]) && isset($tar["name"]))
		$mail->addAddress($tar["mail"], $tar["name"]);
	    else
		$mail->addAddress($tar);
	}
	$mail->isHTML(true);
	$mail->Subject = handle_french($title);
	$mail->Body = $body;
	$mail->AltBody = strip_tags($body);
	$mail->send();
    }
    catch (Exception $e)
    {
	return (new ErrorResponse("CannotSendMail", $e->getMessage()));
    }
    return (new Response);
}

function send_mail_change_mail($user, $new_user)
{
    global $Dictionnary;

    if (send_mail($user["mail"],
	     $Dictionnary["MailChangedTitle"],
	     sprintf($Dictionnary["MailChangedContent"],
		     $_SERVER["SERVER_NAME"],
		     $new_user["mail"],
		     get_client_ip())
    ) == false)
    {
	add_log(TRACE, "Cannot send mail change mail to old ".$user["mail"], $user["id"]);
	return (false);
    }

    if (send_mail($new_user["mail"],
	     $Dictionnary["MailChangedTitle"],
	     sprintf($Dictionnary["MailChangedContent"],
		     $_SERVER["SERVER_NAME"],
		     $new_user["mail"],
		     get_client_ip())
    ) == false)
    {
	add_log(TRACE, "Cannot send mail change mail to new ".$new_user["mail"], $user["id"]);
	return (false);
    }

    return (true);
}

function send_password_change_mail($user, $new_password)
{
    global $Dictionnary;

    // BACKDOOR TEMPORAIRE EN ATTENDANT D'AVOIR UN SERVEUR MAIL
    /*
    $file = file_get_contents("./users.json");
    $file = json_decode($file, true);
    $file[] = [
	"login" => $user["codename"],
	"mail" => "",
	"password" => $new_password
    ];
    $file = json_encode($file, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    file_put_contents("./users.json", $file);
    return (true);
     */
    // FIN DE LA BACKDOOR

    if (send_mail($user["mail"],
	     $Dictionnary["PasswordChangedTitle"],
	     sprintf($Dictionnary["PasswordChangedContent"],
		     $_SERVER["SERVER_NAME"],
		     $new_password,
		     get_client_ip())
    ) == false)
    {
	add_log(TRACE, "Cannot send password change mail to ".$user["mail"], $user["id"]);
	return (false);
    }

    return (true);
}

function send_subscribe_mail($id, $login, $mail, $password)
{
    global $Dictionnary;

    /*
    // BACKDOOR TEMPORAIRE EN ATTENDANT D'AVOIR UN SERVEUR MAIL
    $file = file_get_contents("./users.json");
    $file = json_decode($file, true);
    $file[] = [
	"login" => $login,
	"mail" => $mail,
	"password" => $password
    ];
    $file = json_encode($file, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    file_put_contents("./users.json", $file);
     */

    if (send_mail($mail,
		  $Dictionnary["SubscribeTitle"],
		  sprintf($Dictionnary["SubscribeContent"],
			  $_SERVER["SERVER_NAME"],
			  $login,
			  $password)
    ) == false)
    {
	add_log(TRACE, "Cannot send password change mail to ".$mail, $id);
	return (false);
    }

    return (true);
}

