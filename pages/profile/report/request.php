<?php

/* Le PDF DocBuilder remplace définitivement l'ancien bulletin HTML. */

require_once (__DIR__."/../../../tools/document_print.php");
require_once (__DIR__."/../../../tools/report_card.php");

function report_card_error($message, $status = 500)
{
    http_response_code($status);
    header("Content-Type: text/plain; charset=UTF-8");
    echo $message;
    exit ;
}

function report_card_counter($value)
{
    return (is_object($value) && method_exists($value, "get") ? (int)$value->get() : 0);
}

function report_card_activity_counts($activity)
{
    $registered = !empty($activity->registered);
    return ([
        "attendance" => $registered
            ? report_card_counter($activity->present) + report_card_counter($activity->late) : 0,
        "non_attendance" => $registered ? report_card_counter($activity->missing) : 0,
        "unregistered" => $registered ? 0 : 1,
    ]);
}

function report_card_work_counts($activity)
{
    $registered = !empty($activity->registered);
    return ([
        "delivered" => $registered ? report_card_counter($activity->work) : 0,
        "undelivered" => $registered ? report_card_counter($activity->nowork) : 0,
        "unregistered" => $registered ? 0 : 1,
    ]);
}

function report_card_add_counts(array &$target, array $source)
{
    foreach ($source as $key => $value)
        $target[$key] += (int)$value;
}

function report_card_first_monday_after($timestamp)
{
    if ($timestamp === NULL)
        return (date("d/m/Y"));
    $date = (new DateTimeImmutable)->setTimestamp((int)$timestamp);
    // Toujours un lundi strictement postérieur : si la période finit un
    // lundi, la date d'édition est donc le lundi de la semaine suivante.
    $days = 8 - (int)$date->format("N");
    return ($date->modify("+".$days." days")->format("d/m/Y"));
}

function report_card_user_identity($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ("");

    $person = function_exists("document_context_person")
        ? document_context_person($id_user) : NULL;
    if (is_array($person))
    {
        $identity = trim((string)($person["identity"] ?? ($person["name"] ?? "")));
        if ($identity != "")
            return ($identity);
    }

    $user = fetch_user($id_user);
    if (!$user->is_error())
        return (trim((string)document_builder_name($user->value)));
    return ("");
}

function report_card_cycle_manager_names(array $cycle_ids)
{
    if (!count($cycle_ids))
        return ([]);
    $rows = db_select_all("
        COALESCE(direct_user.id, laboratory_user.id) as id
        FROM cycle_teacher
        LEFT JOIN user as direct_user
          ON direct_user.id = cycle_teacher.id_user
         AND direct_user.authority != -1
         AND direct_user.profile_status != 'jury'
        LEFT JOIN user_laboratory
          ON user_laboratory.id_laboratory = cycle_teacher.id_laboratory
         AND user_laboratory.authority >= 3
        LEFT JOIN user as laboratory_user
          ON laboratory_user.id = user_laboratory.id_user
         AND laboratory_user.authority != -1
         AND laboratory_user.profile_status != 'jury'
        WHERE cycle_teacher.id_cycle IN (".implode(",", array_map("intval", $cycle_ids)).")
          AND (direct_user.id IS NOT NULL OR laboratory_user.id IS NOT NULL)
        ORDER BY cycle_teacher.id ASC,
                 user_laboratory.authority DESC,
                 COALESCE(direct_user.id, laboratory_user.id) ASC
    ");
    $identities = [];
    foreach ($rows as $row)
    {
        $identity = report_card_user_identity((int)$row["id"]);
        if ($identity != "" && !in_array($identity, $identities, true))
            $identities[] = $identity;
    }
    return ($identities);
}

function report_card_cycle_manager_context(array $cycle_ids)
{
    $identities = report_card_cycle_manager_names($cycle_ids);
    return ([
        "identity" => implode(", ", $identities),
        "name" => implode(", ", $identities),
        "role" => "Responsable de cycle",
    ]);
}

function report_card_laboratory_teacher_names($id_laboratory)
{
    $id_laboratory = (int)$id_laboratory;
    if ($id_laboratory <= 0)
        return ([]);

    // Dans un laboratoire, seul le niveau "prof" (2) est affiché comme
    // enseignant de matière : ni les assistants, ni le chef de laboratoire.
    $teacher_level = defined("TEACHER") ? (int)TEACHER : 2;
    $rows = db_select_all("
        user.id
        FROM user_laboratory
        INNER JOIN user ON user.id = user_laboratory.id_user
        WHERE user_laboratory.id_laboratory = ".$id_laboratory."
          AND user_laboratory.authority = ".$teacher_level."
          AND user.deleted IS NULL
          AND user.authority >= 0
          AND user.profile_status = 'member'
        ORDER BY user.id ASC
    ");
    $teachers = [];
    foreach ($rows as $row)
    {
        $identity = report_card_user_identity((int)$row["id"]);
        if ($identity != "" && !in_array($identity, $teachers, true))
            $teachers[] = $identity;
    }
    return ($teachers);
}

function report_card_module_data($module, array $display = [], array $fallback_teachers = [])
{
    $activities = ["attendance" => 0, "non_attendance" => 0, "unregistered" => 0];
    $exams = ["attendance" => 0, "non_attendance" => 0, "unregistered" => 0];
    $work = ["delivered" => 0, "undelivered" => 0, "unregistered" => 0];

    foreach ($module->sublayer as $activity)
    {
        if ((int)$activity->type_type === PROJECT_ACTIVITY || $activity->pickup_date !== NULL)
        {
            report_card_add_counts($work, report_card_work_counts($activity));
            continue ;
        }
        $is_exam = $activity->full_activity !== NULL
            && (int)$activity->full_activity->validation === FullActivity::GRADE_VALIDATION;
        if ($is_exam)
            report_card_add_counts($exams, report_card_activity_counts($activity));
        else
            report_card_add_counts($activities, report_card_activity_counts($activity));
    }

    $grades = ["E", "D", "C", "B", "A"];
    $grade = "—";
    if ($module->registered && $module->done_date !== NULL
        && date_to_timestamp($module->done_date) <= now())
    {
        // Le bonus peut faire monter le grade interne à 5 (A + bonus).
        // Comme ailleurs dans l'interface et pour le calcul des flammes, le
        // bulletin doit alors afficher A : il n'existe pas de grade au-dessus.
        $grade_index = (int)$module->grade;
        if ($grade_index >= 0)
            $grade = $grades[min(4, $grade_index)];
    }

    $teachers = [];
    foreach (fetch_teacher((int)$module->id, false, "activity", true) as $teacher)
    {
        if (!empty($teacher["id_user"]))
        {
            $name = report_card_user_identity((int)$teacher["id_user"]);
            if ($name != "" && !in_array($name, $teachers, true))
                $teachers[] = $name;
            continue ;
        }

        if (!empty($teacher["id_laboratory"]))
            foreach (report_card_laboratory_teacher_names((int)$teacher["id_laboratory"]) as $name)
                if (!in_array($name, $teachers, true))
                    $teachers[] = $name;
    }

    // Une matière sans enseignant explicite reste attribuée au responsable du
    // cycle auquel elle appartient. Cela couvre aussi un labo enseignant qui
    // n'aurait aucun membre au niveau "prof".
    if (!count($teachers))
        foreach ($fallback_teachers as $name)
        {
            $name = trim((string)$name);
            if ($name != "" && !in_array($name, $teachers, true))
                $teachers[] = $name;
        }

    return ([
        "code" => (string)($display["code"] ?? $module->codename),
        "name" => (string)($display["name"] ??
            (trim((string)$module->name) != "" ? $module->name : $module->codename)),
        "teachers" => implode(", ", $teachers),
        "comment" => trim((string)$module->commentaries),
        "grade" => $grade,
        "flames" => [
            "min" => (int)$module->credit_d,
            "max" => (int)$module->credit_a,
            "result" => (int)$module->get_credit(),
        ],
        "activities" => $activities,
        "exam" => $exams,
        "work" => $work,
    ]);
}

$requested_cycles = array_values(array_unique(array_filter(array_map("intval",
    explode(",", (string)($_GET["id_cycle"] ?? ""))), function($id) {
        return ($id > 0);
    })));
if (!count($requested_cycles))
    report_card_error("Aucun trimestre n'a été sélectionné.", 400);

$cycles = [];
foreach ($user->sublayer as $cycle)
    if (in_array((int)$cycle->id, $requested_cycles, true))
        $cycles[(int)$cycle->id] = $cycle;
if (count($cycles) != count($requested_cycles))
    report_card_error("Le trimestre demandé n'appartient pas à cet élève.", 403);

$first_cycle = $cycles[$requested_cycles[0]];
$modules = [];
$module_cycle_ids = [];
$comments = [];
$start = NULL;
$end = NULL;
foreach ($cycles as $cycle)
{
    if ($cycle->first_day !== NULL)
    {
        $timestamp = date_to_timestamp($cycle->first_day);
        $start = $start === NULL ? $timestamp : min($start, $timestamp);
    }
    if ($cycle->last_day !== NULL)
    {
        $timestamp = date_to_timestamp($cycle->last_day);
        $end = $end === NULL ? $timestamp : max($end, $timestamp);
    }
    $comment = trim((string)$cycle->commentaries);
    if ($comment != "" && !in_array($comment, $comments, true))
        $comments[] = $comment;
    foreach ($cycle->sublayer as $module)
        // Le profil peut exposer les matières du cycle même lorsque l'élève
        // n'y est pas inscrit. Elles ne doivent ni apparaître dans son bulletin
        // ni contribuer aux indicateurs calculés à partir de $modules.
        if (empty($module->hidden) && !empty($module->registered))
        {
            $id_module = (int)$module->id;
            $id_cycle = (int)$cycle->id;
            $modules[$id_module] = $module;
            if (!isset($module_cycle_ids[$id_module]))
                $module_cycle_ids[$id_module] = [];
            if (!in_array($id_cycle, $module_cycle_ids[$id_module], true))
                $module_cycle_ids[$id_module][] = $id_cycle;
        }
}
uasort($modules, function($a, $b) {
    $aname = trim((string)$a->name) != "" ? $a->name : $a->codename;
    $bname = trim((string)$b->name) != "" ? $b->name : $b->codename;
    return (strnatcasecmp($aname, $bname));
});

// Un bulletin décrit le programme pédagogique : afficher les identifiants et
// intitulés des templates tant que le lien au template est encore actif, et
// seulement sinon ceux des instances datées.
$cycle_display = [];
$cycle_objective = 0;
$cycle_ids = array_map("intval", array_keys($cycles));
if (count($cycle_ids))
{
    $rows = db_select_all("
        cycle.id,
        cycle.objective as objective,
        COALESCE(NULLIF(template.codename, ''), cycle.codename) as display_code,
        COALESCE(
            NULLIF(template.{$Language}_name, ''),
            NULLIF(cycle.{$Language}_name, ''),
            NULLIF(template.codename, ''),
            cycle.codename
        ) as display_name
        FROM cycle
        LEFT JOIN cycle as template
          ON template.id = cycle.id_template
         AND template.deleted IS NULL
        WHERE cycle.id IN (".implode(",", $cycle_ids).")
    ");
    foreach ($rows as $row)
    {
        $cycle_display[(int)$row["id"]] = [
            "code" => trim((string)$row["display_code"]),
            "name" => trim((string)$row["display_name"]),
        ];
        $cycle_objective += max(0, (int)$row["objective"]);
    }
}

$module_display = [];
$module_ids = array_map("intval", array_keys($modules));
if (count($module_ids))
{
    $rows = db_select_all("
        activity.id,
        COALESCE(NULLIF(template.codename, ''), activity.codename) as display_code,
        CASE
          WHEN activity.template_link = 1 THEN COALESCE(
              NULLIF(template.{$Language}_name, ''),
              NULLIF(activity.{$Language}_name, ''),
              NULLIF(template.codename, ''),
              activity.codename
          )
          ELSE COALESCE(
              NULLIF(activity.{$Language}_name, ''),
              NULLIF(template.codename, ''),
              activity.codename
          )
        END as display_name
        FROM activity
        LEFT JOIN activity as template
          ON template.id = activity.id_template
         AND template.deleted IS NULL
        WHERE activity.id IN (".implode(",", $module_ids).")
    ");
    foreach ($rows as $row)
        $module_display[(int)$row["id"]] = [
            "code" => trim((string)$row["display_code"]),
            "name" => trim((string)$row["display_name"]),
        ];
}

$student_response = document_builder_full_student((int)$user->id);
if ($student_response->is_error())
    report_card_error("Impossible de charger les informations administratives de l'élève.");
$student = $student_response->value;
$financial = document_builder_financial_responsible($student);
$school = document_builder_student_school($student);

$student_context = document_builder_person_context($student);
$financial_context = document_builder_person_context($financial);
$school_context = document_builder_school_context($school);
$cycle_manager_context = report_card_cycle_manager_context($cycle_ids);
$cycle_manager_names = [];
foreach ($cycle_ids as $id_cycle)
    $cycle_manager_names[$id_cycle] = report_card_cycle_manager_names([$id_cycle]);
$director_context = function_exists("document_context_director_for_school")
    ? document_context_director_for_school((int)($school_context["id"] ?? 0)) : NULL;
if (!is_array($director_context))
    $director_context = [];
$director_context["role"] = "Directeur de l'établissement";
$current_flames = array_sum(array_map(function($module) {
    return ((int)$module->get_credit());
}, $modules));
$all_flames = isset($user->acquired_credit) ? (int)$user->acquired_credit : $current_flames;
$cycle_codes = [];
$cycle_names = [];
$cycle_period_names = [];
$cycle_period_name_field = $Language == "en" ? "user_cycle_en_name" : "user_cycle_fr_name";
foreach ($cycles as $cycle)
{
    $display = $cycle_display[(int)$cycle->id] ?? [];
    $code = trim((string)($display["code"] ?? $cycle->codename));

    // FullProfile donne priorité au nom porté par user_cycle. Lorsqu'une
    // surcharge existe pour cet élève, elle doit donc gagner aussi sur le
    // pretty name du template utilisé habituellement par le bulletin.
    $student_period_name = trim((string)($cycle->{$cycle_period_name_field} ?? ""));
    if ($student_period_name != "")
        $name = trim((string)$cycle->name);
    else
        $name = trim((string)($display["name"] ?? $cycle->name));

    if ($code != "" && !in_array($code, $cycle_codes, true))
        $cycle_codes[] = $code;
    if ($name != "" && $name != $code && !in_array($name, $cycle_names, true))
        $cycle_names[] = $name;

    if ($student_period_name != "" && !in_array($name, $cycle_period_names, true))
        $cycle_period_names[] = $name;
}
$report_modules = [];
foreach ($modules as $module)
{
    $fallback_teachers = [];
    foreach ($module_cycle_ids[(int)$module->id] ?? [] as $id_cycle)
        foreach ($cycle_manager_names[$id_cycle] ?? [] as $name)
            if (!in_array($name, $fallback_teachers, true))
                $fallback_teachers[] = $name;

    $report_modules[] = report_card_module_data(
        $module,
        $module_display[(int)$module->id] ?? [],
        $fallback_teachers
    );
}

$context = [
    "school" => $school_context,
    "company" => $school_context,
    "people" => [
        "student" => array_merge($student_context, [
            "role" => "Élève",
            "previous_flames" => max(0, $all_flames - $current_flames),
        ]),
        "tutor" => array_merge($financial_context, [
            "role" => "Responsable financier",
        ]),
        "cycle_manager" => $cycle_manager_context,
        "director" => $director_context,
    ],
    "cycle" => [
        "code" => implode(" + ", $cycle_codes),
        "name" => implode(" + ", $cycle_names),
        "display_name" => implode(" + ", $cycle_period_names),
        "year" => (int)floor((int)$first_cycle->cycle / 4) + 1,
        "trimester" => (int)$first_cycle->cycle % 4 + 1,
        "start" => $start === NULL ? "" : date("d/m/Y", $start),
        "end" => $end === NULL ? "" : date("d/m/Y", $end),
        "comment" => implode("\n\n", $comments),
        "flames" => $current_flames,
        "objective" => $cycle_objective,
        "total_flames" => $all_flames,
        "modules" => $report_modules,
    ],
    "generation" => [
        "date" => report_card_first_monday_after($end),
        "datetime" => date("d/m/Y H:i"),
    ],
];

$model = document_builder_find_letter_model("bulletin.dab", $Language);
if ($model === NULL)
    report_card_error("Le modèle res/docs/".$Language."/bulletin.dab est introuvable.");

$period_key = report_card_period_key($first_cycle, $requested_cycles);
$base = report_card_storage_directory($student["codename"]);
$output = report_card_canonical_path($student["codename"], $period_key);
$existing_reports = report_card_existing_paths($student["codename"], $period_key, $first_cycle);
$replace_confirmed = isset($_POST["replace_report_card"])
    && (string)$_POST["replace_report_card"] === "1";
if (count($existing_reports) && !$replace_confirmed)
    report_card_error("Un bulletin existe déjà pour ce trimestre. Confirmez son remplacement depuis le profil de l'élève.", 409);

$directory = new_directory($output);
if ($directory->is_error())
    report_card_error("Impossible de préparer le dossier de stockage du bulletin.");
$pending_output = tempnam($base, ".bulletin_".$period_key."_");
if ($pending_output === false)
    report_card_error("Impossible de préparer le fichier temporaire du bulletin.");
@unlink($pending_output);
$pending_output .= ".pdf";

$temporary = tempnam(sys_get_temp_dir(), "infosphere_report_");
if ($temporary === false)
    report_card_error("Impossible de créer le contexte temporaire du bulletin.");
@unlink($temporary);
$temporary .= ".dab";

$generated = generate_dabsic($context, $temporary);
if ($generated->is_error())
{
    @unlink($temporary);
    report_card_error("Impossible de préparer les données du bulletin.");
}
$result = build_document_from_parts($pending_output, [
    ["file" => $model],
    ["file" => $temporary],
], array_merge(document_builder_model_dirs($Language), [dirname($model)."/"]));
@unlink($temporary);
if ($result->is_error() || !is_file($pending_output))
{
    @unlink($pending_output);
    report_card_error("La génération du bulletin a échoué : ".
        (trim((string)($result->details ?? "")) != "" ? $result->details : "erreur DocBuilder"));
}

// Do not destroy the previous bulletin until DocBuilder has produced a valid
// replacement. rename() is atomic here because both files live in the same
// private user directory.
if (!@rename($pending_output, $output))
{
    @unlink($pending_output);
    report_card_error("Le nouveau bulletin a été généré, mais le remplacement de l'ancien fichier a échoué.");
}
foreach ($existing_reports as $old_report)
    if ($old_report !== $output && is_file($old_report))
        @unlink($old_report);

$recipient_label = trim((string)($financial_context["identity"] ?? ""));
if ($recipient_label == "")
    $recipient_label = trim((string)($student_context["identity"] ?? $student["codename"]));
$queued = document_print_queue_file($output, "Bulletin — ".implode(" + ", $cycle_codes), [
    "type" => "cycle",
    "owner_user_id" => (int)$student["id"],
    "school_id" => (int)($school["id"] ?? 0),
    "cycle_id" => (int)$first_cycle->id,
    "recipient_user_id" => (int)($financial["id"] ?? $student["id"]),
    "recipient_label" => $recipient_label,
    "source_key" => report_card_source_key((int)$student["id"], $period_key),
]);
if ($queued->is_error())
    report_card_error("Le bulletin a été généré, mais son ajout aux documents à imprimer a échoué : ".
        (trim((string)($queued->details ?? "")) != "" ? $queued->details : "erreur inconnue"));

// A regenerated bulletin supersedes any older pending print obligation for
// this same temporal trimester, including the former cycle-id based key.
$new_print_task_id = (int)($queued->value["id"] ?? 0);
document_print_expire_pending_sources(
    (int)$student["id"],
    [
        report_card_source_key((int)$student["id"], $period_key),
        "report-card:".(int)$student["id"].":".implode("-", $requested_cycles),
    ],
    $new_print_task_id
);

header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=\"".basename($output)."\"");
header("Content-Length: ".filesize($output));
readfile($output);
exit ;
