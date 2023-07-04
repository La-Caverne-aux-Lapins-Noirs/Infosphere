<?php

function trace()
{
    global $Database;
    global $OriginalUser;
    
    $ip = $Database->real_escape_string(get_client_ip());
    if ($OriginalUser != NULL)
	$id_user = $OriginalUser["id"];
    else
	$id_user = "NULL";

    $visit = db_select_one("
      id, id_user, visit_count, fast_visit_count,
      (UNIX_TIMESTAMP(last_visit)) as last_visit,
      (UNIX_TIMESTAMP(NOW(6))) as now
      FROM trace
      WHERE id_user = $id_user
      AND ip = '$ip'
      AND ABS(DATEDIFF(last_visit, NOW(6))) < 1
      ORDER BY last_visit DESC
    ");
    if (!$visit)
	$Database->query("
	    INSERT INTO trace (id_user, ip, last_visit, visit_count, fast_visit_count)
	    VALUES ($id_user, '$ip', NOW(6), 1, 1)
	");
    else
    {
	$id = $visit["id"];
	$visit_count = $visit["visit_count"];
	$fast_visit_count = $visit["fast_visit_count"];
	$last = $visit["last_visit"];
	$now = $visit["now"];
	if ($now - $last < 2.0)
	    $fast_visit_count += 1;
	$visit_count += 1;
	if ($fast_visit_count > 10 && $fast_visit_count % 10 == 0) // Bizarre...
	{
	    add_log(REPORT, "Trace report a high fast visit count ($fast_visit_count) for $ip (".($id_user ? $OriginalUser["codename"] : "").")", 1);
	}
	$Database->query("
	    UPDATE trace
	    SET last_visit = NOW(6),
	        fast_visit_count = $fast_visit_count,
		visit_count = $visit_count
	    WHERE id = $id
	");
    }
    $Database->query("
	DELETE FROM trace WHERE ABS(DATEDIFF(last_visit, NOW())) > 31
    ");
}
