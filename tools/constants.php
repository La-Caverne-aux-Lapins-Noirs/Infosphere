<?php
$DeskTypes = ["ClassSeat", "LaptopSeat", "WindowsSeat", "MacSeat", "LinuxSeat", "OtherSeat"];
$DeskStatusColors = ["green", "yellow", "red", "gray", "black"];
$DeskStatus = ["Free", "Reserved", "Used", "Locked", "Unavailable"];
$ActivityType = [];
$Days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
$Month = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
$one_hour = 60 * 60;
$one_day = $one_hour * 24;
$one_week = $one_day * 7;
$five_minute = 60 * 5;

$Grade = [];

define("BANISHED", -1);
define("USER", 0);
define("ADMINISTRATOR", 1);

define("HIDDEN", 0);
define("PROFILE_ONLY", 1);
define("SUCCESSFUL_ACTIVITIES", 2);
define("COMPLETE_PROFILE", 3);

define("ASSISTANT", 1);
define("TEACHER", 2);

define("MODULE", 18);

function load_constants()
{
    global $ActivityType;
    global $Configuration;
    global $Grade;

    $ActivityType = db_select_all("* FROM activity_type", "id");
    $Grade = db_select_all("* FROM medal WHERE codename LIKE 'grade_%' AND deleted = 0", "codename");

    $prop = [];
    $Configuration->Properties = db_select_all("* FROM configuration", "codename");
    foreach ($Configuration->Properties as $i => $v)
	$prop[$v["codename"]] = $v["value"];
    $Configuration->Properties = $prop;
}

