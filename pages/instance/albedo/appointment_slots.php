<?php

///////////////////////////////////////////////////////
/// Création et affectation automatique des créneaux ///
///////////////////////////////////////////////////////

$activities = db_select_all("
  activity.id as id_activity,
  activity.codename as codename,
  COALESCE(activity.slot_duration, template.slot_duration) as slot_duration,
  COALESCE(activity.team_based_slot_opening, template.team_based_slot_opening, 0) as team_based_slot_opening,
  session.id as id_session
  FROM activity
  LEFT JOIN activity as template ON activity.id_template = template.id AND activity.template_link = 1
  LEFT JOIN session ON session.id_activity = activity.id
  WHERE (activity.is_template = 0 OR activity.is_template IS NULL)
    AND activity.deleted IS NULL
    AND session.deleted IS NULL
    AND session.id IS NOT NULL
    AND COALESCE(activity.slot_duration, template.slot_duration) IS NOT NULL
    AND COALESCE(activity.slot_duration, template.slot_duration) > 0
    AND (
      COALESCE(activity.progressive_slot_opening, template.progressive_slot_opening, 0) = 1
      OR COALESCE(activity.team_based_slot_opening, template.team_based_slot_opening, 0) = 1
    )
    AND (COALESCE(activity.registration_date, template.registration_date) IS NULL OR COALESCE(activity.registration_date, template.registration_date) <= NOW())
    AND (COALESCE(activity.close_date, template.close_date) IS NULL OR COALESCE(activity.close_date, template.close_date) > NOW())
    AND (COALESCE(activity.done_date, template.done_date) IS NULL OR COALESCE(activity.done_date, template.done_date) > NOW())
");

foreach ($activities as $activity)
{
    $slots = db_select_one("
      COUNT(appointment_slot.id) as cnt
      FROM appointment_slot
      WHERE appointment_slot.id_session = ".(int)$activity["id_session"]
    );
    if ($slots == NULL || (int)$slots["cnt"] == 0)
    {
	$duration = (int)$activity["slot_duration"];
	$duration = sprintf("%02d:%02d", $duration / 60, $duration % 60);

	if ($activity["team_based_slot_opening"])
	    $ret = generate_slots_from_registered_teams($activity["id_session"], $duration);
	else
	    $ret = generate_slots($activity["id_session"], $duration, 1);

	if ($ret->is_error())
	{
	    add_log(TRACE, "Albedo failed to generate appointment slots for ".$activity["codename"]." #".$activity["id_activity"].": ".strval($ret), 1);
	    continue ;
	}

	add_log(TRACE, "Albedo generated appointment slots for ".$activity["codename"]." #".$activity["id_activity"]." session #".$activity["id_session"], 1);
    }

    if (!has_automatic_appointment_subscription($activity["id_activity"]))
	continue ;

    $ret = assign_registered_teams_to_slots($activity["id_session"]);
    if ($ret->is_error())
    {
	add_log(TRACE, "Albedo failed to assign appointment slots for ".$activity["codename"]." #".$activity["id_activity"].": ".strval($ret), 1);
	continue ;
    }
    add_log(TRACE, "Albedo assigned ".$ret->value["assigned"]." appointment slots for ".$activity["codename"]." #".$activity["id_activity"], 1);
}
