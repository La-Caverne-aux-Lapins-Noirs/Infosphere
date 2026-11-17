<?php
require_once (__DIR__."/halfday_presence.php");

const USER_LOG_SSH_IDLE = -2;
const USER_LOG_LOCK = -1;
const USER_LOG_INTRA = 0;
const USER_LOG_WORK = 1;
const USER_LOG_DISTANT = 2;

function user_log_valid_activity_types()
{
    return ([USER_LOG_WORK, USER_LOG_DISTANT]);
}

function user_log_sql_type_list($types)
{
    if (!is_array($types))
        $types = [$types];
    $out = [];
    foreach ($types as $type)
        $out[] = (int)$type;
    return (implode(", ", array_unique($out)));
}

// Work logs are calendar data: use an explicit IANA timezone rather than the
// implicit PHP process timezone.  The rest of Infosphere historically encodes
// local wall clock values as UTC-like timestamps; user_log_now() keeps that
// convention while making the DST offset deterministic.
function user_log_timezone_name()
{
    global $Configuration;

    $timezone = "Europe/Paris";
    if (isset($Configuration) && isset($Configuration->Properties))
    {
        if (isset($Configuration->Properties["worklog_timezone"])
            && trim((string)$Configuration->Properties["worklog_timezone"]) != "")
            $timezone = trim((string)$Configuration->Properties["worklog_timezone"]);
        else if (isset($Configuration->Properties["calendar_timezone"])
            && trim((string)$Configuration->Properties["calendar_timezone"]) != "")
            $timezone = trim((string)$Configuration->Properties["calendar_timezone"]);
    }
    try
    {
        new DateTimeZone($timezone);
    }
    catch (Exception $e)
    {
        $timezone = "Europe/Paris";
    }
    return ($timezone);
}

function user_log_wall_timestamp($absolute_timestamp = NULL)
{
    if ($absolute_timestamp === NULL)
        $absolute_timestamp = time();
    $absolute_timestamp = (int)$absolute_timestamp;
    try
    {
        $timezone = new DateTimeZone(user_log_timezone_name());
        $dt = (new DateTimeImmutable("@".$absolute_timestamp))->setTimezone($timezone);
        return ($absolute_timestamp + $dt->getOffset());
    }
    catch (Exception $e)
    {
        return ($absolute_timestamp);
    }
}

function user_log_now()
{
    return (user_log_wall_timestamp(time()));
}

function user_log_distrans_cursor_table_available()
{
    static $available = NULL;

    if ($available !== NULL)
        return ($available);
    $tables = db_get_tables();
    return ($available = isset($tables["user_log_distrans_cursor"]));
}

function user_log_distrans_counter_values(array $counters)
{
    return ([
        "xtime" => max(0, (int)($counters["xtime"] ?? 0)),
        "sshtime" => max(0, (int)($counters["sshtime"] ?? 0)),
        "ssh_idle_time" => max(0, (int)($counters["ssh_idle_time"] ?? ($counters["sshidletime"] ?? 0))),
        "locktime" => max(0, (int)($counters["locktime"] ?? 0)),
    ]);
}

function user_log_distrans_counter_deltas(array $current, $previous = NULL, $reset_token = "")
{
    $current = user_log_distrans_counter_values($current);

    // A cumulative counter with no previous sample has no trustworthy start
    // date.  Importing its whole value would assign potentially days of old
    // activity to the day on which Infosphere first sees the machine.  Use the
    // first sample as a baseline; subsequent samples provide measured intervals.
    if (!is_array($previous))
        return (array_fill_keys(array_keys($current), 0));

    // A new epoch or any regressing component is a new baseline. Importing
    // the entire new value can replay historical counters after a restart.
    if ((string)($previous["reset_token"] ?? "") !== (string)$reset_token)
        return (array_fill_keys(array_keys($current), 0));
    foreach ($current as $field => $value)
        if ($value < max(0, (int)($previous[$field] ?? 0)))
            return (array_fill_keys(array_keys($current), 0));
    $delta = [];
    foreach ($current as $field => $value)
        $delta[$field] = $value - max(0, (int)($previous[$field] ?? 0));
    return ($delta);
}

require_once (__DIR__."/distrans_presence.php");

// Compatibility entry point; v3 dates are absolute observation timestamps.
function import_distrans_activity_counters($user, $source_key, $reset_token, array $counters,
    $date = NULL, $client_ip = NULL, &$failure_reason = NULL, $parallel_local_remote = false)
{
    $counters["source_key"] = $source_key;
    $counters["reset_token"] = $reset_token;
    $counters["counter_timestamp"] = $date === NULL ? time() : (int)$date;
    $counters["ip"] = $client_ip;
    return (import_distrans_activity_batch($user, [$counters], $failure_reason));
}

function compute_student_log($user = NULL, $type = USER_LOG_INTRA, $date = NULL, $client_ip = NULL, $distant = false)
{
    global $OriginalUser;
    global $Database;

    $type = (int)$type;
    if ($client_ip === NULL)
        $client_ip = get_client_ip();
    $client_ip = $Database->real_escape_string((string)$client_ip);
    if ($date === NULL)
	$date = user_log_now();
    $date_sql = $Database->real_escape_string(db_form_date($date));
    if ($user == NULL)
	if (($user = &$OriginalUser) == NULL)
	    return ;
    $today = db_select_one("
      * FROM user_log
      WHERE id_user = {$user["id"]}
      AND log_date >= '".db_form_date($date, true)."'
      AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
      AND type = $type
	");
    if ($today == NULL)
    {
	$Database->query("
           INSERT INTO user_log
           (id_user, log_date, last_log, last_ip, duration, type)
           VALUES ({$user["id"]}, '".db_form_date($date, true)."', '$date_sql', '$client_ip', 0, $type)
	   ");
	return ;
    }
    $last_log = date_to_timestamp($today["last_log"]);
    if ($today["last_ip"] != $client_ip || $date - $last_log > 5 * 60)
    {
	$Database->query("
          UPDATE user_log
          SET last_log = '$date_sql', last_ip = '$client_ip'
          WHERE id_user = {$user["id"]}
          AND log_date >= '".db_form_date($date, true)."'
          AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
          AND type = $type
	  ");
	return ;
    }
    if ($date < $last_log)
    {
	$increment = 0;
	$acc = $today["duration"];
    }
    else
    {
	$increment = $date - $last_log;
	$acc = $today["duration"] + $increment;
    }
    if ($increment > 0)
	halfday_presence_add_interval($user, $type, $last_log, $date);
    $Database->query("
       UPDATE user_log
       SET last_log = '$date_sql', duration = $acc
       WHERE id_user = {$user["id"]}
       AND log_date >= '".db_form_date($date, true)."'
       AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
       AND type = $type
    ");
 }

// Compatibility for callers supplying an amount ending at a local wall time.
function add_student_log_duration($user, $type, $duration, $date = NULL, $client_ip = NULL)
{
    if ($date === NULL)
        $date = user_log_now();
    return (add_student_log_interval($user, $type, (int)$date - max(0, (int)$duration), (int)$date, $client_ip));
}

// Persist exactly the same local interval in daily and halfday accumulators.
// Endpoints are exclusive: 23:59 -> 00:01 is split between the two days.
function add_student_log_interval($user, $type, $begin, $end, $client_ip = NULL)
{
    global $Database;

    if (is_object($user))
        $user = ["id" => $user->id];
    else if (!is_array($user))
        $user = ["id" => $user];
    if (!isset($user["id"]) || !is_number($user["id"]))
        return (false);
    $id_user = (int)$user["id"];
    $type = (int)$type;
    $begin = (int)$begin;
    $end = (int)$end;
    if ($end <= $begin)
        return (true);
    if ($client_ip === NULL)
        $client_ip = get_client_ip();
    $client_ip = $Database->real_escape_string((string)$client_ip);
    while ($begin < $end)
    {
        $day = (int)(floor($begin / 86400) * 86400);
        $part_end = min($end, $day + 86400);
        $duration = $part_end - $begin;
        $date_sql = $Database->real_escape_string(db_form_date($part_end));
        $day_begin = db_form_date($day, true);
        $day_end = db_form_date($day + 86400, true);
        // Bypass the SELECT cache: another interval of this import may already
        // have inserted or incremented this day's row.
        $query = $Database->query("SELECT id FROM user_log
            WHERE id_user = $id_user AND log_date >= '$day_begin'
              AND log_date < '$day_end' AND type = $type ORDER BY id LIMIT 1");
        if ($query == NULL)
            return (false);
        $today = $query->fetch_assoc();
        if ($today == NULL)
            $query = $Database->query("INSERT INTO user_log
                (id_user, log_date, last_log, last_ip, duration, type)
                VALUES ($id_user, '$day_begin', '$date_sql', '$client_ip', $duration, $type)");
        else
        {
            $id_log = (int)$today["id"];
            $query = $Database->query("UPDATE user_log
                SET duration = COALESCE(duration, 0) + $duration,
                    last_log = CASE WHEN last_log IS NULL OR last_log < '$date_sql'
                        THEN '$date_sql' ELSE last_log END, last_ip = '$client_ip'
                WHERE id = $id_log");
        }
        if ($query == NULL || !halfday_presence_add_interval($user, $type, $begin, $part_end))
            return (false);
        $begin = $part_end;
    }
    return (true);
}

function get_student_log($user = NULL, $date = NULL, $types = NULL)
{
    global $OriginalUser;

    if ($user == NULL)
	if (($user = &$OriginalUser) == NULL)
	    return ;
    if ($date == NULL)
	$date = user_log_now();
    else
	$date = date_to_timestamp($date);
    if ($types === NULL)
        $types = user_log_valid_activity_types();
    $types = user_log_sql_type_list($types);
    $fetch = db_select_one("
       COALESCE(SUM(duration), 0) as duration FROM user_log
       WHERE id_user = {$user["id"]}
       AND log_date >= '".db_form_date($date, true)."'
       AND log_date < '".db_form_date($date + 60 * 60 * 24, true)."'
       AND type IN ($types)
       ");
    if ($fetch == NULL)
	return (0);
    return ($fetch["duration"]);
}

function get_week_average($user, $since = 60 * 60 * 24 * 14)
{
    // Nom historique trompeur: cette valeur est utilisée par la home comme
    // total horaire des derniers jours, pas comme moyenne journalière.
    // On somme donc les heures valides sur les N derniers jours calendaires,
    // en incluant explicitement aujourd'hui.

    if (is_array($user))
	$user = $user["id"];
    else if (is_object($user))
	$user = $user->id;
    else if (!is_number($user))
	return (0);

    $days = max(1, (int)ceil($since / (60 * 60 * 24)));
    $today = user_log_now();
    $start = db_form_date($today - ($days - 1) * 60 * 60 * 24, true);
    $end = db_form_date($today + 60 * 60 * 24, true);
    $types = user_log_sql_type_list(user_log_valid_activity_types());

    $fetch = db_select_one("
      COALESCE(SUM(duration), 0) as duration
      FROM user_log
      WHERE id_user = ".(int)$user."
      AND type IN ($types)
      AND log_date >= '$start'
      AND log_date < '$end'
    ");

    if ($fetch == NULL)
	return (0);
    return ($fetch["duration"] / (60 * 60));
}

function get_last_activities_report($user, $since = 60 * 60 * 24 * 14)
{
    $act = collect_dashboard_activities(now() - $since, now(), false);
    $pres = 0;
    $total = 0;
    foreach ($act["participate"] as $a)
    {
	if ($a["present"] > 0 || $a["present"] == -1)
	    $pres += 1;
	$total += 1;
    }
    if ($total == 0)
	return ([0.5, 0, 1]);
    return ([$pres, 0, $total]);
}

function get_last_medals_report($user, $since = 60 * 60 * 24 * 14)
{
    $act = collect_dashboard_activities(now() - $since, now(), false);
    $prj = collect_dashboard_projects(now(), $since, true);

    $medal = 0;
    $total = 0;
    foreach ($act["participate"] as $a)
    {
	$medal += $a["medal_got"];
	$total += $a["medal"];
    }
    foreach ($prj as $a)
    {
	$medal += $a["medal_got"];
	$total += $a["medal"];
    }
    if ($total == 0)
	return ([0.5, 0, 1]);
    return ([$medal, 0, $total]);
}

