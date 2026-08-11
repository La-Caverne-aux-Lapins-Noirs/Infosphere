<?php

require_once (__DIR__."/student_log.php");
require_once (__DIR__."/halfday_presence.php");
require_once (__DIR__."/document_context.php");

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
    $context["NDA"] = trim((string)($full["formation_activity_number"] ?? ""));
    $context["UAI"] = trim((string)($full["uai"] ?? ""));
    $context["cfa_name"] = trim((string)($full["cfa_name"] ?? ""));
    $context["executing_establishment_name"] = trim((string)($full["executing_establishment_name"] ?? ""));

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
	    $configuration["Registers"][] = [
		"Reference" => attendance_register_reference($context["cycle"], $student),
		"Student" => attendance_register_person_context($student),
		"Days" => $days[(int)$student["id"]] ?? [],
		"Totals" => $totals[(int)$student["id"]] ?? attendance_register_empty_totals(),
                "Trainers" => $trainers[(int)$student["id"]] ?? [],
	    ];
    }
    else
    {
	$student = $context["students"][0];
	$configuration["Reference"] = attendance_register_reference($context["cycle"], $student);
	$configuration["Student"] = attendance_register_person_context($student);
	$configuration["Days"] = $days[(int)$student["id"]] ?? [];
	$configuration["Totals"] = $totals[(int)$student["id"]] ?? attendance_register_empty_totals();
        $configuration["Trainers"] = $trainers[(int)$student["id"]] ?? [];
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
