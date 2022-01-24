<?php

if (isset($_GET["a"]))
    $_POST["code"] = $_GET["a"];

$format = "";
$upform = false;
if (isset($_POST["code"]))
{
    $cname = $Database->real_escape_string($_POST["code"]);
    $format = db_select_one("
        activity.id as id,
	activity.repository_name as repository_name,
	template.repository_name as template_repository
	FROM activity
	LEFT JOIN activity as template ON activity.id_template = template.id
	WHERE activity.is_template = 0
	  AND activity.codename = '$cname'
    ");
    if (@strlen($format["repository_name"]) > 0)
	$format = $format["repository_name"];
    else
	$format = $format["template_repository"];
    if ($format != NULL)
	$upform = true;
}

