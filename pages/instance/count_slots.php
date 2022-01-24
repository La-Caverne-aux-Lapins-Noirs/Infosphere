<?php

function count_slots(&$session)
{
    global $Database;

    $forge = "
      SELECT COUNT(appointment_slot.id) as slots_count
      FROM appointment_slot
      WHERE appointment_slot.id_session = ".$session["id_session"]."
      GROUP BY appointment_slot.id
      ";
    $q = $Database->query($forge);
    if (($u = $q->fetch_assoc()) == NULL)
    {
	$session["slots_count"] = 0;
	return (0);
    }
    $session["slots_count"] = $u["slots_count"];
    return ($u["slots_count"]);
}

