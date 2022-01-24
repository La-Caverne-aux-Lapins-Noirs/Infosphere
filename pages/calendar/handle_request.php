<?php
if ($_POST["action"] == "filter")
{
    if (($request = resolve_codename("cycle", @$_POST["filter_cycle"]))->is_error() && $request->label != "MissingCodeName")
	return ;
    if (!is_array($cycle = $request->value))
	$cycle = [$cycle];
    if (($request = resolve_codename("room", @$_POST["filter_room"]))->is_error() && $request->label != "MissingCodeName")
	return ;
    if (!is_array($room = $request->value))
	$room = [$room];
    // $teacher = $_POST["filter_teacher"];
    // set_cookie("filter_cycle", @$_POST["filter_cycle"], time() + 365 * 24 * 60 * 60);
    $User["misc_configuration"]["calendar"]["filter_cycle"] = $cycle;
    $User["misc_configuration"]["calendar"]["filter_room"] = $room;
    $misc = $Database->real_escape_string(json_encode($User["misc_configuration"], JSON_UNESCAPED_SLASHES));
    $Database->query("UPDATE user SET misc_configuration = '$misc' WHERE id = ".$User["id"]);
    $request = new Response;
}

