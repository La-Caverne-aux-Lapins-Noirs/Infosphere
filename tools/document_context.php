<?php

require_once (__DIR__."/document_context_schema.php");
require_once (__DIR__."/school_activity.php");

function document_context_sanitize_key($key)
{
    $key = preg_replace('/[^a-zA-Z0-9_\.]/', '_', trim((string)$key));
    $key = preg_replace('/_+/', '_', $key);
    $key = preg_replace('/\.+/', '.', $key);
    return (trim($key, "._"));
}

function document_context_pascal_key($key)
{
    $key = document_context_sanitize_key($key);
    if ($key == "")
        return ("");
    if (function_exists("snakecase_to_pascalcase"))
        return (snakecase_to_pascalcase($key));
    $out = "";
    foreach (explode("_", $key) as $part)
    {
        if ($part == "")
            continue ;
        $out .= strtoupper($part[0]).substr($part, 1);
    }
    return ($out);
}

function document_context_scalar($value)
{
    if ($value === NULL)
        return ("");
    if (is_bool($value))
        return ($value ? "1" : "0");
    if (is_scalar($value))
        return ((string)$value);
    return (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function document_context_flatten(&$fields, $prefix, $data)
{
    $prefix = document_context_sanitize_key($prefix);
    if ($prefix == "")
        return ;
    if ($data instanceof ValueResponse)
        $data = $data->value;
    if (is_object($data))
        $data = get_object_vars($data);
    if (!is_array($data))
    {
	if ($prefix != "")
	    $fields[] = $prefix."=".document_context_scalar($data);
        return ;
    }
    foreach ($data as $key => $value)
    {
        $key = document_context_pascal_key($key);
        if ($key == "")
            continue ;
        $field = $prefix == "" ? $key : $prefix.".".$key;
        if (is_array($value) || is_object($value))
            document_context_flatten($fields, $field, $value);
        else
            $fields[] = $field."=".document_context_scalar($value);
    }
}

function document_context_user($id)
{
    if (($user = fetch_user($id))->is_error())
        return (NULL);
    return ($user->value);
}

function document_context_person($id)
{
    if (($user = document_context_user($id)) == NULL)
        return (NULL);
    if (function_exists("document_builder_person_context"))
        $person = document_builder_person_context($user);
    else
        $person = $user;

    $identity = function_exists("document_builder_name") ? document_builder_name($user) : trim(@$user["first_name"]." ".@$user["family_name"]);
    if ($identity == "")
        $identity = @$user["codename"];
    $person["name"] = $identity;
    $person["identity"] = $identity;
    if (!isset($person["street"]))
        $person["street"] = $person["address"] ?? ($user["street_name"] ?? "");
    if (!isset($person["address"]))
        $person["address"] = $person["street"] ?? "";
    if (!isset($person["city"]))
        $person["city"] = $user["city"] ?? "";
    if (!isset($person["postal_code"]))
        $person["postal_code"] = $user["postal_code"] ?? "";
    $person["postal_city"] = trim(($person["postal_code"] ?? "")." ".($person["city"] ?? ""));
    // Keep the document field stable even before a signature is configured.
    // This prevents DocBuilder forms from treating Analyst.Signature as an
    // ordinary value the operator could type manually.
    if (!isset($person["signature"]))
    {
        $signature = function_exists("user_identity_document_signature_file")
            ? user_identity_document_signature_file($user) : "";
        $person["signature"] = ($signature != "" && is_file($signature)) ? $signature : "";
    }
    if (!isset($person["initials"]))
    {
        $initials = function_exists("user_identity_document_initials_file")
            ? user_identity_document_initials_file($user) : "";
        $person["initials"] = ($initials != "" && is_file($initials)) ? $initials : "";
    }
    if (function_exists("jury_user_title_context") && isset($user["id"]))
        $person = array_merge($person, jury_user_title_context($user["id"]));
    return ($person);
}


function document_context_school_legal_city(array $school)
{
    $city = "";
    $id_organization = (int)($school["id_organization"] ?? 0);

    if ($id_organization > 0 && function_exists("fetch_enterprises"))
    {
        $organization = fetch_enterprises($id_organization);
        if (is_array($organization))
            $city = trim((string)($organization["head_office_city"] ?? ($organization["city"] ?? "")));
    }

    // Compatibilité avec les anciennes organisations qui n'ont pas encore
    // leurs champs de siège structurés : on n'analyse que l'adresse juridique,
    // jamais l'adresse d'enseignement de l'école.
    if ($city == "")
    {
        $address = trim((string)($school["organization_address"] ?? ""));
        if ($address != "" && preg_match('/(?:^|\R|,\s*)\d{5}\s+([^,\r\n]+)\s*$/u', $address, $match))
            $city = trim($match[1]);
    }
    return ($city);
}

function document_context_school($id)
{
    if (($school = fetch_school($id)) instanceof ErrorResponse)
        return (NULL);
    if (!is_array($school))
        return (NULL);
    if (function_exists("refresh_school"))
        refresh_school($school);
    if (function_exists("document_builder_school_context"))
        $out = document_builder_school_context($school);
    else
        $out = $school;

    // School représente l'établissement, tandis que School.Organization porte
    // la personne morale. On garde quelques alias juridiques au premier niveau
    // pour les anciens documents, mais sans perdre la séparation sémantique.
    $organization_legal_address = function_exists("enterprise_legal_address")
        ? enterprise_legal_address($school) : ($school["organization_address"] ?? "");
    $organization = [
        "id_organization" => $school["id_organization"] ?? -1,
        "organization_codename" => $school["organization_codename"] ?? "",
        "organization_name" => $school["organization_name"] ?? "",
        "legal_name" => $school["legal_name"] ?? "",
        "legal_address" => $organization_legal_address,
        "SIRET" => $school["siret"] ?? "",
        "website" => $school["website"] ?? "",
        "registration_registry" => $school["registration_registry"] ?? "",
        "registration_number" => $school["registration_number"] ?? "",
        "share_capital" => $school["share_capital"] ?? "",
    ];
    $out = array_replace($organization, $out);

    $out["name"] = $out["name"] ?? ($school["fr_name"] ?? ($school["codename"] ?? ""));
    $out["legal_name"] = $out["legal_name"] ?? ($school["legal_name"] ?? ($out["name"] ?? ""));
    $out["legal_city"] = document_context_school_legal_city($school);
    $out["address"] = $out["address"] ?? ($school["address"] ?? "");
    $out["training_address"] = $school["school_address"] ?? ($school["address"] ?? "");
    $out["street"] = $out["street"] ?? $out["address"];
    $out["city"] = $out["city"] ?? "";
    $out["phone"] = $out["phone"] ?? ($school["phone"] ?? "");
    $out["mail"] = $out["mail"] ?? ($school["mail"] ?? "");
    $out["billing_information"] = $out["billing_information"] ?? ($school["organization_billing_information"] ?? "");
    $out["RIB"] = $out["RIB"] ?? $out["billing_information"];
    $out = array_merge($out, school_activity_flags($school));
    $out = array_merge($out, school_activity_document_fields($school));
    $out["main_info"] = function_exists("enterprise_main_info") ? enterprise_main_info($school) : (function_exists("school_main_info") ? school_main_info($school) : ($school["main_info"] ?? ""));
    $out["organization_main_info"] = $out["main_info"];
    $out["organization_phone"] = $school["organization_phone"] ?? "";
    $out["organization_mail"] = $school["organization_mail"] ?? "";
    $out["organization_legal_address"] = $organization_legal_address;
    $out["school_info"] = function_exists("school_private_school_info") ? school_private_school_info($school) : ($school["school_info"] ?? "");
    $out["formation_info"] = function_exists("school_formation_info") ? school_formation_info($school) : ($school["formation_info"] ?? "");
    $out["alternation_info"] = function_exists("school_alternation_info") ? school_alternation_info($school) : ($school["alternation_info"] ?? "");
    $out["document_information"] = $school["document_information"] ?? "";
    $out["vat_exemption_mention"] = $school["vat_exemption_mention"] ?? "";
    $out["teacher_list"] = function_exists("user_school_teacher_list")
        ? user_school_teacher_list((int)($school["id"] ?? 0)) : "";
    // The rectorate roster is intentionally not embedded in every School
    // context. Building it walks teaching assignments and sessions, which is
    // useful only for the annual rectorate document. The dedicated
    // rectorate_teachers semantic context below materializes it lazily.
    $out["main"] = $out["main_info"];
    $out["school"] = $out["school_info"];
    $out["formation"] = $out["formation_info"];
    $out["alternation"] = $out["alternation_info"];
    $out["organization"] = [
        "id" => $school["id_organization"] ?? -1,
        "codename" => $school["organization_codename"] ?? "",
        "name" => $school["organization_name"] ?? ($school["name"] ?? ""),
        "fr_name" => $school["fr_name"] ?? "",
        "en_name" => $school["en_name"] ?? "",
        "legal_name" => $school["legal_name"] ?? "",
        "address" => $organization_legal_address,
        "legal_address" => $organization_legal_address,
        "phone" => $school["organization_phone"] ?? "",
        "mail" => $school["organization_mail"] ?? "",
        "website" => $school["website"] ?? "",
        "SIRET" => $school["siret"] ?? "",
        "registration_registry" => $school["registration_registry"] ?? "",
        "registration_number" => $school["registration_number"] ?? "",
        "share_capital" => $school["share_capital"] ?? "",
        "billing_information" => $school["organization_billing_information"] ?? "",
        "RIB" => $school["organization_billing_information"] ?? "",
        "main_info" => function_exists("enterprise_main_info") ? enterprise_main_info($school) : $out["main_info"],
    ];
    if (function_exists("school_document_logo_path"))
    {
        $out["logo"] = school_document_logo_path($school, true);
        $out["document_logo"] = $out["logo"];
    }
    if (function_exists("school_stamp_path"))
        $out["stamp"] = school_stamp_path($school, true);
    return ($out);
}

function document_context_user_id($id)
{
    if (($id = resolve_codenamef("user", $id))->is_error())
        return (NULL);
    if (is_array($id->value))
        return (count($id->value) ? (int)$id->value[0] : NULL);
    return ((int)$id->value);
}

function document_context_school_id($id)
{
    if (($id = resolve_codenamef("school", $id))->is_error())
        return (NULL);
    if (is_array($id->value))
        return (count($id->value) ? (int)$id->value[0] : NULL);
    return ((int)$id->value);
}

function document_context_cycle_id($id)
{
    if (($id = resolve_codenamef("cycle", $id))->is_error())
        return (NULL);
    if (is_array($id->value))
        return (count($id->value) ? (int)$id->value[0] : NULL);
    return ((int)$id->value);
}

function document_context_book_loan($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return (NULL);

    $where = "";
    if (preg_match('/^[0-9]+$/D', $value))
        $where = "book_user.id = ".((int)$value);
    else
        $where = "book.codename = '".db_escape($value)."'";

    $rows = db_select_all("
        book_user.id,
        book_user.id_book,
        book_user.id_user,
        book_user.status,
        book_user.start_date,
        book_user.end_date,
        book.codename AS book_codename,
        book.name AS book_name,
        user.codename AS user_codename
        FROM book_user
        LEFT JOIN book ON book.id = book_user.id_book
        LEFT JOIN user ON user.id = book_user.id_user
        WHERE $where
          AND book_user.status = 2
          AND book.id IS NOT NULL
          AND user.id IS NOT NULL
          AND user.deleted IS NULL
        ORDER BY book_user.end_date ASC, book_user.id DESC
    ");
    if (!is_array($rows) || !count($rows))
        return (NULL);

    // A book codename may represent several copies/loans. Never guess an
    // addressee. A unique active loan is safe; if several loans are active,
    // the codename remains usable only when exactly one of them is overdue.
    if (count($rows) > 1)
    {
        $late = [];
        foreach ($rows as $row)
        {
            $due = date_to_timestamp($row["end_date"] ?? "");
            if ($due !== NULL && $due < now())
                $late[] = $row;
        }
        if (count($late) != 1)
            return (NULL);
        $row = $late[0];
    }
    else
        $row = $rows[0];

    $start = date_to_timestamp($row["start_date"] ?? "");
    $due = date_to_timestamp($row["end_date"] ?? "");
    return ([
        "id" => (int)$row["id"],
        "book_id" => (int)$row["id_book"],
        "student_id" => (int)$row["id_user"],
        "user_id" => (int)$row["id_user"],
        "book" => (string)($row["book_name"] ?? ($row["book_codename"] ?? "")),
        "book_codename" => (string)($row["book_codename"] ?? ""),
        "student_codename" => (string)($row["user_codename"] ?? ""),
        "start_date" => $start === NULL ? "" : datex("d/m/Y", $start),
        "due_date" => $due === NULL ? "" : datex("d/m/Y", $due),
        "start_date_iso" => (string)($row["start_date"] ?? ""),
        "due_date_iso" => (string)($row["end_date"] ?? ""),
        "days_late" => $due === NULL ? 0 : max(0, (int)floor((now() - $due) / 86400)),
        "status" => (int)$row["status"],
    ]);
}

function document_context_book_loan_id($value)
{
    $loan = document_context_book_loan($value);
    return (is_array($loan) ? (int)$loan["id"] : NULL);
}

function document_context_book_loan_student_id($value)
{
    $loan = document_context_book_loan($value);
    return (is_array($loan) ? (int)$loan["student_id"] : NULL);
}

function document_context_cycle_school_id($id_cycle)
{
    $id_cycle = document_context_cycle_id($id_cycle);
    if ($id_cycle == NULL)
        return (NULL);
    $school = db_select_one("
        school.id as id_school
        FROM school_cycle
        LEFT JOIN school ON school.id = school_cycle.id_school
        WHERE school_cycle.id_cycle = ".((int)$id_cycle)."
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY school.id ASC
    ");
    return (is_array($school) ? (int)$school["id_school"] : NULL);
}

function document_context_cycle($id)
{
    global $Language;

    $id = document_context_cycle_id($id);
    if ($id == NULL)
        return (NULL);
    $cycle = db_select_one("
        *
        FROM cycle
        WHERE id = ".((int)$id)."
          AND deleted IS NULL
          AND (is_template IS NULL OR is_template = 0)
    ");
    if (!is_array($cycle))
        return (NULL);

    $name_key = ($Language == "en" ? "en_name" : "fr_name");
    $name = trim((string)($cycle[$name_key] ?? ""));
    if ($name == "")
        $name = trim((string)($cycle["fr_name"] ?? ""));
    if ($name == "")
        $name = trim((string)($cycle["codename"] ?? ""));

    $first_day = trim((string)($cycle["first_day"] ?? ""));
    $first_day_date = "";
    $preparation_day = "";
    $preparation_day_date = "";
    $integration_day = "";
    $integration_day_date = "";
    if ($first_day != "")
    {
        $timestamp = date_to_timestamp($first_day);
        if ($timestamp !== false && $timestamp > 0)
        {
            $first_day_date = date("d/m/Y", $timestamp);

            // Les événements de pré-rentrée sont ancrés sur la semaine du cycle :
            // - accueil/préparation : lundi de la semaine précédente ;
            // - intégration : dernier samedi précédant le début du cycle.
            $cycle_monday = strtotime("monday this week", $timestamp);
            if ($cycle_monday !== false)
            {
                $preparation_timestamp = strtotime("-7 days", $cycle_monday);
                $integration_timestamp = strtotime("last saturday", $timestamp);
                if ($preparation_timestamp !== false)
                {
                    $preparation_day = date("Y-m-d", $preparation_timestamp);
                    $preparation_day_date = date("d/m/Y", $preparation_timestamp);
                }
                if ($integration_timestamp !== false)
                {
                    $integration_day = date("Y-m-d", $integration_timestamp);
                    $integration_day_date = date("d/m/Y", $integration_timestamp);
                }
            }
        }
    }

    return ([
        "id" => (int)$cycle["id"],
        "codename" => $cycle["codename"] ?? "",
        "number" => (int)($cycle["cycle"] ?? 0),
        "name" => $name,
        "fr_name" => $cycle["fr_name"] ?? "",
        "en_name" => $cycle["en_name"] ?? "",
        "first_day" => $first_day,
        "first_day_date" => $first_day_date,
        "preparation_day" => $preparation_day,
        "preparation_day_date" => $preparation_day_date,
        "integration_day" => $integration_day,
        "integration_day_date" => $integration_day_date,
        "school_id" => document_context_cycle_school_id($id) ?? 0,
    ]);
}


function document_context_organization_id($id)
{
    if (($id = resolve_codenamef("organization", $id))->is_error())
        return (NULL);
    if (is_array($id->value))
        return (count($id->value) ? (int)$id->value[0] : NULL);
    return ((int)$id->value);
}

function document_context_organization($id)
{
    $id = document_context_organization_id($id);
    if ($id == NULL)
        return (NULL);
    if (!function_exists("fetch_enterprises"))
        return (NULL);
    $organization = fetch_enterprises($id);
    if (!is_array($organization) || !count($organization))
        return (NULL);
    if (function_exists("refresh_organization"))
        refresh_organization($organization);
    if (function_exists("enterprise_dabsic_context"))
    {
        $context = enterprise_dabsic_context($organization);
        if (isset($context["company"]) && is_array($context["company"]))
            return ($context["company"]);
    }
    return ($organization);
}

function document_context_title_id($id)
{
    if (function_exists("certification_title_id"))
        return (certification_title_id($id));
    if (($resolved = resolve_codenamef("title", $id))->is_error())
        return (NULL);
    if (is_array($resolved->value))
        return (count($resolved->value) ? (int)$resolved->value[0] : NULL);
    return ((int)$resolved->value);
}

function document_context_title($id)
{
    $id = document_context_title_id($id);
    if ($id == NULL)
        return (NULL);
    $title = db_select_one("* FROM `title` WHERE id = ".((int)$id)." AND deleted IS NULL");
    if (!is_array($title))
        return (NULL);
    $name = function_exists("certification_title_name")
        ? certification_title_name($title)
        : trim((string)($title["fr_name"] ?? ($title["codename"] ?? "")));
    return ([
        "id" => (int)$title["id"],
        "codename" => $title["codename"] ?? "",
        "code" => $title["code"] ?? "",
        "name" => $name,
        "fr_name" => trim((string)($title["fr_name"] ?? "")) != "" ? $title["fr_name"] : $name,
        "en_name" => trim((string)($title["en_name"] ?? "")) != "" ? $title["en_name"] : $name,
    ]);
}

function document_context_staff_for_school($id_school, $authority)
{
    $authority = strtoupper((string)$authority);
    $user = db_select_one("
        user.id as id
        FROM user_school
        LEFT JOIN user ON user.id = user_school.id_user
        WHERE user_school.id_school = ".((int)$id_school)."
          AND user_school.authority = '".db_escape($authority)."'
          AND user.id IS NOT NULL
          AND user.deleted IS NULL
          AND user.profile_status != 'jury'
        ORDER BY user.id ASC
        ");
    if ($user == NULL)
        return (NULL);
    return (document_context_person($user["id"]));
}

function document_context_organization_user_by_role($id_organization, $role)
{
    if (!function_exists("fetch_organization_users"))
        return (NULL);
    $users = fetch_organization_users((int)$id_organization, $role);
    if (!is_array($users) || !count($users))
        return (NULL);
    $user = $users[0];
    if (($person = document_context_person($user["id_user"])) == NULL)
        return (NULL);
    $person["role"] = $user["position"] ?? ($person["role"] ?? "");
    $person["position"] = $user["position"] ?? "";
    $person["document_role"] = $user["document_role"] ?? $role;
    return ($person);
}

function document_context_first_school_for_user($id_user)
{
    return (db_select_one("id_school FROM user_school WHERE id_user = ".((int)$id_user)));
}

function document_context_director_for_school($id_school)
{
    $director = db_select_one("
        user.id as id
        FROM user_school
        LEFT JOIN user ON user.id = user_school.id_user
        WHERE user_school.id_school = ".((int)$id_school)."
          AND (user_school.authority = 'DIRECTOR' OR user_school.authority = 1)
          AND user.id IS NOT NULL
          AND user.profile_status != 'jury'
        ORDER BY user.id ASC
        ");
    if ($director == NULL)
        return (NULL);
    return (document_context_person($director["id"]));
}

function document_context_relation_user($id_user, $relation, $index = 0)
{
    $id_user = (int)$id_user;
    $index = max(0, (int)$index);
    $rows = db_select_all("\n        user.id as id, parent_child.relation as relation\n        FROM parent_child\n        LEFT JOIN user ON user.id = parent_child.id_parent\n        WHERE parent_child.id_child = ".$id_user."\n          AND user.id IS NOT NULL\n          AND user.authority != -1\n        ORDER BY parent_child.id ASC\n        ");
    $matches = [];
    foreach ($rows as $row)
        if (function_exists("user_relation_has") && user_relation_has($row["relation"] ?? "", $relation))
            $matches[] = $row;
    if (!isset($matches[$index]))
        return (NULL);
    return (document_context_person($matches[$index]["id"]));
}

function document_context_user_identity_file($id)
{
    global $Configuration;

    $id = document_context_user_id($id);
    if ($id == NULL)
        return (NULL);
    if (function_exists("refresh_user"))
        refresh_user($id);
    // identity.dab is a portable cache of the live profile. Refresh it on
    // demand so a document never depends on an old identity file.
    if (function_exists("user_identity_write_identity_dabsic"))
        user_identity_write_identity_dabsic($id);
    $user = db_select_one("codename FROM user WHERE id = ".((int)$id)." AND authority != -1");
    if ($user == NULL || trim((string)($user["codename"] ?? "")) == "")
        return (NULL);
    $file = $Configuration->UsersDir($user["codename"])."admin/identity.dab";
    return (is_file($file) ? $file : NULL);
}

function document_context_add_person_scope(&$fields, &$files, &$temporary_files, $prefix, $id, $data, $include_signature = true)
{
    if (!$include_signature)
    {
        if (is_array($data))
        {
            unset($data["signature"]);
            unset($data["Signature"]);
        }
        $context_file = document_context_data_scope_file($prefix, is_array($data) ? $data : [], $temporary_files);
        if ($context_file != NULL)
            $files[] = $context_file;
        else if (is_array($data))
            document_context_flatten($fields, $prefix, $data);
        return ;
    }

    $identity = document_context_user_identity_file($id);
    if ($identity != NULL)
    {
        $wrapper = document_context_scope_file($prefix, $identity, $temporary_files);
        if ($wrapper != NULL)
        {
            $files[] = $wrapper;
            return ;
        }
    }
    document_context_flatten($fields, $prefix, $data);
}

function document_context_parent_for_user($id_user)
{
    $parents = db_select_all("
        user.id as id, parent_child.relation as relation
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_parent
        WHERE parent_child.id_child = ".((int)$id_user)."
          AND user.id IS NOT NULL
        ORDER BY parent_child.id ASC
        ");
    foreach ($parents as $parent)
        if (user_relation_has($parent["relation"] ?? "", "legal"))
            return (document_context_person($parent["id"]));
    return (NULL);
}


function document_context_scope_file($prefix, $file, &$temporary_files)
{
    $prefix = document_context_sanitize_key($prefix);
    if ($prefix == "" || !is_file($file))
        return (NULL);

    $tmp = tempnam(sys_get_temp_dir(), "infosphere_doc_context_");
    if ($tmp === false)
        return (NULL);
    @unlink($tmp);
    $tmp .= ".dab";

    $parts = explode(".", $prefix);
    $content = "";
    $indent = "";
    foreach ($parts as $part)
    {
        $content .= $indent."[".$part."\n";
        $indent .= "  ";
    }
    $content .= $indent."@insert ".json_encode(realpath($file), JSON_UNESCAPED_SLASHES)."\n";
    for ($i = count($parts) - 1; $i >= 0; --$i)
    {
        $indent = substr($indent, 0, max(0, strlen($indent) - 2));
        $content .= $indent."]\n";
    }

    if (file_put_contents($tmp, $content) === false)
    {
        @unlink($tmp);
        return (NULL);
    }
    $temporary_files[] = $tmp;
    return ($tmp);
}


function document_context_binding_school_id($name, array $definition, $value, array $resolved)
{
    $value = trim((string)$value);
    if ($value != "")
        return (document_context_school_id($value));
    foreach ($definition["infer_from"] ?? [] as $source_name)
    {
        if (!isset($resolved[$source_name]))
            continue ;
        $source = $resolved[$source_name];
        $source_type = $source["type"] ?? "";
        $source_value = $source["value"] ?? "";
        if ($source_type === "school")
            return (document_context_school_id($source_value));
        if ($source_type === "title_session" && function_exists("fetch_title_session_basic"))
        {
            $session = fetch_title_session_basic((int)$source_value);
            if (is_array($session) && (int)($session["id_school"] ?? 0) > 0)
                return ((int)$session["id_school"]);
        }
        if ($source_type === "cycle")
        {
            $school = document_context_cycle_school_id($source_value);
            if ($school != NULL)
                return ((int)$school);
        }
        if ($source_type === "book_loan")
        {
            $id_user = document_context_book_loan_student_id($source_value);
            if ($id_user != NULL && ($school = document_context_first_school_for_user($id_user)) != NULL)
                return ((int)$school["id_school"]);
        }
        if (in_array($source_type, ["user", "student", "jury", "staff", "teacher", "director", "commercial", "librarian", "secretariat"], true))
        {
            $id_user = document_context_user_id($source_value);
            if ($id_user != NULL && ($school = document_context_first_school_for_user($id_user)) != NULL)
                return ((int)$school["id_school"]);
        }
    }
    return (NULL);
}

function document_context_binding_user_from_title_session(array $definition, array $resolved)
{
    foreach ($definition["infer_from"] ?? [] as $source_name)
    {
        if (!isset($resolved[$source_name]) || ($resolved[$source_name]["type"] ?? "") !== "title_session")
            continue ;
        $id = (int)($resolved[$source_name]["value"] ?? 0);
        if ($id <= 0)
            continue ;
        if (function_exists("fetch_title_session_basic"))
        {
            $session = fetch_title_session_basic($id);
            if (is_array($session) && (int)($session["id_session_manager"] ?? 0) > 0)
                return ((int)$session["id_session_manager"]);
        }
        if (function_exists("title_session_document_context"))
        {
            $context = title_session_document_context($id);
            if (is_array($context) && (int)($context["session_manager_id"] ?? 0) > 0)
                return ((int)$context["session_manager_id"]);
        }
    }
    return (NULL);
}

function document_context_infer_binding($name, array $definition, array $resolved, array $options = [])
{
    $auto = strtolower(trim((string)($definition["auto"] ?? "")));
    if ($auto === "currentuser" && (int)($options["current_user_id"] ?? 0) > 0)
        return ((string)(int)$options["current_user_id"]);
    if ($auto === "owner" && (int)($options["owner_user_id"] ?? 0) > 0)
        return ((string)(int)$options["owner_user_id"]);

    $type = $definition["type"] ?? "";
    if ($type === "school")
    {
        $id = document_context_binding_school_id($name, $definition, "", $resolved);
        return ($id == NULL ? NULL : (string)$id);
    }
    if ($type === "rectorate_teachers")
    {
        // This context has no operator-entered identity of its own: it is the
        // annual roster derived from an already resolved School context. Keep
        // the same binding value (id or codename) and resolve it only when the
        // chain is materialized.
        foreach ($definition["infer_from"] ?? [] as $source_name)
            if (isset($resolved[$source_name]) && ($resolved[$source_name]["type"] ?? "") === "school")
                return ((string)$resolved[$source_name]["value"]);
        return (NULL);
    }
    if (in_array($type, ["director", "teacher", "commercial", "librarian", "secretariat"], true))
    {
        $school = document_context_binding_school_id($name, $definition, "", $resolved);
        return ($school == NULL ? NULL : "school:".$school);
    }
    if (in_array($type, ["parent", "legal1", "legal2", "finance", "emergency"], true))
    {
        foreach ($definition["infer_from"] ?? [] as $source_name)
            if (isset($resolved[$source_name]) && in_array($resolved[$source_name]["type"] ?? "", ["student", "user"], true))
                return ("student:".(string)$resolved[$source_name]["value"]);
        return (NULL);
    }
    if (in_array($type, ["user", "student"], true))
    {
        foreach ($definition["infer_from"] ?? [] as $source_name)
        {
            if (!isset($resolved[$source_name]) || ($resolved[$source_name]["type"] ?? "") !== "book_loan")
                continue ;
            $id = document_context_book_loan_student_id($resolved[$source_name]["value"] ?? "");
            if ($id != NULL)
                return ((string)$id);
        }
    }
    if ($type === "user")
    {
        $id = document_context_binding_user_from_title_session($definition, $resolved);
        if ($id != NULL)
            return ((string)$id);
    }
    if ($type === "title" || $type === "certification")
    {
        foreach ($definition["infer_from"] ?? [] as $source_name)
        {
            if (!isset($resolved[$source_name]) || ($resolved[$source_name]["type"] ?? "") !== "title_session")
                continue ;
            $session = function_exists("fetch_title_session_basic")
                ? fetch_title_session_basic((int)$resolved[$source_name]["value"]) : NULL;
            if (is_array($session) && (int)($session["id_title"] ?? 0) > 0)
                return ((string)(int)$session["id_title"]);
        }
    }
    if ($type === "organization")
    {
        foreach ($definition["infer_from"] ?? [] as $source_name)
        {
            if (!isset($resolved[$source_name]))
                continue ;
            $source = $resolved[$source_name];
            if (($source["type"] ?? "") === "school")
            {
                $school = fetch_school((int)$source["value"]);
                if (is_array($school) && (int)($school["id_organization"] ?? 0) > 0)
                    return ((string)(int)$school["id_organization"]);
            }
        }
    }
    if ($type === "tutor")
    {
        foreach ($definition["infer_from"] ?? [] as $source_name)
            if (isset($resolved[$source_name]) && ($resolved[$source_name]["type"] ?? "") === "organization")
                return ("organization:".(string)$resolved[$source_name]["value"]);
    }
    return (NULL);
}

function document_context_chain_entry($name, array $definition, $value)
{
    $type = $definition["type"];
    $entry = ["type" => $type, "prefix" => $definition["prefix"]];
    $value = trim((string)$value);
    if (in_array($type, ["director", "teacher", "commercial", "librarian", "secretariat"], true)
        && strncmp($value, "school:", 7) === 0)
        $entry["school"] = substr($value, 7);
    else if (in_array($type, ["parent", "legal1", "legal2", "finance", "emergency"], true)
        && strncmp($value, "student:", 8) === 0)
        $entry["student"] = substr($value, 8);
    else if ($type === "tutor" && strncmp($value, "organization:", 13) === 0)
        $entry["organization"] = substr($value, 13);
    else
        $entry["id"] = $value;
    if (($definition["signatory"] ?? "") !== "")
        $entry["signatory"] = $definition["signatory"];
    return ($entry);
}

function document_context_signatory_user_id(array $definition, $value)
{
    $type = $definition["type"] ?? "";
    $value = trim((string)$value);
    if (in_array($type, ["user", "student", "jury", "staff"], true))
        return (document_context_user_id($value));
    if (in_array($type, ["director", "teacher", "commercial", "librarian", "secretariat"], true))
    {
        if (strncmp($value, "school:", 7) !== 0)
            return (document_context_user_id($value));
        $school = document_context_school_id(substr($value, 7));
        if ($school == NULL)
            return (NULL);
        $roles = [
            "director" => "DIRECTOR", "teacher" => "TEACHER", "commercial" => "COMMERCIAL",
            "librarian" => "LIBRARIAN", "secretariat" => "SECRETARIAT",
        ];
        $person = document_context_staff_for_school($school, $roles[$type]);
        return (is_array($person) && (int)($person["id"] ?? 0) > 0 ? (int)$person["id"] : NULL);
    }
    if ($type === "parent" || in_array($type, ["legal1", "legal2", "finance", "emergency"], true))
    {
        if (strncmp($value, "student:", 8) !== 0)
            return (document_context_user_id($value));
        $id_user = document_context_user_id(substr($value, 8));
        if ($id_user == NULL)
            return (NULL);
        if ($type === "parent")
        {
            $person = document_context_parent_for_user($id_user);
            return (is_array($person) && (int)($person["id"] ?? 0) > 0 ? (int)$person["id"] : NULL);
        }
        if ($type === "finance")
        {
            $person = document_context_relation_user($id_user, "financial", 0);
            return (is_array($person) && (int)($person["id"] ?? 0) > 0 ? (int)$person["id"] : $id_user);
        }
        if ($type === "emergency")
        {
            $person = document_context_relation_user($id_user, "emergency", 0);
            return (is_array($person) && (int)($person["id"] ?? 0) > 0 ? (int)$person["id"] : NULL);
        }
        $person = document_context_relation_user($id_user, "legal", $type === "legal2" ? 1 : 0);
        return (is_array($person) && (int)($person["id"] ?? 0) > 0 ? (int)$person["id"] : NULL);
    }
    if ($type === "tutor")
    {
        if (strncmp($value, "organization:", 13) !== 0)
            return (document_context_user_id($value));
        $organization = document_context_organization_id(substr($value, 13));
        if ($organization == NULL)
            return (NULL);
        $person = document_context_organization_user_by_role($organization, "tutor");
        return (is_array($person) && (int)($person["id"] ?? 0) > 0 ? (int)$person["id"] : NULL);
    }
    return (NULL);
}

/** A selected person must belong to the institution declared by InferFrom. */
function document_context_person_matches_institution(array $definition, $value, array $resolved)
{
    $type = $definition["type"] ?? "";
    if (!in_array($type, ["staff", "tutor"], true))
        return true;
    $id_user = document_context_signatory_user_id($definition, $value);
    if ($id_user === NULL)
        return false;
    foreach ($definition["infer_from"] ?? [] as $source_name)
    {
        $source = $resolved[$source_name] ?? [];
        if ($type === "staff" && ($source["type"] ?? "") === "school")
        {
            $id_school = document_context_school_id($source["value"]);
            if ($id_school === NULL)
                return false;
            $roles = [];
            foreach (["DIRECTOR", "TEACHER", "COMMERCIAL", "SECRETARIAT", "LIBRARIAN", "ACCOUNTANT"] as $role)
                $roles[] = user_school_authority_sql($role);
            return db_select_one("user_school.id FROM user_school
                JOIN user ON user.id = user_school.id_user
                WHERE user_school.id_school = ".(int)$id_school."
                AND user_school.id_user = ".(int)$id_user."
                AND user.deleted IS NULL
                AND user_school.authority IN (".implode(",", $roles).")") !== NULL;
        }
        if ($type === "tutor" && ($source["type"] ?? "") === "organization")
        {
            $id_organization = document_context_organization_id($source["value"]);
            if ($id_organization === NULL)
                return false;
            foreach (fetch_organization_users($id_organization) as $person)
                if ((int)($person["id_user"] ?? ($person["id"] ?? 0)) === (int)$id_user)
                    return true;
            // Internal placements can use a member of the linked school staff.
            foreach (db_select_all("school.id FROM school WHERE school.id_organization = ".(int)$id_organization." AND school.deleted IS NULL") as $school)
                if (document_context_person_matches_institution(
                    ["type" => "staff", "infer_from" => ["School"]], (string)$id_user,
                    ["School" => ["type" => "school", "value" => (string)$school["id"]]]))
                    return true;
            return false;
        }
    }
    return true;
}

function document_context_model_bundle($model_file, $bindings, $fields = [], array $options = [])
{
    $schema = document_context_model_schema($model_file);
    $bindings = document_context_normalize_bindings($bindings, $schema);
    $resolved = [];

    // Explicit values are stable. Inferred values are resolved iteratively so
    // School <- Student <- ... and SessionManager <- TitleSession work without
    // depending on the declaration order in the model.
    foreach ($schema as $name => $definition)
        if (isset($bindings[$name]))
            $resolved[$name] = ["type" => $definition["type"], "value" => $bindings[$name], "inferred" => false];
    do
    {
        $changed = false;
        foreach ($schema as $name => $definition)
        {
            if (isset($resolved[$name]))
                continue ;
            $value = document_context_infer_binding($name, $definition, $resolved, $options);
            if ($value === NULL || trim((string)$value) === "")
                continue ;
            $resolved[$name] = ["type" => $definition["type"], "value" => (string)$value, "inferred" => true];
            $changed = true;
        }
    }
    while ($changed);

    $missing = [];
    if (!empty($options["strict"]))
        foreach ($schema as $name => $definition)
            if (!empty($definition["required"]) && !isset($resolved[$name]))
                $missing[] = $name;

    $chain = [];
    $signature_bindings = [];
    foreach ($schema as $name => $definition)
    {
        if (!isset($resolved[$name]))
            continue ;
        if (!document_context_person_matches_institution($definition, $resolved[$name]["value"], $resolved))
        {
            if (!empty($options["strict"]))
                $missing[] = $name;
            continue ;
        }
        $chain[] = document_context_chain_entry($name, $definition, $resolved[$name]["value"]);
        if (($slot = $definition["signatory"] ?? "") !== "")
        {
            $id_user = document_context_signatory_user_id($definition, $resolved[$name]["value"]);
            if ($id_user != NULL && $id_user > 0)
                $signature_bindings[$slot] = "User_".$id_user;
            else if (!empty($options["strict"]) && !in_array($name, $missing, true))
                $missing[] = $name;
        }
    }
    foreach (document_context_normalize_field_bindings($fields) as $key => $value)
        $chain[] = ["type" => "field", "key" => $key, "value" => $value];
    return ([
        "ok" => !count($missing),
        "schema" => $schema,
        "bindings" => $bindings,
        "resolved" => $resolved,
        "chain" => $chain,
        "signature_bindings" => $signature_bindings,
        "missing" => array_values(array_unique($missing)),
    ]);
}

function document_context_binding_canonical_user($id)
{
    $id = document_context_user_id($id);
    if ($id == NULL)
        return (NULL);
    $user = db_select_one("codename FROM user WHERE id = ".((int)$id)." AND authority != -1");
    if (is_array($user) && trim((string)($user["codename"] ?? "")) != "")
        return ((string)$user["codename"]);
    return ((string)(int)$id);
}

function document_context_binding_canonical_school($id)
{
    $id = document_context_school_id($id);
    if ($id == NULL)
        return (NULL);
    $school = db_select_one("codename FROM school WHERE id = ".((int)$id));
    if (is_array($school) && trim((string)($school["codename"] ?? "")) != "")
        return ((string)$school["codename"]);
    return ((string)(int)$id);
}

function document_context_binding_canonical_organization($id)
{
    $id = document_context_organization_id($id);
    if ($id == NULL)
        return (NULL);
    $organization = db_select_one("codename FROM organization WHERE id = ".((int)$id));
    if (is_array($organization) && trim((string)($organization["codename"] ?? "")) != "")
        return ((string)$organization["codename"]);
    return ((string)(int)$id);
}

/**
 * Turn an inferred internal binding (school:1, student:42, organization:3...)
 * into the same concrete value an operator could have entered explicitly.
 * This is used by the GUI to display automatic completion instead of keeping
 * it as invisible resolver state.
 */
function document_context_materialized_binding_value(array $definition, $value)
{
    $type = strtolower(trim((string)($definition["type"] ?? "")));
    $value = trim((string)$value);
    if ($value == "")
        return (NULL);

    if (in_array($type, ["user", "student", "jury", "staff", "director", "teacher", "commercial", "librarian", "secretariat", "parent", "legal1", "legal2", "finance", "emergency", "tutor"], true))
    {
        $id = document_context_signatory_user_id($definition, $value);
        return ($id == NULL ? NULL : document_context_binding_canonical_user($id));
    }
    if ($type === "school")
        return (document_context_binding_canonical_school($value));
    if ($type === "cycle")
    {
        $id = document_context_cycle_id($value);
        if ($id == NULL)
            return (NULL);
        $cycle = db_select_one("codename FROM cycle WHERE id = ".((int)$id)." AND deleted IS NULL");
        return (is_array($cycle) && trim((string)($cycle["codename"] ?? "")) != ""
            ? (string)$cycle["codename"] : (string)$id);
    }
    if ($type === "organization")
        return (document_context_binding_canonical_organization($value));
    if ($type === "title" || $type === "certification")
    {
        $id = document_context_title_id($value);
        if ($id == NULL)
            return (NULL);
        $title = db_select_one("codename FROM `title` WHERE id = ".((int)$id));
        return (is_array($title) && trim((string)($title["codename"] ?? "")) != ""
            ? (string)$title["codename"] : (string)$id);
    }
    if ($type === "title_session")
        return ((string)(int)$value);
    if ($type === "book_loan")
    {
        $loan = document_context_book_loan($value);
        if (!is_array($loan))
            return (NULL);
        return (trim((string)($loan["book_codename"] ?? "")) != ""
            ? (string)$loan["book_codename"] : (string)(int)$loan["id"]);
    }
    return ($value);
}

function document_context_materialized_bindings(array $bundle)
{
    $out = [];
    foreach (($bundle["schema"] ?? []) as $name => $definition)
    {
        if (!isset($bundle["resolved"][$name]))
            continue ;
        $value = document_context_materialized_binding_value(
            $definition,
            $bundle["resolved"][$name]["value"] ?? ""
        );
        if ($value !== NULL && trim((string)$value) !== "")
            $out[$name] = (string)$value;
    }
    return ($out);
}

function document_context_automatic_names(array $bundle)
{
    $out = [];
    foreach (($bundle["resolved"] ?? []) as $name => $definition)
        if (!empty($definition["inferred"]))
            $out[] = $name;
    return ($out);
}

function document_context_normalize_field_bindings($fields)
{
    if (is_string($fields))
        $fields = json_decode($fields, true);
    if (!is_array($fields))
        return ([]);

    $out = [];
    foreach ($fields as $key => $value)
    {
        $key = document_context_sanitize_key($key);
        if ($key === "" || !(is_scalar($value) || $value === NULL))
            continue ;
        $out[$key] = document_context_scalar($value);
    }
    return ($out);
}

function document_context_bindings_json($bindings)
{
    if (is_string($bindings))
        return ($bindings);
    $json = json_encode(is_array($bindings) ? $bindings : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($json === false ? "{}" : $json);
}

function document_context_fields_json($fields)
{
    $json = json_encode(document_context_normalize_field_bindings($fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($json === false ? "{}" : $json);
}

function document_context_data_scope_write(&$content, $indent, array $data)
{
    foreach ($data as $key => $value)
    {
        $key = document_context_pascal_key($key);
        if ($key == "")
            continue ;
        if (is_array($value))
        {
            $content .= $indent."[".$key."\n";
            document_context_data_scope_write($content, $indent."  ", $value);
            $content .= $indent."]\n";
            continue ;
        }
        if (is_bool($value))
            $encoded = $value ? "1" : "0";
        else if (is_int($value) || is_float($value))
            $encoded = (string)$value;
        else
            $encoded = json_encode(document_context_scalar($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $content .= $indent.$key." = ".$encoded."\n";
    }
}

function document_context_data_scope_file($prefix, array $data, &$temporary_files)
{
    $prefix = document_context_sanitize_key($prefix);
    if ($prefix == "" || !count($data))
        return (NULL);

    $tmp = tempnam(sys_get_temp_dir(), "infosphere_doc_context_");
    if ($tmp === false)
        return (NULL);
    @unlink($tmp);
    $tmp .= ".dab";

    $parts = explode(".", $prefix);
    $content = "";
    $indent = "";
    foreach ($parts as $part)
    {
        $content .= $indent."[".$part."\n";
        $indent .= "  ";
    }
    document_context_data_scope_write($content, $indent, $data);
    for ($i = count($parts) - 1; $i >= 0; --$i)
    {
        $indent = substr($indent, 0, max(0, strlen($indent) - 2));
        $content .= $indent."]\n";
    }

    if (file_put_contents($tmp, $content) === false)
    {
        @unlink($tmp);
        return (NULL);
    }
    $temporary_files[] = $tmp;
    return ($tmp);
}

function document_context_metadata_scope_file($prefix, array $metadata, &$temporary_files)
{
    $prefix = document_context_sanitize_key($prefix);
    if ($prefix == "" || !count($metadata))
        return (NULL);

    $tmp = tempnam(sys_get_temp_dir(), "infosphere_doc_context_");
    if ($tmp === false)
        return (NULL);
    @unlink($tmp);
    $tmp .= ".dab";

    $parts = explode(".", $prefix);
    $content = "";
    $indent = "";
    foreach ($parts as $part)
    {
        $content .= $indent."[".$part."\n";
        $indent .= "  ";
    }
    foreach ($metadata as $key => $value)
    {
        $key = document_context_sanitize_key($key);
        if ($key == "" || strpos($key, ".") !== false)
            continue ;
        if (is_bool($value) || is_int($value) || is_float($value))
            $encoded = (string)$value;
        else
            $encoded = json_encode((string)$value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $content .= $indent.$key." = ".$encoded."\n";
    }
    for ($i = count($parts) - 1; $i >= 0; --$i)
    {
        $indent = substr($indent, 0, max(0, strlen($indent) - 2));
        $content .= $indent."]\n";
    }

    if (file_put_contents($tmp, $content) === false)
    {
        @unlink($tmp);
        return (NULL);
    }
    $temporary_files[] = $tmp;
    return ($tmp);
}

function document_context_add_signatory_metadata(&$files, &$temporary_files, array $entry, $prefix)
{
    $slot = isset($entry["signatory"]) ? trim((string)$entry["signatory"]) : "";
    if ($slot == "" || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $slot))
        return ;
    // Keep semantic role names in Dabsic input files, not in mergeconf -c
    // transformations. The transformation parser still has the historical
    // 12-byte literal buffer on deployed installations, while ordinary Dabsic
    // strings correctly support names such as SessionManager.
    $metadata = document_context_metadata_scope_file($prefix, [
        "Signatory" => 1,
        "As" => $slot,
    ], $temporary_files);
    if ($metadata != NULL)
        $files[] = $metadata;
}

function document_context_relation_identity_role($id_student, $id_person, $type)
{
    $id_student = (int)$id_student;
    $id_person = (int)$id_person;
    if ($id_student <= 0 || $id_person <= 0)
        return ("");
    if ($type === "finance" && $id_person === $id_student)
        return ("Student");

    $legal1 = document_context_relation_user($id_student, "legal", 0);
    if (is_array($legal1) && (int)($legal1["id"] ?? 0) === $id_person)
        return ("Legal1");
    $legal2 = document_context_relation_user($id_student, "legal", 1);
    if (is_array($legal2) && (int)($legal2["id"] ?? 0) === $id_person)
        return ("Legal2");

    if ($type === "emergency")
    {
        $finance = document_context_relation_user($id_student, "financial", 0);
        if (is_array($finance) && (int)($finance["id"] ?? 0) === $id_person)
            return ("Finance");
    }
    return ("Other");
}


function document_context_apply_chain(&$fields, $chain, &$files = NULL, &$temporary_files = NULL, array $options = [])
{
    if (!is_array($chain))
        return ;
    if (!is_array($files))
        $files = [];
    if (!is_array($temporary_files))
        $temporary_files = [];
    $last_user = NULL;
    $last_school = NULL;
    $last_organization = NULL;
    $school_roles = [
        "teacher" => "TEACHER",
        "director" => "DIRECTOR",
        "commercial" => "COMMERCIAL",
        "librarian" => "LIBRARIAN",
        "secretariat" => "SECRETARIAT",
    ];
    foreach ($chain as $entry)
    {
        if (!is_array($entry) || !isset($entry["type"]))
            continue ;
        $type = $entry["type"];
        $prefix = document_context_sanitize_key(@$entry["prefix"]);
        if ($type == "field")
        {
            $key = document_context_sanitize_key(@$entry["key"]);
            if ($key != "")
                $fields[] = $key."=".document_context_scalar(@$entry["value"]);
            continue ;
        }
        if ($type == "user" || $type == "staff" || $type == "student" || $type == "eleve" || $type == "jury")
        {
            $id = document_context_user_id(@$entry["id"]);
            if ($id == NULL)
                continue ;
            if ($type == "jury" && db_select_one("id FROM user WHERE id = $id AND profile_status = 'jury'") == NULL)
                continue ;
            $data = document_context_person($id);
            if ($data == NULL)
                continue ;
            $last_user = $id;
            if (($school = document_context_first_school_for_user($id)) != NULL)
            {
                $last_school = (int)$school["id_school"];
                if (($full_school = fetch_school($last_school)) != NULL && is_array($full_school) && isset($full_school["id_organization"]))
                    $last_organization = (int)$full_school["id_organization"];
            }
            document_context_add_person_scope($fields, $files, $temporary_files, $prefix, $id, $data,
                empty($options["suppress_signatory_signatures"]) || empty($entry["signatory"]));
            document_context_add_signatory_metadata($files, $temporary_files, $entry, $prefix);
            continue ;
        }
        if ($type == "book_loan")
        {
            $data = document_context_book_loan($entry["id"] ?? "");
            if (!is_array($data))
                continue ;
            $context_file = document_context_data_scope_file($prefix, $data, $temporary_files);
            if ($context_file != NULL)
                $files[] = $context_file;
            else
                document_context_flatten($fields, $prefix, $data);
            $last_user = (int)($data["student_id"] ?? 0);
            if ($last_user > 0 && ($school = document_context_first_school_for_user($last_user)) != NULL)
            {
                $last_school = (int)$school["id_school"];
                if (($full_school = fetch_school($last_school)) != NULL && is_array($full_school) && isset($full_school["id_organization"]))
                    $last_organization = (int)$full_school["id_organization"];
            }
            continue ;
        }
        if ($type == "cycle")
        {
            $id = isset($entry["id"]) ? document_context_cycle_id($entry["id"]) : NULL;
            if ($id == NULL)
                continue ;
            $data = document_context_cycle($id);
            if ($data == NULL)
                continue ;
            $context_file = document_context_data_scope_file($prefix, $data, $temporary_files);
            if ($context_file != NULL)
                $files[] = $context_file;
            else
                document_context_flatten($fields, $prefix, $data);
            $cycle_school = (int)($data["school_id"] ?? 0);
            if ($cycle_school > 0)
            {
                $last_school = $cycle_school;
                if (($full_school = fetch_school($last_school)) != NULL && is_array($full_school) && isset($full_school["id_organization"]))
                    $last_organization = (int)$full_school["id_organization"];
            }
            continue ;
        }
        if ($type == "title_session")
        {
            $id = isset($entry["id"]) ? (int)$entry["id"] : 0;
            if ($id <= 0 || !function_exists("title_session_document_context"))
                continue ;
            $data = title_session_document_context($id);
            if ($data == NULL)
                continue ;
            $session = function_exists("fetch_title_session_basic") ? fetch_title_session_basic($id) : (function_exists("fetch_title_session") ? fetch_title_session($id) : NULL);
            if (is_array($session) && isset($session["id_school"]))
            {
                $last_school = (int)$session["id_school"];
                if (($full_school = fetch_school($last_school)) != NULL && is_array($full_school) && isset($full_school["id_organization"]))
                    $last_organization = (int)$full_school["id_organization"];
            }
            // Session/title labels can exceed mergeconf's historical -m literal
            // limit on deployed installations. Keep structured session data in
            // an ordinary Dabsic input file instead of transformations.
            $context_file = document_context_data_scope_file($prefix, $data, $temporary_files);
            if ($context_file != NULL)
                $files[] = $context_file;
            else
                document_context_flatten($fields, $prefix, $data);

            // When a candidate was selected before the title session, the same
            // semantic context can also expose the candidate-specific schedule:
            // session start = first appointment, assigned appointment_slot =
            // individual jury passage.
            if ($last_user != NULL && function_exists("title_session_candidate_schedule_context"))
            {
                $schedule = title_session_candidate_schedule_context($id, $last_user);
                if (is_array($schedule))
                {
                    $candidate_file = document_context_data_scope_file("Candidate", $schedule, $temporary_files);
                    if ($candidate_file != NULL)
                        $files[] = $candidate_file;
                    else
                        document_context_flatten($fields, "Candidate", $schedule);
                }
            }
            continue ;
        }
        if ($type == "title" || $type == "certification")
        {
            $id = isset($entry["id"]) && trim((string)$entry["id"]) != ""
                ? document_context_title_id($entry["id"]) : NULL;
            if ($id == NULL)
                continue ;
            $data = document_context_title($id);
            if ($data == NULL)
                continue ;
            $context_file = document_context_data_scope_file($prefix, $data, $temporary_files);
            if ($context_file != NULL)
                $files[] = $context_file;
            else
                document_context_flatten($fields, $prefix, $data);
            continue ;
        }
        if ($type == "parent")
        {
            if (isset($entry["student"]) && trim((string)$entry["student"]) != "")
            {
                $id = document_context_user_id($entry["student"]);
                $data = $id == NULL ? NULL : document_context_parent_for_user($id);
            }
            else
            {
                $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_user_id($entry["id"]) : NULL;
                $data = $id == NULL ? NULL : document_context_person($id);
            }
            if ($data == NULL)
                continue ;
            $person_id = (int)($data["id"] ?? 0);
            if ($person_id > 0)
                document_context_add_person_scope($fields, $files, $temporary_files, $prefix, $person_id, $data,
                    empty($options["suppress_signatory_signatures"]) || empty($entry["signatory"]));
            else
                document_context_flatten($fields, $prefix, $data);
            document_context_add_signatory_metadata($files, $temporary_files, $entry, $prefix);
            continue ;
        }
        if ($type == "legal1" || $type == "legal2" || $type == "finance" || $type == "emergency")
        {
            $relation_student_id = NULL;
            if (isset($entry["student"]) && trim((string)$entry["student"]) != "")
            {
                $id = document_context_user_id($entry["student"]);
                if ($id == NULL)
                    continue ;
                $relation_student_id = $id;
                if ($type == "finance")
                {
                    $data = document_context_relation_user($id, "financial", 0);
                    // Sans responsable financier distinct, l'apprenant assume
                    // lui-même ce rôle, comme dans le fonctionnement contractuel
                    // existant.
                    if ($data == NULL)
                        $data = document_context_person($id);
                }
                else if ($type == "emergency")
                    $data = document_context_relation_user($id, "emergency", 0);
                else
                    $data = document_context_relation_user($id, "legal", $type == "legal2" ? 1 : 0);
            }
            else
            {
                $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_user_id($entry["id"]) : NULL;
                $data = $id == NULL ? NULL : document_context_person($id);
            }
            if ($data == NULL)
                continue ;
            $person_id = isset($data["id"]) ? (int)$data["id"] : NULL;
            if ($person_id != NULL)
                document_context_add_person_scope($fields, $files, $temporary_files, $prefix, $person_id, $data,
                    empty($options["suppress_signatory_signatures"]) || empty($entry["signatory"]));
            else
                document_context_flatten($fields, $prefix, $data);
            if ($relation_student_id != NULL && ($type == "finance" || $type == "emergency"))
            {
                $relation_role = document_context_relation_identity_role($relation_student_id, $person_id, $type);
                if ($relation_role != "")
                {
                    $metadata = document_context_metadata_scope_file($prefix, ["Is" => $relation_role], $temporary_files);
                    if ($metadata != NULL)
                        $files[] = $metadata;
                }
            }
            document_context_add_signatory_metadata($files, $temporary_files, $entry, $prefix);
            continue ;
        }
        if (isset($school_roles[$type]))
        {
            $data = NULL;
            $person_id = NULL;
            if (isset($entry["id"]) && trim((string)$entry["id"]) != "")
            {
                // In the semantic context format an explicit staff value is a
                // concrete person, never an overloaded school identifier.
                $person_id = document_context_user_id($entry["id"]);
                if ($person_id != NULL)
                {
                    $data = document_context_person($person_id);
                    if (($school = document_context_first_school_for_user($person_id)) != NULL)
                        $last_school = (int)$school["id_school"];
                }
            }
            else if (isset($entry["school"]) && trim((string)$entry["school"]) != "")
            {
                $last_school = document_context_school_id($entry["school"]);
                if ($last_school != NULL)
                {
                    $data = document_context_staff_for_school($last_school, $school_roles[$type]);
                    $person_id = is_array($data) ? (int)($data["id"] ?? 0) : NULL;
                }
            }
            if ($data == NULL)
                continue ;
            if ($person_id != NULL && $person_id > 0)
                document_context_add_person_scope($fields, $files, $temporary_files, $prefix, $person_id, $data,
                    empty($options["suppress_signatory_signatures"]) || empty($entry["signatory"]));
            else
                document_context_flatten($fields, $prefix, $data);
            document_context_add_signatory_metadata($files, $temporary_files, $entry, $prefix);
            continue ;
        }
        if ($type == "school")
        {
            $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_school_id($entry["id"]) : $last_school;
            if ($id == NULL)
                continue ;
            $data = document_context_school($id);
            if ($data == NULL)
                continue ;
            $last_school = $id;
            if (isset($data["organization"]["id"]) && (int)$data["organization"]["id"] > 0)
                $last_organization = (int)$data["organization"]["id"];
            global $Configuration;
            $identity = $Configuration->SchoolsDir($data["codename"] ?? "")."identity.dab";
            $wrapper = document_context_scope_file($prefix, $identity, $temporary_files);
            if ($wrapper != NULL)
            {
                $files[] = $wrapper;
                if (trim((string)($data["legal_city"] ?? "")) != "")
                    document_context_flatten($fields, $prefix, ["legal_city" => $data["legal_city"]]);
            }
            else
                document_context_flatten($fields, $prefix, $data);
            continue ;
        }
        if ($type == "rectorate_teachers")
        {
            $id = isset($entry["id"]) && trim((string)$entry["id"]) != ""
                ? document_context_school_id($entry["id"]) : $last_school;
            if ($id == NULL || !function_exists("user_school_rectorate_teacher_context"))
                continue ;
            $data = user_school_rectorate_teacher_context((int)$id);
            if (!is_array($data))
                continue ;
            $context_file = document_context_data_scope_file($prefix, $data, $temporary_files);
            if ($context_file != NULL)
                $files[] = $context_file;
            else
                document_context_flatten($fields, $prefix, $data);
            continue ;
        }
        if ($type == "organization" || $type == "enterprise" || $type == "entreprise")
        {
            $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_organization_id($entry["id"]) : $last_organization;
            if ($id == NULL)
                continue ;
            $data = document_context_organization($id);
            if ($data == NULL)
                continue ;
            $last_organization = $id;
            document_context_flatten($fields, $prefix, $data);
            continue ;
        }
        if ($type == "tutor" || $type == "tuteur")
        {
            if (isset($entry["organization"]) && trim((string)$entry["organization"]) != "")
            {
                $id = document_context_organization_id($entry["organization"]);
                $data = $id == NULL ? NULL : document_context_organization_user_by_role($id, "tutor");
            }
            else
            {
                $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_user_id($entry["id"]) : NULL;
                $data = $id == NULL ? NULL : document_context_person($id);
            }
            if ($data == NULL)
                continue ;
            if (isset($data["id"]) && (int)$data["id"] > 0)
                document_context_add_person_scope($fields, $files, $temporary_files, $prefix, (int)$data["id"], $data,
                    empty($options["suppress_signatory_signatures"]) || empty($entry["signatory"]));
            else
                document_context_flatten($fields, $prefix, $data);
            document_context_add_signatory_metadata($files, $temporary_files, $entry, $prefix);
            continue ;
        }
    }
}
