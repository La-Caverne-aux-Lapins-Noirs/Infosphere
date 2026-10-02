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
    // first sample as a baseline; subsequent samples (or the remote reset done
    // after this pass) provide measured intervals.
    if (!is_array($previous))
        return (array_fill_keys(array_keys($current), 0));

    $same_epoch = true;
    if ($reset_token != ""
        && (string)($previous["reset_token"] ?? "") !== (string)$reset_token)
        $same_epoch = false;

    $delta = [];
    foreach ($current as $field => $value)
    {
        if (!$same_epoch)
            $delta[$field] = $value;
        else
        {
            $before = max(0, (int)($previous[$field] ?? 0));
            $delta[$field] = $value >= $before ? $value - $before : $value;
        }
    }
    return ($delta);
}

// Import a cumulative Distrans snapshot exactly once.  The remote counters are
// reset after a successful Albedo pass, but a reset request can fail or its
// response can be lost.  Keeping the last observed counter values makes retries
// idempotent instead of counting the same seconds twice.
function import_distrans_activity_counters($user, $source_key, $reset_token, array $counters,
    $date = NULL, $client_ip = NULL, &$failure_reason = NULL, $parallel_local_remote = false)
{
    global $Database;

    $failure_reason = NULL;
    $database_error = function ($fallback) use (&$Database) {
        if (isset($Database->db) && isset($Database->db->error) && trim((string)$Database->db->error) != "")
            return ($fallback.": ".trim((string)$Database->db->error));
        return ($fallback);
    };

    if (is_object($user))
        $user = ["id" => $user->id];
    else if (!is_array($user))
        $user = ["id" => $user];
    if (!isset($user["id"]) || !is_number($user["id"]))
    {
        $failure_reason = "invalid user id";
        return (false);
    }
    if (!user_log_distrans_cursor_table_available())
    {
        $failure_reason = "user_log_distrans_cursor table unavailable";
        return (false);
    }

    $id_user = (int)$user["id"];
    $source_key = trim((string)$source_key);
    if ($source_key == "")
        $source_key = "unknown";
    $reset_token = trim((string)$reset_token);
    $current = user_log_distrans_counter_values($counters);
    $source_sql = $Database->real_escape_string($source_key);
    $token_sql = $Database->real_escape_string($reset_token);
    if ($date === NULL)
        $date = user_log_now();
    $date_sql = $Database->real_escape_string(db_form_date($date));

    $previous = db_select_one("
        reset_token, xtime, sshtime, ssh_idle_time, locktime, last_seen
        FROM user_log_distrans_cursor
        WHERE id_user = $id_user AND source_key = '$source_sql'
    ");
    // A lower counter means Distrans was reset even if the reset reply was
    // lost.  A changed reset token also identifies a fresh counter epoch.
    $delta = user_log_distrans_counter_deltas($current, $previous, $reset_token);

    if ($Database->query("START TRANSACTION") == NULL)
    {
        $failure_reason = $database_error("cannot start transaction");
        return (false);
    }

    // Distrans can report several states for the same student during the same
    // collection interval (for example a local X session and an SSH session).
    // They are not additive presence: keep the most meaningful state for every
    // second instead of turning two simultaneous sessions into twice as much
    // activity.  Raw counters are still written to the cursor below, including
    // counters whose duration is not persisted, so an ignored SSH interval can
    // never reappear on the next pass.
    //
    // Priority: local active work > active SSH > local lock > idle SSH.
    // xtime contains locktime, hence the subtraction before applying priority.
    $raw_durations = [
        USER_LOG_WORK => max(0, $delta["xtime"] - $delta["locktime"]),
        USER_LOG_DISTANT => max(0, $delta["sshtime"]),
        USER_LOG_LOCK => max(0, $delta["locktime"]),
        USER_LOG_SSH_IDLE => max(0, $delta["ssh_idle_time"]),
    ];

    // last_seen gives the real collection window.  Capping the sum to that
    // window lets non-overlapping states survive while simultaneous lower-value
    // states are discarded.  If no previous timestamp is available, use the
    // longest local/remote track as the safest approximation.
    $sample_duration = 0;
    if (is_array($previous) && !empty($previous["last_seen"]))
    {
        $previous_seen = date_to_timestamp($previous["last_seen"]);
        if ($previous_seen > 0 && $date > $previous_seen)
            $sample_duration = (int)$date - (int)$previous_seen;
    }
    $local_duration = $raw_durations[USER_LOG_WORK] + $raw_durations[USER_LOG_LOCK];
    $remote_duration = $raw_durations[USER_LOG_DISTANT] + $raw_durations[USER_LOG_SSH_IDLE];
    $observed_duration = max($local_duration, $remote_duration);
    if ($sample_duration <= 0)
        $sample_duration = $observed_duration;

    // When Distrans says that local and remote sessions are current at the same
    // time, force them onto the same time window.  This is the important case
    // for presence semantics: an SSH session must not add time on top of an
    // active local session.  The priority allocation below keeps the strongest
    // state and the cursor still consumes every raw counter.
    if ($parallel_local_remote && $observed_duration > 0)
        $sample_duration = min($sample_duration, $observed_duration);

    $remaining = max(0, $sample_duration);
    $durations = [];
    foreach ([USER_LOG_WORK, USER_LOG_DISTANT, USER_LOG_LOCK, USER_LOG_SSH_IDLE] as $type)
    {
        $durations[$type] = min($raw_durations[$type], $remaining);
        $remaining -= $durations[$type];
    }
    foreach ($durations as $type => $duration)
    {
        if ($duration <= 0)
            continue ;
        if (!add_student_log_duration($user, $type, $duration, $date, $client_ip))
        {
            $failure_reason = $database_error(
                "cannot persist activity type ".$type." delta ".$duration." second(s)"
            );
            $Database->query("ROLLBACK");
            return (false);
        }
    }

    $cursor_query = "
        INSERT INTO user_log_distrans_cursor
        (id_user, source_key, reset_token, xtime, sshtime, ssh_idle_time, locktime, last_seen)
        VALUES
        ($id_user, '$source_sql', '$token_sql', {$current['xtime']}, {$current['sshtime']},
         {$current['ssh_idle_time']}, {$current['locktime']}, '$date_sql')
        ON DUPLICATE KEY UPDATE
            reset_token = VALUES(reset_token),
            xtime = VALUES(xtime),
            sshtime = VALUES(sshtime),
            ssh_idle_time = VALUES(ssh_idle_time),
            locktime = VALUES(locktime),
            last_seen = VALUES(last_seen)
    ";
    if ($Database->query($cursor_query) == NULL)
    {
        $failure_reason = $database_error("cannot update Distrans cursor");
        $Database->query("ROLLBACK");
        return (false);
    }
    if ($Database->query("COMMIT") == NULL)
    {
        $failure_reason = $database_error("cannot commit Distrans worklog transaction");
        $Database->query("ROLLBACK");
        return (false);
    }
    return (true);
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

// Add a duration measured by Distrans directly to the daily activity log.
// Unlike compute_student_log(), this does not reconstruct elapsed time from
// successive Albedo runs: the duration supplied by Distrans is the source of
// truth. This is important because get_activity_log/reset_activity_log already
// forms an accumulator protocol on the Distrans side.
function add_student_log_duration($user, $type, $duration, $date = NULL, $client_ip = NULL)
{
    global $Database;

    if ($date === NULL)
        $date = user_log_now();
    if (is_object($user))
        $user = ["id" => $user->id];
    else if (!is_array($user))
        $user = ["id" => $user];
    if (!isset($user["id"]) || !is_number($user["id"]))
        return (false);

    $id_user = (int)$user["id"];
    $type = (int)$type;
    $duration = max(0, (int)$duration);
    if ($duration <= 0)
        return (true);
    if ($client_ip === NULL)
        $client_ip = get_client_ip();
    $client_ip = $Database->real_escape_string((string)$client_ip);
    $date_sql = $Database->real_escape_string(db_form_date($date));
    $day_begin = db_form_date($date, true);
    $day_end = db_form_date($date + 24 * 60 * 60, true);

    $today = db_select_one("
        id, COALESCE(duration, 0) AS duration
        FROM user_log
        WHERE id_user = $id_user
          AND log_date >= '$day_begin'
          AND log_date < '$day_end'
          AND type = $type
    ");
    if ($today == NULL)
    {
        if ($Database->query("
            INSERT INTO user_log
            (id_user, log_date, last_log, last_ip, duration, type)
            VALUES ($id_user, '$day_begin', '$date_sql', '$client_ip', $duration, $type)
        ") == NULL)
            return (false);
    }
    else
    {
        $id_log = (int)$today["id"];
        if ($Database->query("
            UPDATE user_log
            SET duration = COALESCE(duration, 0) + $duration,
                last_log = '$date_sql',
                last_ip = '$client_ip'
            WHERE id = $id_log
        ") == NULL)
            return (false);
    }

    // Distrans reports counters accumulated since the previous reset. Albedo
    // normally runs every couple of minutes, so projecting this interval just
    // before the collection time gives the half-day presence accumulator the
    // same measured amount without inventing additional seconds.
    if (in_array($type, user_log_valid_activity_types(), true))
        halfday_presence_add_interval($user, $type, $date - $duration, $date);
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

