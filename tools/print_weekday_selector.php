<?php

function print_weekday_selector($value, array $fields, $second_value = NULL, $check_button = true, $onchange = "")
{
    global $one_week;
    global $one_day;
    global $one_hour;
    global $date0;

    if ($value == NULL)
	$week = 0;
    else
    {
	$week = date_to_timestamp($value);
	$week = $week - date_to_timestamp($date0); // Premier jour virtuel
    }
    $day = $week;
    $hour = $week;
    $min = $week;

    $week = (int)($week / $one_week) + 1;
    $day = (int)($day / $one_day) % 7 + 1;
    $hour = (int)($hour / $one_hour) % 24;
    $min = (int)($min / 60) % 60;

    //$empty = ($value == NULL || ($week == 1 && $day == 1 && $hour == 0));
    $empty = ($value === NULL);
    if ($check_button == false)
	$empty = false;

    if ($onchange != "")
	$onchange = " onchange=\"$onchange\" ";
    
    $rnd = random_name();
    print_int_selector(1, 52, $fields[0], $week, "date_composer", $rnd."0", $empty ? "disabled" : "", $onchange);
    print_int_selector(1, 7, $fields[1], $day, "date_composer", $rnd."1", $empty ? "disabled" : "", $onchange);

    if (isset($fields[2]))
    {
	echo "<input $onchange ".
	     "id=\"{$rnd}2\"".
	     "type=\"time\" ".
	     "name=\"{$fields[2]}\" ".
	     //"min=\"07:00\" ".
	     //"max=\"23:30\" ".
	     //"step=\"1800\" ".
	     "class=\"date_composer hour\" ".
	     "value=\"".sprintf("%02d", $hour).":".sprintf("%02d", $min)."\" ".
	     ($empty ? "disabled" : "").
	     "/>"
	     ;
    }
    
    if ($check_button && isset($fields[3]))
    {
	echo "<input $onchange ".
	     "class=\"checkweekday\" ".
	     "name=\"{$fields[3]}_check\" ".
	     "type=\"checkbox\" ".
	     "onclick=\"enable('{$rnd}0', this.checked); enable('{$rnd}1', this.checked); enable('{$rnd}2', this.checked); \" ".
	     ($empty ? "" : "checked").
	     "/>";
    }
}
