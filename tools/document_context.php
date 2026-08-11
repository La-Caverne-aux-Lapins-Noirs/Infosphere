<?php

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
    if (function_exists("jury_user_title_context") && isset($user["id"]))
        $person = array_merge($person, jury_user_title_context($user["id"]));
    return ($person);
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

    // School est le cocontractant complet : on expose d'abord les données de
    // l'organisation juridique au même niveau, puis les données propres à
    // l'établissement les complètent ou les remplacent.
    $organization = [
        "id_organization" => $school["id_organization"] ?? -1,
        "organization_codename" => $school["organization_codename"] ?? "",
        "organization_name" => $school["organization_name"] ?? "",
        "legal_name" => $school["legal_name"] ?? "",
        "legal_address" => $school["organization_address"] ?? "",
        "SIRET" => $school["siret"] ?? "",
        "website" => $school["website"] ?? "",
        "registration_registry" => $school["registration_registry"] ?? "",
        "registration_number" => $school["registration_number"] ?? "",
    ];
    $out = array_replace($organization, $out);

    $out["name"] = $out["name"] ?? ($school["fr_name"] ?? ($school["codename"] ?? ""));
    $out["legal_name"] = $out["legal_name"] ?? ($school["legal_name"] ?? ($out["name"] ?? ""));
    $out["address"] = $out["address"] ?? ($school["address"] ?? "");
    $out["training_address"] = $school["school_address"] ?? ($school["address"] ?? "");
    $out["street"] = $out["street"] ?? $out["address"];
    $out["city"] = $out["city"] ?? "";
    $out["phone"] = $out["phone"] ?? ($school["phone"] ?? "");
    $out["mail"] = $out["mail"] ?? ($school["mail"] ?? "");
    $out["NDA"] = $school["formation_activity_number"] ?? "";
    $out["UAI"] = $school["uai"] ?? "";
    $out["cfa_name"] = $school["cfa_name"] ?? "";
    $out["executing_establishment_name"] = $school["executing_establishment_name"] ?? "";
    $out["main_info"] = function_exists("enterprise_main_info") ? enterprise_main_info($school) : (function_exists("school_main_info") ? school_main_info($school) : ($school["main_info"] ?? ""));
    $out["school_info"] = function_exists("school_private_school_info") ? school_private_school_info($school) : ($school["school_info"] ?? "");
    $out["formation_info"] = function_exists("school_formation_info") ? school_formation_info($school) : ($school["formation_info"] ?? "");
    $out["alternation_info"] = function_exists("school_alternation_info") ? school_alternation_info($school) : ($school["alternation_info"] ?? "");
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
        "address" => $school["organization_address"] ?? "",
        "phone" => $school["organization_phone"] ?? "",
        "mail" => $school["organization_mail"] ?? "",
        "website" => $school["website"] ?? "",
        "SIRET" => $school["siret"] ?? "",
        "registration_registry" => $school["registration_registry"] ?? "",
        "registration_number" => $school["registration_number"] ?? "",
        "main_info" => $out["main_info"],
    ];
    if (function_exists("school_document_logo_path"))
    {
        $out["logo"] = school_document_logo_path($school, true);
        $out["document_logo"] = $out["logo"];
    }
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
    $user = db_select_one("codename FROM user WHERE id = ".((int)$id)." AND authority != -1");
    if ($user == NULL || trim((string)($user["codename"] ?? "")) == "")
        return (NULL);
    $file = $Configuration->UsersDir($user["codename"])."admin/identity.dab";
    return (is_file($file) ? $file : NULL);
}

function document_context_add_person_scope(&$fields, &$files, &$temporary_files, $prefix, $id, $data)
{
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

function document_context_add_signatory_metadata(&$fields, array $entry, $prefix)
{
    $slot = isset($entry["signatory"]) ? trim((string)$entry["signatory"]) : "";
    if ($slot == "" || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $slot))
        return ;
    $prefix = document_context_sanitize_key($prefix);
    if ($prefix == "")
        return ;
    $fields[] = $prefix.".Signatory=1";
    $fields[] = $prefix.".As=".json_encode($slot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function document_context_apply_chain(&$fields, $chain, &$files = NULL, &$temporary_files = NULL)
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
            document_context_add_person_scope($fields, $files, $temporary_files, $prefix, $id, $data);
            document_context_add_signatory_metadata($fields, $entry, $prefix);
            continue ;
        }
        if ($type == "parent")
        {
            $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_user_id($entry["id"]) : $last_user;
            if ($id == NULL)
                continue ;
            $data = document_context_parent_for_user($id);
            if ($data == NULL)
                continue ;
            document_context_flatten($fields, $prefix, $data);
            continue ;
        }
        if ($type == "legal1" || $type == "legal2" || $type == "finance")
        {
            $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_user_id($entry["id"]) : $last_user;
            if ($id == NULL)
                continue ;
            if ($type == "finance")
            {
                $data = document_context_relation_user($id, "financial", 0);
                // Sans responsable financier distinct, l'apprenant assume lui-même
                // ce rôle. Cela permet notamment qu'une même identité satisfasse
                // à la fois Student et Finance dans DocBuilder.
                if ($data == NULL)
                    $data = document_context_person($id);
            }
            else
                $data = document_context_relation_user($id, "legal", $type == "legal2" ? 1 : 0);
            if ($data == NULL)
                continue ;
            $person_id = isset($data["id"]) ? (int)$data["id"] : NULL;
            if ($person_id != NULL)
                document_context_add_person_scope($fields, $files, $temporary_files, $prefix, $person_id, $data);
            else
                document_context_flatten($fields, $prefix, $data);
            document_context_add_signatory_metadata($fields, $entry, $prefix);
            continue ;
        }
        if (isset($school_roles[$type]))
        {
            if (isset($entry["id"]) && trim((string)$entry["id"]) != "")
                $last_school = document_context_school_id($entry["id"]);
            if ($last_school == NULL)
                continue ;
            $data = document_context_staff_for_school($last_school, $school_roles[$type]);
            if ($data == NULL)
                continue ;
            if (isset($data["id"]))
                document_context_add_person_scope($fields, $files, $temporary_files, $prefix, (int)$data["id"], $data);
            else
                document_context_flatten($fields, $prefix, $data);
            document_context_add_signatory_metadata($fields, $entry, $prefix);
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
                $files[] = $wrapper;
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
            $id = isset($entry["id"]) && trim((string)$entry["id"]) != "" ? document_context_organization_id($entry["id"]) : $last_organization;
            if ($id == NULL)
                continue ;
            $data = document_context_organization_user_by_role($id, "tutor");
            if ($data == NULL)
                continue ;
            document_context_flatten($fields, $prefix, $data);
            continue ;
        }
    }
}

function document_context_parse_chain($raw)
{
    if (!isset($raw) || trim((string)$raw) == "")
        return ([]);
    $json = json_decode($raw, true);
    if (!is_array($json))
        bad_request();
    return ($json);
}

