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
           INSERT INTO appointment_slot (id_session, id_team, begin_date, end_date)
           VALUES ($session, -1, '".$h["begin_date"]."', '".$h["end_date"]."')
	   ");
    }
    return (new ValueResponse(""));
}


function get_slot_generation_context($session)
{
    $session = (int)$session;
    $context = db_select_one("
       session.id,
       session.id_activity,
       session.begin_date,
       session.end_date,
       session.maximum_subscription,
       activity.reference_activity,
       activity.max_team_size,
       activity.slot_duration
       FROM session
       LEFT JOIN activity ON activity.id = session.id_activity
       WHERE session.id = $session
    ");
    if ($context == NULL)
	return (new ErrorResponse("NotAnId"));
    return (new ValueResponse($context));
}

function get_slot_generation_team_count($context)
{
    if ($context["reference_activity"] != NULL && (int)$context["reference_activity"] != -1)
	$where = "team.id_activity = ".(int)$context["reference_activity"];
    else
	$where = "team.id_session = ".(int)$context["id"];

    $teams = db_select_one("
       COUNT(DISTINCT team.id) as cnt
       FROM team
       WHERE $where
    ");
    if ($teams == NULL)
	return (0);
    return ((int)$teams["cnt"]);
}

function get_slot_generation_capacity($context)
{
    $capacity = NULL;

    if ($context["maximum_subscription"] != NULL && (int)$context["maximum_subscription"] > 0)
	$capacity = (int)$context["maximum_subscription"];
    else
    {
	$rooms = db_select_all("
          room.capacity
          FROM session_room
          LEFT JOIN room ON room.id = session_room.id_room
          WHERE session_room.id_session = ".(int)$context["id"]
	);
	foreach ($rooms as $room)
	{
	    if ((int)$room["capacity"] == -1)
		return (20);
	    if ($capacity === NULL)
		$capacity = 0;
	    $capacity += (int)$room["capacity"];
	}
    }

    if ($capacity === NULL || $capacity <= 0)
	return (20);

    $team_size = (int)$context["max_team_size"];
    if ($team_size <= 0)
	$team_size = 1;
    return (max(1, min(20, (int)floor($capacity / $team_size))));
}


function get_slot_generation_team_where($context)
{
    if ($context["reference_activity"] != NULL && (int)$context["reference_activity"] != -1)
	return ("team.id_activity = ".(int)$context["reference_activity"]);
    return ("team.id_session = ".(int)$context["id"]);
}

function get_slot_generation_teams($context)
{
    $where = get_slot_generation_team_where($context);
    return (db_select_all("
       team.id
       FROM team
       WHERE $where
       ORDER BY team.id
    "));
}

function assign_registered_teams_to_slots($session)
{
    global $Database;

    if (($context = get_slot_generation_context($session))->is_error())
	return ($context);
    $context = $context->value;

    $already = db_select_all("
       appointment_slot.id_team
       FROM appointment_slot
       WHERE appointment_slot.id_session = ".(int)$context["id"]."
       AND appointment_slot.id_team > 0
    ", "id_team");

    $teams = [];
    foreach (get_slot_generation_teams($context) as $team)
    {
	if (!isset($already[$team["id"]]))
	    $teams[] = (int)$team["id"];
    }
    if (!count($teams))
	return (new ValueResponse(["assigned" => 0]));

    $slots = db_select_all("
       appointment_slot.id
       FROM appointment_slot
       WHERE appointment_slot.id_session = ".(int)$context["id"]."
       AND appointment_slot.id_team = -1
       ORDER BY appointment_slot.begin_date ASC, appointment_slot.id ASC
    ");
    if (count($slots) < count($teams))
	return (new ErrorResponse("NotEnoughAppointmentSlots", count($slots)."/".count($teams)));

    shuffle($teams);
    shuffle($slots);
    $assigned = 0;
    foreach ($teams as $i => $id_team)
    {
	$id_slot = (int)$slots[$i]["id"];
	if (!$Database->query("UPDATE appointment_slot SET id_team = $id_team WHERE id = $id_slot AND id_team = -1"))
	    return (new ErrorResponse("CannotEdit"));
	$assigned += 1;
    }
    return (new ValueResponse(["assigned" => $assigned]));
}

function has_automatic_appointment_subscription($activity_id)
{
    $activity_id = (int)$activity_id;
    $activity = db_select_one("
       COALESCE(activity.subscription, template.subscription) as subscription
       FROM activity
       LEFT JOIN activity as template ON activity.id_template = template.id AND activity.template_link = 1
       WHERE activity.id = $activity_id
    ");
    if ($activity != NULL && (int)$activity["subscription"] == 2)
	return (true);

    $cycle = db_select_one("
       activity_cycle.id
       FROM activity_cycle
       WHERE activity_cycle.id_activity = $activity_id
       AND activity_cycle.replacement_subscription = 2
       LIMIT 1
    ");
    return ($cycle != NULL);
}

function generate_slots_from_registered_teams($session, $duration)
{
    if (($duration_ts = hour_to_timestamp($duration)) < 5 * 60)
	return (new ErrorResponse("SlotsTooShort"));
    if (($context = get_slot_generation_context($session))->is_error())
	return ($context);
    $context = $context->value;

    $start = date_to_timestamp($context["begin_date"]);
    $session_duration = date_to_timestamp($context["end_date"]) - $start;
    $base_slots = (int)floor($session_duration / $duration_ts);
    if ($base_slots <= 0)
	return (new ErrorResponse("SessionTooShortForAppointmentSlots"));

    $team_count = get_slot_generation_team_count($context);
    if ($team_count <= 0)
	return (new ErrorResponse("NoTeamToOpenSlotsFor"));

    $parallel = (int)ceil($team_count / $base_slots);
    $capacity = get_slot_generation_capacity($context);
    if ($parallel > $capacity)
	return (new ErrorResponse("NotEnoughRoomForAppointmentSlots", $parallel."/".$capacity));

    return (generate_slots($session, $duration, $parallel));
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
  	      INSERT INTO appointment_slot (id_session, id_team, begin_date, end_date)
	      VALUES (".$session["id"].", -1, '".
	  	       datex("Y-m-d H:i:s", ($i + 0) * $duration + $start)."', '".
		       datex("Y-m-d H:i:s", ($i + 1) * $duration + $start)."')
		       ";
	    $Database->query($forge);
	}
    }
    return (new ValueResponse(""));
}

