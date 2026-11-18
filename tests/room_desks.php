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
$desks_fixture = [];
foreach ([1 => 10, 2 => 20, 3 => -1, 4 => 0] as $id => $room)
    $desks_fixture[$id] = ["id" => $id, "id_room" => $room, "codename" => "Poste <$id>",
        "mac" => "", "ip" => "", "type" => 0, "status" => 0, "x" => 1, "y" => 2, "misc" => "",
        "last_update" => "2026-11-18 15:00:00"];
$desks_fixture[1]["huile_version"] = "2:1.0.0+20261118";
$desks_fixture[2]["last_update"] = "2026-11-18 14:55:59";

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
desk_check(preg_match('~<th scope="row">Poste &lt;1&gt;</th>(.*?)</tr>~s', $html, $row) === 1, "occupied desk row");
desk_check(str_contains($row[1], 'href="index.php?p=ProfileMenu&amp;a=60">lea&lt;dev&gt;</a>')
    && str_contains($row[1], "(Sur place)"), "local user profile and label");
desk_check(str_contains($row[1], "aurelien") && str_contains($row[1], "(SSH)")
    && str_contains($row[1], "ssh&lt;invite&gt;") && str_contains($row[1], "(SSH inactif)"), "active and idle SSH users");
desk_check(!str_contains($html, "stale_user"), "no user shown when the workstation is offline");
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
desk_check($Database->queries === [], "denied saves must never reach UPDATE");
desk_check(!SetDesk(4, ["id_room" => 10], "PUT", "json", "")->is_error(), "attach a previously unassigned desk");

$User = ["id" => 1, "authority" => ADMINISTRATOR, "admin_mode" => true];
$html = desk_inventory_page(2);
desk_check(str_contains($html, 'action="/api/desk/2"') && str_contains($html, 'value="-1"'), "admin can edit and unassign");
$Database->fail_update = true;
desk_check(SetDesk(2, ["misc" => "Example"], "PUT", "json", "")->is_error(), "a failed update cannot report success");

echo "Room desk inventory: OK\n";
