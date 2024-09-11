<?php
include 'vendor/autoload.php';
use Mailgun\Mailgun;

// Target is the destination of the mail, it's an array which can be string or array for multiple value
// Title is a string for the title and MUST NOT BE EMPTY
// Content is a string for the text content of the mail, MUST NOT BE EMPTY
// Domain is a string with the domain of the sender. NULL ask the bdd for the domain
// Attachement is a array which contains an array with only one attachements key = name; value = path
// hidden_copy is a boolean for the position of the target if multiple. TRUE -> BCC; FALSE -> TO

// Example :
//
// send_mail("example1@mail.fr", "Example Title", "A content", "efrits.fr", [["filename" => "file content"]]);
//
// send_mail(["example1@mail.fr", "example2@mail.fr"], "Example Title", "A content", NULL, [["filename1" => "file1 content"], ["filename2" => "file2 content"]], false);

function send_mail($target, $title, $content, $domain = NULL, $attachements = NULL, $hidden_copy = true)
{
    global $Configuration;

    $mail_content = [];

    $mail_content["from"] = @$Configuration->Properties["mailgun_sender"];
    if (!is_array($target))
        $mail_content['to'] = $target;
    else
    {
        if ($hidden_copy)
        {
            $mail_content['to'] = $target[0];
            unset($target[0]);
            $mail_content['bcc'] = implode(', ', array_values($target));
        }
        else
            $mail_content['to'] = implode(', ', $target);
    }
    if ($title === "")
	$title = "Mail from Efrits";
    $mail_content['subject'] = $title;
    if ($content === "")
	$content = "This mail has been send by the Efrits administration";
    $mail_content['text'] = $content;

    if ($attachements != NULL && count($attachements) > 0)
    {
        $index = 0;
        $mail_attachements = [];
        foreach ($attachements as $attachement)
        {
            $mail_attachements[$index] = ['fileContent' => (array_values($attachement))[0],'filename'=> array_key_first($attachement)];
            $index++;
        }
        $mail_attachements = array_values($mail_attachements);
        $mail_content['attachment'] = $mail_attachements;
    }
    $Key = @$Configuration->Properties["mailgun_key"];
    if ($domain == NULL)
	$Domain = @$Configuration->Properties["domain"];
    else
	$Domain = $domain;
    if (!$Key || !$Domain)
    {
	add_log(TRACE, "A mail sending was requested without the Infosphere to be able to process it. Set a mailgun key, a sending mail adress and the currently used domain.", 1);
	return (new ErrorResponse("CannotSendMail"));
    }
    $mg = Mailgun::create($Key, 'https://api.eu.mailgun.net');
    print_r($mail_content);
    $mg->messages()->send($Domain, $mail_content);
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

function send_subscribe_mail($id, $login, $mail, $password, $bddpassword, $domain = NULL)
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
		       $password,
		       $bddpassword
    );

    if (($request = send_mail($mail, $Dictionnary["SubscribeTitle"], $Content))->is_error())
	add_log(TRACE, "Cannot send subscription mail to ".$mail, $id);
    return ($request);
}

