<?php

function room_desk_sql_string($value)
{
    global $Database;

    if ($value === NULL)
        return ("NULL");
    return ("'".$Database->real_escape_string((string)$value)."'");
}

function room_desk_read_optional_fields($data)
{
    $fields = [];

    if (isset($data["id_room"]))
        $fields["id_room"] = (int)$data["id_room"];
    if (isset($data["x"]))
        $fields["x"] = max(0, min(1250, (int)$data["x"]));
    if (isset($data["y"]))
        $fields["y"] = max(0, min(500, (int)$data["y"]));
    if (isset($data["codename"]) && trim((string)$data["codename"]) != "")
        $fields["codename"] = trim((string)$data["codename"]);
    if (isset($data["mac"]))
        $fields["mac"] = trim((string)$data["mac"]);
    if (isset($data["ip"]))
        $fields["ip"] = trim((string)$data["ip"]);
    if (isset($data["type"]))
        $fields["type"] = max(0, min(3, (int)$data["type"]));
    if (isset($data["misc"]))
        $fields["misc"] = trim((string)$data["misc"]);
    return ($fields);
}

function room_desk_update_fields($id, $fields)
{
    global $Database;

    $sql = [];
    foreach ($fields as $key => $value)
    {
        if (in_array($key, ["id_room", "x", "y", "type"]))
            $sql[] = "$key = ".(int)$value;
        else
            $sql[] = "$key = ".room_desk_sql_string($value);
    }
    if (!count($sql))
        bad_request();
    $Database->query("\n        UPDATE room_desk\n        SET ".implode(", ", $sql)."\n        WHERE id = ".(int)$id."\n    ");
}

function room_desk_fetch_room_id($id)
{
    $desk = db_select_one("
        id_room
        FROM room_desk
        WHERE id = ".(int)$id."
    ");

    if ($desk == NULL)
        bad_request();
    return ((int)$desk["id_room"]);
}

function room_desk_can_work_on_room($id_room)
{
    if ((int)$id_room == -1)
        return (is_admin());
    return (is_director_for_room((int)$id_room));
}

function room_desk_require_editor_rights($current_room, $target_room)
{
    if ((int)$current_room != -1 && !room_desk_can_work_on_room($current_room))
        bad_request();
    if (!room_desk_can_work_on_room($target_room))
        bad_request();
}

function SetDesk($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    $fields = room_desk_read_optional_fields($data);
    $current_room = room_desk_fetch_room_id($id);
    $target_room = isset($fields["id_room"]) ? $fields["id_room"] : $current_room;
    room_desk_require_editor_rights($current_room, $target_room);
    room_desk_update_fields($id, $fields);
    return (new ValueResponse([
        "msg" => $Dictionnary["Edited"],
        "id" => (int)$id,
    ]));
}

function AddDesk($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
        bad_request();
    $fields = room_desk_read_optional_fields($data);
    if (!isset($fields["id_room"]) || !isset($fields["x"]) || !isset($fields["y"]) || !isset($fields["codename"]))
        bad_request();
    room_desk_require_editor_rights($fields["id_room"], $fields["id_room"]);
    if (!isset($fields["type"]))
        $fields["type"] = 0;
    $fields["status"] = 3;

    $columns = [];
    $values = [];
    foreach ($fields as $key => $value)
    {
        $columns[] = $key;
        if (in_array($key, ["id_room", "x", "y", "type", "status"]))
            $values[] = (string)(int)$value;
        else
            $values[] = room_desk_sql_string($value);
    }
    $Database->query("\n        INSERT INTO room_desk (".implode(", ", $columns).")\n        VALUES (".implode(", ", $values).")\n    ");
    return (new ValueResponse([
        "msg" => $Dictionnary["Added"],
        "id" => (int)$Database->insert_id,
    ]));
}

$Tab = [
    "POST" => [
        "" => [
            "is_director",
            "AddDesk"
        ]
    ],
    "PUT" => [
        "" => [
            "is_director",
            "SetDesk"
        ]
    ]
];
