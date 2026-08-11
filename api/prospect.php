<?php

function TransformProspect($id, $data, $method, $output, $module)
{
    return (transform_prospect($id));
}

function DisplayActions($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $one_day;

    $score = 0;
    if (($datas = fetch_prospecting_actions($id))->is_error())
	return ($datas);
    $actions = $datas->value;
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($actions, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    $last_action = 0;
    $done = false;
    if (count($actions))
	require ("./pages/prospecting/action.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddAction($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;
    global $User;

    $id = (int)$id;
    if ($id == -1)
	bad_request();
    $id_prospector = $User["id"];
    $id_action = $data["id_action"];
    $comment = $Database->real_escape_string($data["comment"]);
    $Database->query("
	INSERT INTO prospection (id_user, id_prospector, id_action, comment)
	VALUES ($id, $id_prospector, $id_action, '$comment')
    ");
    $ret = DisplayActions($id, [], "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Added"];
    return ($ret);
}

function ConcludeProspect($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if (!isset($data["decision"]))
	bad_request();
    if ($data["decision"] == "remove")
    {
	$Database->query("
		UPDATE user SET deleted = NOW()
		WHERE id = $id AND profile_status = 'prospect'
	");
	return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
    }
    if ($data["decision"] == "restore")
    {
	$Database->query("
		UPDATE user SET deleted = NULL
		WHERE id = $id AND profile_status = 'prospect'
	");
	return (new ValueResponse(["msg" => $Dictionnary["Restored"]]));
    }
    if (!in_array($data["decision"], ["ecole", "of", "ofa", "cfa"]))
	bad_request();

    $ret = build_user_contract($id, document_builder_contract_kind($data));
    if ($ret->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => "Contrat généré",
	"content" => document_builder_public_url($ret->value["output"])
    ]));
}



function GenerateProspectDocument($id, $data, $method, $output, $module)
{
    $id = (int)$id;
    $document = trim((string)($data["document"] ?? ""));

    if ($id <= 0 || $document == "")
        bad_request();

    if (preg_match('/^contract:(ECL|OF|OFA|CFA)$/', $document, $match))
    {
        $ret = build_user_contract($id, $match[1]);
        $message = "Contrat généré";
    }
    else if ($document == "admission:domestic" || $document == "admission:foreign")
    {
        $ret = build_admission_certificate($id, [
            "is_foreign" => ($document == "admission:foreign"),
            "definitive" => true,
        ]);
        $message = "Attestation d’admission générée";
    }
    else
        bad_request();

    if ($ret->is_error())
        return ($ret);
    return (new ValueResponse([
        "msg" => $message,
        "content" => document_builder_public_url($ret->value["output"])
    ]));
}

function SendProspectRegistrationForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $result = registration_form_create_invitation((int)$id, $data["kind"] ?? "", (int)$User["id"]);
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));
    $student = $result["student"];
    $name = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? ""));
    $title = $Dictionnary["RegistrationFormMailTitle"] ?? "Votre dossier d'inscription";
    $body = sprintf(
        $Dictionnary["RegistrationFormMailContent"] ?? "Bonjour %s,\n\nVous pouvez compléter votre dossier d'inscription avec le lien suivant, valable quatorze jours :\n%s\n\nVous pouvez sauvegarder un brouillon avant la validation définitive.",
        $name,
        $result["url"]
    );
    $sent = send_mail($student["mail"], $title, $body);
    if ($sent->is_error())
    {
        // Do not leave a valid but undelivered public link behind.
        registration_form_revoke_token($result["token"]);
        return ($sent);
    }
    add_log(EDITING_OPERATION, "Registration form sent to user ".(int)$id." for ".$data["kind"]);
    return (new ValueResponse([
        "msg" => $Dictionnary["RegistrationFormSent"] ?? "Formulaire d'inscription envoyé.",
        "content" => $result["url"]
    ]));
}

function DeleteAction($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Database;
    global $Dictionnary;

    $id = (int)$id;
    $SUBID = (int)$SUBID;
    if ($id == -1)
	bad_request();
    $SUBID = abs($SUBID);
    $Database->query("
	DELETE FROM prospection WHERE id = $SUBID
    ");
    $ret = DisplayActions($id, [], "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Deleted"];
    return ($ret);
}

$Tab = [
    "GET" => [
	"" => [
	    "is_commercial",
	    "DisplayActions"
	]
    ],
    "POST" => [
	"paction" => [
	    "is_commercial",
	    "AddAction",
	]
    ],
    "PUT" => [
	"" => [
	    "is_commercial",
	    "ConcludeProspect",
	],
	"transform" => [
	    "is_commercial",
	    "TransformProspect",
	],
	"registration" => [
	    "is_commercial",
	    "SendProspectRegistrationForm",
	],
	"document" => [
	    "is_commercial",
	    "GenerateProspectDocument",
	],
    ],
    "DELETE" => [
	"paction" => [
	    "is_commercial",
	    "DeleteAction",
	]
    ]
];


