<?php

require_once (__DIR__."/language.php");
require_once (__DIR__."/tools/index.php");
load_constants();
require_once (__DIR__."/login/index.php");
require_once (__DIR__."/tools/student_log_export.php");

header("Cache-Control: private, no-store");
header("X-Content-Type-Options: nosniff");

$id = filter_var($_GET["id"] ?? NULL, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$id)
{
    http_response_code(400);
    exit("Identifiant étudiant invalide.");
}
if (!can_export_student_logs($id))
{
    http_response_code(403);
    exit("Export réservé à l'administrateur et au directeur de l'école de l'élève.");
}
try
{
    $start = student_log_export_date($_GET["start"] ?? "");
    $end = student_log_export_date($_GET["end"] ?? "");
    if ($start !== NULL && $end !== NULL && $start > $end)
        throw new InvalidArgumentException("La date de début doit précéder la date de fin.");
}
catch (InvalidArgumentException $error)
{
    http_response_code(400);
    exit($error->getMessage());
}
$rows = student_log_export_rows($id, $start, $end);
header("Content-Type: text/csv; charset=UTF-8");
header('Content-Disposition: attachment; filename="logs-etudiant-'.$id.'.csv"');
$stream = fopen("php://output", "w");
write_student_log_csv($stream, $rows);
fclose($stream);
exit;
