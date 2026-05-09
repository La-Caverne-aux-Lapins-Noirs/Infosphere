<?php

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
		WHERE id = $id AND password = ''
	");
	return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
    }
    if ($data["decision"] == "restore")
    {
	$Database->query("
		UPDATE user SET deleted = NULL
		WHERE id = $id AND password = ''
	");
	return (new ValueResponse(["msg" => $Dictionnary["Restored"]]));
    }
    if (!in_array($data["decision"], ["ecole", "of", "ofa", "cfa"]))
	bad_request();

    // On écrase le fichier actuellement présent par un placeholder
    shell_exec("cp ".__DIR__."/../res/please_wait.pdf ".__DIR__."/../dres/user/".$codename."/admin/contract.pdf");
    // On crée la configuration Dabsic qui sera utilisée
    //

    // On génère le vrai contrat - qui écrasera le placeholder une fois terminé    
    if ($data["decision"] == "of")
    {

    }
    if ($data["decision"] == "ofa") // of alternance
    {

    }
    if ($data["decision"] == "cfa") // apprentissage
    {

    }
    if ($data["decision"] == "ecole")
    {

    }
    
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
	]
    ],
    "DELETE" => [
	"paction" => [
	    "is_commercial",
	    "DeleteAction",
	]
    ]
];


