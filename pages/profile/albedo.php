<?php
if (!isset($albedo) || $albedo != 1)
    return ;

//////////////////////////////////////////
//// LOG DE CONNEXION ET MAJ DES POSTES //
//////////////////////////////////////////


function albedo_room_machine_type($type)
{
    if (is_numeric($type))
        return ((int)$type);
    $type = strtolower(trim((string)$type));
    if ($type == "linux" || $type == "debian" || $type == "ubuntu" || $type == "archlinux")
        return (0);
    if ($type == "windows")
        return (1);
    if ($type == "mac" || $type == "macos" || $type == "darwin")
        return (2);
    if ($type == "rpi" || $type == "raspberry" || $type == "raspberrypi")
        return (3);
    return (0);
}

function albedo_room_log_username($entry)
{
    if (isset($entry["user"]) && trim((string)$entry["user"]) != "")
        return (trim((string)$entry["user"]));
    if (isset($entry["username"]) && trim((string)$entry["username"]) != "")
        return (trim((string)$entry["username"]));
    return ("");
}

function albedo_room_log_machine_key($entry)
{
    if (isset($entry["mac"]) && trim((string)$entry["mac"]) != "")
        return (strtolower(trim((string)$entry["mac"])));
    if (isset($entry["machine"]) && trim((string)$entry["machine"]) != "")
        return (strtolower(trim((string)$entry["machine"])));
    if (isset($entry["name"]) && trim((string)$entry["name"]) != "")
        return (strtolower(trim((string)$entry["name"])));
    return ("");
}

function albedo_room_log_duration($entry)
{
    $xtime = max(0, (int)(isset($entry["xtime"]) ? $entry["xtime"] : 0));
    $locktime = max(0, (int)(isset($entry["locktime"]) ? $entry["locktime"] : 0));
    $sshtime = max(0, (int)(isset($entry["sshtime"]) ? $entry["sshtime"] : 0));
    $ssh_idle_time = max(0, (int)(isset($entry["ssh_idle_time"])
        ? $entry["ssh_idle_time"]
        : (isset($entry["sshidletime"]) ? $entry["sshidletime"] : 0)));

    // locktime is included in xtime.  This helper is used to decide whether a
    // measured entry contains anything worth importing, so do not double-count
    // the locked portion here either.
    return (max($xtime, $locktime) + $sshtime + $ssh_idle_time);
}

function albedo_room_merge_measured_state($previous, $current)
{
    if (!is_array($previous) || empty($previous["distrans_duration"]))
        return ($current);
    if (empty($current["distrans_duration"]))
        return ($previous);

    $merged = $previous;
    foreach (["xtime", "sshtime", "ssh_idle_time", "locktime"] as $field)
        $merged[$field] = max(0, (int)($previous[$field] ?? 0))
            + max(0, (int)($current[$field] ?? 0));

    $merged["current"] = !empty($previous["current"]) || !empty($current["current"]);
    $merged["distant"] = !empty($previous["distant"]) || !empty($current["distant"]);
    $merged["ssh_idle"] = !empty($previous["ssh_idle"]) || !empty($current["ssh_idle"]);
    $merged["lock"] = !empty($previous["lock"]) || !empty($current["lock"]);
    $merged["local_current"] = !empty($previous["local_current"]) || !empty($current["local_current"]);
    $merged["remote_current"] = !empty($previous["remote_current"]) || !empty($current["remote_current"]);
    $merged["distrans_duration"] = true;
    $merged["activity_timestamp"] = max(
        (int)($previous["activity_timestamp"] ?? 0),
        (int)($current["activity_timestamp"] ?? 0)
    );

    $previous_token = trim((string)($previous["reset_token"] ?? ""));
    $current_token = trim((string)($current["reset_token"] ?? ""));
    if ($previous_token == "")
        $merged["reset_token"] = $current_token;
    else if ($current_token == "" || $current_token == $previous_token)
        $merged["reset_token"] = $previous_token;
    else
        // Different epochs inside one aggregate are safer without a token: the
        // cursor can still detect a reset from counters that moved backwards.
        $merged["reset_token"] = "";

    if (trim((string)($merged["source_key"] ?? "")) == "")
        $merged["source_key"] = (string)($current["source_key"] ?? "");
    if (trim((string)($merged["ip"] ?? "")) == "")
        $merged["ip"] = (string)($current["ip"] ?? "");
    return ($merged);
}

function albedo_room_log_mode($entry)
{
    foreach (["mode", "activity_mode", "activity", "state", "status"] as $field)
        if (isset($entry[$field]) && trim((string)$entry[$field]) != "")
            return (strtolower(trim((string)$entry[$field])));
    return ("");
}

function albedo_room_log_is_ssh_idle($entry)
{
    if (isset($entry["ssh_idle"]) && $entry["ssh_idle"])
        return (true);
    $mode = str_replace("-", "_", albedo_room_log_mode($entry));
    if ($mode == "ssh_idle" || $mode == "idle_ssh")
        return (true);
    if (isset($entry["idle"]) && $entry["idle"] && ($mode == "ssh" || !empty($entry["distant"])))
        return (true);
    return (false);
}

function albedo_room_log_is_distant($entry)
{
    if (isset($entry["distant"]))
        return (!empty($entry["distant"]));
    $mode = str_replace("-", "_", albedo_room_log_mode($entry));
    return ($mode == "ssh" || $mode == "ssh_idle" || $mode == "idle_ssh");
}

function albedo_room_log_timestamp($entry, $fallback = NULL)
{
    foreach (["last_activity_timestamp", "updated_timestamp", "last_activity", "updated_at", "updated", "date", "timestamp", "log_date"] as $field)
    {
        if (!isset($entry[$field]) || trim((string)$entry[$field]) == "")
            continue ;
        if (is_numeric($entry[$field]))
            return ((int)$entry[$field]);
        $ts = @date_to_timestamp($entry[$field]);
        if ($ts > 0)
            return ($ts);
    }
    if (is_array($fallback))
        return (albedo_room_log_timestamp($fallback));
    return (0);
}

function albedo_room_log_reset_token($entry)
{
    foreach (["reset_timestamp", "reset_at", "reset"] as $field)
        if (isset($entry[$field]) && trim((string)$entry[$field]) != "")
            return (trim((string)$entry[$field]));
    return ("");
}

function albedo_room_log_source_key($entry, $fallback = NULL)
{
    $key = albedo_room_log_machine_key($entry);
    if ($key == "" && is_array($fallback))
        $key = albedo_room_log_machine_key($fallback);
    return ($key);
}

function albedo_room_log_counter_source_key($entry, $fallback = NULL)
{
    $machine_key = albedo_room_log_source_key($entry, $fallback);
    if ($machine_key == "")
        return ("");

    // Version the cursor namespace when the aggregation semantics change.  The
    // first v2 sample is deliberately only a baseline (see student_log.php), so
    // old single-state cursors cannot turn a newly discovered SSH/X companion
    // state into a large historical spike.
    return ($machine_key."|activity-v2");
}

function albedo_room_log_is_current($entry, $current_users = [], $fallback = NULL)
{
    if (isset($entry["current"]))
        return (!empty($entry["current"]));
    if (isset($entry["active"]))
        return (!empty($entry["active"]));
    if (isset($entry["present"]))
        return (!empty($entry["present"]));
    if (isset($entry["connected"]))
        return (!empty($entry["connected"]));
    if (albedo_room_distrans_user_is_current($entry, $current_users))
        return (true);

    $ts = albedo_room_log_timestamp($entry, $fallback);
    if ($ts <= 0)
        return (false);
    return (time() - $ts <= 5 * 60);
}

function albedo_room_current_users_by_machine($logs)
{
    $ret = [];

    foreach (isset($logs["machines"]) && is_array($logs["machines"]) ? $logs["machines"] : [] as $machine)
    {
        $machine_key = albedo_room_log_machine_key($machine);
        if ($machine_key == "")
            continue ;
        if (!isset($ret[$machine_key]))
            $ret[$machine_key] = [];
        foreach (isset($machine["users"]) && is_array($machine["users"]) ? $machine["users"] : [] as $username)
        {
            $username = trim((string)$username);
            if ($username != "")
                $ret[$machine_key][$username] = true;
        }
    }
    return ($ret);
}

function albedo_room_distrans_user_is_current($user, $current_users)
{
    $machine_key = albedo_room_log_machine_key($user);
    $username = albedo_room_log_username($user);

    return (
        $machine_key != ""
        && $username != ""
        && isset($current_users[$machine_key])
        && isset($current_users[$machine_key][$username])
    );
}

function albedo_room_keep_distrans_user_log($user, $current_users)
{
    if (albedo_room_distrans_user_is_current($user, $current_users))
        return (true);
    if (albedo_room_log_duration($user) > 0)
        return (true);
    if (isset($user["reset_timestamp"]) && isset($user["updated_timestamp"])
        && (int)$user["updated_timestamp"] <= (int)$user["reset_timestamp"])
        return (false);
    return (true);
}

function albedo_room_logs_from_distrans_payload($logs)
{
    $current_users = albedo_room_current_users_by_machine($logs);
    $users_by_machine = [];
    foreach (isset($logs["users"]) && is_array($logs["users"]) ? $logs["users"] : [] as $user)
    {
	$machine = isset($user["machine"]) ? strtolower(trim((string)$user["machine"])) : "";
	$username = albedo_room_log_username($user);
	if ($machine == "" || $username == "")
            continue ;

	if (function_exists("room_desk_system_user_is_ignored")
            && room_desk_system_user_is_ignored($username))
        continue ;

	if (!isset($users_by_machine[$machine]))
            $users_by_machine[$machine] = [];
	$users_by_machine[$machine][] = $user;
    }

    $content = [];
    foreach (isset($logs["machines"]) && is_array($logs["machines"]) ? $logs["machines"] : [] as $machine)
    {
        if (!isset($machine["name"]) || !isset($machine["mac"]) || !isset($machine["ip"]))
            continue ;
        $mac = strtolower(trim((string)$machine["mac"]));
        $machine_users = isset($users_by_machine[$mac]) ? $users_by_machine[$mac] : [];
        $known_machine_users = [];
        foreach ($machine_users as $machine_user)
        {
            $username = albedo_room_log_username($machine_user);
            if ($username != "")
                $known_machine_users[$username] = true;
        }
        if (isset($machine["users"]) && is_array($machine["users"]))
        {
            foreach ($machine["users"] as $username)
            {
                $username = trim((string)$username);
                if ($username == "" || isset($known_machine_users[$username]))
                    continue ;
                $machine_users[] = [
                    "user" => $username,
                    "machine" => $mac,
                    "mode" => "ssh",
                    "lock" => false,
                    "current" => true,
                    "updated_at" => isset($machine["updated_at"]) ? $machine["updated_at"] : now()
                ];
            }
        }
        if (!count($machine_users))
        {
            $content[] = [
                "name" => $machine["name"],
                "mac" => $machine["mac"],
                "ip" => $machine["ip"],
                "type" => albedo_room_machine_type(isset($machine["type"]) ? $machine["type"] : 0),
                "user" => "",
                "date" => isset($machine["updated_at"]) ? $machine["updated_at"] : now(),
                "lock" => isset($machine["lock"]) && $machine["lock"],
                "distant" => true,
            ];
            continue ;
        }
        foreach ($machine_users as $user)
        {
            $mode = albedo_room_log_mode($user);
            $distant = albedo_room_log_is_distant($user);
            $ssh_idle = albedo_room_log_is_ssh_idle($user);
	    $current = albedo_room_log_is_current($user, $current_users, $machine);
            $content[] = [
                "name" => $machine["name"],
                "mac" => $machine["mac"],
                "ip" => $machine["ip"],
                "type" => albedo_room_machine_type(isset($machine["type"]) ? $machine["type"] : 0),
                "user" => albedo_room_log_username($user),
                "date" => isset($user["last_activity"]) ? $user["last_activity"] : (isset($user["updated_at"]) ? $user["updated_at"] : (isset($machine["updated_at"]) ? $machine["updated_at"] : now())),
		"lock" => $ssh_idle || ((!$distant && isset($user["lock"])) ? $user["lock"] : (isset($machine["lock"]) && $machine["lock"] && !$distant)),
                "distant" => $distant,
                "ssh_idle" => $ssh_idle,
		"current" => $current,
                "distrans_duration" => array_key_exists("xtime", $user)
                    || array_key_exists("sshtime", $user)
                    || array_key_exists("ssh_idle_time", $user)
                    || array_key_exists("sshidletime", $user)
                    || array_key_exists("locktime", $user),
                "source_key" => albedo_room_log_counter_source_key($user, $machine),
                "reset_token" => albedo_room_log_reset_token($user),
                "activity_timestamp" => albedo_room_log_timestamp($user, $machine),
                "xtime" => isset($user["xtime"]) ? (int)$user["xtime"] : 0,
                "sshtime" => isset($user["sshtime"]) ? (int)$user["sshtime"] : 0,
                "ssh_idle_time" => isset($user["ssh_idle_time"]) ? (int)$user["ssh_idle_time"] : (isset($user["sshidletime"]) ? (int)$user["sshidletime"] : 0),
                "locktime" => isset($user["locktime"]) ? (int)$user["locktime"] : 0,
            ];
        }
    }
    return ($content);
}

$logcnt = 0;
$distrans_import_failed = false;
$distrans_structured_payload = false;
$distrans_structured_has_counters = false;
$distrans_worklog_lock = @fopen(sys_get_temp_dir()."/infosphere-distrans-worklog.lock", "c");
$distrans_worklog_locked = $distrans_worklog_lock !== false
    && @flock($distrans_worklog_lock, LOCK_EX | LOCK_NB);
if (!$distrans_worklog_locked)
{
    $logs = false;
    add_log(TRACE, "Distrans activity import skipped because another Albedo process owns the worklog lock", 1);
}
else if (($logs = hand_request(["command" => "get_activity_log"], false)) === false)
    add_log(TRACE, "Failed to get activity log from TechnoCore", 1);
else
{
    $distrans_structured_payload = !isset($logs["content"]) && isset($logs["machines"]);
    if ($distrans_structured_payload)
    {
        foreach (isset($logs["users"]) && is_array($logs["users"]) ? $logs["users"] : [] as $distrans_user)
            if (array_key_exists("xtime", $distrans_user)
                || array_key_exists("sshtime", $distrans_user)
                || array_key_exists("ssh_idle_time", $distrans_user)
                || array_key_exists("sshidletime", $distrans_user)
                || array_key_exists("locktime", $distrans_user))
            {
                $distrans_structured_has_counters = true;
                break ;
            }
        $logs["content"] = albedo_room_logs_from_distrans_payload($logs);
        if (!$distrans_structured_has_counters && count($logs["content"]))
        {
            $distrans_import_failed = true;
            add_log(REPORT, "Distrans structured activity payload has no duration counters; refusing to reset it", 1);
        }
        if ($distrans_structured_has_counters && !user_log_distrans_cursor_table_available())
        {
            $distrans_import_failed = true;
            add_log(REPORT, "Table user_log_distrans_cursor missing; refusing to consume/reset Distrans activity counters", 1);
        }

        // Never acknowledge/reset a measured user that could not be attached to
        // one of the machines returned by Distrans.  Otherwise those seconds
        // would disappear before an administrator can repair the mapping.
        $converted = [];
        foreach ($logs["content"] as $converted_log)
        {
            $converted_user = albedo_room_log_username($converted_log);
            $converted_source = albedo_room_log_source_key($converted_log);
            if ($converted_user != "" && $converted_source != "")
                $converted[$converted_source."/".$converted_user] = true;
        }
        foreach (isset($logs["users"]) && is_array($logs["users"]) ? $logs["users"] : [] as $raw_user)
        {
            $raw_duration = albedo_room_log_duration($raw_user);
            if ($raw_duration <= 0)
                continue ;
            $raw_username = albedo_room_log_username($raw_user);
            if (function_exists("room_desk_system_user_is_ignored")
                && room_desk_system_user_is_ignored($raw_username))
                continue ;
            $raw_source = albedo_room_log_source_key($raw_user);
            if ($raw_username == "" || $raw_source == "" || !isset($converted[$raw_source."/".$raw_username]))
            {
                $distrans_import_failed = true;
                add_log(REPORT, "Distrans measured activity could not be mapped to a machine/user: ".
                    $raw_username." on ".$raw_source." (".$raw_duration." second(s) pending)", 1);
            }
        }
    }
}

if (isset($logs) && is_array($logs) && isset($logs["content"]))
{
    $current_users = albedo_room_current_users_by_machine($logs);
    $ologs = [];
    foreach ($logs["content"] as $log)
    {
	if (!strlen(@$log["name"]) || !strlen(@$log["mac"]) || !strlen(@$log["ip"]) || !isset($log["type"]))
	    continue ;
	if (is_array($log["name"]))
	    continue ;
	if (!isset($log["distant"]))
	    $log["distant"] = albedo_room_log_is_distant($log);
	if (!isset($log["lock"]))
	    $log["lock"] = false;
	if (!isset($log["ssh_idle"]))
	    $log["ssh_idle"] = albedo_room_log_is_ssh_idle($log);
	if (!isset($log["current"]))
	    $log["current"] = albedo_room_log_is_current($log, $current_users);
	$users = strlen(@$log["user"]) && strlen(@$log["date"]) && isset($log["lock"]) && isset($log["distant"]);
	if (!isset($ologs[$log["name"]]))
	{
	    $ologs[$log["name"]] = $log;
	    $ologs[$log["name"]]["user"] = [];
	    $ologs[$log["name"]]["user_state"] = [];
	    $ologs[$log["name"]]["log_user"] = [];
	    $ologs[$log["name"]]["log_user_state"] = [];
	}
	if (!$log["distant"] && !empty($log["current"]))
	{
	    $ologs[$log["name"]]["local"] = $log["user"];
	    $ologs[$log["name"]]["lock"] = $log["lock"];
	}
    }

    foreach ($logs["content"] as $log)
    {
	if (!strlen(@$log["name"]) || !strlen(@$log["mac"]) || !strlen(@$log["ip"]) || !isset($log["type"]))
	    continue ;
	if (is_array($log["name"]))
	    continue ;
	if (!isset($log["distant"]))
	    $log["distant"] = albedo_room_log_is_distant($log);
	if (!isset($log["lock"]))
	    $log["lock"] = false;
	if (!isset($log["ssh_idle"]))
	    $log["ssh_idle"] = albedo_room_log_is_ssh_idle($log);
	$log_user = trim((string)@$log["user"]);
	if ($log_user != "")
	{
	    $state = [
		"distant" => !empty($log["distant"]),
		"lock" => !empty($log["lock"]) || albedo_room_log_is_ssh_idle($log),
		"date" => isset($log["date"]) ? $log["date"] : now(),
		"ip" => isset($log["ip"]) ? $log["ip"] : "",
		"ssh_idle" => albedo_room_log_is_ssh_idle($log),
		"current" => !empty($log["current"]),
		"local_current" => !empty($log["current"]) && empty($log["distant"]),
		"remote_current" => !empty($log["current"]) && !empty($log["distant"]),
		"distrans_duration" => isset($log["distrans_duration"])
		    ? !empty($log["distrans_duration"])
		    : (array_key_exists("xtime", $log)
		        || array_key_exists("sshtime", $log)
		        || array_key_exists("ssh_idle_time", $log)
		        || array_key_exists("sshidletime", $log)
		        || array_key_exists("locktime", $log)),
		"source_key" => isset($log["source_key"]) ? (string)$log["source_key"] : albedo_room_log_source_key($log),
		"reset_token" => isset($log["reset_token"]) ? (string)$log["reset_token"] : albedo_room_log_reset_token($log),
		"activity_timestamp" => isset($log["activity_timestamp"]) ? (int)$log["activity_timestamp"] : albedo_room_log_timestamp($log),
		"xtime" => isset($log["xtime"]) ? (int)$log["xtime"] : 0,
		"sshtime" => isset($log["sshtime"]) ? (int)$log["sshtime"] : 0,
		"ssh_idle_time" => isset($log["ssh_idle_time"]) ? (int)$log["ssh_idle_time"] : (isset($log["sshidletime"]) ? (int)$log["sshidletime"] : 0),
		"locktime" => isset($log["locktime"]) ? (int)$log["locktime"] : 0,
	    ];
	    if (!empty($log["current"]))
	    {
		$ologs[$log["name"]]["user"][] = $log_user;
		$ologs[$log["name"]]["user_state"][$log_user] = $state;
	    }
	    if (!empty($log["current"]) || albedo_room_log_duration($log) > 0)
	    {
		$ologs[$log["name"]]["log_user"][] = $log_user;
		$previous_state = isset($ologs[$log["name"]]["log_user_state"][$log_user])
		    ? $ologs[$log["name"]]["log_user_state"][$log_user]
		    : NULL;
		$ologs[$log["name"]]["log_user_state"][$log_user] =
		    albedo_room_merge_measured_state($previous_state, $state);
	    }
	}
	$ologs[$log["name"]]["user"] = array_unique($ologs[$log["name"]]["user"]);
	$ologs[$log["name"]]["user"] = array_filter($ologs[$log["name"]]["user"]);
	$ologs[$log["name"]]["log_user"] = array_unique($ologs[$log["name"]]["log_user"]);
	$ologs[$log["name"]]["log_user"] = array_filter($ologs[$log["name"]]["log_user"]);
    }
    
    foreach ($ologs as $name => $log)
    {
	$lock = isset($log["lock"]) && $log["lock"];

	// Information sur le poste
	set_desk_data($log);

	// Log étudiants
	if (isset($log["log_user"]))
	{
	    foreach ($log["log_user"] as $user)
	    {
		if (room_desk_system_user_is_ignored($user))
		    continue ;
		$user_state = isset($log["log_user_state"][$user]) ? $log["log_user_state"][$user] : $log;
		if (($cod = resolve_codename("user", $user))->is_error())
		{
		    $unknown_duration = albedo_room_log_duration($user_state);
		    add_log(REPORT, "Trace work log ignored unknown login '".$user."' (".$unknown_duration." second(s) pending)", 1);
		    if ($unknown_duration > 0)
			$distrans_import_failed = true;
		    continue ;
		}
		$cod = $cod->value;
		$user_distant = !empty($user_state["distant"]);
		$user_locked = !$user_distant && !empty($user_state["lock"]);
		$user_ssh_idle = $user_distant && !empty($user_state["ssh_idle"]);
		$user_ip = isset($user_state["ip"]) ? $user_state["ip"] : $log["ip"];
		$activity_timestamp = isset($user_state["activity_timestamp"]) ? (int)$user_state["activity_timestamp"] : 0;
		$collection_date = user_log_wall_timestamp($activity_timestamp > 0 ? $activity_timestamp : time());

		if (!empty($user_state["distrans_duration"]))
		{
		    // Distrans is authoritative.  Import the cumulative snapshot through
		    // a persistent cursor so retries and failed resets cannot double-count.
		    $import_failure = NULL;
		    if (!import_distrans_activity_counters(
			["id" => $cod],
			$user_state["source_key"] ?? albedo_room_log_source_key($log),
			$user_state["reset_token"] ?? "",
			$user_state,
			$collection_date,
			$user_ip,
			$import_failure,
			!empty($user_state["local_current"]) && !empty($user_state["remote_current"])
		    ))
		    {
			$distrans_import_failed = true;
			add_log(REPORT, "Distrans activity persistence failed for '".$user."' on '".
			    ($user_state["source_key"] ?? albedo_room_log_source_key($log))."': ".
			    ($import_failure ?: "unknown persistence error"), 1);
		    }
		}
		else if ($distrans_structured_payload && albedo_room_log_duration($user_state) > 0)
		{
		    // Machine.user may legitimately expose a currently connected account
		    // before Distrans has created its duration-counter row.  Such a synthetic
		    // zero-duration entry has nothing to persist and must not prevent the
		    // counters that do exist from being acknowledged/reset.
		    $distrans_import_failed = true;
		    add_log(REPORT, "Distrans measured activity has no counter metadata for '".$user."'", 1);
		}
		else
		{
		    // Compatibility fallback for genuinely old Hand payloads.
		    $user_log_type = $user_ssh_idle
			? USER_LOG_SSH_IDLE
			: ($user_distant ? USER_LOG_DISTANT : ($user_locked ? USER_LOG_LOCK : USER_LOG_WORK));
		    compute_student_log(
			["id" => $cod],
			$user_log_type,
			$collection_date,
			$user_ip,
			$user_distant
		    );
		}
		add_log(TRACE, "Setting $logcnt students work logs for $cod", 1);
	    }
	}
	++$logcnt;
    }
    if ($logcnt)
    {
	if ($distrans_import_failed)
	    add_log(REPORT, "Distrans activity counters were not reset because at least one duration failed to persist", 1);
	else if (($reset = hand_request(["command" => "reset_activity_log"], false)) === false)
	    add_log(REPORT, "Failed to reset activity log from TechnoCore", 1);
	else if (isset($reset["result"]) && $reset["result"] != "ok")
	    add_log(REPORT, "Failed to reset activity log from TechnoCore: ".json_encode($reset), 1);
	else if (isset($reset["status"]) && $reset["status"] != "ok")
	    add_log(REPORT, "Failed to reset activity log from TechnoCore: ".json_encode($reset), 1);
	else
	{
	    // Keep the local cursors even after a successful remote reset.  On the
	    // next snapshot a lower counter or a changed reset token identifies the
	    // new epoch.  Retaining the cursor also protects us if Distrans answers
	    // "ok" although the reset was not actually applied.
	    add_log(EDITING_OPERATION, "Setting $logcnt students work logs from TechnoCore", 1);
	}
    }
}

if ($distrans_worklog_locked)
    @flock($distrans_worklog_lock, LOCK_UN);
if ($distrans_worklog_lock !== false)
    @fclose($distrans_worklog_lock);

require_once (__DIR__."/albedo/money_bonus.php");
require_once (__DIR__."/albedo/user_guidance.php");
