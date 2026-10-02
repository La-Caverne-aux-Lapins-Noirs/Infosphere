<?php

require_once (__DIR__."/school_activity.php");

function fetch_school($id = -1)
{
    global $Language;
    global $Configuration;

    if ($id !== -1 && $id != "")
    {
	if (($id = resolve_codenamef("school", $id))->is_error())
	    return ($id);
	$id = (int)$id->value;
	$id = " AND school.id = $id ";
    }
    else
	$id = "";

    $out = db_select_all("
       school.*,
       school.address as school_address,
       school.phone as school_phone,
       school.mail as school_mail,
       organization.id as id_organization,
       organization.codename as organization_codename,
       organization.name as organization_name,
       organization.fr_name as fr_name,
       organization.en_name as en_name,
       organization.{$Language}_name as name,
       organization.legal_name as legal_name,
       organization.address as organization_address,
       organization.phone as organization_phone,
       organization.mail as organization_mail,
       organization.website as website,
       organization.siret as siret,
       organization.registration_registry as registration_registry,
       organization.registration_number as registration_number,
       organization.share_capital as share_capital,
       organization.head_office_address_line1 as head_office_address_line1,
       organization.head_office_address_line2 as head_office_address_line2,
       organization.head_office_zipcode as head_office_zipcode,
       organization.head_office_city as head_office_city,
       organization.head_office_country as head_office_country,
       organization.billing_information as organization_billing_information,
       COALESCE(NULLIF(school.address, ''), NULLIF(organization.address, ''), '') as address,
       COALESCE(NULLIF(school.phone, ''), NULLIF(organization.phone, ''), '') as phone,
       COALESCE(NULLIF(school.mail, ''), NULLIF(organization.mail, ''), '') as mail
       FROM school
       LEFT JOIN organization ON organization.id = school.id_organization
       WHERE school.deleted IS NULL $id
       ORDER BY school.codename
    ");
    $school_authority_student = user_school_authority_sql("STUDENT");
    $school_authority_director = user_school_authority_sql("DIRECTOR");
    $school_authority_secretariat = user_school_authority_sql("SECRETARIAT");
    $school_authority_commercial = user_school_authority_sql("COMMERCIAL");
    $school_authority_teacher = user_school_authority_sql("TEACHER");
    $school_authority_librarian = user_school_authority_sql("LIBRARIAN");
    $school_authority_accountant = user_school_authority_sql("ACCOUNTANT");

    foreach ($out as &$v)
    {
	if (($v["name"] ?? "") == "")
	    $v["name"] = $v["organization_name"] ?? $v["codename"];
	$v["icon"] = $Configuration->SchoolsDir($v["codename"]);
	$v["cycle"] = db_select_all("
           cycle.id as id, cycle.id as id_cycle,
           cycle.codename, cycle.{$Language}_name as name
           FROM school_cycle LEFT JOIN cycle ON cycle.id = school_cycle.id_cycle
           WHERE id_school = ".$v["id"]."
           AND first_day > '".db_form_date(now() - 60 * 60 * 24 * 7 * 16)."'
           AND done IS NULL
	   ");
	// Un peu naze comme systeme - mais requis par list_of_links qui utilise
	// id_type_du_mec
	$v["user"] = db_select_all("
           user.id as id, user.id as id_user, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = ".$school_authority_student."
	   AND user.deleted IS NULL
	   AND user.profile_status = 'member'
	   ");
	$v["director"] = db_select_all("
           user.id as id, user.id as id_director, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = ".$school_authority_director."
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["secretariat"] = db_select_all("
           user.id as id, user.id as id_secretariat, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = ".$school_authority_secretariat."
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["commercial"] = db_select_all("
           user.id as id, user.id as id_commercial, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = ".$school_authority_commercial."
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["librarian"] = db_select_all("
           user.id as id, user.id as id_librarian, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = ".$school_authority_librarian."
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["accountant"] = db_select_all("
           user.id as id, user.id as id_accountant, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = ".$school_authority_accountant."
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["mailboxes"] = function_exists("school_mailbox_list") ? school_mailbox_list((int)$v["id"]) : [];
	$v["teacher"] = db_select_all("
           user.id as id, user.id as id_teacher, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = ".$school_authority_teacher."
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");

        // Responsibilities are needed by the detailed school page only.
        // Do not multiply queries when fetch_school() is used merely to list
        // all schools elsewhere in the application.
        if ($id != "")
        {
            // Responsibilities are cumulative and deliberately separate from
            // the structural user_school authority. This also allows a student
            // to be designated for fire safety without turning them into staff.
            $v["responsibility"] = school_responsibility_members((int)$v["id"]);
            $v["responsibilities_by_user"] = school_responsibility_codes_by_user((int)$v["id"]);
            foreach (["user", "director", "secretariat", "commercial", "librarian", "accountant", "teacher"] as $people_field)
                school_responsibility_attach_to_members($v[$people_field], $v["responsibilities_by_user"]);
            break ;
        }
    }
    return ($id == "" ? $out : (count($out) ? $out[0] : []));
}
