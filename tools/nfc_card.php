<?php

/**
 * EFRITS NFC v1
 *
 * The exact 32-byte file is written to pages 4..11 of a compatible
 * Ultralight/Type-2 tag. It deliberately contains no personal data: only a
 * versioned random identifier and an integrity checksum.
 */

function nfc_card_file_size()
{
    return (32);
}

function nfc_card_token_size()
{
    return (16);
}

function nfc_card_build_payload($token = NULL)
{
    if ($token === NULL)
    {
        try
        {
            $token = random_bytes(nfc_card_token_size());
        }
        catch (Throwable $e)
        {
            return (NULL);
        }
    }
    if (!is_string($token) || strlen($token) != nfc_card_token_size())
        return (NULL);

    $head = "EFR1".chr(1).chr(nfc_card_token_size())."\0\0".$token;
    // hash('crc32b', ..., true) is always the network/big-endian representation
    // expected by the companion Windows tool.
    $crc = hash("crc32b", $head, true);
    if (!is_string($crc) || strlen($crc) != 4)
        return (NULL); // @codeCoverageIgnore
    return ($head.$crc."NFC!");
}

function nfc_card_parse_payload($content)
{
    if (!is_string($content) || strlen($content) != nfc_card_file_size())
        return (NULL);
    if (substr($content, 0, 4) !== "EFR1"
        || ord($content[4]) != 1
        || ord($content[5]) != nfc_card_token_size()
        || ord($content[6]) != 0
        || ord($content[7]) != 0
        || substr($content, 28, 4) !== "NFC!")
        return (NULL);

    $expected = hash("crc32b", substr($content, 0, 24), true);
    $actual = substr($content, 24, 4);
    if (!hash_equals($expected, $actual))
        return (NULL);

    return ([
        "version" => 1,
        "token" => substr($content, 8, nfc_card_token_size()),
        "token_hex" => bin2hex(substr($content, 8, nfc_card_token_size())),
        "crc32" => bin2hex($actual),
    ]);
}

function nfc_card_user($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (NULL);
    return (db_select_one("
        id, codename, password, profile_status, authority, deleted
        FROM user
        WHERE id = $id_user
    "));
}

function nfc_card_user_is_eligible($user)
{
    return (is_array($user)
        && (int)($user["id"] ?? 0) > 0
        && trim((string)($user["codename"] ?? "")) != ""
        && trim((string)($user["password"] ?? "")) != ""
        && (string)($user["profile_status"] ?? "") === "member"
        && ($user["deleted"] ?? NULL) === NULL);
}

function nfc_card_file_for_user($user)
{
    global $Configuration;

    if (!is_array($user) || trim((string)($user["codename"] ?? "")) == "")
        return (NULL);
    return ($Configuration->UsersDir($user["codename"])."admin/access.nfc");
}

function nfc_card_file($id_user)
{
    $user = nfc_card_user($id_user);
    if (!nfc_card_user_is_eligible($user))
        return (NULL);
    return (nfc_card_file_for_user($user));
}

function nfc_card_write_atomic($path, $content)
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true))
        return (false);

    $tmp = @tempnam($dir, ".nfc-");
    if ($tmp === false)
        return (false);
    $ok = @file_put_contents($tmp, $content, LOCK_EX) === strlen($content);
    if ($ok)
        @chmod($tmp, 0600);
    if ($ok)
        $ok = @rename($tmp, $path);
    if (!$ok)
        @unlink($tmp);
    else
        @chmod($path, 0600);
    return ($ok);
}

function nfc_card_generate_for_user($id_user, $replace = false)
{
    $user = nfc_card_user($id_user);
    if ($user == NULL)
        return (["ok" => false, "error" => "UserNotFound"]);
    if (!nfc_card_user_is_eligible($user))
        return (["ok" => false, "error" => "NfcCardUnavailable"]);

    $path = nfc_card_file_for_user($user);
    if (!$replace && is_file($path))
    {
        $existing = @file_get_contents($path);
        if (nfc_card_parse_payload($existing) !== NULL)
            return (["ok" => true, "created" => false, "path" => $path, "content" => $existing]);
        // Corrupt/old content must never silently be distributed as a valid card.
        $replace = true;
    }

    $content = nfc_card_build_payload();
    if ($content === NULL || !nfc_card_write_atomic($path, $content))
        return (["ok" => false, "error" => "CannotWriteFile"]);

    return (["ok" => true, "created" => true, "path" => $path, "content" => $content]);
}

function nfc_card_ensure_for_user($id_user)
{
    return (nfc_card_generate_for_user($id_user, false));
}

function nfc_card_regenerate_for_user($id_user)
{
    return (nfc_card_generate_for_user($id_user, true));
}

function nfc_card_download_name($user)
{
    $codename = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($user["codename"] ?? "user"));
    return ($codename.".nfc");
}

function nfc_card_find_user_by_payload($content, $active_only = true)
{
    if (nfc_card_parse_payload($content) === NULL)
        return (NULL);

    $filter = $active_only
        ? "WHERE password != '' AND profile_status = 'member' AND deleted IS NULL"
        : "";
    foreach (db_select_all("
        id, codename, first_name, family_name, use_name, password, profile_status, authority, deleted
        FROM user
        $filter
        ORDER BY id ASC
    ") as $user)
    {
        $path = nfc_card_file_for_user($user);
        if (!is_file($path))
            continue ;
        $known = @file_get_contents($path);
        if (is_string($known)
            && strlen($known) == strlen($content)
            && hash_equals($known, $content))
            return ($user);
    }
    return (NULL);
}

function nfc_card_owner_lookup($token_hex, $expected_user_id = NULL)
{
    $token_hex = strtolower(trim((string)$token_hex));
    if (!preg_match('/^[0-9a-f]{32}$/D', $token_hex))
        return (NULL);

    $token = hex2bin($token_hex);
    if ($token === false)
        return (NULL); // @codeCoverageIgnore
    $payload = nfc_card_build_payload($token);
    if ($payload === NULL)
        return (NULL); // @codeCoverageIgnore

    $owner = nfc_card_find_user_by_payload($payload, false);
    if ($owner === NULL)
        return ([
            "owner_found" => false,
            "belongs_to_user" => false,
        ]);

    $family_name = trim((string)($owner["use_name"] ?? ""));
    if ($family_name == "")
        $family_name = trim((string)($owner["family_name"] ?? ""));
    $display_name = trim((string)($owner["first_name"] ?? "")." ".$family_name);
    if ($display_name == "")
        $display_name = (string)($owner["codename"] ?? "");

    return ([
        "owner_found" => true,
        "belongs_to_user" => $expected_user_id === NULL
            ? false : (int)$owner["id"] === (int)$expected_user_id,
        "owner_active" => nfc_card_user_is_eligible($owner),
        "owner_id" => (int)$owner["id"],
        "owner_codename" => (string)($owner["codename"] ?? ""),
        "owner_name" => $display_name,
    ]);
}

function nfc_card_notification_school_id()
{
    global $User;

    if (!logged_in() || !is_array($User) || (int)($User["id"] ?? 0) <= 0)
        return (0);

    if (is_admin())
    {
        $school = db_select_one("
            school.id as id
            FROM school
            WHERE school.deleted IS NULL
            ORDER BY school.id ASC
        ");
        return ((int)($school["id"] ?? 0));
    }

    $student_authority = user_school_student_authority_sql();
    $school = db_select_one("
        user_school.id_school as id
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = ".((int)$User["id"])."
          AND user_school.authority <> $student_authority
          AND school.deleted IS NULL
        ORDER BY user_school.id ASC
    ");
    return ((int)($school["id"] ?? 0));
}

function nfc_card_info_for_user($id_user)
{
    $user = nfc_card_user($id_user);
    if (!nfc_card_user_is_eligible($user))
        return (["eligible" => false, "exists" => false]);
    $path = nfc_card_file_for_user($user);
    $content = is_file($path) ? @file_get_contents($path) : false;
    $valid = is_string($content) && nfc_card_parse_payload($content) !== NULL;
    return ([
        "eligible" => true,
        "exists" => $valid,
        "generated_at" => $valid ? @filemtime($path) : false,
        "path" => $path,
    ]);
}
