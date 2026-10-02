<?php

function user_school_authority_is_numeric()
{
    global $Database;

    static $numeric = NULL;
    if ($numeric !== NULL)
        return ($numeric);

    $dbname = $Database->real_escape_string($Database->dbname);
    $column = db_select_one("
        DATA_TYPE as data_type
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '$dbname'
          AND TABLE_NAME = 'user_school'
          AND COLUMN_NAME = 'authority'
    ");
    $type = strtolower((string)($column["data_type"] ?? ""));
    $numeric = in_array($type, [
        "tinyint", "smallint", "mediumint", "int", "integer", "bigint",
        "decimal", "numeric"
    ], true);
    return ($numeric);
}

function user_school_authority_numeric_value($authority)
{
    static $map = [
        "STUDENT" => 0,
        "DIRECTOR" => 1,
        "SECRETARIAT" => 2,
        "COMMERCIAL" => 3,
        "TEACHER" => 4,
        "LIBRARIAN" => 5,
        "ACCOUNTANT" => 6,
    ];
    $authority = strtoupper(trim((string)$authority));
    return ($map[$authority] ?? NULL);
}

function user_school_authority_value($authority)
{
    $authority = strtoupper(trim((string)$authority));
    if (!user_school_authority_is_numeric())
        return ($authority);
    $numeric = user_school_authority_numeric_value($authority);
    return ($numeric === NULL ? $authority : $numeric);
}

function user_school_student_authority_value()
{
    return (user_school_authority_value("STUDENT"));
}

function user_school_authority_sql($authority)
{
    global $Database;

    $value = user_school_authority_value($authority);
    if (user_school_authority_is_numeric())
        return ((string)(int)$value);
    return ("'".$Database->real_escape_string((string)$value)."'");
}

function user_school_student_authority_sql()
{
    return (user_school_authority_sql("STUDENT"));
}

/**
 * Return active members of a school having the requested school-level role.
 *
 * Keep the authority comparison centralized here: deployed databases still
 * exist with both the historical integer authority column and the newer
 * symbolic values.
 */
function user_school_members_by_authority($id_school, $authority)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return ([]);
    $authority = user_school_authority_sql($authority);
    return (db_select_all("
        DISTINCT user.id, user.codename, user.nickname, user.first_name, user.use_name, user.family_name,
        user.birth_date, user.administrative_data
        FROM user_school
        INNER JOIN user ON user.id = user_school.id_user
        WHERE user_school.id_school = $id_school
          AND user_school.authority = $authority
          AND user.deleted IS NULL
          AND user.profile_status != 'jury'
        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC
    "));
}

function user_school_teacher_names($id_school)
{
    $out = [];
    foreach (user_school_members_by_authority($id_school, "TEACHER") as $teacher)
    {
        $name = trim((string)($teacher["first_name"] ?? "")." ".(string)($teacher["family_name"] ?? ""));
        if ($name == "")
            $name = trim((string)($teacher["nickname"] ?? ""));
        if ($name == "")
            $name = trim((string)($teacher["codename"] ?? ""));
        if ($name != "" && !in_array($name, $out, true))
            $out[] = $name;
    }
    return ($out);
}

function user_school_teacher_list($id_school)
{
    $names = user_school_teacher_names($id_school);
    if (!count($names))
        return ("");
    return ("• ".implode("\n\n• ", $names));
}

function user_school_rectorate_administrative_value(array $administrative, array $keys)
{
    $sources = [$administrative];
    if (isset($administrative["DocumentContext"]) && is_array($administrative["DocumentContext"]))
        $sources[] = $administrative["DocumentContext"];
    foreach ($sources as $source)
        foreach ($keys as $key)
            if (array_key_exists($key, $source) && !is_array($source[$key]) && !is_object($source[$key]))
                return (trim((string)$source[$key]));
    return ("");
}

function user_school_rectorate_date_label($value)
{
    $value = trim((string)$value);
    if ($value === "")
        return ("");
    $timestamp = strtotime($value);
    return ($timestamp === false ? $value : date("d/m/Y", $timestamp));
}

function user_school_rectorate_school_year($timestamp = NULL)
{
    if ($timestamp === NULL)
        $timestamp = time();
    $year = (int)date("Y", $timestamp);
    if ((int)date("n", $timestamp) < 9)
        $year--;
    return ([
        "label" => $year."-".($year + 1),
        "start" => sprintf("%04d-09-01 00:00:00", $year),
        "end" => sprintf("%04d-09-01 00:00:00", $year + 1),
    ]);
}

function user_school_rectorate_teacher_link_sql($alias, $id_user)
{
    $id_user = (int)$id_user;
    $lab_alias = preg_replace('/[^a-zA-Z0-9_]/', '_', $alias)."_lab_member";
    return ("(
        $alias.id_user = $id_user
        OR ($alias.id_laboratory IS NOT NULL AND EXISTS (
            SELECT 1
            FROM user_laboratory AS $lab_alias
            WHERE $lab_alias.id_laboratory = $alias.id_laboratory
              AND $lab_alias.id_user = $id_user
              AND $lab_alias.authority = 2
        ))
    )");
}

function user_school_rectorate_activity_name_sql($alias = "rectorate_activity", $template_alias = "rectorate_activity_template")
{
    return ("COALESCE(
        NULLIF($template_alias.fr_name, ''),
        NULLIF($alias.fr_name, ''),
        NULLIF($template_alias.codename, ''),
        $alias.codename
    )");
}


/**
 * Resolve an activity to its carrying matter. In Infosphere, a matter is an
 * activity whose parent_activity is NULL/-1; pedagogical activities below it
 * must not appear as separate "enseignements" in the rectorate roster.
 */
function user_school_rectorate_matter_name_sql(
    $alias = "rectorate_activity",
    $template_alias = "rectorate_activity_template",
    $matter_alias = "rectorate_matter",
    $matter_template_alias = "rectorate_matter_template"
)
{
    return ("COALESCE(
        NULLIF($matter_template_alias.fr_name, ''),
        NULLIF($matter_alias.fr_name, ''),
        NULLIF($matter_template_alias.codename, ''),
        NULLIF($matter_alias.codename, ''),
        NULLIF($template_alias.fr_name, ''),
        NULLIF($alias.fr_name, ''),
        NULLIF($template_alias.codename, ''),
        $alias.codename
    )");
}

function user_school_rectorate_cycle_name_sql($alias = "rectorate_cycle", $template_alias = "rectorate_cycle_template")
{
    return ("COALESCE(
        NULLIF($template_alias.fr_name, ''),
        NULLIF($alias.fr_name, ''),
        NULLIF($template_alias.codename, ''),
        $alias.codename
    )");
}

function user_school_rectorate_clean_value($value)
{
    $value = preg_replace('/[\\r\\n\\t]+/u', ' ', trim((string)$value));
    $value = preg_replace('/\\s{2,}/u', ' ', $value);
    // Dabsic tables use | as a cell delimiter.
    return (str_replace('|', '/', $value));
}

function user_school_rectorate_unique_labels(array $rows, $field)
{
    $out = [];
    foreach ($rows as $row)
    {
        $value = user_school_rectorate_clean_value($row[$field] ?? "");
        if ($value !== "" && !in_array($value, $out, true))
            $out[] = $value;
    }
    if (function_exists("natcasesort"))
        natcasesort($out);
    return (array_values($out));
}

/**
 * Return the teaching/formation information that can be established from the
 * school's planning data for one teacher during the current academic year.
 *
 * The rectorate list required by R.913-27 is person-based.  Laboratory links
 * are therefore expanded only to members having the laboratory professor level
 * (authority = 2), consistently with the rest of Infosphere's teaching model.
 */
function user_school_rectorate_teacher_activity($id_school, $id_user, array $period)
{
    $id_school = (int)$id_school;
    $id_user = (int)$id_user;
    $start = $period["start"];
    $end = $period["end"];
    $activity_name = user_school_rectorate_matter_name_sql();
    $cycle_name = user_school_rectorate_cycle_name_sql();
    $activity_teacher = user_school_rectorate_teacher_link_sql("rectorate_activity_teacher", $id_user);
    $session_teacher = user_school_rectorate_teacher_link_sql("rectorate_session_teacher", $id_user);
    $cycle_teacher = user_school_rectorate_teacher_link_sql("rectorate_cycle_teacher", $id_user);

    // Activity-level assignments are the primary source for the teaching
    // actually entrusted to a person. Parent/template/reference links mirror
    // fetch_teacher(..., gather=true) closely enough for document reporting.
    $assigned = db_select_all("
        DISTINCT
        $activity_name AS teaching,
        $cycle_name AS formation
        FROM activity AS rectorate_activity
        LEFT JOIN activity AS rectorate_activity_template
          ON rectorate_activity_template.id = rectorate_activity.id_template
        LEFT JOIN activity AS rectorate_matter
          ON rectorate_matter.id = rectorate_activity.parent_activity
         AND rectorate_activity.parent_activity IS NOT NULL
         AND rectorate_activity.parent_activity != -1
        LEFT JOIN activity AS rectorate_matter_template
          ON rectorate_matter_template.id = rectorate_matter.id_template
        INNER JOIN activity_cycle AS rectorate_activity_cycle
          ON rectorate_activity_cycle.id_activity = rectorate_activity.id
          OR rectorate_activity_cycle.id_activity = rectorate_activity.parent_activity
        INNER JOIN cycle AS rectorate_cycle
          ON rectorate_cycle.id = rectorate_activity_cycle.id_cycle
        LEFT JOIN cycle AS rectorate_cycle_template
          ON rectorate_cycle_template.id = rectorate_cycle.id_template
        INNER JOIN school_cycle AS rectorate_school_cycle
          ON rectorate_school_cycle.id_cycle = rectorate_cycle.id
        INNER JOIN activity_teacher AS rectorate_activity_teacher
          ON rectorate_activity_teacher.id_activity IN (
              rectorate_activity.id,
              rectorate_activity.parent_activity,
              rectorate_activity.id_template,
              rectorate_activity.reference_activity
          )
        WHERE rectorate_school_cycle.id_school = $id_school
          AND rectorate_activity.deleted IS NULL
          AND rectorate_cycle.deleted IS NULL
          AND rectorate_cycle.first_day >= '$start'
          AND rectorate_cycle.first_day < '$end'
          AND $activity_teacher
    ");

    // A session can override/add its teachers. Keep those explicit assignments
    // even when no activity_teacher row exists.
    $scheduled = db_select_all("
        DISTINCT
        $activity_name AS teaching,
        $cycle_name AS formation
        FROM session AS rectorate_session
        INNER JOIN activity AS rectorate_activity
          ON rectorate_activity.id = rectorate_session.id_activity
        LEFT JOIN activity AS rectorate_activity_template
          ON rectorate_activity_template.id = rectorate_activity.id_template
        LEFT JOIN activity AS rectorate_matter
          ON rectorate_matter.id = rectorate_activity.parent_activity
         AND rectorate_activity.parent_activity IS NOT NULL
         AND rectorate_activity.parent_activity != -1
        LEFT JOIN activity AS rectorate_matter_template
          ON rectorate_matter_template.id = rectorate_matter.id_template
        INNER JOIN activity_cycle AS rectorate_activity_cycle
          ON rectorate_activity_cycle.id_activity = rectorate_activity.id
          OR rectorate_activity_cycle.id_activity = rectorate_activity.parent_activity
        INNER JOIN cycle AS rectorate_cycle
          ON rectorate_cycle.id = rectorate_activity_cycle.id_cycle
        LEFT JOIN cycle AS rectorate_cycle_template
          ON rectorate_cycle_template.id = rectorate_cycle.id_template
        INNER JOIN school_cycle AS rectorate_school_cycle
          ON rectorate_school_cycle.id_cycle = rectorate_cycle.id
        INNER JOIN session_teacher AS rectorate_session_teacher
          ON rectorate_session_teacher.id_session = rectorate_session.id
        WHERE rectorate_school_cycle.id_school = $id_school
          AND rectorate_session.deleted IS NULL
          AND rectorate_activity.deleted IS NULL
          AND rectorate_cycle.deleted IS NULL
          AND rectorate_session.begin_date >= '$start'
          AND rectorate_session.begin_date < '$end'
          AND $session_teacher
    ");

    $cycle_rows = db_select_all("
        DISTINCT
        $cycle_name AS formation
        FROM cycle_teacher AS rectorate_cycle_teacher
        INNER JOIN cycle AS rectorate_cycle
          ON rectorate_cycle.id = rectorate_cycle_teacher.id_cycle
        LEFT JOIN cycle AS rectorate_cycle_template
          ON rectorate_cycle_template.id = rectorate_cycle.id_template
        INNER JOIN school_cycle AS rectorate_school_cycle
          ON rectorate_school_cycle.id_cycle = rectorate_cycle.id
        WHERE rectorate_school_cycle.id_school = $id_school
          AND rectorate_cycle.deleted IS NULL
          AND rectorate_cycle.first_day >= '$start'
          AND rectorate_cycle.first_day < '$end'
          AND $cycle_teacher
    ");

    $rows = array_merge(is_array($assigned) ? $assigned : [], is_array($scheduled) ? $scheduled : []);
    $teachings = user_school_rectorate_unique_labels($rows, "teaching");
    $formations = user_school_rectorate_unique_labels(array_merge($rows, is_array($cycle_rows) ? $cycle_rows : []), "formation");

    // Count each session at most once. Activity-level teachers are implicit
    // session teachers in Infosphere; explicit session_teacher rows are the
    // other accepted source.
    $session_activity_teacher = user_school_rectorate_teacher_link_sql("rectorate_volume_activity_teacher", $id_user);
    $session_explicit_teacher = user_school_rectorate_teacher_link_sql("rectorate_volume_session_teacher", $id_user);
    $sessions = db_select_all("
        DISTINCT
        rectorate_volume_session.id,
        rectorate_volume_session.begin_date,
        rectorate_volume_session.end_date
        FROM session AS rectorate_volume_session
        INNER JOIN activity AS rectorate_volume_activity
          ON rectorate_volume_activity.id = rectorate_volume_session.id_activity
        WHERE rectorate_volume_session.deleted IS NULL
          AND rectorate_volume_activity.deleted IS NULL
          AND rectorate_volume_session.begin_date >= '$start'
          AND rectorate_volume_session.begin_date < '$end'
          AND EXISTS (
              SELECT 1
              FROM activity_cycle AS rectorate_volume_activity_cycle
              INNER JOIN school_cycle AS rectorate_volume_school_cycle
                ON rectorate_volume_school_cycle.id_cycle = rectorate_volume_activity_cycle.id_cycle
              WHERE rectorate_volume_school_cycle.id_school = $id_school
                AND (
                    rectorate_volume_activity_cycle.id_activity = rectorate_volume_activity.id
                    OR rectorate_volume_activity_cycle.id_activity = rectorate_volume_activity.parent_activity
                )
          )
          AND (
              EXISTS (
                  SELECT 1
                  FROM session_teacher AS rectorate_volume_session_teacher
                  WHERE rectorate_volume_session_teacher.id_session = rectorate_volume_session.id
                    AND $session_explicit_teacher
              )
              OR EXISTS (
                  SELECT 1
                  FROM activity_teacher AS rectorate_volume_activity_teacher
                  WHERE rectorate_volume_activity_teacher.id_activity IN (
                      rectorate_volume_activity.id,
                      rectorate_volume_activity.parent_activity,
                      rectorate_volume_activity.id_template,
                      rectorate_volume_activity.reference_activity
                  )
                    AND $session_activity_teacher
              )
          )
    ");

    $seconds = 0;
    foreach (is_array($sessions) ? $sessions : [] as $session)
    {
        $begin = strtotime((string)($session["begin_date"] ?? ""));
        $finish = strtotime((string)($session["end_date"] ?? ""));
        if ($begin !== false && $finish !== false && $finish > $begin)
            $seconds += $finish - $begin;
    }
    $hours = $seconds > 0 ? $seconds / 3600 : 0.0;
    if ($hours > 0)
    {
        $formatted = number_format(round($hours, 2), 2, ",", " ");
        $formatted = rtrim(rtrim($formatted, "0"), ",");
        $volume = $formatted." h";
    }
    else
        $volume = "À vérifier";

    return ([
        "teachings" => $teachings,
        "formations" => $formations,
        "volume" => $volume,
        "session_count" => count(is_array($sessions) ? $sessions : []),
    ]);
}

/**
 * Context used by the annual rectorate list of persons exercising teaching
 * functions in a private technical higher-education institution (R.913-27).
 * Identity comes strictly from user_school(TEACHER); pedagogical details are
 * derived from cycle/activity/session assignments of the current school year.
 */
function user_school_rectorate_teacher_context($id_school)
{
    $teachers = user_school_members_by_authority($id_school, "TEACHER");
    $period = user_school_rectorate_school_year();
    $out = [
        "count" => count($teachers),
        "generated_date" => date("d/m/Y"),
        "school_year" => $period["label"],
        "period_start" => user_school_rectorate_date_label($period["start"]),
        "period_end" => user_school_rectorate_date_label(date("Y-m-d H:i:s", strtotime($period["end"]." -1 day"))),
        "missing_entry_date_count" => 0,
        "missing_teaching_count" => 0,
        "unverified_volume_count" => 0,
        // Keep the rows as one semantic array. DocBuilder's TeacherList
        // renderer iterates this structure directly, so the Dabsic model no
        // longer needs a fixed Row01..Row64 expansion (and has no row limit).
        // Named keys are deliberate: document_context_data_scope_file() emits
        // associative Dabsic scopes, which mergeconf preserves without relying
        // on numeric scope identifiers.
        "teachers" => [],
    ];

    foreach ($teachers as $teacher)
    {
        $administrative = json_decode((string)($teacher["administrative_data"] ?? "{}"), true);
        if (!is_array($administrative))
            $administrative = [];
        $entry_date = user_school_rectorate_date_label(user_school_rectorate_administrative_value($administrative, [
            "RectoratTeachingStartDate", "RectoratEntryDate", "TeachingStartDate", "TeacherStartDate",
        ]));
        if ($entry_date === "")
        {
            $entry_date = "À renseigner";
            $out["missing_entry_date_count"]++;
        }

        $teaching = user_school_rectorate_teacher_activity((int)$id_school, (int)$teacher["id"], $period);
        $teachings = $teaching["teachings"] ?? [];
        $formations = $teaching["formations"] ?? [];
        if (!count($teachings))
            $out["missing_teaching_count"]++;
        if (($teaching["volume"] ?? "") === "À vérifier")
            $out["unverified_volume_count"]++;

        $teacher_key = "teacher_".(int)$teacher["id"];
        if (isset($out["teachers"][$teacher_key]))
            $teacher_key .= "_".(count($out["teachers"]) + 1);
        $out["teachers"][$teacher_key] = [
            "family_name" => user_school_rectorate_clean_value($teacher["family_name"] ?? ""),
            "first_name" => user_school_rectorate_clean_value($teacher["first_name"] ?? ""),
            "teachings" => count($teachings) ? implode(" ; ", $teachings) : "À renseigner",
            "formations" => count($formations) ? implode(" ; ", $formations) : "À renseigner",
            "volume" => user_school_rectorate_clean_value($teaching["volume"] ?? "À vérifier"),
            "entry_date" => user_school_rectorate_clean_value($entry_date),
        ];
    }
    return ($out);
}

function get_user_school(array &$usr, $by_name = false)
{
    global $Database;
    global $Language;

    if (!isset($usr["id"]) || !is_number($usr["id"]))
	return ([]);
    if (isset($usr["school"]))
	return ($usr["school"]);
    $forge = "
        school.id as id_school,
	school.codename as codename,
	COALESCE(
	    NULLIF(organization.{$Language}_name, ''),
	    NULLIF(organization.name, ''),
	    NULLIF(organization.legal_name, ''),
	    school.codename
	) as name,
        user_school.authority as authority,
        user_school.id as id
        FROM school
        LEFT JOIN organization
        ON organization.id = school.id_organization
        LEFT JOIN user_school
        ON user_school.id_school = school.id
        WHERE user_school.id_user = ".$usr["id"]." AND school.deleted IS NULL
	";
    if (count($usr["school"] = db_select_all($forge, $by_name ? "codename" : "")))
	$usr["last_school"] =
	    $usr["school"][array_key_first($usr["school"])]["codename"];
    else
	$usr["last_school"] = NULL;
    $constants = [
	"STUDENT",
	"DIRECTOR",
	"SECRETARIAT",
	"COMMERCIAL",
	"TEACHER",
	"LIBRARIAN",
	"ACCOUNTANT",
    ];

    $usr["school_authority"] = $constants[0];
    foreach ($usr["school"] as $school)
    {
	if ($school["authority"] !== "STUDENT")
	{
	    if (is_number($school["authority"]))
		$usr["school_authority"] = $constants[$school["authority"]];
	    else
		$usr["school_authority"] = $school["authority"];
	}
    }
    
    // TEMPORAIRE
    if (!count($usr["school"]))
    {
	$usr["school"]["efrits"]["id"] = -1;
	$usr["school"]["efrits"]["id_school"] = -1;
	$usr["school"]["efrits"]["codename"] = "efrits";
	$usr["school"]["efrits"]["name"] = "efrits";
	$usr["school"]["efrits"]["authority"] = "DIRECTOR";
	$usr["last_school"] = "efrits";
    }

    return ($usr["school"]);
}

