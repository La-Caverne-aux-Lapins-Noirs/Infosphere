<?php

/*
 * Session ownership / visibility helpers.
 *
 * Historical sessions belong to an activity. Standalone sessions deliberately
 * do not: they belong either to one user, one laboratory, or one or more
 * schools. Keeping this rule in one place avoids making every calendar
 * consumer reinvent it.
 */

function session_reference_id($session, $field)
{
    if (is_object($session))
        $session = get_object_vars($session);
    if (!is_array($session) || !array_key_exists($field, $session))
        return (0);
    return ((int)$session[$field] > 0 ? (int)$session[$field] : 0);
}



function session_school_schema_ready()
{
    static $ready = NULL;
    if ($ready !== NULL)
        return ($ready);
    $row = db_select_one("COUNT(*) AS total FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'session_school'");
    return ($ready = ($row && (int)$row["total"] === 1));
}

function session_school_ids($session)
{
    if (is_object($session))
        $session = get_object_vars($session);
    if (is_array($session) && isset($session["_school_ids"]) && is_array($session["_school_ids"]))
        return (array_values(array_unique(array_filter(array_map("intval", $session["_school_ids"]), function($id) { return ($id > 0); }))));
    $id_session = session_reference_id($session, "id");
    if ($id_session <= 0 || !session_school_schema_ready())
        return ([]);
    $out = [];
    foreach (db_select_all("id_school FROM session_school WHERE id_session = $id_session ORDER BY id_school") as $row)
        if ((int)$row["id_school"] > 0)
            $out[] = (int)$row["id_school"];
    return ($out);
}

function session_school_can_manage($session, $id_user = -1)
{
    global $User;

    if ($id_user == -1)
        $id_user = (int)($User["id"] ?? 0);
    $schools = session_school_ids($session);
    if ($id_user <= 0 || !count($schools))
        return (false);
    if (is_admin() && $id_user == (int)($User["id"] ?? 0))
        return (true);
    foreach ($schools as $id_school)
        if (!(user_has_school_authority($id_user, "DIRECTOR", $id_school)
            || user_has_school_authority($id_user, "SECRETARIAT", $id_school)
            || user_has_school_authority($id_user, "COMMERCIAL", $id_school)))
            return (false);
    return (true);
}

function session_source_value($session, $field)
{
    if (is_object($session))
        $session = get_object_vars($session);
    if (!is_array($session) || !array_key_exists($field, $session))
        return ("");
    return (trim((string)$session[$field]));
}

function session_has_source($session)
{
    return (
        session_source_value($session, "source_type") !== "" &&
        session_source_value($session, "source_id") !== "" &&
        session_source_value($session, "source_key") !== ""
    );
}

function session_source_is_complete($session)
{
    $count = 0;
    foreach (["source_type", "source_id", "source_key"] as $field)
        if (session_source_value($session, $field) !== "")
            ++$count;
    return ($count === 0 || $count === 3);
}

function session_source_matches($session, $type, $id, $key = NULL)
{
    if (!session_has_source($session))
        return (false);
    if (session_source_value($session, "source_type") !== trim((string)$type) ||
        session_source_value($session, "source_id") !== trim((string)$id))
        return (false);
    return ($key === NULL || session_source_value($session, "source_key") === trim((string)$key));
}

function session_has_activity($session)
{
    return (session_reference_id($session, "id_activity") > 0);
}

function session_standalone_kind($session)
{
    if (session_has_activity($session))
        return (NULL);

    $id_user = session_reference_id($session, "id_user");
    $id_laboratory = session_reference_id($session, "id_laboratory");
    if ($id_user > 0 && $id_laboratory == 0)
        return ("user");
    if ($id_laboratory > 0 && $id_user == 0)
        return ("laboratory");
    if ($id_user == 0 && $id_laboratory == 0 && count(session_school_ids($session)))
        return ("school");
    return (NULL);
}

function session_is_standalone($session)
{
    return (session_standalone_kind($session) !== NULL);
}

function session_laboratory_authority($id_user, $id_laboratory)
{
    static $cache = [];

    $id_user = (int)$id_user;
    $id_laboratory = (int)$id_laboratory;
    if ($id_user <= 0 || $id_laboratory <= 0)
        return (-1);
    $key = $id_user.":".$id_laboratory;
    if (array_key_exists($key, $cache))
        return ($cache[$key]);
    $row = db_select_one("
        user_laboratory.authority
        FROM user_laboratory
        LEFT JOIN laboratory ON laboratory.id = user_laboratory.id_laboratory
        WHERE user_laboratory.id_user = $id_user
          AND user_laboratory.id_laboratory = $id_laboratory
          AND laboratory.deleted IS NULL
        ORDER BY user_laboratory.authority DESC
    ");
    return ($cache[$key] = ($row == NULL ? -1 : (int)$row["authority"]));
}

function session_is_visible_to_user($session, $id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);

    $kind = session_standalone_kind($session);
    if ($kind === "user")
        return (session_reference_id($session, "id_user") === $id_user);
    if ($kind === "laboratory")
        return (session_laboratory_authority(
            $id_user,
            session_reference_id($session, "id_laboratory")
        ) >= 0);
    if ($kind === "school")
    {
        foreach (session_school_ids($session) as $id_school)
            if (db_select_one("id FROM user_school WHERE id_user = $id_user AND id_school = ".(int)$id_school) != NULL)
                return (true);
    }
    return (false);
}

function session_director_manages_laboratory($id_laboratory)
{
    $id_laboratory = (int)$id_laboratory;
    if ($id_laboratory <= 0)
        return (false);
    foreach (db_select_all("
        id_school
        FROM school_laboratory
        WHERE id_laboratory = $id_laboratory
    ") as $school)
        if (is_director_for_school((int)$school["id_school"]))
            return (true);
    return (false);
}

function session_standalone_can_manage($session)
{
    global $User;

    if (!is_array($User) || !is_intranet_member_profile())
        return (false);
    if (is_admin())
        return (true);

    $kind = session_standalone_kind($session);
    if ($kind === "user")
    {
        $target = session_reference_id($session, "id_user");
        return ($target === (int)$User["id"] || is_director_for_student($target, false));
    }
    if ($kind === "laboratory")
    {
        $id_laboratory = session_reference_id($session, "id_laboratory");
        return (session_laboratory_authority((int)$User["id"], $id_laboratory) >= ASSISTANT
            || session_director_manages_laboratory($id_laboratory));
    }
    if ($kind === "school")
        return (session_school_can_manage($session, (int)$User["id"]));
    return (false);
}

function session_standalone_owner_label($session)
{
    global $Language;
    static $cache = [];

    $kind = session_standalone_kind($session);
    $owner_id = $kind === "user"
        ? session_reference_id($session, "id_user")
        : ($kind === "laboratory" ? session_reference_id($session, "id_laboratory") : implode(",", session_school_ids($session)));
    $cache_key = ($kind ?: "none").":".$owner_id.":".$Language;
    if (array_key_exists($cache_key, $cache))
        return ($cache[$cache_key]);
    if ($kind === "user")
    {
        $id_user = session_reference_id($session, "id_user");
        $row = db_select_one("
            codename, nickname, first_name, use_name, family_name
            FROM user
            WHERE id = $id_user AND deleted IS NULL
        ");
        if ($row == NULL)
            return ($cache[$cache_key] = "Personnel");
        $family = trim((string)($row["use_name"] ?: $row["family_name"]));
        $identity = trim(trim((string)$row["first_name"])." ".$family);
        if ($identity != "")
            return ($cache[$cache_key] = $identity);
        if (trim((string)$row["nickname"]) != "")
            return ($cache[$cache_key] = $row["nickname"]);
        return ($cache[$cache_key] = $row["codename"]);
    }
    if ($kind === "laboratory")
    {
        $id_laboratory = session_reference_id($session, "id_laboratory");
        $column = $Language == "en" ? "en_name" : "fr_name";
        $row = db_select_one("
            codename, $column as name
            FROM laboratory
            WHERE id = $id_laboratory AND deleted IS NULL
        ");
        if ($row == NULL)
            return ($cache[$cache_key] = "Laboratoire");
        return ($cache[$cache_key] = (trim((string)$row["name"]) != "" ? $row["name"] : $row["codename"]));
    }
    if ($kind === "school")
    {
        $names = [];
        $column = $Language == "en" ? "en_name" : "fr_name";
        foreach (session_school_ids($session) as $id_school)
        {
            $row = db_select_one("
                school.codename, COALESCE(NULLIF(organization.$column, ''), NULLIF(organization.name, ''), school.codename) AS name
                FROM school
                LEFT JOIN organization ON organization.id = school.id_organization
                WHERE school.id = ".(int)$id_school." AND school.deleted IS NULL
            ");
            if ($row)
                $names[] = $row["name"];
        }
        return ($cache[$cache_key] = (count($names) ? implode(", ", $names) : "École"));
    }
    return ($cache[$cache_key] = "");
}

function session_standalone_parent(array $session)
{
    $kind = session_standalone_kind($session);
    $name = trim((string)($session["name"] ?? ""));
    if ($name == "")
        $name = $kind === "laboratory" ? "Événement du laboratoire" : ($kind === "school" ? "Événement de l’école" : "Événement personnel");

    $parent = new stdClass;
    $parent->id = -1;
    $parent->codename = "standalone-session-".(int)($session["id"] ?? 0);
    $parent->name = $name;
    $parent->parent_name = session_standalone_owner_label($session);
    $parent->parent_codename = $kind === "laboratory"
        ? "laboratory-".session_reference_id($session, "id_laboratory")
        : ($kind === "school" ? "school-".implode("-", session_school_ids($session)) : "user-".session_reference_id($session, "id_user"));
    $parent->type = 19;          // Misc, uniquement pour les vieux renderers.
    $parent->type_name = "Misc";
    $parent->type_type = 2;
    $parent->reference_activity = -1;
    $parent->min_team_size = 1;
    $parent->teacher = [];
    $parent->standalone_session = true;
    $parent->standalone_kind = $kind;
    return ($parent);
}
