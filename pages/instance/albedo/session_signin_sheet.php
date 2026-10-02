<?php

// Rattrape également une exécution Albedo manquée : seules les sessions sans
// PDF enregistré sont candidates. Le verrou du générateur évite les doublons.
if (!session_signin_schema_ready())
    return ;
foreach (db_select_all("
    session.id, session.signin_morning_end, session.signin_afternoon_start
    FROM session
    INNER JOIN activity ON activity.id = session.id_activity
    WHERE session.deleted IS NULL AND activity.deleted IS NULL
      AND session.end_date IS NOT NULL AND session.end_date <= NOW()
      AND session.signin_generated_at IS NULL
    ORDER BY session.end_date DESC
    LIMIT 1
") as $session_row)
{
    $result = session_signin_pdf_generate(
        (int)$session_row["id"],
        0,
        (string)($session_row["signin_morning_end"] ?: "13:00"),
        (string)($session_row["signin_afternoon_start"] ?: "14:00")
    );
    if (!$result["ok"])
        add_log(REPORT, "Session sign-in sheet #".$session_row["id"].": ".$result["error"]);
}
