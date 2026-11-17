<?php

// Counter cursors in the activity-v3 namespace store UTC observation times.
// user_log itself retains Infosphere's historical local-wall-time convention.
function user_log_distrans_utc_timestamp($value)
{
    if (is_numeric($value))
        return ((int)$value);
    if (!is_string($value) || trim($value) == "")
        return (0);
    try
    {
        return ((new DateTimeImmutable($value, new DateTimeZone("UTC")))->getTimestamp());
    }
    catch (Exception $e)
    {
        return (0);
    }
}

// Union of measured intervals, with one presence state per second even when
// the same login is active on several workstations. Locked/idle time cannot
// replace active work. Inputs and outputs use absolute Unix timestamps.
function user_log_distrans_interval_union(array $intervals)
{
    $events = [];
    $priority = [USER_LOG_WORK, USER_LOG_DISTANT, USER_LOG_LOCK, USER_LOG_SSH_IDLE];
    foreach ($intervals as $interval)
    {
        $begin = (int)$interval["begin"];
        $end = (int)$interval["end"];
        $type = (int)$interval["type"];
        if ($end <= $begin || !in_array($type, $priority, true))
            continue ;
        $events[$begin][$type] = ($events[$begin][$type] ?? 0) + 1;
        $events[$end][$type] = ($events[$end][$type] ?? 0) - 1;
    }
    ksort($events, SORT_NUMERIC);
    $active = [];
    $previous = NULL;
    $out = [];
    foreach ($events as $timestamp => $changes)
    {
        if ($previous !== NULL && $timestamp > $previous)
        {
            foreach ($priority as $type)
            {
                if (($active[$type] ?? 0) <= 0)
                    continue ;
                $last = count($out) - 1;
                if ($last >= 0 && $out[$last]["type"] === $type && $out[$last]["end"] === $previous)
                    $out[$last]["end"] = (int)$timestamp;
                else
                    $out[] = ["begin" => $previous, "end" => (int)$timestamp, "type" => $type];
                break ;
            }
        }
        foreach ($changes as $type => $change)
            $active[$type] = ($active[$type] ?? 0) + $change;
        $previous = (int)$timestamp;
    }
    return ($out);
}

function add_student_log_absolute_interval($user, $type, $begin, $end, $client_ip = NULL)
{
    $begin = (int)$begin;
    $end = (int)$end;
    if ($end <= $begin)
        return (true);
    $timezone = new DateTimeZone(user_log_timezone_name());
    // Split at UTC offset changes before converting to local wall time. This
    // keeps the real 23/25-hour length of a daylight-saving transition day.
    $transitions = $timezone->getTransitions($begin, $end);
    $offset = $timezone->getOffset(new DateTimeImmutable("@".$begin));
    foreach ($transitions === false ? [] : $transitions as $transition)
    {
        $timestamp = (int)$transition["ts"];
        if ($timestamp > $begin)
        {
            if (!add_student_log_interval($user, $type, $begin + $offset, $timestamp + $offset, $client_ip))
                return (false);
            $begin = $timestamp;
        }
        $offset = (int)$transition["offset"];
    }
    return (add_student_log_interval($user, $type, $begin + $offset, $end + $offset, $client_ip));
}

function import_distrans_activity_batch($user, array $samples, &$failure_reason = NULL)
{
    global $Database;

    $failure_reason = NULL;
    if (is_object($user))
        $user = ["id" => $user->id];
    else if (!is_array($user))
        $user = ["id" => $user];
    if (!isset($user["id"]) || !is_number($user["id"]) || (int)$user["id"] <= 0)
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
    if ($Database->query("START TRANSACTION") == NULL)
    {
        $failure_reason = "cannot start Distrans transaction";
        return (false);
    }
    try
    {
        // This row serializes every source of one student, including the very
        // first import. Read directly: db_select_one() caches SELECT results.
        $window_key = "activity-v3-window";
        if ($Database->query("INSERT INTO user_log_distrans_cursor
            (id_user, source_key) VALUES ($id_user, '$window_key')
            ON DUPLICATE KEY UPDATE id_user = VALUES(id_user)") == NULL)
            throw new RuntimeException("cannot lock student import window");
        $query = $Database->query("SELECT last_seen FROM user_log_distrans_cursor
            WHERE id_user = $id_user AND source_key = '$window_key' FOR UPDATE");
        if ($query == NULL)
            throw new RuntimeException("cannot read student import window");
        $window = $query->fetch_assoc();
        $watermark = user_log_distrans_utc_timestamp($window["last_seen"] ?? NULL);
        $latest = $watermark;
        $intervals = [];
        $seen_sources = [];
        foreach ($samples as $sample)
        {
            $source = trim((string)($sample["source_key"] ?? ""));
            $end = (int)($sample["counter_timestamp"] ?? 0);
            if ($source == "" || strlen($source) > 191 || $end <= 0)
                throw new RuntimeException("missing/invalid counter source or observation timestamp");
            if (isset($seen_sources[$source]))
                throw new RuntimeException("duplicate counter source in student batch: ".$source);
            $seen_sources[$source] = true;
            $source_sql = $Database->real_escape_string($source);
            $token = trim((string)($sample["reset_token"] ?? ""));
            $token_sql = $Database->real_escape_string($token);
            $query = $Database->query("SELECT reset_token, xtime, sshtime, ssh_idle_time, locktime, last_seen
                FROM user_log_distrans_cursor
                WHERE id_user = $id_user AND source_key = '$source_sql' FOR UPDATE");
            if ($query == NULL)
                throw new RuntimeException("cannot read counter cursor");
            $previous = $query->fetch_assoc();
            $begin = user_log_distrans_utc_timestamp($previous["last_seen"] ?? NULL);
            // An old/replayed snapshot never moves a cursor backwards.
            if ($begin > 0 && $end < $begin)
                continue ;
            $current = user_log_distrans_counter_values($sample);
            $delta = user_log_distrans_counter_deltas($current, $previous, $token);
            if (is_array($previous) && $token === (string)($previous["reset_token"] ?? ""))
                foreach ($current as $field => $value)
                    if ($value < (int)($previous[$field] ?? 0))
                    {
                        if (function_exists("add_log"))
                            add_log(REPORT, "Distrans counters regressed; new baseline for user ".$id_user.
                                " source ".$source.": ".json_encode($current), 1);
                        break ;
                    }
            $raw = [
                USER_LOG_WORK => max(0, $delta["xtime"] - $delta["locktime"]),
                USER_LOG_DISTANT => $delta["sshtime"],
                USER_LOG_LOCK => $delta["locktime"],
                USER_LOG_SSH_IDLE => $delta["ssh_idle_time"],
            ];
            $remaining = $begin > 0 ? max(0, $end - $begin) : 0;
            $durations = [];
            foreach ($raw as $type => $duration)
            {
                $durations[$type] = min($duration, $remaining);
                $remaining -= $durations[$type];
            }
            if (array_sum($raw) > array_sum($durations) && function_exists("add_log"))
                add_log(REPORT, "Distrans counter exceeds observation window for user ".$id_user.
                    " source ".$source.": ".json_encode($delta), 1);
            // Counters do not contain event timestamps. Place their measured
            // seconds at the end of the observation window; never use the last
            // keyboard activity as the observation time, nor invent gap time.
            $position = $end - array_sum($durations);
            foreach ($durations as $type => $duration)
            {
                $interval_begin = max($position, $watermark);
                $position += $duration;
                if ($position > $interval_begin)
                    $intervals[] = ["begin" => $interval_begin, "end" => $position,
                        "type" => $type, "ip" => $sample["ip"] ?? NULL];
            }
            $date_sql = gmdate("Y-m-d H:i:s", $end);
            if ($Database->query("INSERT INTO user_log_distrans_cursor
                (id_user, source_key, reset_token, xtime, sshtime, ssh_idle_time, locktime, last_seen)
                VALUES ($id_user, '$source_sql', '$token_sql', {$current['xtime']}, {$current['sshtime']},
                    {$current['ssh_idle_time']}, {$current['locktime']}, '$date_sql')
                ON DUPLICATE KEY UPDATE reset_token = VALUES(reset_token), xtime = VALUES(xtime),
                    sshtime = VALUES(sshtime), ssh_idle_time = VALUES(ssh_idle_time),
                    locktime = VALUES(locktime), last_seen = VALUES(last_seen)") == NULL)
                throw new RuntimeException("cannot save counter cursor");
            $latest = max($latest, $end);
        }
        $client_ip = $samples[0]["ip"] ?? NULL;
        foreach (user_log_distrans_interval_union($intervals) as $interval)
            if (!add_student_log_absolute_interval($user, $interval["type"],
                $interval["begin"], $interval["end"], $client_ip))
                throw new RuntimeException("cannot persist daily/halfday presence");
        if ($latest > 0)
        {
            $latest_sql = gmdate("Y-m-d H:i:s", $latest);
            if ($Database->query("UPDATE user_log_distrans_cursor SET last_seen = '$latest_sql'
                WHERE id_user = $id_user AND source_key = '$window_key'") == NULL)
                throw new RuntimeException("cannot save student import window");
        }
        if ($Database->query("COMMIT") == NULL)
            throw new RuntimeException("cannot commit Distrans transaction");
    }
    catch (Throwable $e)
    {
        $database_error = isset($Database->db->error) ? trim((string)$Database->db->error) : "";
        $Database->query("ROLLBACK");
        $failure_reason = $e->getMessage();
        if ($database_error != "")
            $failure_reason .= ": ".$database_error;
        return (false);
    }
    return (true);
}
