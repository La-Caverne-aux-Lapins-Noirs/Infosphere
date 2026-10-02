<?php

function student_log_export_date($value)
{
    if (!is_string($value))
        throw new InvalidArgumentException("Date invalide (AAAA-MM-JJ attendu).");
    if ($value === "")
        return (NULL);
    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
    if (!$date || $date->format("Y-m-d") !== $value)
        throw new InvalidArgumentException("Date invalide (AAAA-MM-JJ attendu).");
    return ($date);
}

function student_log_export_rows($id, $start, $end)
{
    $id = (int)$id;
    $where = "";
    if ($start !== NULL)
        $where .= " AND log_date >= '".$start->format("Y-m-d")." 00:00:00'";
    if ($end !== NULL)
        $where .= " AND log_date < '".$end->modify("+1 day")->format("Y-m-d")." 00:00:00'";
    // Same active categories as get_student_log() and get_week_average().
    $types = user_log_sql_type_list(user_log_valid_activity_types());
    return (db_select_all("
        DATE(log_date) AS day, SUM(COALESCE(duration, 0)) AS seconds
        FROM user_log WHERE id_user = $id AND type IN ($types)
        $where GROUP BY DATE(log_date) ORDER BY DATE(log_date)
    "));
}

function write_student_log_csv($stream, $rows)
{
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, ["Date", "Heures"], ";", '"', "\\");
    foreach ($rows as $row)
        fputcsv($stream, [$row["day"], number_format($row["seconds"] / 3600, 4, ",", "")], ";", '"', "\\");
}
