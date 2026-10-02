<?php

require_once (__DIR__."/student_log.php");
require_once (__DIR__."/halfday_presence.php");
require_once (__DIR__."/document_context.php");
require_once (__DIR__."/document_tasks.php");
require_once (__DIR__."/document_workflow.php");

function attendance_register_status_marker($value)
{
    if ($value === NULL || trim((string)$value) == "")
	return ("");
    if (is_numeric($value))
    {
	$value = (int)$value;
	if ($value == 1)
	    return ("P");
	if ($value == -1)
	    return ("R");
	if ($value == -2)
	    return ("A");
	return ("");
    }

    $value = strtolower(trim((string)$value));
    $value = strtr($value, [
	"é" => "e", "è" => "e", "ê" => "e", "ë" => "e",
	"à" => "a", "â" => "a", "ä" => "a", "ç" => "c",
    ]);
    if (in_array($value, ["p", "present", "presence", "yes", "oui", "true"], true))
	return ("P");
    if (in_array($value, ["r", "late", "retard"], true))
	return ("R");
    if (in_array($value, ["a", "absent", "absence", "missing", "no", "non", "false"], true))
	return ("A");
    return ("");
}

function attendance_register_truthy($value)
{
    if (is_bool($value))
	return ($value);
    if (is_numeric($value))
	return ((int)$value != 0);
    return (in_array(strtolower(trim((string)$value)), ["true", "yes", "oui", "on"], true));
}

function attendance_register_load_identity($file)
{
    if (!is_file($file))
	return ([]);
    $configuration = load_configuration($file);
    if (!is_object($configuration) || $configuration->is_error() || !is_array($configuration->value))
	return ([]);
    return ($configuration->value);
}

function attendance_register_person_context(array $user)
{
    global $Configuration;

    $id_user = (int)($user["id"] ?? 0);
    $context = [];
    if ($id_user > 0)
    {
	if (function_exists("user_identity_write_identity_dabsic"))
	    user_identity_write_identity_dabsic($id_user);
	if (function_exists("document_context_person"))
	{
	    $tmp = document_context_person($id_user);
	    if (is_array($tmp))
		$context = $tmp;
	}
    }

    $codename = (string)($user["codename"] ?? ($context["codename"] ?? ""));
    if ($codename != "")
    {
	$file = $Configuration->UsersDir($codename)."admin/identity.dab";
	$context = array_replace_recursive($context, attendance_register_load_identity($file));
    }
    $context = array_replace($user, $context);

    $identity = trim((string)($context["identity"] ?? ($context["Identity"] ?? "")));
    if ($identity == "")
	$identity = trim((string)($context["first_name"] ?? "")." ".(string)($context["family_name"] ?? ""));
    if ($identity == "")
	$identity = $codename;

    $context["id"] = $id_user;
    $context["student_id"] = $id_user;
    $context["codename"] = $codename;
    $context["identity"] = $identity;
    $context["name"] = $identity;
    unset($context["signature"], $context["Signature"], $context["initials"], $context["Initials"]);
    return (dabsic_pascalcase_array($context));
}

function attendance_register_school_context(array $school)
{
    global $Configuration;

    $id_school = (int)($school["id"] ?? $school["id_school"] ?? 0);
    $full = $id_school > 0 ? fetch_school($id_school) : $school;
    if (is_object($full) && $full->is_error())
	$full = $school;
    if (!is_array($full))
	$full = $school;

    if (function_exists("refresh_school"))
	refresh_school($full);
    $context = function_exists("document_context_school") && $id_school > 0
	? document_context_school($id_school) : $full;
    if (!is_array($context))
	$context = $full;

    $codename = (string)($full["codename"] ?? ($context["codename"] ?? ""));
    if ($codename != "")
    {
	$file = $Configuration->SchoolsDir($codename)."identity.dab";
	$context = array_replace_recursive($context, attendance_register_load_identity($file));
    }

    // Le document d'émargement doit afficher le lieu réel de formation et non
    // le siège social de l'organisation. school_address est la valeur brute de
    // school.address ; contrairement à address, elle ne retombe pas sur
    // organization.address lorsqu'elle est vide.
    $context["training_address"] = trim((string)($full["school_address"] ?? ""));
    foreach (school_activity_document_fields($full) as $field => $value)
	$context[$field] = trim((string)$value);

    $directors = $full["director"] ?? [];
    if (is_array($directors) && isset($directors[0]) && is_array($directors[0]))
	$context["contact"] = attendance_register_person_context($directors[0]);

    return (dabsic_pascalcase_array($context));
}

function attendance_register_person_label(array $person)
{
    $name = trim((string)($person["first_name"] ?? "")." ".(string)($person["family_name"] ?? ""));
    if ($name == "")
        $name = trim((string)($person["nickname"] ?? ""));
    if ($name == "")
        $name = trim((string)($person["codename"] ?? ""));
    return ($name);
}

function attendance_register_activity_type_label($codename)
{
    $codename = strtolower(trim((string)$codename));
    $types = [
        "practicalwork" => "TP",
        "project" => "TP",
        "rush" => "TP",
        "miniproject" => "TP",
        "product" => "TP",
        "exercise" => "TP",
        "pickableexercise" => "TP",
        "challenge" => "Colle",
        "exam" => "Exam",
        "mcq" => "Exam",
        "recode" => "Exam",
        "marathon" => "Exam",
        "demomeeting" => "Soutenance",
        "dailymeeting" => "Suivi",
        "planificationmeeting" => "Suivi",
        "retrospectivemeeting" => "Suivi",
        "learningday" => "TP",
        "class" => "Cours",
        "debate" => "Cours",
        "misc" => "Cours",
    ];
    return ($types[$codename] ?? "Cours");
}

function attendance_register_person_context_key(array $person)
{
    $id = (int)($person["Id"] ?? ($person["id"] ?? 0));
    if ($id > 0)
        return ("user:".$id);
    $codename = strtolower(trim((string)($person["Codename"] ?? ($person["codename"] ?? ""))));
    if ($codename != "")
        return ("codename:".$codename);
    $identity = strtolower(trim((string)($person["Identity"] ?? ($person["identity"] ?? ""))));
    return ("identity:".$identity);
}

function attendance_register_laboratory_chiefs($id_laboratory)
{
    $id_laboratory = (int)$id_laboratory;
    if ($id_laboratory <= 0)
        return ([]);
    return (db_select_all("
        user.*
        FROM user_laboratory
        INNER JOIN user ON user.id = user_laboratory.id_user
        WHERE user_laboratory.id_laboratory = $id_laboratory
          AND user_laboratory.authority >= 3
          AND user.deleted IS NULL
          AND user.profile_status = 'member'
        ORDER BY user_laboratory.authority DESC,
                 COALESCE(NULLIF(user.family_name, ''), NULLIF(user.use_name, ''), user.codename),
                 user.first_name,
                 user.codename
    "));
}

function attendance_register_activity_trainers($id_session, $id_activity, array $fallback, array &$cache)
{
    $key = (int)$id_session.":".(int)$id_activity;
    if (isset($cache[$key]))
        return ($cache[$key]);

    $people = [];
    if (function_exists("fetch_session_teachers"))
    {
        foreach (fetch_session_teachers((int)$id_session, false, true, (int)$id_activity) as $teacher)
        {
            if (!is_array($teacher))
                continue ;
            $users = [];
            $role = "Formateur";
            if ((int)($teacher["id_user"] ?? 0) > 0)
            {
                $id_user = (int)$teacher["id_user"];
                $user = db_select_one("* FROM user WHERE id = $id_user AND deleted IS NULL");
                if (is_array($user))
                    $users[] = $user;
            }
            else if ((int)($teacher["id_laboratory"] ?? 0) > 0)
            {
                $id_laboratory = (int)$teacher["id_laboratory"];
                $laboratory_name = trim((string)($teacher["name"] ?? ($teacher["codename"] ?? "")));
                $laboratory_name = ltrim($laboratory_name, "#");
                $role = $laboratory_name == ""
                    ? "Chef de laboratoire"
                    : "Chef du laboratoire ".$laboratory_name;
                $users = attendance_register_laboratory_chiefs($id_laboratory);
            }

            foreach ($users as $user)
            {
                if (!is_array($user) || attendance_register_person_label($user) == "")
                    continue ;
                $person = attendance_register_person_context($user);
                $person["Role"] = $role;
                $people[attendance_register_person_context_key($person)] = $person;
            }
        }
    }

    if (!count($people))
    {
        $person = attendance_register_person_context($fallback);
        $person["Role"] = "Responsable de formation";
        $people[attendance_register_person_context_key($person)] = $person;
    }

    $names = [];
    foreach ($people as $person)
    {
        $name = trim((string)($person["Identity"] ?? ($person["Name"] ?? "")));
        if ($name != "")
            $names[] = $name;
    }
    return ($cache[$key] = [
        "label" => implode(", ", array_values(array_unique($names))),
        "people" => array_values($people),
    ]);
}

function attendance_register_empty_totals()
{
    return ([
        "PlannedDuration" => 0,
        "PresenceDuration" => 0,
        "LateDuration" => 0,
        "AbsenceDuration" => 0,
        "JustifiedAbsenceDuration" => 0,
        "UnjustifiedAbsenceDuration" => 0,
        "UndeterminedDuration" => 0,
        "AttendanceRate" => 0,
    ]);
}

function attendance_register_finalize_totals(array $totals)
{
    $totals["JustifiedAbsenceDuration"] = min(
        $totals["JustifiedAbsenceDuration"],
        $totals["AbsenceDuration"]
    );
    $totals["UnjustifiedAbsenceDuration"] = max(
        0,
        $totals["AbsenceDuration"] - $totals["JustifiedAbsenceDuration"]
    );

    // Toute demi-journée échue doit recevoir une conclusion exploitable sur
    // une feuille officielle. L'absence de donnée technique n'est donc plus
    // exposée comme un état distinct : elle est assimilée à une absence.
    $totals["UndeterminedDuration"] = 0;
    return ($totals);
}

function attendance_register_cycle_context(array $cycle)
{
    $cycle_number = max(0, (int)($cycle["cycle"] ?? 0));
    $current_year = max(1, min(5, intdiv($cycle_number, 4) + 1));
    $trimester = ($cycle_number % 4) + 1;
    $promotion_year = "";

    // Un cycle représente un trimestre de douze semaines. On extrapole donc
    // la fin du vingtième trimestre à partir du début du cycle courant, puis
    // on utilise l'année du jury final comme promotion de sortie.
    $raw_first_day = substr((string)($cycle["first_day"] ?? ""), 0, 10);
    $cycle_start = DateTimeImmutable::createFromFormat("!Y-m-d", $raw_first_day);
    $date_errors = DateTimeImmutable::getLastErrors();
    if ($cycle_start !== false &&
        ($date_errors === false || (!$date_errors["warning_count"] && !$date_errors["error_count"])))
    {
        $remaining_trimesters = max(1, 20 - $cycle_number);
        $jury_date = $cycle_start
            ->modify("+".($remaining_trimesters * 12)." weeks")
            ->modify("-1 day");
        $promotion_year = (int)$jury_date->format("Y");
    }

    return (dabsic_pascalcase_array([
	"id" => (int)$cycle["id"],
	"codename" => (string)$cycle["codename"],
	"name" => (string)($cycle["fr_name"] ?? $cycle["codename"]),
	"number" => $cycle_number,
	"current_year" => $current_year,
	"trimester" => $trimester,
	"promotion_year" => $promotion_year,
	"first_day" => $raw_first_day,
    ]));
}

function attendance_register_cycle_data($id_cycle, $id_student = NULL)
{
    $id_cycle = (int)$id_cycle;
    $cycle = db_select_one("* FROM cycle WHERE id = $id_cycle AND deleted IS NULL");
    if ($cycle == NULL)
	return (NULL);

    $school = db_select_one("
	school.id as id,
	school.id as id_school,
	school.codename as codename
	FROM school_cycle
	INNER JOIN school ON school.id = school_cycle.id_school
	WHERE school_cycle.id_cycle = $id_cycle
	  AND school.deleted IS NULL
	ORDER BY school_cycle.id ASC
    ");

    // Dans la page cycle, les utilisateurs placés dans « Responsables » sont
    // enregistrés dans cycle_teacher. Les laboratoires ne peuvent pas signer
    // la feuille : on sélectionne donc le premier responsable utilisateur.
    $director = db_select_one("
	user.*
	FROM cycle_teacher
	INNER JOIN user ON user.id = cycle_teacher.id_user
	WHERE cycle_teacher.id_cycle = $id_cycle
	  AND cycle_teacher.id_user IS NOT NULL
	  AND user.deleted IS NULL
	  AND user.profile_status != 'jury'
	ORDER BY cycle_teacher.id ASC
    ");

    $student_filter = $id_student === NULL ? "" : " AND user.id = ".(int)$id_student;
    $students = db_select_all("
	user.*
	FROM user_cycle
	INNER JOIN user ON user.id = user_cycle.id_user
	WHERE user_cycle.id_cycle = $id_cycle
	  AND user.deleted IS NULL
	  AND user.profile_status = 'member'
	  $student_filter
	ORDER BY
	  COALESCE(NULLIF(user.family_name, ''), NULLIF(user.use_name, ''), user.codename),
	  user.first_name,
	  user.codename
    ");

    return ([
	"cycle" => $cycle,
	"school" => $school,
	"director" => $director,
	"students" => $students,
    ]);
}

function attendance_register_period(array $cycle)
{
    $raw = substr((string)($cycle["first_day"] ?? ""), 0, 10);
    $start = DateTimeImmutable::createFromFormat("!Y-m-d", $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if ($start === false || ($errors !== false && ($errors["warning_count"] || $errors["error_count"])))
	return (NULL);

    $end_exclusive = $start->modify("+12 weeks");
    return ([
	"period" => [
	    "Label" => "Trimestre ".(((int)$cycle["cycle"] % 4) + 1),
	    "Cycle" => (string)$cycle["codename"],
	    "Start" => $start->format("Y-m-d"),
	    "End" => $end_exclusive->modify("-1 day")->format("Y-m-d"),
	],
	"start" => $start,
	"end_exclusive" => $end_exclusive,
    ]);
}

function attendance_register_activity_rows(array $user_ids, $id_cycle, DateTimeImmutable $start, DateTimeImmutable $end_exclusive)
{
    global $Database;
    global $Language;

    if (!count($user_ids))
	return ([]);
    $ids = implode(", ", array_map("intval", $user_ids));
    $id_cycle = (int)$id_cycle;
    $start_sql = $Database->real_escape_string($start->format("Y-m-d H:i:s"));
    $end_sql = $Database->real_escape_string($end_exclusive->format("Y-m-d H:i:s"));
    $language = in_array($Language, ["fr", "en"], true) ? $Language : "fr";

    return (db_select_all("
	DISTINCT
	    user_team.id_user as id_user,
	    session.id as id_session,
	    activity.id as id_activity,
	    COALESCE(appointment_slot.begin_date, session.begin_date) as begin_date,
	    COALESCE(appointment_slot.end_date, session.end_date, DATE_ADD(session.begin_date, INTERVAL 1 HOUR)) as end_date,
	    COALESCE(NULLIF(activity.{$language}_name, ''), NULLIF(template.{$language}_name, ''), activity.codename) as activity_name,
	    COALESCE(activity_type.codename, template_type.codename, '') as activity_type_codename,
	    COALESCE(appointment_slot.was_present, team.present) as present,
	    team.declaration_date as declaration_date,
	    team.late_time as late_time,
	    team.absence_justified as absence_justified
	FROM user_team
	INNER JOIN team ON team.id = user_team.id_team
	INNER JOIN session ON session.id = team.id_session
	INNER JOIN activity ON activity.id = session.id_activity
	LEFT JOIN activity AS template ON template.id = activity.id_template
	LEFT JOIN activity_type ON activity_type.id = activity.type
	LEFT JOIN activity_type AS template_type ON template_type.id = template.type
	LEFT JOIN activity AS parent ON parent.id = activity.parent_activity
	LEFT JOIN activity_cycle AS direct_cycle
	    ON direct_cycle.id_activity = activity.id
	   AND direct_cycle.id_cycle = $id_cycle
	LEFT JOIN activity_cycle AS parent_cycle
	    ON parent_cycle.id_activity = parent.id
	   AND parent_cycle.id_cycle = $id_cycle
	LEFT JOIN appointment_slot
	    ON appointment_slot.id_session = session.id
	   AND appointment_slot.id_team = team.id
	WHERE user_team.id_user IN ($ids)
	  AND session.deleted IS NULL
	  AND activity.deleted IS NULL
	  AND COALESCE(appointment_slot.begin_date, session.begin_date) < '$end_sql'
	  AND COALESCE(appointment_slot.end_date, session.end_date, DATE_ADD(session.begin_date, INTERVAL 1 HOUR)) > '$start_sql'
	  AND (direct_cycle.id IS NOT NULL OR parent_cycle.id IS NOT NULL)
	ORDER BY id_user ASC, begin_date ASC, activity_name ASC
    "));
}

function attendance_register_halfday_bounds($day, $period)
{
    $day = remove_hour((int)$day);
    if ((int)$period == 0)
        return (["begin" => $day + 9 * 60 * 60, "end" => $day + 13 * 60 * 60]);
    return (["begin" => $day + 14 * 60 * 60, "end" => $day + 17 * 60 * 60]);
}

function attendance_register_halfday_expected_duration($period)
{
    $bounds = attendance_register_halfday_bounds(0, $period);
    return ($bounds["end"] - $bounds["begin"]);
}

function attendance_register_halfday_end($day, $period)
{
    $bounds = attendance_register_halfday_bounds($day, $period);
    return ($bounds["end"]);
}

function attendance_register_activity_marker(array $row, $end, $cutoff)
{
    $marker = attendance_register_status_marker($row["present"] ?? NULL);
    $declaration_raw = trim((string)($row["declaration_date"] ?? ""));

    // Une activité terminée sans déclaration ni conclusion explicite est une
    // absence. Une conclusion manuelle ou issue d'un créneau reste prioritaire.
    if ($marker == "" && $declaration_raw == "" && $end && $end <= $cutoff)
        return ("A");
    return ($marker);
}

function attendance_register_activity_late_seconds(array $row, $begin, $end)
{
    $late_raw = trim((string)($row["late_time"] ?? ""));
    if ($late_raw == "")
        return (0);

    // team.late_time est un DATETIME employé comme durée depuis l'époque Unix.
    // Certaines anciennes lignes ont été enregistrées directement sous la
    // forme HH:MM[:SS] : on accepte ces deux représentations, mais on ne déduit
    // plus jamais un retard depuis declaration_date. Cette dernière peut être
    // une date de saisie administrative et non une heure réelle d'arrivée.
    if (!preg_match('/^(?:[0-9]{4}-[0-9]{2}-[0-9]{2}[ T])?([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?$/', $late_raw, $match))
        return (0);

    $late = (int)$match[1] * 60 * 60 + (int)$match[2] * 60 + (int)($match[3] ?? 0);
    if ($late <= 0)
        return (0);
    if ($begin && $end && $end > $begin)
        $late = min($late, (int)($end - $begin));
    return ($late);
}

function attendance_register_format_delay($seconds)
{
    $minutes = max(1, (int)round(max(0, (int)$seconds) / 60));
    $hours = intdiv($minutes, 60);
    $minutes %= 60;
    if ($hours <= 0)
        return ($minutes." min");
    if ($minutes == 0)
        return ($hours." h");
    return ($hours." h ".sprintf("%02d", $minutes));
}

function attendance_register_activity_reference_periods($begin, $end, $day)
{
    $morning = attendance_register_halfday_bounds($day, 0);
    $afternoon = attendance_register_halfday_bounds($day, 1);
    $day_end = remove_hour($day) + 24 * 60 * 60;
    $local_begin = max((int)$begin, remove_hour($day));
    $local_end = min((int)$end, $day_end);

    if ($local_end <= $local_begin)
        return ([]);

    // Une activité couvrant réellement la journée apporte une preuve pour les
    // deux demi-journées. Une conclusion P ou R ne doit jamais être contredite
    // par l'absence de journaux historiques ; une conclusion A vaut de même
    // pour M et AM. Un examen commencé le matin et débordant simplement après
    // 14 h reste en revanche rattaché au seul créneau du matin.
    if (
        $local_begin < $morning["end"]
        && $local_end >= $afternoon["end"]
        && $local_end - $local_begin >= 6 * 60 * 60
    )
        return ([0, 1]);

    return ([$local_begin < $afternoon["begin"] ? 0 : 1]);
}

function attendance_register_activity_delay_period($begin, $late_seconds = 0)
{
    $day = remove_hour((int)$begin);
    $afternoon = attendance_register_halfday_bounds($day, 1);
    $arrival = (int)$begin + max(0, (int)$late_seconds);
    return ($arrival < $afternoon["begin"] ? 0 : 1);
}

function attendance_register_activity_evidence(array $rows, $cutoff)
{
    $out = [];
    foreach ($rows as $row)
    {
        $id_user = (int)($row["id_user"] ?? 0);
        $begin = date_to_timestamp($row["begin_date"] ?? "");
        $end = date_to_timestamp($row["end_date"] ?? "");
        if ($id_user <= 0 || !$begin || !$end || $end <= $begin || $end > $cutoff)
            continue ;
        $marker = attendance_register_activity_marker($row, $end, $cutoff);
        if (!in_array($marker, ["P", "R", "A"], true))
            $marker = "A";
        $late_seconds = $marker == "R"
            ? attendance_register_activity_late_seconds($row, $begin, $end)
            : 0;

        for ($day = remove_hour($begin); $day <= remove_hour($end - 1); $day += 24 * 60 * 60)
        {
            $periods = attendance_register_activity_reference_periods($begin, $end, $day);
            $date = datex("Y-m-d", $day);
            $is_full_day = count($periods) == 2;
            $afternoon_begin = attendance_register_halfday_bounds($day, 1)["begin"];
            $arrival = $late_seconds > 0 ? $begin + $late_seconds : NULL;

            foreach ($periods as $period)
            {
                if (!isset($out[$id_user][$date][$period]))
                    $out[$id_user][$date][$period] = [
                        "covered" => true,
                        "present" => false,
                        "late" => false,
                        "absent" => false,
                        "justified_absence" => false,
                        "unjustified_absence" => false,
                    ];
                else
                    $out[$id_user][$date][$period]["covered"] = true;

                $period_marker = $marker;
                // Pour une activité couvrant toute la journée, un retard fiable
                // permet de répartir la présence selon l'heure réelle d'arrivée.
                // Une arrivée à partir de 14 h ne peut pas créditer le matin,
                // mais elle établit bien la présence de l'après-midi. Sans durée
                // fiable, la conclusion R reste une preuve de présence sur
                // l'activité sans inventer une heure d'arrivée.
                if ($is_full_day && $marker == "R" && $arrival !== NULL)
                {
                    if ((int)$period == 0 && $arrival >= $afternoon_begin)
                        $period_marker = "A";
                    else
                        $period_marker = "P";
                }

                $evidence = &$out[$id_user][$date][$period];
                if ($period_marker == "P")
                    $evidence["present"] = true;
                else if ($period_marker == "R")
                    $evidence["late"] = true;
                else
                {
                    $evidence["absent"] = true;
                    if (!empty($row["absence_justified"]))
                        $evidence["justified_absence"] = true;
                    else
                        $evidence["unjustified_absence"] = true;
                }
                unset($evidence);
            }
        }
    }
    return ($out);
}

function attendance_register_activity_evidence_marker(array $evidence)
{
    // Une activité conclue présente, même avec retard, établit une présence
    // sur la demi-journée. Le détail et la durée du retard restent portés par
    // la ligne de l'activité, sans dégrader la colonne synthétique M/AM.
    if (!empty($evidence["present"]) || !empty($evidence["late"]))
        return ("P");
    if (!empty($evidence["absent"]))
        return ("A");
    return ("");
}

function attendance_register_activity_evidence_is_justified(array $evidence)
{
    return (
        !empty($evidence["absent"])
        && !empty($evidence["justified_absence"])
        && empty($evidence["unjustified_absence"])
    );
}

function attendance_register_halfday_result(array $status, $period, $day, $cutoff, array $evidence)
{
    $bounds = attendance_register_halfday_bounds($day, $period);
    if ($bounds["end"] > $cutoff)
        return (["marker" => "", "justified" => false]);

    // La conclusion explicite d'une activité est la preuve prioritaire pour
    // la ou les demi-journées auxquelles elle a été rattachée. Pour une activité
    // couvrant toute la journée, un retard fiable a déjà été ventilé selon
    // l'heure d'arrivée ; les journaux ne servent qu'en l'absence de preuve.
    $marker = attendance_register_activity_evidence_marker($evidence);
    if ($marker != "")
        return ([
            "marker" => $marker,
            "justified" => $marker == "A"
                && attendance_register_activity_evidence_is_justified($evidence),
        ]);

    // halfday_presence_build() porte la règle commune de validation par les
    // journaux de connexion (2 h le matin comme l'après-midi). Si cette règle
    // conclut déjà à une présence, la feuille d'émargement doit la reprendre
    // au lieu d'appliquer ensuite son ancien seuil proportionnel.
    if ((string)try_get($status, "status", "") === "present")
        return (["marker" => "P", "justified" => false]);

    // Sans activité de référence (journée de projet, autre demi-journée ou
    // activité de journée complète), on utilise le temps réellement constaté.
    $duration = max(0, (int)try_get($status, "duration", 0));
    $expected = $bounds["end"] - $bounds["begin"];
    $late_threshold = (int)ceil($expected * 0.66);
    if ($duration >= $expected)
        return (["marker" => "P", "justified" => false]);
    if ($duration >= $late_threshold)
        return (["marker" => "R", "justified" => false]);

    // Sur un relevé destiné à être contrôlé, une demi-journée échue ne peut
    // pas rester sans conclusion à cause d'une lacune technique de l'intranet.
    return (["marker" => "A", "justified" => false]);
}

function attendance_register_add_halfday_total(array &$totals, $period, array $result)
{
    $duration = attendance_register_halfday_expected_duration($period);
    $totals["PlannedDuration"] += $duration;
    if (in_array($result["marker"], ["P", "R"], true))
        $totals["PresenceDuration"] += $duration;
    else if ($result["marker"] == "A")
    {
        $totals["AbsenceDuration"] += $duration;
        if (!empty($result["justified"]))
            $totals["JustifiedAbsenceDuration"] += $duration;
    }
}

function attendance_register_collect_days(array $user_ids, $id_cycle, DateTimeImmutable $start, DateTimeImmutable $end_exclusive, $cutoff, array $fallback_trainer)
{
    $out = [];
    $totals = [];
    $trainers = [];
    $trainer_cache = [];
    $late_by_halfday = [];
    $fallback_trainer_name = attendance_register_person_label($fallback_trainer);
    $fallback_trainer_context = attendance_register_person_context($fallback_trainer);
    $fallback_trainer_context["Role"] = "Encadrant de projet";
    $fallback_trainer_key = attendance_register_person_context_key($fallback_trainer_context);
    foreach ($user_ids as $id_user)
    {
        $totals[(int)$id_user] = attendance_register_empty_totals();
        $trainers[(int)$id_user] = [];
    }

    $start_timestamp = $start->getTimestamp();
    $days_count = (int)$start->diff($end_exclusive)->format("%a");
    $activity_rows = attendance_register_activity_rows(
        $user_ids,
        $id_cycle,
        $start,
        $end_exclusive
    );
    $activity_evidence = attendance_register_activity_evidence($activity_rows, $cutoff);
    $halfday = halfday_presence_build(
        $user_ids,
        $start_timestamp,
        $days_count,
        (int)$id_cycle
    );

    foreach ($user_ids as $id_user)
    {
        $id_user = (int)$id_user;
        for ($offset = 0; $offset < $days_count; ++$offset)
        {
            $timestamp = $start_timestamp + $offset * 24 * 60 * 60;
            $day_key = (int)(remove_hour($timestamp) / (24 * 60 * 60));
            $date = datex("Y-m-d", $timestamp);
            $weekend = (int)datex("N", $timestamp) > 5;
            foreach ([0 => "Morning", 1 => "Afternoon"] as $period => $field)
            {
                $status = try_get(
                    try_get(try_get($halfday, $id_user, []), $day_key, []),
                    $period,
                    []
                );
                $evidence = try_get(
                    try_get(try_get($activity_evidence, $id_user, []), $date, []),
                    $period,
                    []
                );
                $evidence = is_array($evidence) ? $evidence : [];

                // Le week-end, seules les demi-journées rendues pertinentes par
                // une activité sont affichées et comptabilisées. Une activité
                // du samedi matin peut donc produire M=P, sans inventer un état
                // pour l'après-midi resté libre.
                if ($weekend && empty($evidence["covered"]))
                    continue ;

                $result = attendance_register_halfday_result(
                    is_array($status) ? $status : [],
                    $period,
                    $timestamp,
                    $cutoff,
                    $evidence
                );
                if (attendance_register_halfday_end($timestamp, $period) <= $cutoff)
                    attendance_register_add_halfday_total($totals[$id_user], $period, $result);
                if ($result["marker"] != "")
                    $out[$id_user][$date][$field] = $result["marker"];
            }
        }
    }

    foreach ($activity_rows as $row)
    {
        $id_user = (int)$row["id_user"];
        $begin = date_to_timestamp($row["begin_date"]);
        if (!$begin)
            continue ;
        $date = datex("Y-m-d", $begin);
        $end = date_to_timestamp($row["end_date"]);
        $name = trim(html_entity_decode((string)$row["activity_name"], ENT_QUOTES | ENT_HTML5, "UTF-8"));
        if ($name == "")
            $name = "Activité";
        $marker = attendance_register_activity_marker($row, $end, $cutoff);
        $declaration_raw = trim((string)($row["declaration_date"] ?? ""));

        $activity_trainers = attendance_register_activity_trainers(
            (int)$row["id_session"],
            (int)$row["id_activity"],
            $fallback_trainer,
            $trainer_cache
        );
        $activity = [
            "Name" => $name,
            "TypeLabel" => attendance_register_activity_type_label($row["activity_type_codename"] ?? ""),
            "Status" => $marker,
            "Trainer" => $activity_trainers["label"],
        ];
        // Pour une présence ponctuelle, l'heure de déclaration reste utile.
        // Pour un retard, la durée de retard est plus lisible que l'heure
        // absolue puisque les horaires planifiés ne figurent plus sur la feuille.
        if ($marker == "P" && $declaration_raw != "")
        {
            $declaration = date_to_timestamp($declaration_raw);
            if ($declaration)
                $activity["Declaration"] = datex("H:i", $declaration);
        }
        else if ($marker == "R")
        {
            $late_seconds = attendance_register_activity_late_seconds($row, $begin, $end);
            if ($late_seconds > 0)
            {
                $activity["LateDuration"] = attendance_register_format_delay($late_seconds);
                if ($end && $end <= $cutoff)
                {
                    // Plusieurs activités simultanées peuvent partager la même
                    // déclaration de retard. Pour ne pas compter plusieurs fois
                    // une seule arrivée tardive, on conserve le retard maximal
                    // de chaque demi-journée, puis on les additionne à la fin.
                    $late_period = attendance_register_activity_delay_period($begin, $late_seconds);
                    $late_key = datex("Y-m-d", $begin);
                    $late_by_halfday[$id_user][$late_key][$late_period] = max(
                        (int)($late_by_halfday[$id_user][$late_key][$late_period] ?? 0),
                        $late_seconds
                    );
                }
            }
        }
        if ($begin <= $cutoff)
        {
            foreach ($activity_trainers["people"] as $trainer)
                $trainers[$id_user][attendance_register_person_context_key($trainer)] = $trainer;
        }
        $out[$id_user][$date]["Activities"][] = $activity;
    }

    foreach ($late_by_halfday as $id_user => $student_late_days)
    {
        foreach ($student_late_days as $periods)
            foreach ($periods as $late_seconds)
                $totals[(int)$id_user]["LateDuration"] += (int)$late_seconds;
    }

    // Les jours ouvrés sans activité planifiée correspondent au travail de
    // projet. On les matérialise afin que la colonne ne reste pas vide ; le
    // responsable du cycle est alors indiqué comme encadrant.
    foreach ($user_ids as $id_user)
    {
        $id_user = (int)$id_user;
        for ($offset = 0; $offset < $days_count; ++$offset)
        {
            $timestamp = $start_timestamp + $offset * 24 * 60 * 60;
            if ((int)datex("N", $timestamp) > 5)
                continue ;
            $date = datex("Y-m-d", $timestamp);
            if (!empty($out[$id_user][$date]["Activities"]))
                continue ;
            $out[$id_user][$date]["Activities"][] = [
                "Name" => "Projet(s)",
                "Trainer" => $fallback_trainer_name,
                "TrainerLabel" => "Encadrant",
                "Status" => "",
            ];
            $project_begin = attendance_register_halfday_bounds($timestamp, 0)["begin"];
            if ($project_begin <= $cutoff && !isset($trainers[$id_user][$fallback_trainer_key]))
                $trainers[$id_user][$fallback_trainer_key] = $fallback_trainer_context;
        }
    }

    $days = [];
    foreach ($out as $id_user => $student_days)
    {
        ksort($student_days);
        foreach ($student_days as $date => $day)
            $days[$id_user][] = array_merge(["Date" => $date], $day);
    }
    foreach ($totals as $id_user => $student_totals)
        $totals[$id_user] = attendance_register_finalize_totals($student_totals);
    foreach ($trainers as $id_user => $student_trainers)
        $trainers[$id_user] = array_values($student_trainers);
    return (["days" => $days, "totals" => $totals, "trainers" => $trainers]);
}

function attendance_register_reference(array $cycle, array $student)
{
    $cycle_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string)($cycle["codename"] ?? "cycle"));
    $student_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string)($student["codename"] ?? ($student["id"] ?? "student")));
    return ("EMARG-".$cycle_name."-".$student_name);
}

function attendance_register_task_person_id(array $person)
{
    return ((int)($person["Id"] ?? ($person["id"] ?? 0)));
}

function attendance_register_task_person_identity(array $person)
{
    $identity = trim((string)($person["Identity"] ?? ($person["identity"] ?? "")));
    if ($identity == "")
        $identity = attendance_register_person_label($person);
    return ($identity);
}

/** Per-sheet obligation: the student signs their own attendance register. */
function attendance_register_student_signature_task_plan(array $student)
{
    $plan = [];
    $student_id = attendance_register_task_person_id($student);
    if ($student_id > 0)
        $plan["Student"] = [
            "Action" => "sign",
            "Role" => "Student",
            "RoleLabel" => "Élève",
            "AssigneeUserId" => $student_id,
            "AssigneeLabel" => attendance_register_task_person_identity($student),
            "Required" => 1,
            "Metadata" => ["Context" => "Student"],
        ];
    return ($plan);
}

/**
 * Signatures carried once by the attendance-register campaign.
 * A user who is both Director and Teacher deliberately receives two distinct
 * obligations. A Teacher is however materialized only once regardless of the
 * number of activities they supervised during the quarter.
 */
function attendance_register_campaign_signature_task_plan(array $cycle_director, array $trainers)
{
    $plan = [];
    $director_id = attendance_register_task_person_id($cycle_director);
    if ($director_id > 0)
        $plan["Director"] = [
            "Action" => "sign",
            "Role" => "Director",
            "RoleLabel" => "Responsable pédagogique",
            "AssigneeUserId" => $director_id,
            "AssigneeLabel" => attendance_register_task_person_identity($cycle_director),
            "Required" => 1,
            "Metadata" => ["Context" => "CycleDirector", "Scope" => "AttendanceCampaign"],
        ];

    $seen = [];
    foreach ($trainers as $trainer)
    {
        if (!is_array($trainer))
            continue ;
        $id_user = attendance_register_task_person_id($trainer);
        if ($id_user <= 0 || isset($seen[$id_user]))
            continue ;
        $seen[$id_user] = true;
        $slot = "Teacher_".$id_user;
        $plan[$slot] = [
            "Action" => "sign",
            "Role" => "Teacher",
            "RoleLabel" => "Formateur intervenant",
            "AssigneeUserId" => $id_user,
            "AssigneeLabel" => attendance_register_task_person_identity($trainer),
            "Required" => 1,
            "Metadata" => [
                "Context" => "Trainers",
                "Scope" => "AttendanceCampaign",
                "InterventionRole" => trim((string)($trainer["Role"] ?? ($trainer["role"] ?? ""))),
            ],
        ];
    }
    return ($plan);
}

/** Compatibility helper used by the preview configuration. */
function attendance_register_signature_task_plan(array $student, array $cycle_director, array $trainers)
{
    return (array_merge(
        attendance_register_student_signature_task_plan($student),
        attendance_register_campaign_signature_task_plan($cycle_director, $trainers)
    ));
}

function attendance_register_campaign_trainers(array $trainers_by_student)
{
    $out = [];
    foreach ($trainers_by_student as $student_trainers)
        foreach ((array)$student_trainers as $trainer)
        {
            if (!is_array($trainer))
                continue ;
            $id_user = attendance_register_task_person_id($trainer);
            if ($id_user > 0 && !isset($out[$id_user]))
                $out[$id_user] = $trainer;
        }
    return (array_values($out));
}

function attendance_register_campaign_period_matches(array $instance, $id_cycle, array $period)
{
    $source = $instance["SourceContext"] ?? [];
    return (is_array($source)
        && (string)($source["Type"] ?? "") === "AttendanceRegisterCampaign"
        && (int)($source["CycleId"] ?? 0) === (int)$id_cycle
        && (string)($source["PeriodStart"] ?? "") === $period["start"]->format("Y-m-d")
        && (string)($source["PeriodEnd"] ?? "") === $period["end_exclusive"]->modify("-1 day")->format("Y-m-d"));
}

function attendance_register_find_campaign($id_cycle, array $period, $include_expired = false)
{
    global $Configuration;

    $pattern = rtrim($Configuration->UsersDir(), "/")."/*/".
        trim(document_workflow_root(), "/")."/*/instance.dab";
    $found = [];
    foreach (glob($pattern) ?: [] as $file)
    {
        $loaded = document_workflow_load_instance(dirname($file));
        if ($loaded->is_error())
            continue ;
        $instance = $loaded->value["data"];
        if (!attendance_register_campaign_period_matches($instance, $id_cycle, $period))
            continue ;
        if (!$include_expired && ($instance["Status"] ?? "") === "Expired")
            continue ;
        $found[] = [
            "instance" => $instance,
            "file" => $loaded->value["file"],
            "directory" => $loaded->value["directory"],
            "owner_user_id" => (int)($instance["OwnerUserId"] ?? 0),
        ];
    }
    usort($found, function ($a, $b) {
        return (strcmp((string)($b["instance"]["CreatedAt"] ?? ""), (string)($a["instance"]["CreatedAt"] ?? "")));
    });
    return (count($found) ? $found[0] : NULL);
}

function attendance_register_cycle_campaigns($id_cycle)
{
    global $Configuration;

    $id_cycle = (int)$id_cycle;
    if ($id_cycle <= 0)
        return ([]);
    $pattern = rtrim($Configuration->UsersDir(), "/")."/*/".
        trim(document_workflow_root(), "/")."/*/instance.dab";
    $out = [];
    foreach (glob($pattern) ?: [] as $file)
    {
        $loaded = document_workflow_load_instance(dirname($file));
        if ($loaded->is_error())
            continue ;
        $instance = $loaded->value["data"];
        $source = $instance["SourceContext"] ?? [];
        if (!is_array($source)
            || (string)($source["Type"] ?? "") !== "AttendanceRegisterCampaign"
            || (int)($source["CycleId"] ?? 0) !== $id_cycle)
            continue ;
        $progress = document_workflow_signature_progress($instance);
        $materialized = document_workflow_materialize_instance_task_plan($instance);
        if ($materialized->is_error())
            add_log(REPORT, "Cannot materialize attendance campaign tasks for ".(string)($instance["Id"] ?? "?"));
        $out[] = [
            "instance" => $instance,
            "directory" => $loaded->value["directory"],
            "owner_user_id" => (int)($instance["OwnerUserId"] ?? 0),
            "signature_signed" => $progress["signed"],
            "signature_total" => $progress["total"],
            "tasks" => document_task_rows_for_instance((string)($instance["Id"] ?? ""), (int)($instance["OwnerUserId"] ?? 0)),
        ];
    }
    usort($out, function ($a, $b) {
        return (strcmp((string)($b["instance"]["CreatedAt"] ?? ""), (string)($a["instance"]["CreatedAt"] ?? "")));
    });
    return ($out);
}

function attendance_register_cycle_workflows($id_cycle)
{
    global $Configuration;

    $id_cycle = (int)$id_cycle;
    if ($id_cycle <= 0)
        return ([]);
    $students = db_select_all("user.id, user.codename, user.first_name, user.family_name
        FROM user_cycle
        INNER JOIN user ON user.id = user_cycle.id_user
        WHERE user_cycle.id_cycle = $id_cycle
          AND user.deleted IS NULL
        ORDER BY user.codename ASC");
    $out = [];
    foreach ($students as $student)
    {
        $root = $Configuration->UsersDir($student["codename"]).document_workflow_root()."/";
        foreach (glob($root."*/instance.dab") ?: [] as $file)
        {
            $loaded = document_workflow_load_instance(dirname($file));
            if ($loaded->is_error())
                continue ;
            $instance = $loaded->value["data"];
            $source = $instance["SourceContext"] ?? [];
            if (!is_array($source)
                || (string)($source["Type"] ?? "") !== "AttendanceRegister"
                || (int)($source["CycleId"] ?? 0) !== $id_cycle)
                continue ;
            $progress = document_workflow_signature_progress($instance);
            $materialized = document_workflow_materialize_instance_task_plan($instance);
            if ($materialized->is_error())
                add_log(REPORT, "Cannot materialize attendance-register tasks for ".(string)($instance["Id"] ?? "?"));
            $tasks = document_task_rows_for_instance((string)($instance["Id"] ?? ""), (int)$student["id"]);
            $out[] = [
                "student" => $student,
                "instance" => $instance,
                "directory" => $loaded->value["directory"],
                "signature_signed" => $progress["signed"],
                "signature_total" => $progress["total"],
                "tasks" => $tasks,
            ];
        }
    }
    usort($out, function ($a, $b) {
        return (strcmp((string)($b["instance"]["CreatedAt"] ?? ""), (string)($a["instance"]["CreatedAt"] ?? "")));
    });
    return ($out);
}

function attendance_register_existing_workflow(array $student, $id_cycle)
{
    global $Configuration;

    $codename = trim((string)($student["codename"] ?? ""));
    if ($codename == "")
        return (NULL);
    $root = $Configuration->UsersDir($codename).document_workflow_root()."/";
    foreach (glob($root."*/instance.dab") ?: [] as $file)
    {
        $loaded = document_workflow_load_instance(dirname($file));
        if ($loaded->is_error())
            continue ;
        $instance = $loaded->value["data"];
        $source = $instance["SourceContext"] ?? [];
        if (!is_array($source)
            || (string)($source["Type"] ?? "") !== "AttendanceRegister"
            || (int)($source["CycleId"] ?? 0) !== (int)$id_cycle)
            continue ;
        if (($instance["Status"] ?? "") !== "Expired")
            return ($instance);
    }
    return (NULL);
}

function attendance_register_workflow_filename(array $cycle, array $student)
{
    $cycle_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string)($cycle["codename"] ?? "cycle"));
    $student_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string)($student["codename"] ?? ($student["id"] ?? "student")));
    return ("attendance-register-".$cycle_name."-".$student_name."-".date("Ymd_His").".pdf");
}

function attendance_register_campaign_filename(array $cycle, array $period)
{
    $cycle_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string)($cycle["codename"] ?? "cycle"));
    return ("attendance-campaign-".$cycle_name."-".$period["start"]->format("Ymd")."-".
        $period["end_exclusive"]->modify("-1 day")->format("Ymd").".pdf");
}

function attendance_register_prepare_workflow_document(array $context, array $period, array $document_info,
    array $cycle_director, array $student, array $days, array $totals, array $trainers)
{
    $student_context = attendance_register_person_context($student);
    $configuration = [
        "Document" => "AttendanceRegister",
        "Title" => "Feuille d'émargement trimestrielle",
        "Period" => $period["period"],
        "DocumentInfo" => $document_info,
        "Cycle" => attendance_register_cycle_context($context["cycle"]),
        "School" => attendance_register_school_context($context["school"]),
        "CycleDirector" => $cycle_director,
        "Reference" => attendance_register_reference($context["cycle"], $student),
        "Student" => $student_context,
        "Days" => $days,
        "Totals" => $totals,
        "Trainers" => $trainers,
        // The preview may display every expected role, but only Student is a
        // per-sheet signature obligation. Director/Teacher are campaign-wide.
        "TaskPlan" => attendance_register_signature_task_plan($student_context, $cycle_director, $trainers),
    ];
    $document = attendance_register_run_docbuilder($configuration);
    if (!$document["ok"])
        return (new ErrorResponse("AttendanceRegisterGenerationFailed", $document["error"]));
    return (new ValueResponse([
        "student" => $student,
        "student_context" => $student_context,
        "trainers" => $trainers,
        "content" => $document["content"],
        "hash" => hash("sha256", $document["content"]),
    ]));
}

function attendance_register_manifest_safe($value)
{
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$value);
    return (str_replace(["[", "]"], ["(", ")"], trim((string)$value)));
}

function attendance_register_campaign_manifest_pdf(array $context, array $period, array $prepared, array $campaign_plan)
{
    $cycle = attendance_register_manifest_safe($context["cycle"]["codename"] ?? "cycle");
    $start = $period["start"]->format("d/m/Y");
    $end = $period["end_exclusive"]->modify("-1 day")->format("d/m/Y");
    $content = [
        "[@Center;[@Size;7] Campagne de signatures des feuilles d'émargement trimestrielles]\n\n",
        "Cycle : ".$cycle."\n\n",
        "Période : ".$start." au ".$end."\n\n",
        "Ce manifeste fige le lot exact des feuilles individuelles couvertes par les signatures du responsable pédagogique et des formateurs. Chaque élève signe séparément sa propre feuille.\n\n",
        "## Feuilles couvertes\n\n",
    ];
    foreach ($prepared as $entry)
        $content[] = "- ".attendance_register_manifest_safe($entry["student"]["codename"] ?? ("#".$entry["student"]["id"])).
            " — SHA-256 : ".$entry["hash"]."\n\n";
    $content[] = "## Signatures attendues pour la campagne\n\n";
    foreach (document_task_plan_normalize($campaign_plan) as $definition)
        $content[] = "- ".attendance_register_manifest_safe($definition["role_label"]).
            ($definition["assignee_label"] != "" ? " — ".attendance_register_manifest_safe($definition["assignee_label"]) : "")."\n\n";
    return (attendance_register_run_docbuilder([
        "Document" => "Generic",
        "Title" => "Campagne d'émargement trimestrielle",
        "Content" => $content,
    ]));
}

function attendance_register_create_campaign_instance(array $context, array $period, array $prepared,
    array $cycle_director, array $campaign_trainers)
{
    $campaign_plan = attendance_register_campaign_signature_task_plan($cycle_director, $campaign_trainers);
    if (!count($campaign_plan))
        return (new ErrorResponse("AttendanceRegisterMissingDirector"));
    $manifest = attendance_register_campaign_manifest_pdf($context, $period, $prepared, $campaign_plan);
    if (!$manifest["ok"])
        return (new ErrorResponse("AttendanceRegisterGenerationFailed", $manifest["error"]));

    $members = [];
    foreach ($prepared as $entry)
    {
        $sid = (int)$entry["student"]["id"];
        $members["Student_".$sid] = [
            "StudentId" => $sid,
            "StudentCodename" => (string)($entry["student"]["codename"] ?? ""),
            "FrozenHash" => (string)$entry["hash"],
            "LeafInstanceId" => "",
        ];
    }
    $cycle_context = attendance_register_cycle_context($context["cycle"]);
    $owner_user_id = attendance_register_task_person_id($cycle_director);
    $display = "Campagne d'émargement trimestrielle — ".(string)$context["cycle"]["codename"]." — ".
        $period["start"]->format("d/m/Y")." au ".$period["end_exclusive"]->modify("-1 day")->format("d/m/Y");
    $instance = document_workflow_create_frozen_instance(
        $owner_user_id,
        "AttendanceRegisterCampaign",
        __DIR__."/../res/docs/fr/.formlabels/attendance.dab",
        (int)($cycle_context["CurrentYear"] ?? 0),
        [],
        $manifest["content"],
        $campaign_plan,
        [
            "display_name" => $display,
            "source_context" => [
                "type" => "AttendanceRegisterCampaign",
                "cycle_id" => (int)$context["cycle"]["id"],
                "cycle_codename" => (string)$context["cycle"]["codename"],
                "period_start" => $period["start"]->format("Y-m-d"),
                "period_end" => $period["end_exclusive"]->modify("-1 day")->format("Y-m-d"),
                "members" => $members,
            ],
            "archive_targets" => [
                "CycleManifest" => [
                    "kind" => "Cycle",
                    "cycle_id" => (int)$context["cycle"]["id"],
                    "relative" => "attendance/".attendance_register_campaign_filename($context["cycle"], $period),
                ],
            ],
        ]
    );
    if ($instance->is_error())
        return ($instance);
    return (new ValueResponse([
        "owner_user_id" => $owner_user_id,
        "instance_id" => (string)$instance->value["id"],
        "hash" => (string)$instance->value["hash"],
        "directory" => (string)$instance->value["directory"],
        "plan" => $campaign_plan,
    ]));
}

function attendance_register_attach_campaign_signatures($owner_user_id, $instance_id,
    $campaign_owner_user_id, $campaign_instance_id, $campaign_hash, array $campaign_plan)
{
    $loaded = document_workflow_find_instance((int)$owner_user_id, $instance_id);
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    foreach (document_task_plan_normalize($campaign_plan) as $slot => $definition)
    {
        if ($definition["action"] !== "sign")
            continue ;
        $instance["Signatures"][$slot] = [
            "Required" => $definition["required"] ? 1 : 0,
            "Source" => "AttendanceRegisterCampaign",
            "Status" => "Pending",
            "TaskRole" => $definition["role"],
            "RoleLabel" => $definition["role_label"],
            "SignatoryUserId" => (int)$definition["id_assignee_user"],
            "ExternalCampaignOwnerUserId" => (int)$campaign_owner_user_id,
            "ExternalCampaignInstanceId" => (string)$campaign_instance_id,
            "ExternalCampaignFrozenHash" => (string)$campaign_hash,
        ];
    }
    return (document_workflow_write_instance($loaded->value["file"], $instance));
}

function attendance_register_create_workflow_instance(array $context, array $period, array $prepared,
    $campaign_owner_user_id, $campaign_instance_id, $campaign_hash, array $campaign_plan)
{
    $student = $prepared["student"];
    $existing = attendance_register_existing_workflow($student, (int)$context["cycle"]["id"]);
    if (is_array($existing))
        return (new ErrorResponse("AttendanceRegisterWorkflowExists", (string)($existing["Id"] ?? "")));

    $filename = attendance_register_workflow_filename($context["cycle"], $student);
    $cycle_context = attendance_register_cycle_context($context["cycle"]);
    $task_plan = attendance_register_student_signature_task_plan($prepared["student_context"]);
    $instance = document_workflow_create_frozen_instance(
        (int)$student["id"],
        "Feuille d'émargement trimestrielle",
        __DIR__."/../res/docs/fr/.formlabels/attendance.dab",
        (int)($cycle_context["CurrentYear"] ?? 0),
        [],
        $prepared["content"],
        $task_plan,
        [
            "display_name" => "Feuille d'émargement trimestrielle",
            "source_context" => [
                "type" => "AttendanceRegister",
                "cycle_id" => (int)$context["cycle"]["id"],
                "cycle_codename" => (string)$context["cycle"]["codename"],
                "student_id" => (int)$student["id"],
                "reference" => attendance_register_reference($context["cycle"], $student),
                "campaign_owner_user_id" => (int)$campaign_owner_user_id,
                "campaign_instance_id" => (string)$campaign_instance_id,
                "campaign_frozen_hash" => (string)$campaign_hash,
            ],
            "archive_targets" => [
                "Student" => [
                    "kind" => "UserDocumentation",
                    "owner_user_id" => (int)$student["id"],
                    "relative" => "generated/attendance/".$filename,
                ],
                "Cycle" => [
                    "kind" => "Cycle",
                    "cycle_id" => (int)$context["cycle"]["id"],
                    "relative" => "attendance/".$filename,
                ],
            ],
        ]
    );
    if ($instance->is_error())
        return ($instance);
    $external = attendance_register_attach_campaign_signatures(
        (int)$student["id"], (string)$instance->value["id"],
        (int)$campaign_owner_user_id, (string)$campaign_instance_id,
        (string)$campaign_hash, $campaign_plan
    );
    if ($external->is_error())
        return ($external);
    return (new ValueResponse([
        "student_id" => (int)$student["id"],
        "student" => (string)($student["codename"] ?? ""),
        "instance_id" => (string)$instance->value["id"],
        "status" => (string)$instance->value["status"],
        "frozen_hash" => (string)$instance->value["hash"],
    ]));
}

function attendance_register_update_campaign_members($campaign_owner_user_id, $campaign_instance_id, array $created)
{
    $loaded = document_workflow_find_instance((int)$campaign_owner_user_id, $campaign_instance_id);
    if ($loaded->is_error())
        return ($loaded);
    $instance = $loaded->value["data"];
    if (!isset($instance["SourceContext"]) || !is_array($instance["SourceContext"]))
        return (new ErrorResponse("InvalidFile", "campaign source context"));
    $members = $instance["SourceContext"]["Members"] ?? [];
    if (!is_array($members))
        $members = [];
    foreach ($created as $entry)
    {
        $key = "Student_".(int)$entry["student_id"];
        if (!isset($members[$key]) || !is_array($members[$key]))
            $members[$key] = ["StudentId" => (int)$entry["student_id"]];
        $members[$key]["LeafInstanceId"] = (string)$entry["instance_id"];
        $members[$key]["OwnerUserId"] = (int)$entry["student_id"];
        $members[$key]["FrozenHash"] = (string)$entry["frozen_hash"];
    }
    $instance["SourceContext"]["Members"] = $members;
    return (document_workflow_write_instance($loaded->value["file"], $instance));
}

/**
 * Once the campaign itself is completed, its global signatures satisfy the
 * external Director/Teacher placeholders of every child sheet. No new mail or
 * signature action is created on the child instances.
 */
function attendance_register_sync_campaign_instance($campaign_file)
{
    $loaded = document_workflow_load_instance(dirname((string)$campaign_file));
    if ($loaded->is_error())
        return ($loaded);
    $campaign = $loaded->value["data"];
    $source = $campaign["SourceContext"] ?? [];
    if (!is_array($source) || (string)($source["Type"] ?? "") !== "AttendanceRegisterCampaign")
        return (new ValueResponse(["campaign" => false]));
    if (($campaign["Status"] ?? "") !== "Completed")
        return (new ValueResponse(["campaign" => true, "pending" => true]));

    $campaign_final_hash = trim((string)($campaign["FinalHash"] ?? ($campaign["SealedHash"] ?? "")));
    $updated = 0;
    $synchronized = 0;
    foreach ((array)($source["Members"] ?? []) as $member)
    {
        if (!is_array($member))
            continue ;
        $owner = (int)($member["OwnerUserId"] ?? ($member["StudentId"] ?? 0));
        $leaf_id = trim((string)($member["LeafInstanceId"] ?? ""));
        if ($owner <= 0 || $leaf_id == "")
            continue ;
        $leaf = document_workflow_find_instance($owner, $leaf_id);
        if ($leaf->is_error())
            return ($leaf);
        $instance = $leaf->value["data"];
        if (($member["FrozenHash"] ?? "") != ""
            && (string)($instance["FrozenHash"] ?? "") !== (string)$member["FrozenHash"])
            return (new ErrorResponse("DocumentHashMismatch", $leaf_id));
        $changed = false;
        foreach (($campaign["Signatures"] ?? []) as $slot => $signature)
        {
            if (!is_array($signature) || empty($signature["Required"]) || ($signature["Status"] ?? "") !== "Signed")
                continue ;
            if (!isset($instance["Signatures"][$slot]) || !is_array($instance["Signatures"][$slot]))
                continue ;
            $child = &$instance["Signatures"][$slot];
            if ((string)($child["ExternalCampaignInstanceId"] ?? "") !== (string)($campaign["Id"] ?? ""))
            {
                unset($child);
                continue ;
            }
            $already_inherited = ($child["Status"] ?? "") === "Signed"
                && !empty($child["InheritedFromCampaign"])
                && (string)($child["EvidenceSha256"] ?? "") === (string)($signature["EvidenceSha256"] ?? "")
                && (string)($child["ExternalCampaignFinalHash"] ?? "") === $campaign_final_hash;
            if (!$already_inherited)
            {
                $child["Status"] = "Signed";
                $child["SignatoryUserId"] = (int)($signature["SignatoryUserId"] ?? 0);
                $child["SignedAt"] = (string)($signature["SignedAt"] ?? "");
                $child["SignatureSha256"] = (string)($signature["SignatureSha256"] ?? "");
                $child["EvidenceSha256"] = (string)($signature["EvidenceSha256"] ?? "");
                $child["InheritedFromCampaign"] = 1;
                $child["ExternalCampaignFinalHash"] = $campaign_final_hash;
                $changed = true;
            }
            unset($child);
        }
        ++$synchronized;
        if ($changed && ($instance["Status"] ?? "") === "AwaitingSignature"
            && document_workflow_all_required_signed($instance))
        {
            $instance["Status"] = "Signed";
            $instance["ReadyForSealAt"] = date("Y-m-d H:i:s");
        }
        if ($changed)
        {
            $written = document_workflow_write_instance($leaf->value["file"], $instance);
            if ($written->is_error())
                return ($written);
            ++$updated;
        }
    }
    if (empty($campaign["ChildrenSynchronizedAt"])
        || (int)($campaign["ChildrenSynchronizedCount"] ?? -1) !== $synchronized)
    {
        $campaign["ChildrenSynchronizedAt"] = date("Y-m-d H:i:s");
        $campaign["ChildrenSynchronizedCount"] = $synchronized;
        $written = document_workflow_write_instance($loaded->value["file"], $campaign);
        if ($written->is_error())
            return ($written);
    }
    return (new ValueResponse(["campaign" => true, "updated" => $updated, "synchronized" => $synchronized]));
}

function attendance_register_run_docbuilder(array $configuration)
{
    $tmp = tempnam(sys_get_temp_dir(), "infosphere_attendance_");
    if ($tmp === false)
	return (["ok" => false, "error" => "Impossible de créer le fichier temporaire."]);
    @unlink($tmp);
    $json_file = $tmp.".json";
    $pdf_file = $tmp.".pdf";
    $json = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($json_file, $json) === false)
    {
	@unlink($json_file);
	return (["ok" => false, "error" => "Impossible d'écrire la configuration temporaire."]);
    }

    $command = ["docbuilder", "-i", $json_file, "-o", $pdf_file];
    $descriptors = [
	0 => ["pipe", "r"],
	1 => ["pipe", "w"],
	2 => ["pipe", "w"],
    ];
    $process = proc_open($command, $descriptors, $pipes, NULL, NULL, ["bypass_shell" => true]);
    if (!is_resource($process))
    {
	@unlink($json_file);
	return (["ok" => false, "error" => "Impossible de lancer DocBuilder."]);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $content = is_file($pdf_file) ? file_get_contents($pdf_file) : false;
    @unlink($json_file);
    @unlink($pdf_file);

    if ($status != 0 || $content === false || substr($content, 0, 4) != "%PDF")
    {
	$error = trim((string)$stderr."\n".(string)$stdout);
	if ($error == "")
	    $error = "DocBuilder a terminé avec le statut $status sans produire de PDF.";
	else if (stripos($error, "Invalid document type attendanceregister") !== false)
	    $error .= "\nLe DocBuilder exécuté ne contient pas le type AttendanceRegister. "
		."Vérifie la présence de /usr/lib/docbuilder/documents/attendanceregister/BuildDocument.php "
		."et réinstalle la version patchée de DocBuilder.";
	return (["ok" => false, "error" => $error]);
    }
    return (["ok" => true, "content" => $content]);
}

function GenerateAttendanceRegisterWorkflow($id, $data, $method, $output, $module)
{
    if ($id == -1 || $module != "cycle")
        bad_request();

    // Final signatures are campaign-wide. Individual sheets remain available
    // as previews, but a definitive signature workflow always freezes the
    // complete cohort so staff members sign one stable manifest only once.
    if (!isset($data["all"]) || !attendance_register_truthy($data["all"]))
        return (new ErrorResponse("AttendanceRegisterCampaignOnly"));

    $context = attendance_register_cycle_data($id, NULL);
    if ($context === NULL)
        return (new ErrorResponse("AttendanceRegisterInvalidCycle"));
    if (!is_array($context["school"]))
        return (new ErrorResponse("AttendanceRegisterMissingSchool"));
    if (!is_array($context["director"]))
        return (new ErrorResponse("AttendanceRegisterMissingDirector"));
    if (!count($context["students"]))
        return (new ErrorResponse("AttendanceRegisterNoStudent"));

    $period = attendance_register_period($context["cycle"]);
    if ($period === NULL)
        return (new ErrorResponse("AttendanceRegisterInvalidCycle", "La date de début du cycle est absente ou invalide."));
    $issued_at = now();
    $period_end_exclusive = date_to_timestamp($period["end_exclusive"]->format("Y-m-d H:i:s"));
    if ($issued_at < $period_end_exclusive)
        return (new ErrorResponse("AttendanceRegisterNotFinal", "Le trimestre est encore en cours. Utilise la prévisualisation jusqu'à sa clôture."));

    $existing_campaign = attendance_register_find_campaign((int)$context["cycle"]["id"], $period);
    if (is_array($existing_campaign))
        return (new ErrorResponse(
            "AttendanceRegisterCampaignExists",
            (string)($existing_campaign["instance"]["Id"] ?? "")." (".(string)($existing_campaign["instance"]["Status"] ?? "").")"
        ));

    // Refuse to silently mix legacy per-student workflows with a new campaign.
    foreach ($context["students"] as $student)
        if (is_array(attendance_register_existing_workflow($student, (int)$context["cycle"]["id"])))
            return (new ErrorResponse("AttendanceRegisterWorkflowExists", (string)($student["codename"] ?? $student["id"])));

    $data_cutoff = $period_end_exclusive - 1;
    $document_info = [
        "IssueDate" => datex("d/m/Y H:i", $issued_at),
        "DataCutoff" => datex("d/m/Y H:i", $data_cutoff),
        "Status" => "Définitif",
        "InProgress" => false,
    ];
    $user_ids = array_map(function ($student) { return ((int)$student["id"]); }, $context["students"]);
    $cycle_director = array_replace(
        attendance_register_person_context($context["director"]),
        ["Role" => "Responsable de formation"]
    );
    $register_data = attendance_register_collect_days(
        $user_ids,
        (int)$context["cycle"]["id"],
        $period["start"],
        $period["end_exclusive"],
        $data_cutoff,
        $context["director"]
    );

    // Build every frozen leaf first. Their hashes are what the campaign
    // manifest certifies, so no sheet can change after a staff signature.
    $prepared = [];
    foreach ($context["students"] as $student)
    {
        $uid = (int)$student["id"];
        $ret = attendance_register_prepare_workflow_document(
            $context,
            $period,
            $document_info,
            $cycle_director,
            $student,
            $register_data["days"][$uid] ?? [],
            $register_data["totals"][$uid] ?? attendance_register_empty_totals(),
            $register_data["trainers"][$uid] ?? []
        );
        if ($ret->is_error())
            return ($ret);
        $prepared[] = $ret->value;
    }

    $campaign_trainers = attendance_register_campaign_trainers($register_data["trainers"] ?? []);
    $campaign = attendance_register_create_campaign_instance(
        $context, $period, $prepared, $cycle_director, $campaign_trainers
    );
    if ($campaign->is_error())
        return ($campaign);

    $created = [];
    foreach ($prepared as $entry)
    {
        $ret = attendance_register_create_workflow_instance(
            $context,
            $period,
            $entry,
            (int)$campaign->value["owner_user_id"],
            (string)$campaign->value["instance_id"],
            (string)$campaign->value["hash"],
            (array)$campaign->value["plan"]
        );
        if ($ret->is_error())
        {
            foreach ($created as $leaf)
                document_workflow_expire_instance((int)$leaf["student_id"], (string)$leaf["instance_id"]);
            document_workflow_expire_instance((int)$campaign->value["owner_user_id"], (string)$campaign->value["instance_id"]);
            return ($ret);
        }
        $created[] = $ret->value;
    }
    $linked = attendance_register_update_campaign_members(
        (int)$campaign->value["owner_user_id"],
        (string)$campaign->value["instance_id"],
        $created
    );
    if ($linked->is_error())
        return ($linked);

    add_log(TRACE, "Attendance register campaign created: cycle_id=".(int)$context["cycle"]["id"].
        ", campaign=".(string)$campaign->value["instance_id"].", sheets=".count($created));
    return (new ValueResponse([
        "msg" => "Campagne d'émargement créée : ".count($created)." feuille(s) élève(s), ".
            count(document_task_plan_normalize((array)$campaign->value["plan"]))." signature(s) globale(s) à recueillir.",
        "campaign" => [
            "owner_user_id" => (int)$campaign->value["owner_user_id"],
            "instance_id" => (string)$campaign->value["instance_id"],
        ],
        "created" => $created,
    ]));
}

function GenerateAttendanceRegister($id, $data, $method, $output, $module)
{
    if ($id == -1 || $module != "cycle")
	bad_request();

    $all = isset($data["all"]) && attendance_register_truthy($data["all"]);
    $id_student = $all ? NULL : (int)($data["student"] ?? 0);
    if (!$all && $id_student <= 0)
	bad_request();

    $context = attendance_register_cycle_data($id, $id_student);
    if ($context === NULL)
	return (new ErrorResponse("AttendanceRegisterInvalidCycle"));
    if (!is_array($context["school"]))
	return (new ErrorResponse("AttendanceRegisterMissingSchool"));
    if (!is_array($context["director"]))
	return (new ErrorResponse("AttendanceRegisterMissingDirector"));
    if (!count($context["students"]))
	return (new ErrorResponse("AttendanceRegisterNoStudent"));

    $period = attendance_register_period($context["cycle"]);
    if ($period === NULL)
	return (new ErrorResponse("AttendanceRegisterInvalidCycle", "La date de début du cycle est absente ou invalide."));

    $issued_at = now();
    $period_end_exclusive = date_to_timestamp($period["end_exclusive"]->format("Y-m-d H:i:s"));
    $is_final = $issued_at >= $period_end_exclusive;
    $data_cutoff = $is_final ? $period_end_exclusive - 1 : $issued_at;
    $document_info = [
	"IssueDate" => datex("d/m/Y H:i", $issued_at),
	"DataCutoff" => datex("d/m/Y H:i", $data_cutoff),
	"Status" => $is_final ? "Définitif" : "Provisoire — trimestre en cours",
	"InProgress" => !$is_final,
    ];

    $user_ids = array_map(function ($student) { return ((int)$student["id"]); }, $context["students"]);
    $fallback_trainer = $context["director"];
    $cycle_director = array_replace(
        attendance_register_person_context($context["director"]),
        ["Role" => "Responsable de formation"]
    );
    $register_data = attendance_register_collect_days(
	$user_ids,
	(int)$context["cycle"]["id"],
	$period["start"],
	$period["end_exclusive"],
	$data_cutoff,
	$fallback_trainer
    );
    $days = $register_data["days"];
    $totals = $register_data["totals"];
    $trainers = $register_data["trainers"];

    $configuration = [
	"Document" => "AttendanceRegister",
	"Title" => $all ? "Feuilles d'émargement trimestrielles" : "Feuille d'émargement trimestrielle",
	"Period" => $period["period"],
	"DocumentInfo" => $document_info,
	"Cycle" => attendance_register_cycle_context($context["cycle"]),
	"School" => attendance_register_school_context($context["school"]),
	"CycleDirector" => $cycle_director,
    ];

    if ($all)
    {
	$configuration["Registers"] = [];
	foreach ($context["students"] as $student)
        {
            $student_context = attendance_register_person_context($student);
            $student_trainers = $trainers[(int)$student["id"]] ?? [];
	    $configuration["Registers"][] = [
		"Reference" => attendance_register_reference($context["cycle"], $student),
		"Student" => $student_context,
		"Days" => $days[(int)$student["id"]] ?? [],
		"Totals" => $totals[(int)$student["id"]] ?? attendance_register_empty_totals(),
                "Trainers" => $student_trainers,
                "TaskPlan" => attendance_register_signature_task_plan($student_context, $cycle_director, $student_trainers),
	    ];
        }
    }
    else
    {
	$student = $context["students"][0];
        $student_context = attendance_register_person_context($student);
        $student_trainers = $trainers[(int)$student["id"]] ?? [];
	$configuration["Reference"] = attendance_register_reference($context["cycle"], $student);
	$configuration["Student"] = $student_context;
	$configuration["Days"] = $days[(int)$student["id"]] ?? [];
	$configuration["Totals"] = $totals[(int)$student["id"]] ?? attendance_register_empty_totals();
        $configuration["Trainers"] = $student_trainers;
        $configuration["TaskPlan"] = attendance_register_signature_task_plan($student_context, $cycle_director, $student_trainers);
    }

    $document = attendance_register_run_docbuilder($configuration);
    if (!$document["ok"])
	return (new ErrorResponse("AttendanceRegisterGenerationFailed", $document["error"]));

    $cycle_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string)$context["cycle"]["codename"]);
    if ($all)
	$filename = "attendance-registers-".$cycle_name.".pdf";
    else
    {
	$student_name = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', (string)$context["students"][0]["codename"]);
	$filename = "attendance-register-".$cycle_name."-".$student_name.".pdf";
    }

    add_log(TRACE, "Attendance register generated: cycle_id=".(int)$context["cycle"]["id"].", cycle_codename=".$context["cycle"]["codename"].", student_ids=".implode(",", $user_ids));
    return (new ValueResponse([
	"filename" => $filename,
	"content_type" => "application/pdf",
	"disposition" => "inline",
	"content" => $document["content"],
    ]));
}
