<?php

function campaign_origin_action_ids()
{
    return ([42, 43, 44, 45, 46, 47, 48, 49, 51]);
}

function campaign_origin_action_sql()
{
    return (implode(", ", campaign_origin_action_ids()));
}

function campaign_date_is_valid($date)
{
    return (is_string($date) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date));
}

function campaign_date_cmp($a, $b)
{
    return (strcmp($a, $b));
}

function campaign_fetch_all($with_deleted = false)
{
    $deleted = $with_deleted ? "" : " WHERE deleted IS NULL ";

    return (db_select_all("*
        FROM campaign
        $deleted
        ORDER BY start_date DESC, end_date DESC, id DESC
    "));
}

function campaign_fetch_one($id)
{
    $id = (int)$id;
    return (db_select_one("*
        FROM campaign
        WHERE id = $id
    "));
}

function campaign_fetch_prospect($campaign)
{
    global $Database;

    $start = $Database->real_escape_string($campaign["start_date"]);
    $end = $Database->real_escape_string($campaign["end_date"]);
    $origin_ids = campaign_origin_action_sql();

    return (db_select_all("
        user.id,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        user.phone,
        user.registration_date,
        user.password,
        origin.id_action AS origin_action,
        origin.action_name AS origin_name,
        terminal.consequence AS terminal_consequence,
        terminal.action_name AS terminal_name
        FROM user
        LEFT JOIN (
            SELECT
                prospection.id_user,
                prospection.id_action,
                action.name AS action_name
            FROM prospection
            LEFT JOIN action ON action.id = prospection.id_action
            INNER JOIN (
                SELECT id_user, MIN(id) AS id
                FROM prospection
                WHERE id_action IN ($origin_ids)
                GROUP BY id_user
            ) first_origin ON first_origin.id = prospection.id
            WHERE prospection.id_action IN ($origin_ids)
        ) origin ON origin.id_user = user.id
        LEFT JOIN (
            SELECT
                prospection.id_user,
                action.consequence,
                action.name AS action_name
            FROM prospection
            LEFT JOIN action ON action.id = prospection.id_action
            INNER JOIN (
                SELECT prospection.id_user, MAX(prospection.id) AS id
                FROM prospection
                LEFT JOIN action ON action.id = prospection.id_action
                WHERE action.consequence IN ('lost', 'transformed')
                GROUP BY prospection.id_user
            ) last_terminal ON last_terminal.id = prospection.id
            WHERE action.consequence IN ('lost', 'transformed')
        ) terminal ON terminal.id_user = user.id
        WHERE user.prospect = 1
        AND user.registration_date >= '$start 00:00:00'
        AND user.registration_date <= '$end 23:59:59'
        ORDER BY user.registration_date ASC, user.codename ASC
    "));
}

function campaign_prospect_status($prospect)
{
    if ($prospect["terminal_consequence"] == "transformed" || trim($prospect["password"] ?? "") != "")
	return ("transformed");
    if ($prospect["terminal_consequence"] == "lost")
	return ("lost");
    return ("active");
}

function campaign_status_label($status)
{
    return ([
        "transformed" => "Transformé",
        "lost" => "Refusé / perdu",
        "active" => "En cours",
    ][$status] ?? $status);
}

function campaign_build_stats($prospects)
{
    $stats = [
        "total" => 0,
        "transformed" => 0,
        "lost" => 0,
        "active" => 0,
        "origin" => [],
    ];

    foreach ($prospects as &$prospect)
    {
        $status = campaign_prospect_status($prospect);
        $origin = trim($prospect["origin_name"] ?? "");

        if ($origin == "")
            $origin = "Unknown";

        $prospect["campaign_status"] = $status;
        $prospect["campaign_origin"] = $origin;
        $stats["total"] += 1;
        $stats[$status] += 1;

        if (!isset($stats["origin"][$origin]))
            $stats["origin"][$origin] = [
                "origin" => $origin,
                "total" => 0,
                "transformed" => 0,
                "lost" => 0,
                "active" => 0,
            ];
        $stats["origin"][$origin]["total"] += 1;
        $stats["origin"][$origin][$status] += 1;
    }

    uasort($stats["origin"], function($a, $b) {
        if ($a["total"] == $b["total"])
            return (strcmp($a["origin"], $b["origin"]));
        return ($b["total"] - $a["total"]);
    });

    return ($stats);
}

function campaign_rate($value, $total)
{
    if ($total <= 0)
        return ("0 %");
    return (round($value * 100 / $total, 1)." %");
}

function campaign_display_date($date)
{
    return (datex("d/m/Y", date_to_timestamp($date)));
}
