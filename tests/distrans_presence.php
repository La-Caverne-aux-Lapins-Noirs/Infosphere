<?php
// Standalone replay tests. Only an in-memory SQLite database is modified.
require_once (__DIR__."/../tools/utils.php");
require_once (__DIR__."/../tools/student_log.php");

const REPORT = 1;
const TRACE = 2;
const EDITING_OPERATION = 3;
$PresenceReports = [];
function add_log($type, $message, $level = 0)
{
    $GLOBALS["PresenceReports"][] = $message;
}
function get_client_ip() { return ("127.0.0.1"); }
function hand_request($request, $unused = false)
{
    $GLOBALS["PresenceHandCalls"][] = $request["command"];
    return ($GLOBALS["PresencePayload"]);
}
function room_desk_system_user_is_ignored($user) { return ($user === "root"); }
function set_desk_data($log) { $GLOBALS["PresenceDesks"][] = $log; }
class PresenceCodename
{
    public $value = 109;
    function is_error() { return (false); }
}
function resolve_codename($table, $user) { return (new PresenceCodename); }
function db_get_tables()
{
    return (["user_log_distrans_cursor" => true, "user_log_halfday" => true]);
}
function db_select_one($sql)
{
    $result = $GLOBALS["Database"]->query("SELECT ".$sql." LIMIT 1");
    return ($result === NULL ? NULL : $result->fetch_assoc());
}

class PresenceResult
{
    public $result;
    function __construct($result) { $this->result = $result; }
    function fetch_assoc() { return ($this->result->fetchArray(SQLITE3_ASSOC) ?: NULL); }
}

class PresenceDatabase
{
    public $db;
    public $fail_next_halfday = false;
    public $journal = [];

    function __construct()
    {
        $this->db = new SQLite3(":memory:");
        $this->db->exec("CREATE TABLE user_log (id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_user INT, log_date TEXT, last_log TEXT, last_ip TEXT, duration INT, type INT)");
        $this->db->exec("CREATE TABLE user_log_distrans_cursor (id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_user INT, source_key TEXT, reset_token TEXT DEFAULT '', xtime INT DEFAULT 0,
            sshtime INT DEFAULT 0, ssh_idle_time INT DEFAULT 0, locktime INT DEFAULT 0,
            last_seen TEXT, UNIQUE(id_user, source_key))");
        $this->db->exec("CREATE TABLE user_log_halfday (id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_user INT, log_date TEXT, period INT, type INT, duration INT,
            UNIQUE(id_user, log_date, period, type))");
    }
    function real_escape_string($value) { return ($this->db->escapeString($value)); }
    function query($sql)
    {
        if ($this->fail_next_halfday && strpos($sql, "INSERT INTO user_log_halfday") !== false)
        {
            $this->fail_next_halfday = false;
            return (NULL);
        }
        // When replaying the journal under InnoDB, generated IDs can differ:
        // InnoDB consumes an AUTO_INCREMENT value on a rolled-back INSERT.
        // Resolve the selected row there instead of reusing SQLite's ID.
        if (preg_match('/^\s*SELECT id FROM user_log\s/i', $sql))
            $this->journal[] = "SET @presence_selected_id = (".$sql.")";
        if (preg_match('/^\s*UPDATE user_log\s/i', $sql))
            $this->journal[] = preg_replace('/WHERE id = [0-9]+/', 'WHERE id = @presence_selected_id', $sql);
        else
            $this->journal[] = $sql;
        // Translate SQL dialect only; run real relational updates/transactions.
        $sql = str_replace(["START TRANSACTION", " FOR UPDATE"], ["BEGIN", ""], $sql);
        if (strpos($sql, "ON DUPLICATE KEY UPDATE") !== false)
        {
            $keys = strpos($sql, "INSERT INTO user_log_halfday") !== false
                ? "id_user, log_date, period, type" : "id_user, source_key";
            $sql = str_replace("ON DUPLICATE KEY UPDATE", "ON CONFLICT ($keys) DO UPDATE SET", $sql);
            $sql = preg_replace('/VALUES\(([a-z_]+)\)/i', 'excluded.$1', $sql);
        }
        $sql = preg_replace('/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', '$1 $2', $sql);
        if (preg_match('/^\s*SELECT/i', $sql))
        {
            $result = $this->db->query($sql);
            return ($result === false ? NULL : new PresenceResult($result));
        }
        return ($this->db->exec($sql) ? true : NULL);
    }
}

function presence_same($actual, $expected, $message)
{
    if ($actual !== $expected)
        throw new RuntimeException($message.": ".var_export($actual, true)." != ".var_export($expected, true));
}
function presence_ts($date)
{
    return ((new DateTimeImmutable($date, new DateTimeZone("Europe/Paris")))->getTimestamp());
}
function presence_sample($end, $x, $token = "epoch-a", $source = "aa|activity-v3", $ssh = 0, $lock = 0, $idle = 0)
{
    return (["source_key" => $source, "counter_timestamp" => $end, "reset_token" => $token,
        "xtime" => $x, "sshtime" => $ssh, "locktime" => $lock, "ssh_idle_time" => $idle,
        "ip" => "192.0.2.1"]);
}
function presence_import($id, $samples)
{
    $reason = NULL;
    presence_same(import_distrans_activity_batch($id, $samples, $reason), true, "import failed: ".$reason);
}
function presence_total($id, $type = USER_LOG_WORK, $day = NULL)
{
    global $Database;
    $where = $day === NULL ? "" : " AND DATE(log_date) = '$day'";
    return ((int)$Database->db->querySingle("SELECT COALESCE(SUM(duration), 0) FROM user_log
        WHERE id_user = $id AND type = $type $where"));
}

$Database = new PresenceDatabase;
$t = presence_ts("2026-10-23 10:00:00");
$Database->query("INSERT INTO user_log (id_user, log_date, duration, type, last_ip)
    VALUES (97, '2026-10-15 00:00:00', 2233336, 1, '')");
$Database->query("INSERT INTO user_log_distrans_cursor
    (id_user, source_key, xtime, last_seen) VALUES (97, 'aa|activity-v2', 1163870, '2026-10-16 17:03:21')");
$historical = $Database->db->querySingle("SELECT * FROM user_log WHERE id = 1", true);
$old_cursor = $Database->db->querySingle("SELECT * FROM user_log_distrans_cursor WHERE id = 1", true);
presence_import(97, [presence_sample($t, 1163870)]);
presence_same(presence_total(97, 1, "2026-10-23"), 0, "first snapshot must be a baseline");
presence_import(97, [presence_sample($t + 120, 1163990)]);
presence_import(97, [presence_sample($t + 120, 1163990)]);
presence_same(presence_total(97, 1, "2026-10-23"), 120, "repeated collection counted twice");
// This is the old unbounded-fallback regression: unchanged observation time,
// but a historical cumulative counter suddenly appears.
presence_import(97, [presence_sample($t + 120, 2233336)]);
presence_same(presence_total(97, 1, "2026-10-23"), 120, "frozen timestamp imported a huge counter");
presence_import(97, [presence_sample($t + 240, 2233456)]);
presence_same(presence_total(97, 1, "2026-10-23"), 240, "normal measurement did not resume");
presence_import(97, [presence_sample($t + 360, 900000, "epoch-b")]);
presence_import(97, [presence_sample($t + 480, 900120, "epoch-b")]);
presence_same(presence_total(97, 1, "2026-10-23"), 360, "new epoch replayed historical values");
presence_import(97, [presence_sample($t + 600, 10, "epoch-b")]);
presence_import(97, [presence_sample($t + 720, 130, "epoch-b")]);
presence_import(97, [presence_sample($t + 300, 500000, "older")]);
presence_same(presence_total(97, 1, "2026-10-23"), 480, "regression or stale snapshot replayed time");
presence_same($Database->db->querySingle("SELECT * FROM user_log WHERE id = 1", true), $historical,
    "historical daily row changed");
presence_same($Database->db->querySingle("SELECT * FROM user_log_distrans_cursor WHERE id = 1", true), $old_cursor,
    "old cursor changed");

// Two machines, duplicate local + SSH sessions: one student timeline.
presence_import(101, [presence_sample($t, 0), presence_sample($t, 0, "b", "bb|activity-v3")]);
presence_import(101, [presence_sample($t + 120, 120),
    presence_sample($t + 120, 0, "b", "bb|activity-v3", 120)]);
presence_same(presence_total(101), 120, "local session missing");
presence_same(presence_total(101, 2), 0, "parallel SSH double counted");
presence_import(101, [presence_sample($t + 240, 240, "epoch-a", "aa|activity-v3", 0, 120),
    presence_sample($t + 240, 0, "b", "bb|activity-v3", 240)]);
presence_same(presence_total(101, 2), 120, "active SSH must beat local lock");
presence_same(presence_total(101, -1), 0, "lock duplicated active SSH");
presence_import(102, [presence_sample($t, 0)]);
presence_import(102, [presence_sample($t + 120, 0, "epoch-a", "aa|activity-v3", 0, 0, 120)]);
presence_same(presence_total(102, -2), 120, "idle SSH is not tracked separately");
presence_same(presence_total(102), 0, "idle SSH counted as active work");

$midnight = presence_ts("2026-10-24 00:00:00");
presence_import(103, [presence_sample($midnight - 120, 0)]);
presence_import(103, [presence_sample($midnight + 120, 240)]);
presence_same(presence_total(103, 1, "2026-10-23"), 120, "midnight: preceding day");
presence_same(presence_total(103, 1, "2026-10-24"), 120, "midnight: following day");
$noon = presence_ts("2026-10-23 13:00:00");
presence_import(104, [presence_sample($noon - 60, 0)]);
presence_import(104, [presence_sample($noon + 60, 120)]);
foreach ([0, 1] as $period)
    presence_same((int)$Database->db->querySingle("SELECT duration FROM user_log_halfday
        WHERE id_user = 104 AND period = $period"), 60, "13:00 halfday split");

foreach ([[105, "2026-10-25", "2026-10-26", 90000], [106, "2026-03-29", "2026-03-30", 82800]] as $case)
{
    [$id, $start, $end, $seconds] = $case;
    presence_same(add_student_log_absolute_interval($id, 1, presence_ts($start), presence_ts($end)), true, "DST write");
    presence_same(presence_total($id), $seconds, "DST daily duration");
    presence_same((int)$Database->db->querySingle("SELECT SUM(duration) FROM user_log_halfday WHERE id_user = $id"),
        11 * 3600, "DST halfday duration");
}

presence_import(107, [presence_sample($t, 0)]);
$Database->fail_next_halfday = true;
$reason = NULL;
presence_same(import_distrans_activity_batch(107, [presence_sample($t + 120, 120)], $reason), false,
    "injected halfday error must fail the transaction");
presence_same(presence_total(107), 0, "failed transaction kept daily seconds");
presence_import(107, [presence_sample($t + 120, 120)]);
presence_import(107, [presence_sample($t + 120, 120)]);
presence_same(presence_total(107), 120, "rollback/retry lost or duplicated seconds");

// Execute Albedo's real conversion helpers, without running its cron jobs.
$source = file_get_contents(__DIR__."/../pages/profile/albedo.php");
$functions = substr($source, strpos($source, "function albedo_room_machine_type"));
$functions = substr($functions, 0, strpos($functions, "\n$"."logcnt = 0;"));
eval($functions);
$entry = ["machine" => "aa", "last_activity_timestamp" => $t - 86400, "updated_timestamp" => $t];
presence_same(albedo_room_log_counter_timestamp($entry), $t, "collection used last keyboard activity");
$entry = ["distrans_duration" => true, "xtime" => 1000, "reset_token" => "x", "counter_timestamp" => $t];
presence_same(albedo_room_merge_measured_state($entry, $entry)["xtime"], 1000, "duplicate snapshot summed");
presence_same(albedo_room_log_counter_source_key(["mac" => "AA"]), "aa|activity-v3", "cursor migration");
$idle_desk = ["name" => "pc-idle", "mac" => "aa:bb:cc:dd:ee:ff", "ip" => "192.0.2.3",
    "type" => "linux", "users" => [], "huile_version" => "2:1.0.0+1761642810~deb13u1-1"];
$idle_logs = albedo_room_logs_from_distrans_payload(["machines" => [$idle_desk], "users" => []]);
presence_same($idle_logs[0]["huile_version"], $idle_desk["huile_version"], "Huile version on a desk with no login");

// Optional companion checkout: replay real Persoc-compatible packets all the
// way through Distrans, Albedo conversion and SQL persistence.
if (isset($argv[1]))
{
    require_once (rtrim($argv[1], "/")."/src/tools/activity_log.php");
    $tmp = sys_get_temp_dir()."/presence-chain-".bin2hex(random_bytes(6));
    $conf = ["Machines" => ["Directory" => $tmp."/machines"], "Users" => ["Directory" => $tmp."/users"]];
    $packet = ["mac" => "aa:bb:cc:dd:ee:ff", "name" => "pc-test", "ip" => "192.0.2.2", "type" => "linux",
        "huile_version" => "2:1.0.0+1761642810~deb13u1-1",
        "users" => [["username" => "student", "mode" => "x", "lock" => false, "last_activity" => $t]]];
    try
    {
        foreach ([0, 5, 10, 15, 20, 25, 30] as $step)
        {
            distrans_activity_record($conf, distrans_activity_normalize_log_command($packet), $t + $step);
            $payload = albedo_room_logs_from_distrans_payload(distrans_get_activity_log($conf, []));
            presence_import(108, [$payload[0]]);
        }
        presence_same(presence_total(108), 30, "complete chain duration");
        distrans_activity_record($conf, distrans_activity_normalize_log_command($packet), $t + 86400);
        $payload = albedo_room_logs_from_distrans_payload(distrans_get_activity_log($conf, []));
        presence_import(108, [$payload[0]]);
        presence_same(presence_total(108), 30, "complete chain invented overnight presence");

        // Run the real Albedo import body, with only its external I/O mocked.
        // The remaining cron tasks (bonuses, guidance) are outside this test.
        $body = substr($source, strpos($source, "\n$"."logcnt = 0;"));
        $body = substr($body, 0, strpos($body, 'require_once (__DIR__."/albedo/money_bonus.php");'));
        $PresenceHandCalls = [];
        // Fresh source/epoch, after the deterministic replay above.
        unlink($tmp."/users/student.json");
        unlink($tmp."/machines/aa-bb-cc-dd-ee-ff.json");
        $observed = time();
        foreach ([0, 5, 10] as $step)
        {
            distrans_activity_record($conf, distrans_activity_normalize_log_command($packet), $observed + $step);
            $PresencePayload = distrans_get_activity_log($conf, []);
            eval($body);
        }
        presence_same(presence_total(109), 10, "Albedo cron body did not persist measured seconds");
        presence_same(end($PresenceDesks)["huile_version"], $packet["huile_version"], "Albedo passes the Huile version to the desk");
        presence_same($PresenceHandCalls, ["get_activity_log", "get_activity_log", "get_activity_log"],
            "Albedo must not reset cumulative counters");

        // One real hour: Persoc every five seconds, Albedo every two minutes,
        // duplicate collections, simultaneous local/SSH, and Intra browsing.
        $rate_conf = ["Machines" => ["Directory" => $tmp."/rate-machines"],
            "Users" => ["Directory" => $tmp."/rate-users"]];
        for ($step = 0; $step <= 3600; $step += 5)
        {
            $local = $packet;
            if ($step >= 1800)
                $local["users"] = [];
            distrans_activity_record($rate_conf, distrans_activity_normalize_log_command($local), $t + $step);
            $remote = $packet;
            $remote["mac"] = "aa:bb:cc:dd:ee:01";
            $remote["name"] = "pc-remote";
            $remote["users"][0]["mode"] = "ssh";
            $remote["users"][] = $remote["users"][0];
            distrans_activity_record($rate_conf, distrans_activity_normalize_log_command($remote), $t + $step);
            if ($step % 60 === 0)
                compute_student_log(["id" => 110], USER_LOG_INTRA, user_log_wall_timestamp($t + $step));
            if ($step % 120 === 0)
            {
                $samples = albedo_room_logs_from_distrans_payload(distrans_get_activity_log($rate_conf, []));
                presence_import(110, $samples);
                presence_import(110, $samples);
                presence_same(presence_total(110) + presence_total(110, USER_LOG_DISTANT), $step,
                    "regular import must credit exactly elapsed time");
            }
        }
        presence_same(presence_total(110), 1800, "local half-hour duration");
        presence_same(presence_total(110, USER_LOG_DISTANT), 1800, "remote half-hour duration");
        presence_same(presence_total(110, USER_LOG_INTRA), 3600, "Intra observations kept independently");
        presence_same((int)get_student_log(["id" => 110], user_log_wall_timestamp($t + 3600)), 3600,
            "home must show one hour, despite simultaneous Intra observations");
        presence_same((int)$Database->db->querySingle("SELECT SUM(duration) FROM user_log_halfday
            WHERE id_user = 110 AND type IN (1, 2)"), 3600, "halfday measured duration");
    }
    finally
    {
        foreach ([$tmp."/machines", $tmp."/users", $tmp."/rate-machines", $tmp."/rate-users"] as $dir)
        {
            foreach (glob($dir."/{*,.*}", GLOB_BRACE) ?: [] as $file)
                if (is_file($file)) unlink($file);
            if (is_dir($dir)) rmdir($dir);
        }
        if (is_dir($tmp)) rmdir($tmp);
    }
}
if (getenv("PRESENCE_SQL_JOURNAL"))
    file_put_contents(getenv("PRESENCE_SQL_JOURNAL"), implode(";\n", $Database->journal).";\n");
echo "distrans_presence: OK (cursors, replay, resets, overlap, midnight, DST, rollback, Albedo, optional full chain)\n";
