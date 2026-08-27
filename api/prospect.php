<?php

require_once (__DIR__."/../tools/post_interview_report.php");

function MoveProspectToCampaign($id, $data, $method, $output, $module)
{
    require_once ("./pages/prospecting/campaign_tools.php");

    $id = (int)$id;
    $campaign_id = (int)($data["campaign_id"] ?? 0);
    $registration_date = trim((string)($data["registration_date"] ?? ""));

    if ($id <= 0 || $campaign_id <= 0 || !campaign_date_is_valid($registration_date))
        bad_request();

    $campaign = campaign_fetch_one($campaign_id);
    if ($campaign == NULL || $campaign["deleted"] != NULL)
        return (new ErrorResponse("NotFound"));
    if ($registration_date < $campaign["start_date"] || $registration_date > $campaign["end_date"])
        bad_request();

    $prospect = db_select_one("id, registration_date
        FROM user
        WHERE id = $id AND profile_status = 'prospect'
    ");
    if ($prospect == NULL)
        return (new ErrorResponse("NotFound"));

    $time = "00:00:00";
    if (preg_match('/ ([0-9]{2}:[0-9]{2}:[0-9]{2})$/', (string)($prospect["registration_date"] ?? ""), $match))
        $time = $match[1];

    if (db_update_one("user", $id, ["registration_date" => $registration_date." ".$time]) === NULL)
        return (new ErrorResponse("CannotUpdate"));
    return (new ValueResponse([
        "msg" => "Prospect rattaché à la campagne ".($campaign["name"] ?? ""),
        "content" => datex("d/m/Y", $registration_date." ".$time),
    ]));
}

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



function FinalizeProspectInterviewReport($id, $data, $method, $output, $module)
{
    global $User;

    return (post_interview_report_finalize((int)$id, (int)($User["id"] ?? 0)));
}

function GenerateProspectDocument($id, $data, $method, $output, $module)
{
    global $User;

    $id = (int)$id;
    $document = trim((string)($data["document"] ?? ""));

    if ($id <= 0 || $document == "")
        bad_request();

    // Le compte rendu suit le même point d'entrée que les autres documents :
    // sélection dans la liste puis clic sur « Générer ». Sa « génération »
    // initiale ouvre le formulaire de travail et fige son auteur/signataire.
    if ($document == "post-interview-report")
        return (post_interview_report_start($id, (int)($User["id"] ?? 0)));

    // Les autres générations conservaient historiquement le droit commercial.
    // La route est maintenant seulement « logged_in » afin que le compte rendu
    // puisse aussi respecter ses propres droits (direction/secrétariat/commercial),
    // mais cela ne doit pas élargir l'accès aux contrats et attestations.
    if (!is_commercial())
        return (new ErrorResponse("PermissionDenied"));

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
	],
        "interview_report" => [
            "logged_in",
            "FinalizeProspectInterviewReport",
        ],
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
	"campaign" => [
	    "is_commercial,is_secretariat",
	    "MoveProspectToCampaign",
	],
	"registration" => [
	    "is_commercial",
	    "SendProspectRegistrationForm",
	],
	"document" => [
	    "logged_in",
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


