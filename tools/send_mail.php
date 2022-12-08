<?php

function send_mail($target, $title, $content, $domain = NULL)
{
    global $Configuration;

    if (!is_array($target))
	$target = [$target];
    $Key = @$Configuration->Properties["mailgun_key"];
    $Sender = @$Configuration->Properties["mailgun_sender"];
    if ($domain == NULL)
	$Domain = @$Configuration->Properties["domain"];
    else
	$Domain = $domain;
    if (!$Key || !$Sender || !$Domain)
    {
	add_log(TRACE, "A mail sending was requested without the Infosphere to be able to process it. Set a mailgun key, a sending mail adress and the currently used domain.", 1);
	return (new ErrorResponse("CannotSendMail"));
    }
    $Cmd = [
	"curl -s --user 'api:$Key'",
	"https://api.eu.mailgun.net/v3/$Domain/messages",
	"-F from='Infosphere $Domain <mailgun@$Domain>'"
    ];
    foreach ($target as $tar)
	$Cmd[] = "-F to=$tar";

    $title = html_entity_decode($title);
    $title = str_replace("\"", "\\\"", $title);
    $title = str_replace(";", "", $title);
    $content = html_entity_decode($content);
    $content = str_replace("\"", "\\\"", $content);
    $content = str_replace(";", "", $content);
    $Cmd = array_merge($Cmd, [
	"-F subject=\"$title\"",
	"-F text=\"$content\"",
    ]);
    $Cmd[] = "2>&1";
    $Cmd = implode(" ", $Cmd);
    if (($Output = shell_exec($Cmd))[0] != "{")
	return (new ErrorResponse("CannotSendMail", $Output, 1));
    return (new Response);
}

function send_mail_change_mail($user, $new_user, $domain = NULL)
{
    global $Dictionnary;
    global $Configuration;

    if ($domain == NULL)
	$Domain = @$Configuration->Properties["domain"];
    else
	$Domain = $domain;
    $Content = sprintf($Dictionnary["MailChangedContent"],
		       $Domain,
		       $new_user["mail"],
		       get_client_ip()
    );
    if (($request = send_mail($user["mail"], $Dictionnary["MailChangedTitle"], $Content))->is_error())
    {
	add_log(TRACE, "Cannot send mail change mail to old ".$user["mail"], $user["id"]);
	return ($request);
    }
    
    if (($request = send_mail($new_user["mail"], $Dictionnary["MailChangedTitle"], $Content))->is_error())
	add_log(TRACE, "Cannot send mail change mail to new ".$new_user["mail"], $user["id"]);
    
    return ($request);
}

function send_password_change_mail($user, $new_password, $domain = NULL)
{
    global $Dictionnary;
    global $Configuration;

    /*
    // BACKDOOR TEMPORAIRE EN ATTENDANT D'AVOIR UN SERVEUR MAIL
    $file = file_get_contents("./users.json");
    $file = json_decode($file, true);
    $file[] = [
	"login" => $user["codename"],
	"mail" => "",
	"password" => $new_password
    ];
    $file = json_encode($file, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    file_put_contents("./users.json", $file);
    //return (true);
    // FIN DE LA BACKDOOR
     */

    if ($domain == NULL)
	$Domain = @$Configuration->Properties["domain"];
    else
	$Domain = $domain;
    $Content = sprintf($Dictionnary["PasswordChangedContent"],
		       $Domain,
		       $new_password,
		       get_client_ip()
    );
    if (($request = send_mail($user["mail"], $Dictionnary["PasswordChangedTitle"], $Content))->is_error())
        add_log(TRACE, "Cannot send password change mail to ".$user["mail"], $user["id"]);
    return ($request);
}

function send_subscribe_mail($id, $login, $mail, $password, $domain = NULL)
{
    global $Dictionnary;
    global $Configuration;

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

    if ($domain == NULL)
	$Domain = @$Configuration->Properties["domain"];
    else
	$Domain = $domain;
    $Content = sprintf($Dictionnary["SubscribeContent"],
		       $Domain,
		       $login,
		       $password
    );

    if (($request = send_mail($mail, $Dictionnary["SubscribeTitle"], $Content))->is_error())
	add_log(TRACE, "Cannot send subscription mail to ".$mail, $id);
    return ($request);
}

