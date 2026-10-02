<?php

/*
** Shared medal resolution
** -----------------------
** Evaluator/corrections, quizzes and future pedagogical engines all need the
** same rule: a referenced medal is resolved by codename and, if it does not
** exist yet, created with the historical "automatic" tag and icon command.
*/
function medal_resolve_or_create($codename, $source = "")
{
    global $Database;

    $codename = trim((string)$codename);
    if (!is_symbol($codename))
        return (["ok" => false, "error" => "InvalidParameter", "details" => $codename]);

    $escaped = $Database->real_escape_string($codename);
    $existing = db_select_one("id FROM medal WHERE codename = '$escaped' ORDER BY id ASC");
    if (is_array($existing))
        return (["ok" => true, "id" => (int)$existing["id"], "created" => false]);

    $command = $Database->real_escape_string(
        "genicon sband ".$codename." -c dres/medals/.ressources/.default_style.dab"
    );
    if ($Database->query("
        INSERT INTO medal (codename, tags, type, command, fr_name, en_name)
        VALUES ('$escaped', 'automatic', 0, '$command', '$escaped', '$escaped')
    ") === NULL)
    {
        // Concurrent creators are harmless: prefer the medal that now exists.
        $existing = db_select_one("id FROM medal WHERE codename = '$escaped' ORDER BY id ASC");
        if (!is_array($existing))
            return (["ok" => false, "error" => "CannotCreateMedal", "details" => $codename]);
        return (["ok" => true, "id" => (int)$existing["id"], "created" => false]);
    }

    $id = (int)$Database->insert_id;
    $origin = trim((string)$source);
    add_log(CREATIVE_OPERATION,
        "Automatically created medal '$codename'".($origin === "" ? "." : " from $origin."), 1);
    return (["ok" => true, "id" => $id, "created" => true]);
}
