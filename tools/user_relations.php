<?php

function user_relation_definitions()
{
    return ([
        "financial" => ["bit" => 1, "label" => "Responsable financier"],
        "legal" => ["bit" => 2, "label" => "Responsable légal"],
        "emergency" => ["bit" => 4, "label" => "Contact d'urgence"],
        "internship" => ["bit" => 8, "label" => "Tuteur de stage / alternance"],
    ]);
}

function user_relation_values($relation)
{
    $definitions = user_relation_definitions();
    $out = [];

    if ($relation === NULL || $relation === "")
        return ($out);

    if (is_array($relation))
    {
        foreach ($relation as $name)
            if (isset($definitions[$name]) && !in_array($name, $out, true))
                $out[] = $name;
        return ($out);
    }

    // Compatibilité avec les anciennes bases où relation était un bitfield.
    if (is_numeric($relation) && preg_match('/^-?[0-9]+$/', (string)$relation))
    {
        $mask = (int)$relation;
        foreach ($definitions as $name => $definition)
            if (($mask & $definition["bit"]) != 0)
                $out[] = $name;
        return ($out);
    }

    foreach (preg_split('/\s*,\s*/', (string)$relation, -1, PREG_SPLIT_NO_EMPTY) as $name)
        if (isset($definitions[$name]) && !in_array($name, $out, true))
            $out[] = $name;
    return ($out);
}

function user_relation_has($relation, $name)
{
    return (in_array($name, user_relation_values($relation), true));
}

function user_relation_value($relation)
{
    if (!is_array($relation))
        $relation = user_relation_values($relation);
    $definitions = user_relation_definitions();
    $out = [];
    foreach ($relation as $name)
        if (isset($definitions[$name]) && !in_array($name, $out, true))
            $out[] = $name;
    return (implode(",", $out));
}

function user_relation_labels($relation)
{
    $definitions = user_relation_definitions();
    $out = [];
    foreach (user_relation_values($relation) as $name)
        $out[] = $definitions[$name]["label"];
    return ($out);
}

function create_external_user_relation($id_child, array $data)
{
    global $Database;

    $id_child = (int)$id_child;
    if ($id_child <= 0 || db_select_one("id FROM user WHERE id = $id_child AND authority != -1") == NULL)
        return (new ErrorResponse("UserNotFound"));

    $relations = user_relation_values($data["relation"] ?? []);
    if (!count($relations))
        return (new ErrorResponse("MissingField", "relation"));

    $first_name = trim((string)($data["first_name"] ?? ""));
    $family_name = trim((string)($data["family_name"] ?? ""));
    $mail = trim((string)($data["mail"] ?? ""));
    $phone = trim((string)($data["phone"] ?? ""));
    if ($first_name == "" || $family_name == "" || $mail == "")
        return (new ErrorResponse("MissingField", "first_name, family_name, mail"));

    $login = build_named_user_login($first_name, $family_name);
    $request = subscribe($login, $mail, NULL, false, true, "extern");
    if ($request->is_error())
        return ($request);
    $external = $request->value;
    $id_parent = (int)$external["id"];

    $request = set_user_data($id_parent, [
        "first_name" => strtolower($first_name),
        "family_name" => strtolower($family_name),
        "phone" => $phone,
    ]);
    if ($request->is_error())
    {
        $Database->query("DELETE FROM user WHERE id = $id_parent AND profile_status = 'extern' AND password = ''");
        return ($request);
    }

    $relation = $Database->real_escape_string(user_relation_value($relations));
    if ($Database->query("\n        INSERT INTO parent_child (id_parent, id_child, relation)\n        VALUES ($id_parent, $id_child, '$relation')\n    ") === false)
    {
        $Database->query("DELETE FROM user WHERE id = $id_parent AND profile_status = 'extern' AND password = ''");
        return (new ErrorResponse("CannotRegister"));
    }

    add_log(EDITING_OPERATION,
        "External relation user ".$external["codename"]." added for user $id_child with relation $relation",
        $id_child
    );
    return (new ValueResponse([
        "id" => $id_parent,
        "codename" => $external["codename"],
        "relation" => $relations,
    ]));
}


function user_relation_is_administrative_contact($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);
    foreach (db_select_all("
        parent_child.relation
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_parent
        WHERE parent_child.id_parent = $id_user
          AND user.authority != -1
    ") as $row)
        if (user_relation_has($row["relation"] ?? "", "legal")
            || user_relation_has($row["relation"] ?? "", "financial"))
            return (true);
    return (false);
}

function user_relation_managed_children($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);
    return (db_select_all("
        user.id, user.codename, user.first_name, user.family_name, parent_child.relation
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_child
        WHERE parent_child.id_parent = $id_user
          AND user.id IS NOT NULL
          AND user.authority != -1
        ORDER BY parent_child.id ASC
    "));
}

function can_manage_relation_administrative_user($id_user)
{
    if (is_admin())
        return (true);
    if (!logged_in() || !user_relation_is_administrative_contact($id_user))
        return (false);
    foreach (user_relation_managed_children($id_user) as $child)
        if (is_identity_authority_for_user((int)$child["id"]) || is_commercial())
            return (true);
    return (false);
}

function can_manage_user_administrative_profile($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0 || !logged_in())
        return (false);
    if (is_identity_authority_for_user($id_user))
        return (true);
    $user = db_select_one("profile_status FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (false);
    if (($user["profile_status"] ?? "") == "prospect" && is_commercial())
        return (true);
    return (can_manage_relation_administrative_user($id_user));
}

function can_send_user_administrative_form($id_user)
{
    $id_user = (int)$id_user;
    if (!logged_in())
        return (false);
    $user = db_select_one("profile_status, mail FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL || trim((string)($user["mail"] ?? "")) == "")
        return (false);
    $status = $user["profile_status"] ?? "";
    if (user_relation_is_administrative_contact($id_user))
        return (is_admin() || is_commercial() || can_manage_relation_administrative_user($id_user));
    if ($status == "prospect")
        return (is_admin() || is_commercial() || is_identity_authority_for_user($id_user));
    return (false);
}

function can_view_user_relations($id_user)
{
    global $User;

    if (!$User)
        return (false);
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);
    if (is_me($id_user) || is_identity_authority_for_user($id_user) || is_commercial())
        return (true);

    $me = (int)$User["id"];
    return (db_select_one("\n        id FROM parent_child\n        WHERE (id_parent = $me AND id_child = $id_user)\n           OR (id_child = $me AND id_parent = $id_user)\n    ") != NULL);
}

function fetch_user_relation_summary($id_user)
{
    global $User;

    $id_user = (int)$id_user;
    if (!can_view_user_relations($id_user))
        return (["responsible_for" => [], "responsible_by" => []]);

    $viewer = (int)$User["id"];
    $can_view_all = is_me($id_user) || is_identity_authority_for_user($id_user) || is_commercial();
    $parent_filter = $can_view_all ? "" : " AND parent_child.id_child = $viewer ";
    $child_filter = $can_view_all ? "" : " AND parent_child.id_parent = $viewer ";

    $responsible_for = db_select_all("\n        user.id, user.codename, user.first_name, user.family_name, user.profile_status,\n        parent_child.relation, parent_child.id as id_relation\n        FROM parent_child\n        LEFT JOIN user ON user.id = parent_child.id_child\n        WHERE parent_child.id_parent = $id_user\n          AND user.id IS NOT NULL\n          AND user.authority != -1\n          $parent_filter\n        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC\n    ");
    $responsible_by = db_select_all("\n        user.id, user.codename, user.first_name, user.family_name, user.profile_status,\n        parent_child.relation, parent_child.id as id_relation\n        FROM parent_child\n        LEFT JOIN user ON user.id = parent_child.id_parent\n        WHERE parent_child.id_child = $id_user\n          AND user.id IS NOT NULL\n          AND user.authority != -1\n          $child_filter\n        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC\n    ");

    return ([
        "responsible_for" => $responsible_for,
        "responsible_by" => $responsible_by,
    ]);
}

function user_relation_display_name($user)
{
    $name = trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? ""));
    if ($name == "")
        $name = (string)($user["codename"] ?? "");
    return ($name);
}
