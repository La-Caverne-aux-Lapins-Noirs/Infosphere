<?php

function DisplaySprint($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $TicketStatus;

    $act = db_select_one("
	activity.id
	FROM team
	LEFT JOIN activity
	ON team.id_activity = activity.id
        WHERE team.id = $id AND activity.deleted IS NULL
    ");
    ($activity = new FullActivity)->build($act["id"]);
    foreach ($activity->team as &$team)
	if ($team["id"] == $id)
	    $activity->user_team = &$team;

    ob_start();
    if (isset($data["id_sprint"]))
    {
	$tab_data = $data["id_sprint"];
	require ("./pages/instance/ticket_list.php");
    }
    else
	require ("./pages/instance/sprint_list.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function SetTicket($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;
    global $User;

    $id_team = (int)$id;
    foreach (["title", "description", "estimated_time", "id_user", "id_sprint"] as $verif)
	if (!isset($data[$verif]))
	    return (new ErrorResponse("MissingField", $verif));
    if (strlen($data["title"]) < 4)
	return (new ErrorResponse("TitleTooShort", $data["title"], "<4"));
    $title = $Database->real_escape_string($data["title"]);
    $description = $Database->real_escape_string($data["description"]);
    if (($estimated_time = (int)$data["estimated_time"]) < 0)
	bad_request();
    $id_user = (int)$data["id_user"];
    if (($id_sprint = (int)$data["id_sprint"]) <= 0)
	bad_request();

    if ($method == "POST")
    {
	$Database->query("
	  INSERT INTO ticket
           (id_sprint, id_author, id_user, estimated_time, title, description)
           VALUES
           ($id_sprint, {$User["id"]}, $id_user, $estimated_time, '$title', '$description')
	   ");
	$new_data = [
	    "id_sprint" => $id_sprint,
	    "id_ticket" => $Database->insert_id
	];
	return (DisplaySprint($id_team, $new_data, "GET", $output, $module));
    }
    foreach (["status", "real_time"] as $verif)
	if (!isset($data[$verif]))
	    return (new ErrorResponse("MissingField", $verif));
    if (($status = (int)$data["status"]) < -1 || $status > 3)
	bad_request();
    if (($real_time = (int)$data["real_time"]) < 0)
	bad_request();

    if ($id == -1)
	bad_request();
    $id = abs($data["ticket"]);
    $check = db_select_one("
       id FROM sprint WHERE id = $id_sprint AND id_team = $id_team
    ");
    if ($check == NULL || $check["id"] != $id_sprint)
	bad_request();
    if ($method == "DELETE")
	$Database->query("
          UPDATE ticket SET deleted = NOW() WHERE id = $id AND id_sprint = $id_sprint
	");
    else
    {
	if ($status == 3)
	    $done_date = "'".db_form_date(now(), true)."'";
	else
	    $done_date = "NULL";
	$Database->query("
	  UPDATE ticket SET
            title = '$title',
            description = '$description',
            id_user = $id_user,
            status = $status,
            estimated_time = $estimated_time,
            real_time = real_time + $real_time,
            done_date = $done_date
          WHERE id = $id AND id_sprint = $id_sprint
	");
    }
    return (DisplaySprint($id_team, ["id_sprint" => $id_sprint], "GET", $output, $module));
}

function SetSprint($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    $id_team = (int)$id;
    foreach (["title", "description", "start_date", "done_date"] as $verif)
	if (!isset($data[$verif]))
	    return (new ErrorResponse("MissingField", $verif));
    if (strlen($data["title"]) < 4)
	return (new ErrorResponse("TitleTooShort", $data["title"], "<4"));
    if (date_to_timestamp($data["start_date"]) >= date_to_timestamp($data["done_date"]))
	return (new ErrorResponse("InvalidDate"));
    $title = $Database->real_escape_string($data["title"]);
    $description = $Database->real_escape_string($data["description"]);
    $start_date = $Database->real_escape_string(db_form_date($data["start_date"]));
    $done_date = $Database->real_escape_string(db_form_date($data["done_date"]));
   
    if ($method == "POST")
    {
	$Database->query("
           INSERT INTO sprint (id_team, title, description, start_date, done_date)
           VALUES (
             $id_team,
             '$title',
	     '$description',
             '$start_date',
             '$done_date'
           )
	");
	return (DisplaySprint($id, [], "GET", $output, $module));
    }
    if ($id == -1)
	bad_request();
    $id = abs($data["sprint"]);
    if ($method == "DELETE")
	$Database->query("
          UPDATE sprint SET deleted = NOW() WHERE id = $id AND id_team = $id_team
	");
    else
	$Database->query("
	    UPDATE sprint SET
              title = '$title',
              description = '$description',
              start_date = '$start_date',
              done_date = '$done_date'
            WHERE id_team = $id_team AND id = $id
	");
    return (DisplaySprint($id_team, [], "GET", $output, $module));
}

$Tab = [
    "GET" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "DisplaySprint",
	],
    ],
    "POST" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "SetSprint"
	],
	"ticket" => [
	    "is_my_team_or_assistant",
	    "SetTicket",
	],
    ],
    "PUT" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "SetSprint"
	],
	"ticket" => [
	    "is_my_team_or_assistant",
	    "SetTicket",
	],
    ],
    "DELETE" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "SetSprint"
	],
	"ticket" => [
	    "is_my_team_or_assistant",
	    "SetTicket",
	],
    ]
];
