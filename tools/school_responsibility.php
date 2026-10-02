<?php

/**
 * School responsibilities are deliberately separate from user_school.authority.
 *
 * user_school describes structural roles and is still queried as such in a
 * number of legacy access-control paths. Responsibilities are cumulative,
 * auditable assignments layered on top of that membership.
 *
 * Definitions are data-driven:
 *   - id_school IS NULL: built-in responsibility available to every school;
 *   - id_school = N: custom responsibility owned by school N.
 *
 * Both definitions and assignments are soft-deleted so that the historical
 * trail is preserved.
 */

function school_responsibility_label(array $definition)
{
    global $Language;

    $language_field = (($Language ?? "fr") == "en") ? "en_name" : "fr_name";
    $fallback_field = $language_field == "fr_name" ? "en_name" : "fr_name";
    $label = trim((string)($definition[$language_field] ?? ""));
    if ($label == "")
        $label = trim((string)($definition[$fallback_field] ?? ""));
    if ($label == "")
        $label = trim((string)($definition["codename"] ?? ""));
    return ($label);
}

function school_responsibility_definitions($id_school = NULL, $include_deleted = false)
{
    $id_school = $id_school === NULL ? NULL : (int)$id_school;
    $where_school = $id_school === NULL
        ? "school_responsibility.id_school IS NULL"
        : "(school_responsibility.id_school IS NULL OR school_responsibility.id_school = $id_school)";
    $where_deleted = $include_deleted ? "" : " AND school_responsibility.deleted IS NULL";

    $rows = db_select_all("
        school_responsibility.id,
        school_responsibility.id_school,
        school_responsibility.codename,
        school_responsibility.fr_name,
        school_responsibility.en_name,
        school_responsibility.student_assignable,
        school_responsibility.show_on_school_home,
        school_responsibility.icon,
        school_responsibility.insert_date,
        school_responsibility.id_creator,
        school_responsibility.deleted,
        school_responsibility.id_deleter
        FROM school_responsibility
        WHERE $where_school
          $where_deleted
        ORDER BY
          CASE WHEN school_responsibility.id_school IS NULL THEN 0 ELSE 1 END,
          school_responsibility.id
    ");

    $out = [];
    foreach (is_array($rows) ? $rows : [] as $row)
    {
        $id = (int)($row["id"] ?? 0);
        if ($id <= 0)
            continue ;
        $row["id"] = $id;
        $row["id_school"] = $row["id_school"] === NULL ? NULL : (int)$row["id_school"];
        $row["codename"] = strtoupper(trim((string)($row["codename"] ?? "")));
        $row["student_assignable"] = !empty($row["student_assignable"]);
        $row["show_on_school_home"] = !empty($row["show_on_school_home"]);
        $row["builtin"] = $row["id_school"] === NULL;
        // Compatibility aliases used by the first UI version.
        $row["staff_only"] = !$row["student_assignable"];
        $row["root"] = $row["show_on_school_home"];
        $row["label"] = school_responsibility_label($row);
        $out[$id] = $row;
    }
    return ($out);
}

function school_responsibility_definition($id_school, $identifier, $include_deleted = false)
{
    $identifier_string = trim((string)$identifier);
    if ($identifier_string == "")
        return (NULL);
    $identifier_id = preg_match('/^[0-9]+$/', $identifier_string) ? (int)$identifier_string : 0;
    $identifier_code = strtoupper($identifier_string);

    foreach (school_responsibility_definitions($id_school, $include_deleted) as $definition)
    {
        if (($identifier_id > 0 && (int)$definition["id"] === $identifier_id)
            || $definition["codename"] === $identifier_code)
            return ($definition);
    }
    return (NULL);
}

function school_responsibility_member_label(array $member)
{
    $first = trim((string)($member["first_name"] ?? ""));
    $use = trim((string)($member["use_name"] ?? ""));
    $family = trim((string)($member["family_name"] ?? ""));
    $name = trim($first." ".($use !== "" ? $use : $family));
    if ($name === "")
        $name = trim((string)($member["nickname"] ?? ""));
    if ($name === "")
        $name = trim((string)($member["codename"] ?? ""));
    return ($name);
}

function school_responsibility_normalize_codename($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return ("");
    if (function_exists("handle_french"))
        $value = handle_french($value, false);
    $value = strtoupper($value);
    $value = preg_replace('/[^A-Z0-9]+/', '_', $value);
    $value = trim((string)$value, '_');
    $value = preg_replace('/_+/', '_', $value);
    return (substr((string)$value, 0, 64));
}

function school_responsibility_codes_for_user($id_school, $id_user)
{
    $id_school = (int)$id_school;
    $id_user = (int)$id_user;
    if ($id_school <= 0 || $id_user <= 0)
        return ([]);
    $rows = db_select_all("
        school_responsibility.codename
        FROM user_school_responsibility
        INNER JOIN school_responsibility
          ON school_responsibility.id = user_school_responsibility.id_school_responsibility
        WHERE user_school_responsibility.id_school = $id_school
          AND user_school_responsibility.id_user = $id_user
          AND user_school_responsibility.deleted IS NULL
          AND school_responsibility.deleted IS NULL
          AND (school_responsibility.id_school IS NULL OR school_responsibility.id_school = $id_school)
          AND EXISTS (
              SELECT 1 FROM user_school
              WHERE user_school.id_school = $id_school
                AND user_school.id_user = $id_user
          )
        ORDER BY school_responsibility.codename
    ");
    $out = [];
    foreach (is_array($rows) ? $rows : [] as $row)
    {
        $code = strtoupper(trim((string)($row["codename"] ?? "")));
        if ($code != "")
            $out[] = $code;
    }
    return (array_values(array_unique($out)));
}

function school_responsibility_codes_by_user($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return ([]);
    $rows = db_select_all("
        user_school_responsibility.id_user,
        school_responsibility.codename
        FROM user_school_responsibility
        INNER JOIN school_responsibility
          ON school_responsibility.id = user_school_responsibility.id_school_responsibility
        WHERE user_school_responsibility.id_school = $id_school
          AND user_school_responsibility.deleted IS NULL
          AND school_responsibility.deleted IS NULL
          AND (school_responsibility.id_school IS NULL OR school_responsibility.id_school = $id_school)
          AND EXISTS (
              SELECT 1 FROM user_school
              WHERE user_school.id_school = $id_school
                AND user_school.id_user = user_school_responsibility.id_user
          )
        ORDER BY user_school_responsibility.id_user, school_responsibility.codename
    ");
    $out = [];
    foreach (is_array($rows) ? $rows : [] as $row)
    {
        $id_user = (int)($row["id_user"] ?? 0);
        $code = strtoupper(trim((string)($row["codename"] ?? "")));
        if ($id_user <= 0 || $code == "")
            continue ;
        if (!isset($out[$id_user]))
            $out[$id_user] = [];
        $out[$id_user][] = $code;
    }
    foreach ($out as &$codes)
        $codes = array_values(array_unique($codes));
    unset($codes);
    return ($out);
}

function school_responsibility_members($id_school)
{
    $id_school = (int)$id_school;
    $definitions = school_responsibility_definitions($id_school);
    $out = [];
    foreach ($definitions as $definition)
        $out[(int)$definition["id"]] = [];
    if ($id_school <= 0)
        return ($out);

    $rows = db_select_all("
        user_school_responsibility.id_school_responsibility,
        user.id, user.id AS id_user, user.codename, user.nickname,
        user.first_name, user.use_name, user.family_name
        FROM user_school_responsibility
        INNER JOIN school_responsibility
          ON school_responsibility.id = user_school_responsibility.id_school_responsibility
        INNER JOIN user ON user.id = user_school_responsibility.id_user
        WHERE user_school_responsibility.id_school = $id_school
          AND user_school_responsibility.deleted IS NULL
          AND school_responsibility.deleted IS NULL
          AND (school_responsibility.id_school IS NULL OR school_responsibility.id_school = $id_school)
          AND EXISTS (
              SELECT 1 FROM user_school
              WHERE user_school.id_school = $id_school
                AND user_school.id_user = user_school_responsibility.id_user
          )
          AND user.deleted IS NULL
          AND user.profile_status != 'jury'
        ORDER BY user_school_responsibility.id_school_responsibility,
                 user.family_name, user.first_name, user.codename
    ");
    foreach (is_array($rows) ? $rows : [] as $row)
    {
        $id_responsibility = (int)($row["id_school_responsibility"] ?? 0);
        if (!isset($definitions[$id_responsibility]) || !isset($out[$id_responsibility]))
            continue ;
        $definition = $definitions[$id_responsibility];
        if (!$definition["student_assignable"]
            && !school_responsibility_user_is_staff($id_school, (int)($row["id_user"] ?? 0)))
            continue ;
        unset($row["id_school_responsibility"]);
        $row["school_responsibilities"] = [$definition["codename"]];
        $out[$id_responsibility][] = $row;
    }
    return ($out);
}

function school_responsibility_school_summary($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return ([]);

    $definitions = school_responsibility_definitions($id_school);
    $members = school_responsibility_members($id_school);
    $out = [];
    foreach ($definitions as $definition)
    {
        if (empty($definition["show_on_school_home"])
            || ($definition["codename"] ?? "") === "FIRE_SAFETY")
            continue ;
        $people = $members[(int)$definition["id"]] ?? [];
        if (!count($people))
            continue ;
        $labels = [];
        foreach ($people as $person)
        {
            $label = school_responsibility_member_label($person);
            if ($label !== "")
                $labels[] = $label;
        }
        if (!count($labels))
            continue ;
        $out[] = [
            "label" => $definition["label"],
            "people" => array_values(array_unique($labels)),
        ];
    }
    return ($out);
}

function school_responsibility_user_belongs_to_school($id_school, $id_user)
{
    $id_school = (int)$id_school;
    $id_user = (int)$id_user;
    if ($id_school <= 0 || $id_user <= 0)
        return (false);
    return (db_select_one("
        user_school.id
        FROM user_school
        INNER JOIN user ON user.id = user_school.id_user
        WHERE user_school.id_school = $id_school
          AND user_school.id_user = $id_user
          AND user.deleted IS NULL
    ") !== NULL);
}

function school_responsibility_user_is_staff($id_school, $id_user)
{
    static $cache = [];

    $id_school = (int)$id_school;
    $id_user = (int)$id_user;
    $key = $id_school.":".$id_user;
    if (array_key_exists($key, $cache))
        return ($cache[$key]);

    $authorities = user_school_authorities($id_user, $id_school);
    $school_authorities = $authorities[$id_school] ?? [];
    foreach ($school_authorities as $authority => $enabled)
        if ($enabled && $authority !== "STUDENT")
            return ($cache[$key] = true);
    return ($cache[$key] = false);
}

function school_responsibility_can_assign($id_school, $id_user, $responsibility)
{
    $definition = is_array($responsibility)
        ? $responsibility
        : school_responsibility_definition($id_school, $responsibility);
    if ($definition === NULL || !school_responsibility_user_belongs_to_school($id_school, $id_user))
        return (false);
    if (!$definition["student_assignable"]
        && !school_responsibility_user_is_staff($id_school, $id_user))
        return (false);
    return (true);
}

function school_responsibility_set($id_school, $id_user, $responsibility, $enabled, $id_actor = 0)
{
    global $Database;

    $id_school = (int)$id_school;
    $id_user = (int)$id_user;
    $id_actor = (int)$id_actor;
    $definition = school_responsibility_definition($id_school, $responsibility);
    if ($definition === NULL
        || !school_responsibility_user_belongs_to_school($id_school, $id_user)
        || ($enabled && !school_responsibility_can_assign($id_school, $id_user, $definition)))
        return (new ErrorResponse("InvalidParameter", "responsibility"));

    $id_responsibility = (int)$definition["id"];
    if ($enabled)
    {
        $existing = db_select_one("
            id
            FROM user_school_responsibility
            WHERE id_user = $id_user
              AND id_school = $id_school
              AND id_school_responsibility = $id_responsibility
              AND deleted IS NULL
        ");
        if ($existing !== NULL)
            return (new ValueResponse(NULL));
        if ($Database->query("
            INSERT INTO user_school_responsibility
                (id_user, id_school, id_school_responsibility, id_actor)
            VALUES ($id_user, $id_school, $id_responsibility, ".($id_actor > 0 ? $id_actor : "NULL").")
        ") === false)
            return (new ErrorResponse("CannotAdd"));
    }
    else
    {
        if ($Database->query("
            UPDATE user_school_responsibility
            SET deleted = current_timestamp(),
                id_deleter = ".($id_actor > 0 ? $id_actor : "NULL")."
            WHERE id_user = $id_user
              AND id_school = $id_school
              AND id_school_responsibility = $id_responsibility
              AND deleted IS NULL
        ") === false)
            return (new ErrorResponse("CannotDelete"));
    }
    return (new ValueResponse(NULL));
}

function school_responsibility_create($id_school, array $data, $id_actor = 0)
{
    global $Database;

    $id_school = (int)$id_school;
    $id_actor = (int)$id_actor;
    $fr_name = trim((string)($data["fr_name"] ?? $data["name"] ?? ""));
    $en_name = trim((string)($data["en_name"] ?? ""));
    $codename = school_responsibility_normalize_codename($data["codename"] ?? $fr_name);
    $student_assignable = !empty($data["student_assignable"]) ? 1 : 0;
    $show_on_school_home = 1;

    if ($id_school <= 0 || $fr_name == "" || $codename == ""
        || !preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $codename))
        return (new ErrorResponse("InvalidParameter", "responsibility"));
    if ($en_name == "")
        $en_name = $fr_name;

    $escaped_code = $Database->real_escape_string($codename);
    $existing = db_select_one("
        id
        FROM school_responsibility
        WHERE codename = '$escaped_code'
          AND (id_school IS NULL OR id_school = $id_school)
    ");
    if ($existing !== NULL)
        return (new ErrorResponse("AlreadyExists", "responsibility"));

    $escaped_fr = $Database->real_escape_string($fr_name);
    $escaped_en = $Database->real_escape_string($en_name);
    if ($Database->query("
        INSERT INTO school_responsibility
            (id_school, scope_school_id, codename, fr_name, en_name,
             student_assignable, show_on_school_home, id_creator)
        VALUES
            ($id_school, $id_school, '$escaped_code', '$escaped_fr', '$escaped_en',
             $student_assignable, $show_on_school_home, ".($id_actor > 0 ? $id_actor : "NULL").")
    ") === false)
        return (new ErrorResponse("CannotAdd"));

    $id = isset($Database->insert_id) ? (int)$Database->insert_id : 0;
    return (new ValueResponse($id));
}

function school_responsibility_delete_definition($id_school, $responsibility, $id_actor = 0)
{
    global $Database;

    $id_school = (int)$id_school;
    $id_actor = (int)$id_actor;
    $definition = school_responsibility_definition($id_school, $responsibility);
    if ($definition === NULL || $definition["builtin"] || (int)$definition["id_school"] !== $id_school)
        return (new ErrorResponse("InvalidParameter", "responsibility"));

    $id_responsibility = (int)$definition["id"];
    $deleter = $id_actor > 0 ? $id_actor : "NULL";
    if ($Database->query("START TRANSACTION") === false)
        return (new ErrorResponse("CannotDelete"));
    if ($Database->query("
        UPDATE school_responsibility
        SET deleted = current_timestamp(), id_deleter = $deleter
        WHERE id = $id_responsibility
          AND id_school = $id_school
          AND deleted IS NULL
    ") === false
        || $Database->query("
        UPDATE user_school_responsibility
        SET deleted = current_timestamp(), id_deleter = $deleter
        WHERE id_school = $id_school
          AND id_school_responsibility = $id_responsibility
          AND deleted IS NULL
    ") === false)
    {
        $Database->query("ROLLBACK");
        return (new ErrorResponse("CannotDelete"));
    }
    $Database->query("COMMIT");
    return (new ValueResponse(NULL));
}

function school_responsibility_attach_to_members(array &$members, array $by_user)
{
    foreach ($members as &$member)
    {
        $id = (int)($member["id_user"] ?? $member["id"] ?? 0);
        $member["school_responsibilities"] = $by_user[$id] ?? [];
    }
    unset($member);
}
