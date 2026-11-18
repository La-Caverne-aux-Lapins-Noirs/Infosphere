<?php

function room_desk_user_codename_column_exists()
{
    static $exists = NULL;

    if ($exists !== NULL)
        return ($exists);
    $exists = in_array("codename", db_select_rows("room_desk_user"));
    return ($exists);
}

function room_desk_system_user_is_ignored($username)
{
    $username = strtolower(trim((string)$username));

    if ($username == "" || $username == "root" || $username == "technocore")
        return (true);
    return (false);
}

function room_desk_user_insert_values($desk, $users, $local_user, $lock, $user_state = [])
{
    global $Database;

    $desk = (int)$desk;
    $insert = [];
    $seen = [];
    foreach ($users as $usr)
    {
        $usr = trim((string)$usr);
        if (room_desk_system_user_is_ignored($usr))
            continue ;

        $state = isset($user_state[$usr]) && is_array($user_state[$usr]) ? $user_state[$usr] : [];
        if (isset($state["distant"]))
            $distant = !empty($state["distant"]) ? "1" : "0";
        else
            $distant = ($usr != $local_user) ? "1" : "0";
        $key = strtolower($usr)."/".$distant;
        if (isset($seen[$key]))
            continue ;
        $seen[$key] = true;

        $id_user = resolve_codename("user", $usr);
        $id_user_sql = $id_user->is_error() ? "NULL" : (int)$id_user->value;
        $codename_sql = "'".$Database->real_escape_string($usr)."'";
        $locked = "0";
        if (isset($state["lock"]) && $state["lock"])
            $locked = "1";
        else if ($distant == "0" && $lock)
            $locked = "1";
        $insert[] = "($desk, $id_user_sql, $codename_sql, $distant, $locked)";
    }
    return ($insert);
}

function set_desk_data($ddata)
{
    global $Database;

    $name = $ddata["name"];
    $mac = $ddata["mac"];
    $ip = $ddata["ip"];
    $user = $ddata["user"];
    $lock = $ddata["lock"];
    $local_user = isset($ddata["local"]) ? $ddata["local"] : "";
    $user_state = isset($ddata["user_state"]) && is_array($ddata["user_state"]) ? $ddata["user_state"] : [];
    $type = $ddata["type"];

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
        return (new ErrorResponse("InvalidParameter", "ip $ip"));
    if (filter_var($mac, FILTER_VALIDATE_MAC) === false)
        return (new ErrorResponse("InvalidParameter", "mac $mac"));

    $name = explode(".", $name)[0];
    if (($desk = resolve_codename("room_desk", $name))->is_error())
    {
	if ($desk->label != "BadCodeName")
	    return ($desk);
        // A desk can have been renamed in Infosphère while retaining its hostname.
        $mac_key = strtolower(str_replace([":", "-", "."], "", $mac));
        $matches = db_select_all("
            id FROM room_desk
            WHERE LOWER(REPLACE(REPLACE(REPLACE(mac, ':', ''), '-', ''), '.', '')) = '$mac_key'
            LIMIT 2
        ");
        if (count($matches) == 1)
            $desk = (int)$matches[0]["id"];
        else
        {
            $name = $Database->real_escape_string($name);
            if (!$Database->query("INSERT INTO room_desk (codename, id_room, type) VALUES ('$name', -1, 0)"))
                return (new ErrorResponse("CannotUpdate"));
            $desk = $Database->insert_id;
        }
    }
    else
	$desk = $desk->value;

    // Status: 0: libre, 1: utilisé physiquement, 2: verrouillé physiquement,
    // 3: en panne/injoignable, 4: occupé uniquement en SSH.
    $has_local_user = trim((string)$local_user) != "" && !room_desk_system_user_is_ignored($local_user);
    $has_ssh_user = false;
    foreach ($user as $usr)
    {
	$usr = trim((string)$usr);
	if (room_desk_system_user_is_ignored($usr))
	    continue ;
	if ($usr != $local_user)
	{
	    $has_ssh_user = true;
	    break ;
	}
    }

    if ($has_local_user && $lock)
	$status = 2;
    else if ($has_local_user)
	$status = 1;
    else if ($has_ssh_user)
	$status = 4;
    else
	$status = 0;
    
    // Type: 0 linux, 1 windows 2 mac 3 rpi
    $type = (int)$type;
    if ($type < 0 || $type > 3)
	return (new ErrorResponse("InvalidParameter", "type $type", "[0-3]"));

    $package_update = "";
    if (array_key_exists("huile_version", $ddata))
    {
        static $has_huile_version = NULL;
        $has_huile_version ??= in_array("huile_version", db_select_rows("room_desk"), true);
        if ($has_huile_version)
        {
            $version = $ddata["huile_version"];
            $version_sql = is_string($version) && $version !== "" && strlen($version) <= 255
                ? "'".$Database->real_escape_string($version)."'" : "NULL";
            $package_update = ", huile_version = $version_sql";
        }
    }

    $Database->query("
        UPDATE room_desk
        SET ip = '$ip', mac = '$mac', status = $status, type = $type, last_update = NOW()$package_update
        WHERE id = $desk
    ");

    // Suppression des utilisateurs indiqués
    // Puis remise des utilisateurs indiqués comme connecté
    // En une glorieuse requete

    $Database->query("DELETE FROM room_desk_user WHERE id_room_desk = $desk");
    if (count($user))
    {
	if (room_desk_user_codename_column_exists())
	{
	    $ins = room_desk_user_insert_values($desk, $user, $local_user, $lock, $user_state);
	    if (count($ins))
		$Database->query("\n                    INSERT INTO room_desk_user\n                        (id_room_desk, id_user, codename, distant, locked)\n                    VALUES ".implode(", ", $ins)."\n                ");
	}
	else
	{
	    $ins = [];
	    foreach ($user as $usr)
	    {
		$state = isset($user_state[$usr]) && is_array($user_state[$usr]) ? $user_state[$usr] : [];
		if (($id_user = resolve_codename("user", $usr))->is_error())
		    continue ;
		$id_user = $id_user->value;
		if (isset($state["distant"]))
		    $distant = !empty($state["distant"]) ? "1" : "0";
		else
		    $distant = ($usr != $local_user) ? "1" : "0";
		if (isset($state["lock"]))
		    $known_lock = $state["lock"] ? "1" : "0";
		else if ($distant == "0")
		    $known_lock = $lock ? "1" : "0";
		else
		    $known_lock = "0";
		$ins[] = "($desk, $id_user, $distant, $known_lock)";
	    }
	    if (count($ins))
		$Database->query("\n                    INSERT INTO room_desk_user\n                        (id_room_desk, id_user, distant, locked)\n                    VALUES ".implode(", ", $ins)."\n                ");
	}
    }
}
