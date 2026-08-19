<?php

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
       COALESCE(NULLIF(school.address, ''), NULLIF(organization.address, ''), '') as address,
       COALESCE(NULLIF(school.phone, ''), NULLIF(organization.phone, ''), '') as phone,
       COALESCE(NULLIF(school.mail, ''), NULLIF(organization.mail, ''), '') as mail
       FROM school
       LEFT JOIN organization ON organization.id = school.id_organization
       WHERE school.deleted IS NULL $id
       ORDER BY school.codename
    ");
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
           WHERE id_school = ".$v["id"]." AND user_school.authority = 'STUDENT'
	   AND user.deleted IS NULL
	   AND user.profile_status = 'member'
	   ");
	$v["director"] = db_select_all("
           user.id as id, user.id as id_director, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = 'DIRECTOR'
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["secretariat"] = db_select_all("
           user.id as id, user.id as id_secretariat, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = 'SECRETARIAT'
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["commercial"] = db_select_all("
           user.id as id, user.id as id_commercial, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = 'COMMERCIAL'
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["librarian"] = db_select_all("
           user.id as id, user.id as id_librarian, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = 'LIBRARIAN'
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	$v["mailboxes"] = function_exists("school_mailbox_list") ? school_mailbox_list((int)$v["id"]) : [];
	$v["teacher"] = db_select_all("
           user.id as id, user.id as id_teacher, user.codename as codename
           FROM user_school LEFT JOIN user ON user_school.id_user = user.id
           WHERE id_school = ".$v["id"]." AND user_school.authority = 'TEACHER'
	   AND user.deleted IS NULL
	   AND user.profile_status != 'jury'
	   ");
	if ($id != "")
	    break ;
    }
    return ($id == "" ? $out : (count($out) ? $out[0] : []));
}
