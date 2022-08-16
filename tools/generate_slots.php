<?php

function add_slot_layer($session)
{
    global $Database;

    $hours = [];
    $all = db_select_all("* FROM appointment_slot WHERE id_session = $session");
    foreach ($all as $l)
    {
	$hours[$l["begin_date"]] = $l;
    }
    foreach ($hours as $h)
    {
	$Database->query("
           INSERT INTO appointment_slot (id_session, begin_date, end_date)
           VALUES ($session, '".$h["begin_date"]."', '".$h["end_date"]."')
	   ");
    }
    return (new ValueResponse(""));
}

function generate_slots($session, $duration, $parralel = 1)
{
    global $Database;

    if (($duration = hour_to_timestamp($duration)) < 5 * 60)
	return (new ErrorResponse("SlotsTooShort"));
    if ($parralel < 1 || $parralel > 20)
	return (new ErrorResponse("InvalidSimultaneous"));

    // On commence par supprimer l'intégralité des créneaux existants pour cette instance.
    if (($session = db_select_one("* FROM session WHERE id = $session")) == NULL)
	return (new ErrorResponse("NotAnId"));
    $Database->query("DELETE FROM appointment_slot WHERE id_session = ".$session["id"]);

    $start = date_to_timestamp($session["begin_date"]);
    $session_duration = date_to_timestamp($session["end_date"]) - $start;
    $nbr_slots = floor($session_duration / $duration);

    for ($i = 0; $i < $nbr_slots; ++$i)
    {
	for ($j = 0; $j < $parralel; ++$j)
	{
 	    $forge = "
  	      INSERT INTO appointment_slot (id_session, begin_date, end_date)
	      VALUES (".$session["id"].", '".
	  	       datex("Y-m-d H:i:s", ($i + 0) * $duration + $start)."', '".
		       datex("Y-m-d H:i:s", ($i + 1) * $duration + $start)."')
		       ";
	    $Database->query($forge);
	}
    }
    return (new ValueResponse(""));
}

