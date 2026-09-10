<?php

/**
 * Functional school mailboxes.
 *
 * A mailbox purpose is deliberately a short semantic key (admission,
 * candidate, billing, ...). Workflows can target that key without knowing the
 * actual address used by a school. When no dedicated mailbox is configured,
 * the normal school/organisation contact mail remains the fallback.
 */

function school_mailbox_purposes()
{
    return ([
        "admission" => "Admissions",
        "candidate" => "Candidats",
        "billing" => "Facturation",
        "pedagogy" => "Pédagogie",
        "jury" => "Jurys / examens",
        "quality" => "Qualité",
        "secretariat" => "Secrétariat",
        "archive" => "Archives des envois (CCI)",
    ]);
}

function school_mailbox_purpose_is_valid($purpose)
{
    return (is_string($purpose) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $purpose) === 1);
}

function school_mailbox_table_available()
{
    return (function_exists("db_get_tables") && in_array("school_mailbox", db_get_tables(), true));
}

function school_mailbox_list($id_school)
{
    if (!school_mailbox_table_available())
        return ([]);
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return ([]);

    $out = [];
    foreach (db_select_all("purpose, mail FROM school_mailbox
        WHERE id_school = $id_school AND deleted IS NULL
        ORDER BY purpose ASC") as $row)
    {
        $purpose = trim((string)($row["purpose"] ?? ""));
        $mail = trim((string)($row["mail"] ?? ""));
        if (school_mailbox_purpose_is_valid($purpose) && filter_var($mail, FILTER_VALIDATE_EMAIL))
            $out[$purpose] = $mail;
    }
    return ($out);
}

function school_mailbox_set($id_school, $purpose, $mail)
{
    global $Database;

    $id_school = (int)$id_school;
    $purpose = strtolower(trim((string)$purpose));
    $mail = trim((string)$mail);
    if ($id_school <= 0 || !school_mailbox_purpose_is_valid($purpose))
        return (new ErrorResponse("InvalidParameter", "school mailbox"));
    if (!school_mailbox_table_available())
        return (new ErrorResponse("CannotEdit", "school_mailbox table is missing"));
    if ($mail != "" && filter_var($mail, FILTER_VALIDATE_EMAIL) === false)
        return (new ErrorResponse("BadMail"));

    $epurpose = $Database->real_escape_string($purpose);
    $existing = db_select_one("id FROM school_mailbox
        WHERE id_school = $id_school AND purpose = '$epurpose'");

    if ($mail == "")
    {
        if ($existing != NULL)
        {
            $id = (int)$existing["id"];
            if (!$Database->query("UPDATE school_mailbox SET deleted = NOW(), mail = '' WHERE id = $id"))
                return (new ErrorResponse("CannotEdit"));
        }
        return (new Response);
    }

    $email = $Database->real_escape_string($mail);
    if ($existing != NULL)
    {
        $id = (int)$existing["id"];
        if (!$Database->query("UPDATE school_mailbox SET mail = '$email', deleted = NULL WHERE id = $id"))
            return (new ErrorResponse("CannotEdit"));
    }
    else if (!$Database->query("INSERT INTO school_mailbox (id_school, purpose, mail)
        VALUES ($id_school, '$epurpose', '$email')"))
        return (new ErrorResponse("CannotEdit"));
    return (new Response);
}

function school_mailbox_resolve($id_school, $purpose = "", $fallback = true)
{
    global $Database;

    $id_school = (int)$id_school;
    $purpose = strtolower(trim((string)$purpose));
    if ($id_school <= 0)
        return ("");

    if ($purpose != "" && school_mailbox_purpose_is_valid($purpose) && school_mailbox_table_available())
    {
        $epurpose = $Database->real_escape_string($purpose);
        $row = db_select_one("mail FROM school_mailbox
            WHERE id_school = $id_school AND purpose = '$epurpose' AND deleted IS NULL");
        $mail = trim((string)($row["mail"] ?? ""));
        if (filter_var($mail, FILTER_VALIDATE_EMAIL))
            return ($mail);
    }

    if (!$fallback)
        return ("");
    $school = db_select_one("COALESCE(NULLIF(school.mail, ''), NULLIF(organization.mail, ''), '') AS mail
        FROM school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE school.id = $id_school AND school.deleted IS NULL");
    $mail = trim((string)($school["mail"] ?? ""));
    return (filter_var($mail, FILTER_VALIDATE_EMAIL) ? $mail : "");
}
