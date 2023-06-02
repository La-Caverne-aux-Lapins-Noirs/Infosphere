<?php

function DisplaySprint($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

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
    if (isset($data["id_sprint"]))
	$sprint = db_select_one("
           * FROM sprint
	   WHERE id = ".$data["id_sprint"]." AND id_team = $id AND deleted IS NULL
	   ");
    ob_start();
    require ("./pages/instance/sprint_list.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function SetSprint($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    $id_team = (int)$id;
    foreach (["title", "description", "start_date", "done_date"] as $verif)
	if (!isset($data[$verif]))
	    bad_request();
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
	return (DisplaySprint($id, ["id_sprint" => $Database->insert_id], "GET", $output, $module));
    }
    if ($id == -1)
	bad_request();
    $id = abs($data["sprint"]);
    if ($method == "DELETE")
	$Database->query("
          DELETE FROM sprint WHERE id = $id AND id_team = $id_team
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
    return (DisplaySprint($id_team, ["id_sprint" => $id], "GET", $output, $module));
}

$Tab = [
    "GET" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "DisplaySprint",
	]
    ],
    "POST" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "SetSprint"
	],
    ],
    "PUT" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "SetSprint"
	],
    ],
    "DELETE" => [
	"sprint" => [
	    "is_my_team_or_assistant",
	    "SetSprint"
	],
    ]
];
