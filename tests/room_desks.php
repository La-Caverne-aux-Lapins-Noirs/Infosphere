<?php

define("BANISHED", -1);
define("ADMINISTRATOR", 1);

$root = dirname(__DIR__);
require_once "$root/languages/fr.php";
require_once "$root/tools/is_admin.php";
require_once "$root/tools/access.php";
require_once "$root/tools/is_page_authorized.php";
require_once "$root/tools/fetch_rooms.php";
require_once "$root/tools/set_desk_data.php";
require_once "$root/tools/response.php";
require_once "$root/api/desk.php";

$Language = "fr";
$_COOKIE["password"] = "test-authentication-cookie";
$desks_fixture = [];
foreach ([1 => 10, 2 => 20, 3 => -1, 4 => 0] as $id => $room)
    $desks_fixture[$id] = ["id" => $id, "id_room" => $room, "codename" => "Poste <$id>",
        "mac" => "", "ip" => "", "type" => 0, "status" => 0, "x" => 1, "y" => 2, "misc" => "",
        "last_update" => "2026-11-18 15:00:00"];
$desks_fixture[1]["huile_version"] = "2:1.0.0+20261118";
$desks_fixture[1]["mac"] = "aa:bb:cc:dd:ee:01";
$desks_fixture[2]["mac"] = "aa:bb:cc:dd:ee:02";
$desks_fixture[2]["last_update"] = "2026-11-18 14:55:59";
$upgrade_calls = [];
$upgrade_response = ['result' => 'ok', 'machines' => [
    'aa:bb:cc:dd:ee:01' => ['supported' => true, 'online' => true],
    'aa:bb:cc:dd:ee:02' => ['supported' => true, 'online' => true],
]];

function hand_request($packet, $code = true, $timeout = 0)
{
    global $upgrade_calls, $upgrade_response;

    $upgrade_calls[] = [$packet, $code];
    if ($packet['command'] === 'request_machine_upgrade')
        return ['result' => 'ok', 'upgrade' => ['id' => str_repeat('a', 32), 'status' => 'pending']];
    return $upgrade_response;
}

function is_symbol($value)
{
    return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/D', $value);
}

function db_select_rows($table)
{
    if ($table === "room_desk_user")
        return ["codename"];
    throw new RuntimeException("Unexpected table $table");
}

function db_select_all($query, $key = "")
{
    global $desks_fixture;

    if (str_contains($query, "FROM user_school"))
    {
        if (str_contains($query, "id_user = 42") && !str_contains($query, "id_school = 20"))
            return [["id_school" => 10, "authority" => "DIRECTOR"]];
        return [];
    }
    if (str_contains($query, "FROM school_room"))
        return [["id_school" => str_contains($query, "id_room = 10") ? 10 : 20]];
    if (str_contains($query, "FROM room_desk_user"))
        return [
            ["id_room_desk" => 1, "id" => 60, "codename" => "lea<dev>", "distant" => 0, "locked" => 0],
            ["id_room_desk" => 1, "id" => 61, "codename" => "aurelien", "distant" => 1, "locked" => 0],
            ["id_room_desk" => 1, "id" => null, "codename" => "ssh<invite>", "distant" => 1, "locked" => 1],
            ["id_room_desk" => 2, "id" => 62, "codename" => "stale_user", "distant" => 1, "locked" => 0],
        ];
    if (str_contains($query, "FROM room_desk"))
        return $desks_fixture;
    if (str_contains($query, "FROM room"))
        return [10 => ["id" => 10, "name" => "Salle A"], 20 => ["id" => 20, "name" => "Salle B"]];
    throw new RuntimeException("Unexpected query $query");
}

function db_select_one($query)
{
    global $desks_fixture;
    preg_match('/WHERE id = (\d+)/', $query, $match);
    return $desks_fixture[(int)$match[1]] ?? null;
}

class TestDeskDatabase
{
    public $fail_update = false;
    public $queries = [];
    function real_escape_string($value) { return addslashes($value); }
    function query($sql)
    {
        $this->queries[] = $sql;
        if (str_contains($sql, "SELECT node_key, observed_at, payload"))
            return new class($GLOBALS['desk_health_fixture'] ?? []) {
                private $rows;
                function __construct($rows) { $this->rows = $rows; }
                function fetch_assoc() { return array_shift($this->rows); }
            };
        return !$this->fail_update;
    }
}

class TestDeskRequestDenied extends RuntimeException {}
function bad_request() { throw new TestDeskRequestDenied; }
function now() { return strtotime("2026-11-18 15:01:00"); }
function date_to_timestamp($date) { return strtotime($date); }

$Database = new TestDeskDatabase;

function desk_check($condition, $message)
{
    if (!$condition)
        throw new RuntimeException($message);
}

function desk_inventory_page($desk_id): string
{
    global $root, $Language, $Dictionnary, $User;

    $directory = "$root/pages/room/desks";
    if (!is_page_authorized($directory, $User))
        return "";
    $_GET["desk"] = $desk_id;
    ob_start();
    require "$directory/index.phtml";
    return ob_get_clean();
}

$User = null;
desk_check(desk_inventory_page(1) === "", "anonymous access");
$User = ["id" => 77, "authority" => 0];
desk_check(desk_inventory_page(1) === "" && !am_i_director(), "student access");

$User = ["id" => 42, "authority" => 0];
$html = desk_inventory_page(1);
desk_check(str_contains($html, 'action="/api/desk/1"'), "director can edit a desk in their school");
desk_check(str_contains($html, "Poste &lt;2&gt;") && str_contains($html, "Lecture seule"),
    "the inventory still lists the other school's desk");
desk_check(str_contains($html, "2:1.0.0+20261118") && str_contains($html, "Sans salle"), "version and unassigned desks");
desk_check(str_contains($html, '<th scope="col">Utilisé par</th>'), "users column");
desk_check(preg_match('~<th scope="row">\s*<button[^>]*aria-controls="desk-health-1".*?</th>(.*?)</tr>~s', $html, $row) === 1, "occupied desk row");
desk_check(str_contains($html, 'id="desk-health-1" class="mh-detail" hidden'),
    "a workstation without monitoring history stays collapsed");
desk_check(str_contains($row[1], 'href="index.php?p=ProfileMenu&amp;a=60">lea&lt;dev&gt;</a>')
    && str_contains($row[1], "(Sur place)"), "local user profile and label");
desk_check(str_contains($row[1], "aurelien") && str_contains($row[1], "(SSH)")
    && str_contains($row[1], "ssh&lt;invite&gt;") && str_contains($row[1], "(SSH inactif)"), "active and idle SSH users");
desk_check(!str_contains($html, "stale_user"), "no user shown when the workstation is offline");
desk_check(str_contains($html, 'action="/api/desk/1/upgrade"')
    && !str_contains($html, 'action="/api/desk/2/upgrade"'), "upgrade button follows room authority");
desk_check(str_contains($html, 'name="upgrade_token"'), "upgrade form includes a session token");
desk_check(!str_contains($html, 'value="20"') && !str_contains($html, 'value="-1"'), "no forbidden destination");
desk_check(!str_contains(desk_inventory_page(2), 'action="/api/desk/2"'), "direct edit link is checked");
desk_check(str_contains(desk_inventory_page(4), 'action="/api/desk/4"'), "old unassigned desks can be attached");
foreach ([[2, 10], [1, 20], [1, -1]] as [$desk, $destination])
{
    try
    {
        SetDesk($desk, ["id_room" => $destination], "PUT", "json", "");
        throw new RuntimeException("Forbidden edit of $desk to $destination succeeded");
    }
    catch (TestDeskRequestDenied $e) {}
}
desk_check(!array_filter($Database->queries, static fn($query) => str_contains($query, "UPDATE room_desk")),
    "denied saves must never reach UPDATE");
desk_check(!SetDesk(4, ["id_room" => 10], "PUT", "json", "")->is_error(), "attach a previously unassigned desk");

$User = ["id" => 1, "authority" => ADMINISTRATOR, "admin_mode" => true];
$html = desk_inventory_page(2);
desk_check(str_contains($html, 'action="/api/desk/2"') && str_contains($html, 'value="-1"'), "admin can edit and unassign");
$Database->fail_update = true;
desk_check(SetDesk(2, ["misc" => "Example"], "PUT", "json", "")->is_error(), "a failed update cannot report success");

$User = ['id' => 42, 'authority' => 0];
$token = room_desk_upgrade_token();
$valid_upgrade = ['upgrade_token' => $token];
$call_count = count($upgrade_calls);
foreach ([
    [['id' => 42, 'authority' => 0], 2, $valid_upgrade],
    [['id' => 42, 'authority' => 0], 1, ['upgrade_token' => 'wrong']],
    [['id' => 42, 'authority' => 0], 1, []],
    [['id' => 77, 'authority' => 0], 1, $valid_upgrade],
] as [$actor, $desk, $data])
{
    $User = $actor;
    try
    {
        UpgradeDesk($desk, $data, "POST", "json", "");
        throw new RuntimeException("Unauthorized upgrade succeeded");
    }
    catch (TestDeskRequestDenied $e) {}
}
desk_check(count($upgrade_calls) === $call_count, "denied upgrades never contact Distrans");
$User = ['id' => 42, 'authority' => 0];
desk_check(room_desk_upgrade_token() === $token, "upgrade token is stable across page and API requests");
$_COOKIE['password'] = 'different-authentication-cookie';
desk_check(room_desk_upgrade_token() !== $token, "upgrade token is bound to the authenticated login");
$_COOKIE['password'] = 'test-authentication-cookie';
desk_check(!UpgradeDesk(1, $valid_upgrade, "POST", "json", "")->is_error(), "director requests an upgrade");
desk_check(end($upgrade_calls) === [['command' => 'request_machine_upgrade', 'mac' => 'aa:bb:cc:dd:ee:01'], true],
    "upgrade command uses the stored MAC and internal authentication");

$states = GetDeskUpgradeStates(-1, [], "GET", "json", "")->value['content'];
desk_check($states[1]['can_request'] && !$states[2]['can_request'], "polling preserves school scope");
$alternative_mac = $desks_fixture;
$alternative_mac[1]['mac'] = 'AA-BB-CC-DD-EE-01';
desk_check(room_desk_upgrade_states($alternative_mac)[1]['can_request'], "MAC address separators and case are normalized");
$alternative_mac[1]['mac'] = 'aabb.ccdd.ee01';
desk_check(room_desk_upgrade_states($alternative_mac)[1]['can_request'], "dotted MAC addresses are normalized");
$upgrade_response['machines']['aa:bb:cc:dd:ee:01']['upgrade'] = ['status' => 'pending'];
desk_check(!room_desk_upgrade_states($desks_fixture)[1]['can_request'], "pending requests disable the button");
$upgrade_response = false;
desk_check(room_desk_upgrade_states($desks_fixture)[1]['status'] === 'unavailable', "Distrans failure is visible");

$Database->fail_update = false;
$desk_health_fixture = [
    ['node_key' => 'desk:aa:bb:cc:dd:ee:01', 'observed_at' => time(),
     'payload' => json_encode(['kind' => 'workstation', 'online' => true,
         'metrics' => tc_metrics_normalize(['cpu_percent' => 99])])],
    ['node_key' => 'desk:aa:bb:cc:dd:ee:02', 'observed_at' => time(),
     'payload' => json_encode(['kind' => 'workstation', 'online' => false])],
];
$html = desk_inventory_page(1);
desk_check(preg_match('~id="desk-health-1" class="mh-detail"\s+data-health-url=~', $html),
    "a degraded machine unfolds automatically");
desk_check(str_contains($html, 'id="desk-health-2" class="mh-detail" hidden'),
    "being powered off does not unfold a workstation as an alert");
desk_check(machine_health_desk_key(['mac' => 'aabb.ccdd.ee01']) === 'desk:aa:bb:cc:dd:ee:01',
    "health history survives legacy MAC formatting");
$card = GetDeskHealth(1, [], 'GET', 'json', '')->value['content'];
desk_check(str_contains($card, 'Utilisation des ressources'), 'lazy history endpoint renders graphs');
foreach ([null, ['id' => 77, 'authority' => 0]] as $actor)
{
    $User = $actor;
    try
    {
        GetDeskHealth(1, [], 'GET', 'json', '');
        throw new RuntimeException('unauthorized history request succeeded');
    }
    catch (TestDeskRequestDenied $e) {}
}
echo "Room desk inventory: OK\n";
