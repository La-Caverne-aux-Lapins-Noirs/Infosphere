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

