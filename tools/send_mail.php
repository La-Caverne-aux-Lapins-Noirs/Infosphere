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

function send_mail_plain_text($text)
{
    return (html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, "UTF-8"));
}

function send_mail($target, $title, $content, $domain = NULL, $attachements = NULL, $hidden_copy = true, $sender = NULL, $blind_copy = NULL)
{
    global $Configuration;

    // Une relation administrative peut volontairement ne pas avoir d'adresse
    // mail. Ne jamais transmettre une cible vide (ni l'ancien marqueur nomail)
    // à Mailgun. Pour une liste, on conserve uniquement les vraies cibles.
    if (is_array($target))
    {
        $target = array_values(array_filter($target, function ($mail) {
            $mail = trim((string)$mail);
            return ($mail != "" && strcasecmp($mail, "nomail") != 0);
        }));
        if (!count($target))
            return (new ErrorResponse("CannotSendMail"));
    }
    else
    {
        $target = trim((string)$target);
        if ($target == "" || strcasecmp($target, "nomail") == 0)
            return (new ErrorResponse("CannotSendMail"));
    }

    $mail_content = [];

    $mail_content["Content-Type"] = 'text/plain; charset="utf-8"';
    $mail_content["h:Reply-To"] = @$Configuration->Properties["mailgun_replyto"];
    if ($sender == NULL)
	$mail_content["from"] = @$Configuration->Properties["mailgun_sender"];
    else
	$mail_content["from"] = $sender;
    $to_addresses = is_array($target) ? array_values($target) : [$target];
    $bcc_addresses = [];
    if (!is_array($target))
        $mail_content['to'] = $target;
    else
    {
        if ($hidden_copy)
        {
            $mail_content['to'] = $target[0];
            unset($target[0]);
            $bcc_addresses = array_values($target);
        }
        else
            $mail_content['to'] = implode(', ', $target);
    }

    // Some workflows need an internal archival/accounting copy without
    // exposing that address to the external recipients. Keep this separate
    // from $hidden_copy so an existing list of visible TO recipients stays
    // visible while the additional addresses are sent as BCC.
    if ($blind_copy !== NULL)
    {
        $blind_copy = is_array($blind_copy) ? $blind_copy : [$blind_copy];
        foreach ($blind_copy as $mail)
        {
            $mail = trim((string)$mail);
            if ($mail == '' || strcasecmp($mail, 'nomail') == 0)
                continue ;
            $already_to = false;
            foreach ($to_addresses as $to)
                if (strcasecmp(trim((string)$to), $mail) == 0)
                {
                    $already_to = true;
                    break ;
                }
            if (!$already_to)
                $bcc_addresses[] = $mail;
        }
    }
    $bcc_addresses = array_values(array_unique($bcc_addresses));
    if (count($bcc_addresses))
        $mail_content['bcc'] = implode(', ', $bcc_addresses);
    if ($title === "")
	$title = "Mail from Efrits";
    $mail_content['subject'] = send_mail_plain_text($title);
    if ($content === "")
	$content = "This mail has been send by the Efrits administration";
    $mail_content['text'] = send_mail_plain_text($content);

    if ($attachements != NULL && count($attachements) > 0)
    {
        $mail_attachements = [];
        foreach ($attachements as $filename => $filecontent)
        {
            $mail_attachements[] = [
		'fileContent' => $filecontent,
		'filename' => $filename
	    ];
        }
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
    $mailgun_response = $mg->messages()->send($Domain, $mail_content);
    $message_id = "";
    $message = "";
    if (is_object($mailgun_response))
    {
        if (method_exists($mailgun_response, "getId"))
            $message_id = trim((string)$mailgun_response->getId());
        if (method_exists($mailgun_response, "getMessage"))
            $message = trim((string)$mailgun_response->getMessage());
    }
    else if (is_array($mailgun_response))
    {
        $message_id = trim((string)($mailgun_response["id"] ?? ""));
        $message = trim((string)($mailgun_response["message"] ?? ""));
    }
    return (new ValueResponse([
        "status" => "accepted",
        "message_id" => $message_id,
        "message" => $message,
        "to" => $to_addresses,
        "bcc" => $bcc_addresses,
    ]));
}

/**
 * Ask Mailgun what happened after a message was accepted for sending.
 *
 * send_mail() only proves that Mailgun queued the message.  The Events API is
 * what tells us whether the recipient's mail server subsequently accepted it
 * (delivered) or rejected/deferred it.
 */
function send_mail_delivery_status($message_id, $recipient, $domain = NULL)
{
    global $Configuration;

    $message_id = trim((string)$message_id);
    $recipient = trim((string)$recipient);
    if ($message_id == "" || !filter_var($recipient, FILTER_VALIDATE_EMAIL))
        return (new ValueResponse(["status" => "unknown"]));

    $Key = @$Configuration->Properties["mailgun_key"];
    $Domain = $domain == NULL ? @$Configuration->Properties["domain"] : $domain;
    if (!$Key || !$Domain)
        return (new ValueResponse(["status" => "unknown", "error" => "mailgun configuration missing"]));

    try
    {
        $mg = Mailgun::create($Key, 'https://api.eu.mailgun.net');
        $response = $mg->events()->get($Domain, [
            "message-id" => $message_id,
            "recipient" => $recipient,
            "limit" => 50,
        ]);
    }
    catch (Throwable $e)
    {
        return (new ValueResponse([
            "status" => "unknown",
            "error" => $e->getMessage(),
        ]));
    }

    if (is_object($response) && method_exists($response, "getItems"))
        $items = $response->getItems();
    else if (is_array($response))
        $items = $response["items"] ?? [];
    else
        $items = [];

    $best = [
        "status" => "accepted",
        "event" => "accepted",
        "timestamp" => "",
        "details" => "",
        "recipient" => $recipient,
    ];
    $priority = [
        "unknown" => 0,
        "accepted" => 1,
        "temporary_failure" => 2,
        "failed" => 3,
        "delivered" => 4,
    ];
    $best_priority = 1;

    foreach ($items as $event)
    {
        if (is_object($event))
        {
            $kind = method_exists($event, "getEvent") ? strtolower(trim((string)$event->getEvent())) : "";
            $severity = method_exists($event, "getSeverity") ? strtolower(trim((string)$event->getSeverity())) : "";
            $event_recipient = method_exists($event, "getRecipient") ? trim((string)$event->getRecipient()) : $recipient;
            $timestamp = method_exists($event, "getRawTimestamp") ? $event->getRawTimestamp() : (method_exists($event, "getTimestamp") ? $event->getTimestamp() : "");
            $delivery_status = method_exists($event, "getDeliveryStatus") ? $event->getDeliveryStatus() : [];
            $reason = method_exists($event, "getReason") ? trim((string)$event->getReason()) : "";
        }
        else if (is_array($event))
        {
            $kind = strtolower(trim((string)($event["event"] ?? "")));
            $severity = strtolower(trim((string)($event["severity"] ?? "")));
            $event_recipient = trim((string)($event["recipient"] ?? $recipient));
            $timestamp = $event["timestamp"] ?? "";
            $delivery_status = $event["delivery-status"] ?? [];
            $reason = trim((string)($event["reason"] ?? ""));
        }
        else
            continue ;

        if ($event_recipient != "" && strcasecmp($event_recipient, $recipient) != 0)
            continue ;

        $status = "accepted";
        if ($kind === "delivered")
            $status = "delivered";
        else if ($kind === "failed" && $severity === "permanent")
            $status = "failed";
        else if ($kind === "rejected")
            $status = "failed";
        else if ($kind === "failed")
            $status = "temporary_failure";
        else if ($kind !== "accepted")
            continue ;

        $details = "";
        if (is_array($delivery_status))
        {
            $parts = [];
            if (trim((string)($delivery_status["code"] ?? "")) != "")
                $parts[] = trim((string)$delivery_status["code"]);
            if (trim((string)($delivery_status["message"] ?? "")) != "")
                $parts[] = trim((string)$delivery_status["message"]);
            $details = implode(" — ", $parts);
        }
        if ($details == "")
            $details = $reason;

        $p = $priority[$status] ?? 0;
        if ($p < $best_priority)
            continue ;
        $best_priority = $p;
        $best = [
            "status" => $status,
            "event" => $kind,
            "timestamp" => $timestamp === "" ? "" : date("Y-m-d H:i:s", (int)$timestamp),
            "details" => $details,
            "recipient" => $recipient,
        ];
    }
    return (new ValueResponse($best));
}

/**
 * Recover the Mailgun message id of a legacy send for which Infosphere did not
 * store SendResponse::getId().  Recovery is intentionally conservative: only
 * one distinct message in a narrow time window is accepted.
 */
function send_mail_find_message_id($recipient, $subject, $accepted_at, $domain = NULL)
{
    global $Configuration;

    $recipient = trim((string)$recipient);
    $subject = trim((string)$subject);
    $accepted_time = strtotime((string)$accepted_at);
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $subject == "" || $accepted_time === false)
        return (new ValueResponse(["found" => false]));

    $Key = @$Configuration->Properties["mailgun_key"];
    $Domain = $domain == NULL ? @$Configuration->Properties["domain"] : $domain;
    if (!$Key || !$Domain)
        return (new ValueResponse(["found" => false]));

    try
    {
        $mg = Mailgun::create($Key, 'https://api.eu.mailgun.net');
        $response = $mg->events()->get($Domain, [
            "begin" => $accepted_time - 300,
            "end" => $accepted_time + 900,
            "recipient" => $recipient,
            "subject" => $subject,
            "limit" => 100,
        ]);
    }
    catch (Throwable $e)
    {
        return (new ValueResponse(["found" => false, "error" => $e->getMessage()]));
    }

    if (is_object($response) && method_exists($response, "getItems"))
        $items = $response->getItems();
    else if (is_array($response))
        $items = $response["items"] ?? [];
    else
        $items = [];

    $messages = [];
    foreach ($items as $event)
    {
        if (is_object($event))
        {
            $event_recipient = method_exists($event, "getRecipient") ? trim((string)$event->getRecipient()) : "";
            $message = method_exists($event, "getMessage") ? $event->getMessage() : [];
            $timestamp = method_exists($event, "getRawTimestamp") ? $event->getRawTimestamp() : "";
        }
        else if (is_array($event))
        {
            $event_recipient = trim((string)($event["recipient"] ?? ""));
            $message = $event["message"] ?? [];
            $timestamp = $event["timestamp"] ?? "";
        }
        else
            continue ;
        if ($event_recipient != "" && strcasecmp($event_recipient, $recipient) != 0)
            continue ;
        $headers = is_array($message) ? ($message["headers"] ?? []) : [];
        $message_id = trim((string)($headers["message-id"] ?? ""));
        if ($message_id == "")
            continue ;
        if (!isset($messages[$message_id]))
            $messages[$message_id] = ["distance" => PHP_INT_MAX, "timestamp" => ""];
        if ($timestamp !== "")
        {
            $distance = abs((int)$timestamp - $accepted_time);
            if ($distance < $messages[$message_id]["distance"])
                $messages[$message_id] = ["distance" => $distance, "timestamp" => (int)$timestamp];
        }
    }

    if (!count($messages))
        return (new ValueResponse(["found" => false, "candidates" => 0]));
    uasort($messages, function ($a, $b) { return (($a["distance"] ?? PHP_INT_MAX) <=> ($b["distance"] ?? PHP_INT_MAX)); });
    $message_ids = array_keys($messages);
    $message_id = $message_ids[0];
    $best_distance = (int)($messages[$message_id]["distance"] ?? PHP_INT_MAX);
    if (count($message_ids) > 1)
    {
        $second_distance = (int)($messages[$message_ids[1]]["distance"] ?? PHP_INT_MAX);
        // DeliveredAt is written immediately after Mailgun accepts the request.
        // Multiple matching certificate mails can therefore be disambiguated
        // only when one event is essentially simultaneous with that timestamp.
        if ($best_distance > 3 || $second_distance <= $best_distance)
            return (new ValueResponse(["found" => false, "candidates" => count($messages)]));
    }
    return (new ValueResponse([
        "found" => true,
        "message_id" => $message_id,
        "timestamp" => $messages[$message_id]["timestamp"],
    ]));
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

