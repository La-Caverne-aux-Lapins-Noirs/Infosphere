<?php

require_once (__DIR__."/document_signatures.php");
require_once (__DIR__."/document_context.php");
require_once (__DIR__."/document_sources.php");
require_once (__DIR__."/form_field.php");
require_once (__DIR__."/public_invitation.php");
require_once (__DIR__."/communication_event.php");
require_once (__DIR__."/user_identity.php");

function registration_form_kinds()
{
    return (["ECL", "OF", "OFA", "CFA"]);
}

function registration_form_kinds_for_user($id_user)
{
    $loaded = registration_form_student((int)$id_user);
    if (empty($loaded["ok"]))
        return ([]);
    $school = document_builder_fetch_student_school($loaded["student"]);
    if (!is_array($school))
        return ([]);
    return (array_values(array_filter(registration_form_kinds(), function($kind) use ($school) {
        return (school_activity_contract_kind_allowed($school, $kind));
    })));
}

function registration_form_profile_kind()
{
    return ("PROFILE");
}

function registration_form_is_document_kind($kind)
{
    return (is_string($kind) && strncmp($kind, "DOC-", 4) === 0);
}

function registration_form_is_document_signature_kind($kind)
{
    return (is_string($kind) && strncmp($kind, "SIG-", 4) === 0);
}

/**
 * A contribution request (DOC-*) only collects declarative data.  Initials
 * belong to the later immutable-document signature step (SIG-*), never to the
 * contribution phase.  Legacy/profile forms keep their historical paraphe.
 */
function registration_form_requires_paraph($kind, array $schema = [])
{
    if ($kind === communication_event_kind() || communication_event_is_response_kind($kind))
        return (false);
    if (registration_form_is_document_kind($kind))
        return (false);
    if (registration_form_is_document_signature_kind($kind))
        return (!empty($schema["document_signature"]["require_initials"]));
    return (true);
}

function registration_form_document_signature_consent_text()
{
    return ("Je confirme avoir pris connaissance du document affiché et demande que la signature que j’ai choisie ou tracée soit associée à cette version précise du document.");
}

function registration_form_document_context_bindings($model_file, array $user, $bindings)
{
    return (document_context_normalize_bindings(
        $bindings,
        document_context_model_schema($model_file)
    ));
}

function registration_form_document_bundle($model_file, array $user, $id_creator, $target_year, $context_bindings = [], $strict = true)
{
    $context_bindings = registration_form_document_context_bindings($model_file, $user, $context_bindings);
    $target_year = (int)$target_year;
    $fields = [];
    if ($target_year >= 1 && $target_year <= 5)
    {
        $fields["Student.ChosenClass"] = "EF".$target_year;
        $fields["Student.CurrentYear"] = (string)$target_year;
    }
    return (document_context_model_bundle(
        $model_file,
        $context_bindings,
        $fields,
        [
            "current_user_id" => (int)$id_creator,
            "owner_user_id" => (int)($user["id"] ?? 0),
            "strict" => (bool)$strict,
        ]
    ));
}

function registration_form_build_document_schema(array $metadata, $role = "", array $known_values = [], $model_reference = "")
{
    $role = trim((string)$role);
    $definition = dabsic_form_role_definition($metadata, $role);
    if ($definition == NULL)
        return ([
            "requested_fields" => [],
            "fields" => [],
            "groups" => [],
            "group_labels" => [],
            "group_access" => [],
            "signature_groups" => [],
        ]);

    $read = array_flip(dabsic_form_role_groups($metadata, $role, "read"));
    $edit = array_flip(dabsic_form_role_groups($metadata, $role, "edit"));
    $validate = array_flip(dabsic_form_role_groups($metadata, $role, "validate"));
    $schema = [
        "requested_fields" => [],
        "fields" => [],
        "groups" => [],
        "group_labels" => [],
        "group_access" => [],
        "signature_groups" => [],
    ];
    foreach (($metadata["group_order"] ?? []) as $group)
    {
        if (!isset($read[$group]))
            continue ;
        $group_definition = $metadata["groups"][$group] ?? [];
        $excluded_models = is_array($group_definition["exclude_models"] ?? NULL)
            ? $group_definition["exclude_models"] : [];
        if (count($excluded_models) && in_array(basename((string)$model_reference), $excluded_models, true))
            continue ;
        $schema["groups"][] = $group;
        $schema["group_labels"][$group] = (string)($metadata["groups"][$group]["label"] ?? $group);
        $schema["group_access"][$group] = [
            "edit" => isset($edit[$group]),
            "validate" => isset($validate[$group]),
        ];
        foreach (($metadata["groups"][$group]["fields"] ?? []) as $field)
        {
            $field_definition = $metadata["fields"][$field] ?? [];
            $has_prefilled_value = array_key_exists($field, $known_values)
                && $known_values[$field] !== NULL
                && !(is_string($known_values[$field]) && trim($known_values[$field]) === "");
            $editable = isset($edit[$group])
                && empty($field_definition["readonly"])
                && !( !empty($field_definition["readonly_if_prefilled"]) && $has_prefilled_value );
            if ($editable)
                $schema["requested_fields"][] = $field;
            $declared_type = strtolower(trim((string)($field_definition["type"] ?? "")));
            $field_type = !empty($field_definition["type_explicit"]) && form_field_is_common_type($declared_type)
                ? $declared_type
                : registration_form_field_type($field);
            $schema["fields"][$field] = [
                "label" => (string)($field_definition["label"] ?? registration_form_label($field)),
                "custom_label" => true,
                "type" => $field_type,
                "declared_type" => $declared_type,
                "group" => $group,
                "editable" => $editable,
                "required" => !empty($field_definition["required"]),
                "readonly_if_prefilled" => !empty($field_definition["readonly_if_prefilled"]),
                "choices" => $field_definition["choices"] ?? [],
                "choice_values" => $field_definition["choice_values"] ?? [],
                "start_field" => $field_definition["start_field"] ?? "",
                "end_field" => $field_definition["end_field"] ?? "",
                "summary_field" => $field_definition["summary_field"] ?? "",
            ];
        }
    }
    return ($schema);
}

function registration_form_profile_fields()
{
    return ([
        "Signatories.Student.FirstName",
        "Signatories.Student.FamilyName",
        "Signatories.Student.Gender",
        "Signatories.Student.Mail",
        "Signatories.Student.Phone",
        "Signatories.Student.Address",
        "Signatories.Student.PostalCode",
        "Signatories.Student.City",
        "Signatories.Student.BirthDate",
        "Signatories.Student.BirthCity",
        "Signatories.Student.BirthCountry",
        "Signatories.Student.Nationality",
        "Signatories.Student.SendSchoolReport",
        "Signatories.Student.IntranetAccess",
        "Signatories.Student.Signature",
    ]);
}

function registration_form_profile_answers(array $user)
{
    // Le formulaire public complète à la fois user et administrative_data.
    // Il doit donc préremplir l'identité complète utilisée par les contrats,
    // indépendamment du sous-ensemble affiché dans le bloc administratif.
    $values = dabsic_pascalcase_array(document_builder_contract_person_fields($user));
    $answers = [];
    foreach (registration_form_profile_fields() as $path)
    {
        if (substr($path, -10) == ".Signature")
            continue ;
        $leaf = substr($path, strlen("Signatories.Student."));
        if (!array_key_exists($leaf, $values))
            continue ;
        $value = $values[$leaf];
        if (is_bool($value))
            $value = $value ? "1" : "0";
        // document_builder_contract_person_fields() expose les dates sous leur
        // forme lisible française (dd/mm/YYYY), alors qu'un input type=date
        // n'accepte qu'une valeur ISO YYYY-mm-dd.
        if ($leaf == "BirthDate" && is_string($value) && trim($value) != "")
            $value = datex("Y-m-d", date_to_timestamp($value));
        if (is_scalar($value) || $value === NULL)
            $answers[$path] = (string)$value;
    }
    return ($answers);
}

function registration_form_is_profile_kind($kind)
{
    return (strtoupper(trim((string)$kind)) == registration_form_profile_kind());
}

function registration_form_token_hash($token)
{
    return (public_invitation_token_hash($token));
}

/**
 * Revoke invitations while preserving their rows as an audit trail.
 * Pending document tasks belong to the invitation and must be expired with it.
 */
function registration_form_revoke_rows(array $ids)
{
    global $Database;

    $ids = array_values(array_unique(array_filter(array_map("intval", $ids), function ($id) {
        return ($id > 0);
    })));
    if (!count($ids))
        return (true);
    $list = implode(",", $ids);
    if ($Database->query("UPDATE user_form
        SET revoked_at = COALESCE(revoked_at, NOW()),
            expires_at = LEAST(expires_at, DATE_SUB(NOW(), INTERVAL 1 SECOND))
        WHERE id IN ($list)") === false)
        return (false);

    require_once (__DIR__."/document_tasks.php");
    foreach ($ids as $id)
        if (!document_task_expire_form($id))
            return (false);
    return (true);
}

function registration_form_document_kind($reference, $target_year, $form_role)
{
    return ("DOC-".substr(hash("sha256", implode("|", [
        (string)$reference,
        (string)(int)$target_year,
        trim((string)$form_role),
    ])), 0, 28));
}

function registration_form_superseded_document_form_ids($id_user, $output_key, $form_role)
{
    $id_user = (int)$id_user;
    $ids = [];
    foreach (db_select_all("id, fields FROM user_form
        WHERE id_user = $id_user AND kind LIKE 'DOC-%' AND revoked_at IS NULL") as $row)
    {
        $schema = registration_form_decode_json($row["fields"] ?? "", []);
        $document = isset($schema["document"]) && is_array($schema["document"])
            ? $schema["document"] : [];
        if ((string)($document["output"] ?? "") !== (string)$output_key
            || (string)($document["form_role"] ?? "") !== (string)$form_role)
            continue ;
        if (trim((string)($document["processed_at"] ?? "")) != ""
            || trim((string)($document["instance_id"] ?? "")) != "")
            continue ;
        $ids[] = (int)$row["id"];
    }
    return ($ids);
}

function registration_form_decode_json($value, $default = [])
{
    if (is_array($value))
        return ($value);
    $decoded = json_decode((string)$value, true);
    return (is_array($decoded) ? $decoded : $default);
}

function registration_form_public_url($token, $base_url = NULL)
{
    return (public_invitation_url("RegistrationForm", $token, [], $base_url));
}

function registration_form_document_signature_base_url(array $instance, $owner_user_id)
{
    global $Database;

    $school = NULL;
    $codename = trim((string)($instance["SchoolCodename"] ?? ""));
    if ($codename != "")
    {
        $codename_sql = $Database->real_escape_string($codename);
        $school = db_select_one("base_url FROM school
            WHERE codename = '$codename_sql' AND deleted IS NULL");
    }

    if ($school == NULL && (int)$owner_user_id > 0)
    {
        $owner_user_id = (int)$owner_user_id;
        $school = db_select_one("school.base_url AS base_url FROM user_school
            LEFT JOIN school ON school.id = user_school.id_school
            WHERE user_school.id_user = $owner_user_id AND school.deleted IS NULL
            ORDER BY user_school.id ASC");
    }

    $base = trim((string)($school["base_url"] ?? ""));
    if ($base != "" && function_exists("school_base_url_normalize"))
    {
        $normalized = school_base_url_normalize($base);
        $base = ($normalized === false ? "" : $normalized);
    }
    if ($base != "")
        return (rtrim($base, "/"));

    // Interactive requests can still provide their own origin. Albedo runs from
    // CLI and therefore has no HTTP_HOST: in that context a configured school
    // base_url is required for a usable absolute invitation link.
    return (rtrim(public_invitation_request_base_url(), "/"));
}

function registration_form_allowed_prefixes()
{
    return ([
        "Signatories.Student.",
        "Signatories.Finance.",
        "Signatories.Legal1.",
        "Signatories.Legal2.",
        "Signatories.Emergency.",
        "Legal1.",
        "Legal2.",
        "Emergency.",
    ]);
}

function registration_form_field_allowed($field)
{
    if (strncmp($field, "Signatories.Director.", 23) === 0)
        return (false);
    foreach (registration_form_allowed_prefixes() as $prefix)
        if (strncmp($field, $prefix, strlen($prefix)) === 0)
            return (true);
    return (false);
}

function registration_form_group($field)
{
    $parts = user_identity_path_parts($field);
    if (!count($parts))
        return ("");
    if ($parts[0] == "Signatories" && isset($parts[1]))
        return ("Signatories.".$parts[1]);
    if (in_array($parts[0], ["Legal1", "Legal2", "Emergency"], true))
        return ($parts[0]);
    return ($parts[0]);
}

function registration_form_field_type($field)
{
    $parts = user_identity_path_parts($field);
    $leaf = count($parts) ? strtolower(end($parts)) : "";
    if (user_identity_boolean_field($field))
        return ("boolean");
    if ($leaf == "gender")
        return ("gender");
    if (preg_match('/(?:birthdate|date)$/', $leaf))
        return ("date");
    if ($leaf == "mail" || $leaf == "email" || $leaf == "courriel")
        return ("email");
    if ($leaf == "phone" || $leaf == "telephone")
        return ("tel");
    if (in_array($leaf, ["handicapkind", "lastclass", "address"], true))
        return ("textarea");
    return ("text");
}

function registration_form_label($field)
{
    global $Dictionnary;
    $parts = user_identity_path_parts($field);
    $leaf = count($parts) ? end($parts) : $field;
    return ($Dictionnary[$leaf] ?? preg_replace('/(?<!^)([A-Z])/', ' $1', $leaf));
}

function registration_form_strip_missing_values($value)
{
    if (!is_array($value))
        return ($value);
    $out = [];
    foreach ($value as $key => $child)
    {
        if (is_array($child))
        {
            $child = registration_form_strip_missing_values($child);
            if (count($child))
                $out[$key] = $child;
        }
        else if ($child !== NULL && $child !== "")
            $out[$key] = $child;
    }
    return ($out);
}

function registration_form_signature_user(array $student, $group, array $answers = [])
{
    if ($group == "Signatories.Student")
        return ($student);
    $parents = document_builder_fetch_legal_representatives((int)$student["id"]);
    if ($group == "Legal1" || $group == "Signatories.Legal1")
        return ($parents[0] ?? []);
    if ($group == "Legal2" || $group == "Signatories.Legal2")
        return ($parents[1] ?? []);
    if ($group == "Signatories.Finance")
    {
        $is = $answers["Signatories.Finance.Is"] ?? "";
        if ($is == "" && function_exists("user_identity_document_context"))
        {
            $document_context = user_identity_document_context($student);
            $found = false;
            $known = user_identity_nested_get($document_context, ["Signatories", "Finance", "Is"], $found);
            if ($found)
                $is = $known;
        }
        if ($is == "Student")
            return ($student);
        if ($is == "Legal1")
            return ($parents[0] ?? []);
        if ($is == "Legal2")
            return ($parents[1] ?? []);
    }
    return ([]);
}

function registration_form_signature_path(array $student, $group, array $answers = [])
{
    global $Configuration;
    $user = registration_form_signature_user($student, $group, $answers);
    if (count($user) && isset($user["codename"]))
        return ($Configuration->UsersDir($user["codename"])."admin/signature.png");
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', "_", $group));
    return ($Configuration->UsersDir($student["codename"])."admin/signatures/".$slug."/signature.png");
}

function registration_form_answer_target(array $student, $path, $parents = NULL)
{
    if ($parents === NULL)
        $parents = document_builder_fetch_legal_representatives((int)$student["id"]);
    foreach ([
        "Legal1." => $parents[0] ?? [],
        "Signatories.Legal1." => $parents[0] ?? [],
        "Legal2." => $parents[1] ?? [],
        "Signatories.Legal2." => $parents[1] ?? [],
    ] as $prefix => $parent)
        if (strncmp($path, $prefix, strlen($prefix)) === 0 && isset($parent["id"]))
            return ([
                "id_user" => (int)$parent["id"],
                "path" => "Signatories.Student.".substr($path, strlen($prefix))
            ]);
    return (["id_user" => (int)$student["id"], "path" => $path]);
}

function registration_form_partition_answers(array $student, array $answers)
{
    $out = [];
    $parents = document_builder_fetch_legal_representatives((int)$student["id"]);
    foreach ($answers as $path => $value)
    {
        $target = registration_form_answer_target($student, $path, $parents);
        $out[$target["id_user"]][$target["path"]] = $value;
    }
    return ($out);
}

function registration_form_partition_paths(array $student, array $paths)
{
    $out = [];
    $parents = document_builder_fetch_legal_representatives((int)$student["id"]);
    foreach ($paths as $path)
    {
        $target = registration_form_answer_target($student, $path, $parents);
        $out[$target["id_user"]][] = $target["path"];
    }
    return ($out);
}

function registration_form_context(array $student, $kind, array $answers, array $groups)
{
    $context = dabsic_pascalcase_array(
        registration_form_strip_missing_values(document_builder_contract_context($student, $kind))
    );
    // Valeurs techniques ou déjà déterminées par la fiche de prospection.
    $context["Delay"] = "";
    $context["Certification"] = "";
    user_identity_nested_set($context, ["Signatories", "Director", "Signature"], "");
    $classes = [0 => "", 1 => "EF1", 2 => "EF2", 3 => "EF3", 4 => "EF4", 5 => "EF5", 6 => "EF2X", 7 => "EF3X"];
    $entries = [0 => "September", 1 => "January", 2 => "April"];
    if (isset($student["target_class"], $classes[(int)$student["target_class"]]) && $classes[(int)$student["target_class"]] != "")
        user_identity_nested_set($context, ["Signatories", "Student", "ChosenClass"], $classes[(int)$student["target_class"]]);
    if (isset($student["target_entry"], $entries[(int)$student["target_entry"]]))
        user_identity_nested_set($context, ["Signatories", "Student", "Month"], $entries[(int)$student["target_entry"]]);
    foreach ($answers as $path => $value)
        user_identity_nested_set($context, user_identity_path_parts($path), user_identity_normalize_answer($path, $value));
    $context = user_identity_complete_signatory_context($context);
    foreach ($groups as $group)
    {
        $file = registration_form_signature_path($student, $group, $answers);
        if (is_file($file))
            user_identity_nested_set($context, user_identity_path_parts($group.".Signature"), $file);
    }
    return ($context);
}

function registration_form_analyse(array $student, $kind, array $answers = [], array $known_groups = [])
{
    global $Language;
    $model = document_builder_find_model($kind, $Language);
    if ($model === NULL)
        return (["ok" => false, "error" => "MissingFile", "details" => "contract model: ".$kind]);

    $context = registration_form_context($student, $kind, $answers, $known_groups);
    $tmp = tempnam(sys_get_temp_dir(), "infosphere_registration_");
    if ($tmp === false)
        return (["ok" => false, "error" => "CannotWriteFile"]);
    @unlink($tmp);
    $tmp .= ".dab";
    $generated = generate_dabsic($context, $tmp);
    if ($generated->is_error())
    {
        @unlink($tmp);
        return (["ok" => false, "error" => "DabsicFormCannotSave", "details" => strval($generated)]);
    }
    $process = dabsic_form_process(dabsic_form_mergeconf_command($model, [$tmp], true));
    @unlink($tmp);
    if (in_array($process["status"], [126, 127], true))
        return (["ok" => false, "error" => "DabsicEditorMergeconfUnavailable", "details" => dabsic_form_clean_diagnostic($process["stderr"])]);
    if ($process["status"] !== 0)
        return (["ok" => false, "error" => "DabsicFormAnalysisFailed", "details" => dabsic_form_clean_diagnostic($process["stderr"] ?: $process["stdout"])]);

    $missing = dabsic_form_extract_missing_fields($process["stderr"]);
    $editable = [];
    $blocked = [];
    foreach ($missing as $field)
        if (registration_form_field_allowed($field))
            $editable[] = $field;
        else
            $blocked[] = $field;
    return (["ok" => true, "model" => $model, "missing" => $missing, "editable" => $editable, "blocked" => $blocked]);
}

function registration_form_group_is_signatory($group)
{
    return (strncmp($group, "Signatories.", 12) === 0 || in_array($group, ["Legal1", "Legal2"], true));
}

function registration_form_build_schema(array $fields)
{
    $requested = [];
    foreach ($fields as $field)
    {
        $field = dabsic_form_normalize_field($field);
        if ($field !== NULL && registration_form_field_allowed($field))
            $requested[$field] = true;
    }
    $requested = array_keys($requested);
    natcasesort($requested);
    $requested = array_values($requested);

    $schema = [
        "requested_fields" => $requested,
        "fields" => [],
        "groups" => [],
        "signature_groups" => []
    ];
    foreach ($requested as $field)
    {
        $group = registration_form_group($field);
        if ($group == "")
            continue ;
        $schema["groups"][$group] = true;
        if (registration_form_group_is_signatory($group))
            $schema["signature_groups"][$group] = true;
        if (substr($field, -10) == ".Signature")
            continue ;
        $schema["fields"][$field] = [
            "label" => registration_form_label($field),
            "type" => registration_form_field_type($field),
            "group" => $group,
        ];
    }
    $schema["groups"] = array_keys($schema["groups"]);
    natcasesort($schema["groups"]);
    $schema["groups"] = array_values($schema["groups"]);
    $schema["signature_groups"] = array_keys($schema["signature_groups"]);
    natcasesort($schema["signature_groups"]);
    $schema["signature_groups"] = array_values($schema["signature_groups"]);
    ksort($schema["fields"], SORT_NATURAL | SORT_FLAG_CASE);
    return ($schema);
}

function registration_form_schema_requested_fields(array $schema)
{
    if (isset($schema["requested_fields"]) && is_array($schema["requested_fields"]))
        return ($schema["requested_fields"]);

    // Compatibility with invitations created before requested_fields existed.
    $fields = array_keys($schema["fields"] ?? []);
    foreach (($schema["signature_groups"] ?? []) as $group)
        $fields[] = $group.".Signature";
    return ($fields);
}

function registration_form_refresh_schema($id, array $schema, array $new_fields)
{
    global $Database;

    $fields = array_merge(registration_form_schema_requested_fields($schema), $new_fields);
    $new_schema = registration_form_build_schema($fields);
    $old_json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $new_json = json_encode($new_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($new_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    $changed = ($old_json !== $new_json);
    if ($changed)
    {
        $id = (int)$id;
        $escaped = $Database->real_escape_string($new_json);
        if (!$Database->query("UPDATE user_form SET fields = '$escaped' WHERE id = $id"))
            return (["ok" => false, "error" => "CannotEdit"]);
    }
    return (["ok" => true, "schema" => $new_schema, "changed" => $changed]);
}

function registration_form_fetch_invitation($token, $allow_completed = true)
{
    global $Database;
    if (!public_invitation_token_is_valid($token))
        return (["ok" => false, "error" => "RegistrationFormInvalidToken"]);
    $hash = $Database->real_escape_string(registration_form_token_hash($token));
    $row = db_select_one("user_form.*, user.codename, user.mail, user.first_name, user.family_name, user.profile_status
        FROM user_form LEFT JOIN user ON user.id = user_form.id_user
        WHERE token_hash = '$hash'");
    if ($row == NULL)
    {
        $event = communication_event_find_by_token($token);
        if ($event != NULL)
        {
            $schema = communication_event_form_schema($event);
            if (!count($schema["event"]["sessions"] ?? []))
                return (["ok" => false, "error" => "CommunicationEventNoSession"]);
            return (["ok" => true, "invitation" => [
                "id" => 0,
                "id_user" => 0,
                "id_creator" => (int)($event["id_creator"] ?? 0),
                "recipient_mail" => "",
                "recipient_name" => "",
                "kind" => communication_event_kind(),
                "token_hash" => $hash,
                "fields" => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                "answers" => "{}",
                "created_at" => $event["created_at"] ?? NULL,
                "expires_at" => NULL,
                "last_saved_at" => NULL,
                "completed_at" => NULL,
                "revoked_at" => NULL,
                "event_public" => true,
                "event" => $event,
                "schema" => $schema,
                "answers_data" => [],
            ]]);
        }
    }
    if ($row == NULL || public_invitation_is_revoked($row["revoked_at"] ?? NULL))
        return (["ok" => false, "error" => "RegistrationFormInvalidToken"]);
    if (public_invitation_is_expired($row["expires_at"] ?? NULL))
        return (["ok" => false, "error" => "RegistrationFormExpired"]);
    if (!$allow_completed && $row["completed_at"] !== NULL)
        return (["ok" => false, "error" => "RegistrationFormCompleted"]);
    $row["schema"] = registration_form_decode_json($row["fields"], ["fields" => [], "groups" => [], "signature_groups" => []]);
    $row["answers_data"] = registration_form_decode_json($row["answers"], []);

    // Les invitations PROFILE créées par les premières versions pouvaient arriver
    // avec un schéma vide. Un compte extern n'a par ailleurs aucune raison d'avoir
    // une invitation contractuelle ECL/OF/OFA/CFA : on peut donc réparer sans ambiguïté.
    $kind = strtoupper(trim((string)($row["kind"] ?? "")));
    $is_relation_profile = function_exists("user_relation_is_administrative_contact")
        && user_relation_is_administrative_contact((int)($row["id_user"] ?? 0));
    if (!registration_form_is_document_kind($kind)
        && !registration_form_is_document_signature_kind($kind)
        && ($is_relation_profile || ($row["profile_status"] ?? "") == "extern"))
    {
        // La relation est plus fiable que profile_status pour les anciens comptes :
        // certains responsables créés/manipulés avant le statut extern peuvent
        // encore être marqués prospect. Une personne légal/finance reçoit ici
        // uniquement son formulaire administratif, jamais un contrat étudiant.
        $row["kind"] = registration_form_profile_kind();
        $kind = registration_form_profile_kind();
    }
    if (registration_form_is_document_kind($kind))
    {
        $document = isset($row["schema"]["document"]) && is_array($row["schema"]["document"])
            ? $row["schema"]["document"] : [];
        $known = dabsic_form_prefill_from_chain((string)($document["chain"] ?? ""));
        $output_key = trim((string)($document["output"] ?? ""));
        if ($output_key != "")
        {
            $output = dabsic_form_resolve_output($output_key, false, (int)$row["id_user"]);
            if ($output["ok"])
            {
                $loaded_output = dabsic_form_load_output_values($output);
                if ($loaded_output["ok"])
                    $known = array_merge($known, $loaded_output["values"]);
            }
        }
        foreach (($row["schema"]["fields"] ?? []) as $field => $definition)
            if (empty($definition["editable"]))
                $row["answers_data"][$field] = dabsic_form_field_value($known, $field, $definition);
    }

    if (registration_form_is_profile_kind($kind))
    {
        // PROFILE a un schéma canonique. Ne jamais conserver les champs ECL/OF/
        // OFA/CFA d'une vieille invitation mal typée : un responsable ne doit
        // voir que son identité contractuelle et ses données administratives.
        $required = registration_form_profile_fields();
        $repaired_schema = registration_form_build_schema($required);
        $schema_changed = (($row["schema"]["fields"] ?? []) != ($repaired_schema["fields"] ?? [])
            || ($row["schema"]["signature_groups"] ?? []) != ($repaired_schema["signature_groups"] ?? [])
            || ($row["schema"]["requested_fields"] ?? []) != ($repaired_schema["requested_fields"] ?? []));
        $row["schema"] = $repaired_schema;

        $profile_user = db_select_one("* FROM user WHERE id = ".(int)$row["id_user"]." AND authority != -1");
        $profile_answers = $profile_user != NULL ? registration_form_profile_answers($profile_user) : [];
        $allowed_answers = array_flip($required);
        $stored_answers = array_intersect_key($row["answers_data"], $allowed_answers);
        // Un brouillon déjà saisi prime sur la valeur actuellement en base.
        $row["answers_data"] = array_merge($profile_answers, $stored_answers);

        if ($schema_changed || $kind != registration_form_profile_kind()
            || $row["answers_data"] != registration_form_decode_json($row["answers"], []))
        {
            $schema_json = json_encode($repaired_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answers_json = json_encode($row["answers_data"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($schema_json !== false && $answers_json !== false)
            {
                $id = (int)$row["id"];
                $escaped_schema = $Database->real_escape_string($schema_json);
                $escaped_answers = $Database->real_escape_string($answers_json);
                $escaped_kind = $Database->real_escape_string(registration_form_profile_kind());
                $Database->query("UPDATE user_form
                    SET fields = '$escaped_schema', answers = '$escaped_answers', kind = '$escaped_kind'
                    WHERE id = $id");
            }
        }
    }
    return (["ok" => true, "invitation" => $row]);
}

function registration_form_student($id_user)
{
    $ret = document_builder_full_student((int)$id_user);
    if ($ret->is_error())
        return (["ok" => false, "error" => $ret->label ?? "UserNotFound", "details" => strval($ret)]);
    return (["ok" => true, "student" => $ret->value]);
}

function registration_form_canonicalize($value)
{
    if (!is_array($value))
        return ($value);
    $associative = array_keys($value) !== range(0, count($value) - 1);
    if ($associative)
        ksort($value, SORT_STRING);
    foreach ($value as $key => $child)
        $value[$key] = registration_form_canonicalize($child);
    return ($value);
}

function registration_form_canonical_json($value)
{
    return (json_encode(
        registration_form_canonicalize($value),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    ));
}


function registration_form_paraph_path(array $invitation, array $owner_user)
{
    global $Configuration;

    $id_form = (int)($invitation["id"] ?? 0);
    $codename = trim((string)($owner_user["codename"] ?? ""));
    if ($id_form <= 0 || $codename == "")
        return ("");
    return ($Configuration->UsersDir($codename)."admin/form_paraphs/registration-".$id_form.".png");
}

function registration_form_paraph_evidence_path(array $invitation, array $owner_user)
{
    $image = registration_form_paraph_path($invitation, $owner_user);
    return ($image == "" ? "" : preg_replace('/\.png$/D', '.dab', $image));
}

function registration_form_handle_paraph(array $invitation, array $owner_user)
{
    $target = registration_form_paraph_path($invitation, $owner_user);
    if ($target == "")
        return (["ok" => false, "error" => "CannotWritePngFile"]);

    $reuse_profile_initials = function () use ($invitation, $owner_user, $target) {
        if (is_file($target) || !registration_form_is_document_signature_kind($invitation["kind"] ?? "")
            || !function_exists("user_identity_document_initials_file"))
            return (["ok" => true, "exists" => is_file($target)]);
        $profile_initials = user_identity_document_initials_file($owner_user);
        if ($profile_initials == "" || !is_file($profile_initials))
            return (["ok" => true, "exists" => false]);
        $saved = user_identity_store_signature_png($profile_initials, $target, false);
        if (!$saved["ok"])
            return ($saved);
        return (["ok" => true, "exists" => true, "profile" => true]);
    };

    if (!isset($_FILES["paraph"]) || !is_array($_FILES["paraph"]))
        return ($reuse_profile_initials());
    $error = (int)($_FILES["paraph"]["error"] ?? UPLOAD_ERR_NO_FILE);
    if ($error == UPLOAD_ERR_NO_FILE)
        return ($reuse_profile_initials());
    if ($error != UPLOAD_ERR_OK || empty($_FILES["paraph"]["tmp_name"]))
        return (["ok" => false, "error" => "RegistrationFormInvalidParaph"]);
    $saved = registration_form_store_png($_FILES["paraph"]["tmp_name"], $target);
    if (!$saved["ok"])
        return ($saved);
    return (["ok" => true, "exists" => true]);
}

function registration_form_record_paraph_evidence(array $invitation, array $owner_user, array $answers)
{
    $image = registration_form_paraph_path($invitation, $owner_user);
    if ($image == "" || !is_file($image))
        return (["ok" => false, "error" => "RegistrationFormParaphRequired"]);
    $paraph_hash = hash_file("sha256", $image);
    if ($paraph_hash === false)
        return (["ok" => false, "error" => "CannotReadFile"]);

    $snapshot = $answers;
    foreach (array_keys($snapshot) as $key)
        if (substr((string)$key, -10) === ".Signature")
            unset($snapshot[$key]);
    $answers_json = registration_form_canonical_json($snapshot);
    if ($answers_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);

    $now = new DateTimeImmutable("now");
    $evidence = [
        "version" => "form-paraph-v1",
        "registration_form_id" => (int)($invitation["id"] ?? 0),
        "kind" => (string)($invitation["kind"] ?? ""),
        "token_hash" => (string)($invitation["token_hash"] ?? ""),
        "owner_user_id" => (int)($owner_user["id"] ?? 0),
        "recipient_name" => trim((string)($invitation["recipient_name"] ?? "")),
        "recipient_mail" => trim((string)($invitation["recipient_mail"] ?? "")),
        "validated_at" => $now->format("Y-m-d\TH:i:s.uP"),
        "client_ip" => function_exists("get_client_ip") ? (string)get_client_ip() : (string)($_SERVER["REMOTE_ADDR"] ?? ""),
        "user_agent" => (string)($_SERVER["HTTP_USER_AGENT"] ?? ""),
        "answers_sha256" => hash("sha256", $answers_json),
        "paraph_sha256" => $paraph_hash,
    ];
    $target = registration_form_paraph_evidence_path($invitation, $owner_user);
    if ($target == "")
        return (["ok" => false, "error" => "CannotWriteFile"]);
    $written = generate_dabsic($evidence, $target);
    if ($written->is_error())
        return (["ok" => false, "error" => $written->label ?? "CannotWriteFile", "details" => strval($written)]);
    add_log(EDITING_OPERATION,
        "Online form ".(int)($invitation["id"] ?? 0)." validated with paraph ".$paraph_hash,
        (int)($owner_user["id"] ?? 0)
    );
    return (["ok" => true, "paraph_sha256" => $paraph_hash]);
}
function registration_form_signature_consent_text()
{
    return ("Je certifie sur l'honneur l'exactitude des informations renseignées et confirme être la personne désignée par ce formulaire. Je demande que la signature tracée soit associée à cette validation électronique.");
}

function registration_form_required_profile_signature(array $schema)
{
    return (in_array(
        "Signatories.Student.Signature",
        registration_form_schema_requested_fields($schema),
        true
    ));
}

function registration_form_evidence_signature_path(array $user, $id_form, $evidence_hash)
{
    global $Configuration;
    $codename = $user["codename"] ?? "unknown";
    return ($Configuration->UsersDir($codename)."admin/signature_evidence/registration-".
        (int)$id_form."-".substr($evidence_hash, 0, 20).".png");
}

function registration_form_record_profile_signature_evidence(array $invitation, array $user, array $schema, array $answers)
{
    global $Database;

    $id_form = (int)($invitation["id"] ?? 0);
    $id_user = (int)($user["id"] ?? 0);
    if ($id_form <= 0 || $id_user <= 0)
        return (["ok" => false, "error" => "CannotEdit"]);

    $group = "Signatories.Student";
    $signature = registration_form_signature_path($user, $group, $answers);
    if (!is_file($signature))
        return (["ok" => false, "error" => "RegistrationFormSignatureRequired"]);
    $signature_hash = hash_file("sha256", $signature);
    if ($signature_hash === false)
        return (["ok" => false, "error" => "CannotReadFile"]);

    $snapshot_answers = $answers;
    unset($snapshot_answers[$group.".Signature"]);
    $schema_json = registration_form_canonical_json($schema);
    $answers_json = registration_form_canonical_json($snapshot_answers);
    if ($schema_json === false || $answers_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);

    $now = new DateTimeImmutable("now");
    $signed_at = $now->format("Y-m-d H:i:s.u");
    $signed_at_iso = $now->format("Y-m-d\\TH:i:s.uP");
    $recipient_mail = trim((string)($invitation["recipient_mail"] ?? $invitation["mail"] ?? $user["mail"] ?? ""));
    $recipient_name = trim((string)($invitation["recipient_name"] ?? ""));
    if ($recipient_name == "")
        $recipient_name = trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? ""));
    $client_ip = function_exists("get_client_ip") ? (string)get_client_ip() : (string)($_SERVER["REMOTE_ADDR"] ?? "");
    $user_agent = (string)($_SERVER["HTTP_USER_AGENT"] ?? "");
    $consent = registration_form_signature_consent_text();

    $evidence = [
        "version" => 1,
        "registration_form_id" => $id_form,
        "kind" => (string)($invitation["kind"] ?? ""),
        "token_hash" => (string)($invitation["token_hash"] ?? ""),
        "signatory" => [
            "id_user" => $id_user,
            "name" => $recipient_name,
            "recipient_mail" => $recipient_mail,
        ],
        "signed_at" => $signed_at_iso,
        "request" => [
            "ip" => $client_ip,
            "user_agent" => $user_agent,
        ],
        "consent" => [
            "version" => "profile-signature-v1",
            "text" => $consent,
        ],
        "schema_sha256" => hash("sha256", $schema_json),
        "answers_sha256" => hash("sha256", $answers_json),
        "signature_sha256" => $signature_hash,
        "answers" => registration_form_canonicalize($snapshot_answers),
    ];
    $evidence_json = registration_form_canonical_json($evidence);
    if ($evidence_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    $evidence_hash = hash("sha256", $evidence_json);

    $existing = db_select_one("evidence_sha256 FROM user_form_signature_evidence WHERE id_form = $id_form AND signature_group = 'Signatories.Student'");
    if ($existing != NULL)
        return (($existing["evidence_sha256"] ?? "") === $evidence_hash
            ? ["ok" => true, "evidence_sha256" => $evidence_hash]
            : ["ok" => false, "error" => "RegistrationFormAlreadySigned"]);

    $archive = registration_form_evidence_signature_path($user, $id_form, $evidence_hash);
    if (($ret = new_directory($archive))->is_error())
        return (["ok" => false, "error" => "CannotWritePngFile", "details" => strval($ret)]);
    if (!@copy($signature, $archive))
        return (["ok" => false, "error" => "CannotWritePngFile"]);
    @chmod($archive, 0640);
    if (hash_file("sha256", $archive) !== $signature_hash)
    {
        @unlink($archive);
        return (["ok" => false, "error" => "CannotWritePngFile"]);
    }

    $sql = function ($value) use ($Database) {
        return ($Database->real_escape_string((string)$value));
    };
    $group_sql = $sql($group);
    $mail_sql = $sql($recipient_mail);
    $name_sql = $sql($recipient_name);
    $ip_sql = $sql($client_ip);
    $ua_sql = $sql($user_agent);
    $consent_sql = $sql($consent);
    $schema_hash = hash("sha256", $schema_json);
    $answers_hash = hash("sha256", $answers_json);
    $archive_sql = $sql($archive);
    $evidence_sql = $sql($evidence_json);
    if (!$Database->query("INSERT INTO user_form_signature_evidence
        (id_form, id_user, signature_group, signed_at,
         recipient_mail, signatory_name, client_ip, user_agent,
         consent_version, consent_text, schema_sha256, answers_sha256,
         signature_sha256, evidence_sha256, signature_file, evidence)
        VALUES
        ($id_form, $id_user, '$group_sql', '$signed_at',
         '$mail_sql', '$name_sql', '$ip_sql', '$ua_sql',
         'profile-signature-v1', '$consent_sql', '$schema_hash', '$answers_hash',
         '$signature_hash', '$evidence_hash', '$archive_sql', '$evidence_sql')"))
    {
        @unlink($archive);
        return (["ok" => false, "error" => "CannotEdit"]);
    }
    add_log(EDITING_OPERATION,
        "Electronic administrative signature recorded for user $id_user, evidence $evidence_hash",
        $id_user
    );
    return (["ok" => true, "evidence_sha256" => $evidence_hash]);
}

function registration_form_latest_signature_evidence($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (NULL);
    return (db_select_one("* FROM user_form_signature_evidence
        WHERE id_user = $id_user AND signature_group = 'Signatories.Student'
        ORDER BY signed_at DESC, id DESC"));
}

function registration_form_create_invitation($id_user, $kind, $id_creator)
{
    global $Database;
    $kind = strtoupper(trim((string)$kind));
    if (!in_array($kind, registration_form_kinds(), true))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "kind"]);
    $loaded = registration_form_student($id_user);
    if (!$loaded["ok"])
        return ($loaded);
    $student = $loaded["student"];
    $school = document_builder_fetch_student_school($student);
    if (!is_array($school) || !school_activity_contract_kind_allowed($school, $kind))
        return (["ok" => false, "error" => "ContractModeUnavailable", "details" => $kind]);
    if (($student["profile_status"] ?? "") != "prospect" || trim((string)($student["mail"] ?? "")) == "")
        return (["ok" => false, "error" => "RegistrationFormProspectRequired"]);

    $analysis = registration_form_analyse($student, $kind);
    if (!$analysis["ok"])
        return ($analysis);
    if (count($analysis["blocked"]))
        return (["ok" => false, "error" => "RegistrationFormInternalFieldsMissing", "details" => implode("\n", $analysis["blocked"])]);
    $schema = registration_form_build_schema($analysis["editable"]);
    $schema_json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($schema_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);

    $signature_base_url = registration_form_document_signature_base_url($instance, $owner_user_id);
    if ($signature_base_url == "")
        return ([
            "ok" => false,
            "error" => "InvalidSchoolBaseUrl",
            "details" => "A complete school base_url is required for background signature invitations.",
        ]);

    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(registration_form_token_hash($token));
    $schema_sql = $Database->real_escape_string($schema_json);
    $id_user = (int)$id_user;
    $id_creator = (int)$id_creator;
    $kind_sql = $Database->real_escape_string($kind);
    $recipient_mail = $Database->real_escape_string((string)($student["mail"] ?? ""));
    $recipient_name = $Database->real_escape_string(trim((string)($student["first_name"] ?? "")." ".(string)($student["family_name"] ?? "")));
    $Database->query("UPDATE user_form SET revoked_at = NOW()
        WHERE id_user = $id_user AND kind = '$kind_sql' AND completed_at IS NULL AND revoked_at IS NULL");
    if (!$Database->query("INSERT INTO user_form
        (id_user, id_creator, recipient_mail, recipient_name, kind, token_hash, fields, answers, expires_at)
        VALUES ($id_user, $id_creator, '$recipient_mail', '$recipient_name', '$kind_sql', '$hash', '$schema_sql', '{}', DATE_ADD(NOW(), INTERVAL 14 DAY))"))
        return (["ok" => false, "error" => "CannotEdit"]);
    return (["ok" => true, "token" => $token, "url" => registration_form_public_url($token), "student" => $student, "schema" => $schema]);
}

function registration_form_create_profile_invitation($id_user, $id_creator)
{
    global $Database;

    $id_user = (int)$id_user;
    $id_creator = (int)$id_creator;
    $user = db_select_one("* FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (["ok" => false, "error" => "UserNotFound"]);
    if (!function_exists("user_relation_is_administrative_contact")
        || !user_relation_is_administrative_contact($id_user))
        return (["ok" => false, "error" => "RegistrationFormExternalRequired"]);
    if (trim((string)($user["mail"] ?? "")) == "")
        return (["ok" => false, "error" => "MissingField", "details" => "mail"]);

    $schema = registration_form_build_schema(registration_form_profile_fields());
    if (!count($schema["fields"] ?? []))
        return (["ok" => false, "error" => "CannotEdit", "details" => "Empty administrative profile schema"]);
    $answers = registration_form_profile_answers($user);
    $schema_json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($schema_json === false || $answers_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);

    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(registration_form_token_hash($token));
    $schema_sql = $Database->real_escape_string($schema_json);
    $answers_sql = $Database->real_escape_string($answers_json);
    $kind = $Database->real_escape_string(registration_form_profile_kind());
    $recipient_mail = $Database->real_escape_string((string)($user["mail"] ?? ""));
    $recipient_name = $Database->real_escape_string(trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? "")));
    $Database->query("UPDATE user_form SET revoked_at = NOW()
        WHERE id_user = $id_user AND kind = '$kind' AND completed_at IS NULL AND revoked_at IS NULL");
    if (!$Database->query("INSERT INTO user_form
        (id_user, id_creator, recipient_mail, recipient_name, kind, token_hash, fields, answers, expires_at)
        VALUES ($id_user, $id_creator, '$recipient_mail', '$recipient_name', '$kind', '$hash', '$schema_sql', '$answers_sql', DATE_ADD(NOW(), INTERVAL 14 DAY))"))
        return (["ok" => false, "error" => "CannotEdit"]);
    return ([
        "ok" => true,
        "token" => $token,
        "url" => registration_form_public_url($token),
        "student" => $user,
        "schema" => $schema,
        "profile_form" => true,
    ]);
}

function registration_form_create_document_invitation($id_user, $reference, $model_hash, $target_year, $label, $id_creator, $context_bindings = [], $form_role = "Beneficiaire", $recipient_user_id = 0)
{
    global $Database;

    $id_user = (int)$id_user;
    $id_creator = (int)$id_creator;
    $target_year = (int)$target_year;
    if ($target_year < 0 || $target_year > 5)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "target_year"]);
    $form_role = trim((string)$form_role);
    if ($form_role != "" && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $form_role))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "form_role"]);
    if (!is_string($model_hash) || !preg_match('/^[a-f0-9]{32}$/i', $model_hash))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "model_hash"]);

    $user = db_select_one("* FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (["ok" => false, "error" => "UserNotFound"]);

    $resolved_reference = dabsic_editor_resolve_file($reference, false);
    if (!$resolved_reference["ok"])
        return ($resolved_reference);
    $label = document_title_from_file(
        $resolved_reference["absolute"],
        trim((string)$label) != "" ? trim((string)$label) : document_title_fallback($resolved_reference["relative"])
    );

    $source_reference = function_exists("document_reference_from_editor_path")
        ? (string)(document_reference_from_editor_path($resolved_reference["relative"]) ?? "") : "";
    $workflow_mailbox = function_exists("document_model_workflow_mailbox")
        ? document_model_workflow_mailbox($resolved_reference["absolute"]) : "";

    $bundle = registration_form_document_bundle(
        $resolved_reference["absolute"],
        $user,
        $id_creator,
        $target_year,
        $context_bindings,
        true
    );
    if (!$bundle["ok"])
        return (["ok" => false, "error" => "MissingField", "details" => "Contexts.".implode(", Contexts.", $bundle["missing"])]);
    $context_bindings = $bundle["bindings"];
    $signature_bindings = $bundle["signature_bindings"];
    $chain = $bundle["chain"];
    $chain_json = json_encode($chain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($chain_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);

    $discovery = dabsic_form_discover_fields($reference, "docbuilder", $chain_json);
    if (!$discovery["ok"])
        return ($discovery);

    $output_key = "user-document:".$id_user.":".strtolower($model_hash).":".$target_year;
    $output = dabsic_form_resolve_output($output_key, false, $id_user);
    if (!$output["ok"])
        return ($output);
    $loaded = dabsic_form_load_output_values($output);
    if (!$loaded["ok"])
        return ($loaded);

    $form_metadata = $discovery["form_metadata"] ?? dabsic_form_empty_form_metadata();
    $role_definition = dabsic_form_role_definition($form_metadata, $form_role);
    if ($role_definition == NULL)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "form_role"]);
    // Determine whether this role has an actual contribution before resolving
    // a recipient. A pure signatory/no-field role must never require an email
    // address or produce a public contribution invitation.
    $known_values = array_merge(dabsic_form_prefill_from_chain($chain_json), $loaded["values"]);
    $schema = registration_form_build_document_schema(
        $form_metadata, $form_role, $known_values, $resolved_reference["relative"]
    );
    if (!count($schema["fields"] ?? []) || !count($schema["requested_fields"] ?? []))
        return ([
            "ok" => true,
            "skipped" => true,
            "reason" => "no_fields",
            "schema" => ["document" => ["form_role" => $form_role]],
        ]);

    require_once (__DIR__."/document_workflow.php");
    $semantic_bindings = array_merge(
        is_array($context_bindings) ? $context_bindings : [],
        is_array($signature_bindings) ? $signature_bindings : []
    );
    $primary_recipient_user_id = document_workflow_form_role_primary_assignee(
        $id_user, $role_definition, $id_creator, $semantic_bindings
    );
    if ((int)$recipient_user_id <= 0)
        $recipient_user_id = document_workflow_form_role_assignee(
            $id_user, $role_definition, $id_creator, $semantic_bindings
        );
    $recipient_user_id = (int)$recipient_user_id;
    if ($recipient_user_id <= 0)
        return (["ok" => false, "error" => "DocumentRoleNoRecipient", "details" => $form_role]);
    $recipient = db_select_one("* FROM user WHERE id = $recipient_user_id AND authority != -1");
    if ($recipient == NULL)
        return (["ok" => false, "error" => "UserNotFound"]);
    if (!document_workflow_user_can_receive_form($recipient_user_id))
        return (["ok" => false, "error" => "MissingField", "details" => "mail"]);
    if (!document_workflow_form_role_dependencies_satisfied($id_user, $output_key, $role_definition))
        return (["ok" => false, "error" => "DocumentRoleDependencyPending", "details" => $form_role]);

    $reference_content = @file_get_contents($resolved_reference["absolute"]);
    if ($reference_content === false)
        return (["ok" => false, "error" => "DabsicEditorCannotRead"]);
    $form_fields = array_keys($form_metadata["fields"] ?? []);
    natcasesort($form_fields);
    $form_fields = array_values($form_fields);
    $form_roles = $form_metadata["roles"] ?? [];

    $schema["document"] = [
        "reference" => $resolved_reference["relative"],
        "source_reference" => $source_reference,
        "reference_hash" => hash("sha256", $reference_content),
        "form_fields" => $form_fields,
        "output" => $output_key,
        "chain" => $chain_json,
        "label" => trim((string)$label),
        "target_year" => $target_year,
        "model_hash" => strtolower($model_hash),
        "context_bindings" => $context_bindings,
        "signature_bindings" => $signature_bindings,
        "form_role" => $form_role,
        "form_roles" => $form_roles,
        "recipient_user_id" => $recipient_user_id,
        "primary_recipient_user_id" => (int)$primary_recipient_user_id,
        "mailbox" => $workflow_mailbox,
    ];
    $answers = [];
    foreach (($schema["fields"] ?? []) as $field => $definition)
    {
        $value = dabsic_form_field_value($known_values, $field, $definition);
        if (form_field_is_common_type($definition["type"] ?? ""))
            $answers[$field] = form_field_storage_value($definition, $value);
        else
            $answers[$field] = is_scalar($value) || $value === NULL ? (string)($value ?? "") : "";
    }

    $schema_json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($schema_json === false || $answers_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);

    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(registration_form_token_hash($token));
    $schema_sql = $Database->real_escape_string($schema_json);
    $answers_sql = $Database->real_escape_string($answers_json);
    // Each role is an autonomous contributor. Invitations for two roles of
    // the same document must coexist instead of revoking one another.
    $kind = registration_form_document_kind($resolved_reference["relative"], $target_year, $form_role);
    $kind_sql = $Database->real_escape_string($kind);
    $recipient_mail = $Database->real_escape_string((string)$recipient["mail"]);
    $recipient_name = $Database->real_escape_string(trim((string)($recipient["first_name"] ?? "")." ".(string)($recipient["family_name"] ?? "")));

    if ($Database->query("START TRANSACTION") === false)
        return (["ok" => false, "error" => "CannotEdit"]);
    $superseded = registration_form_superseded_document_form_ids(
        $id_user,
        $output_key,
        $form_role
    );
    if (!registration_form_revoke_rows($superseded))
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotEdit"]);
    }
    if (!$Database->query("INSERT INTO user_form
        (id_user, id_creator, recipient_mail, recipient_name, kind, token_hash, fields, answers, expires_at)
        VALUES ($id_user, $id_creator, '$recipient_mail', '$recipient_name', '$kind_sql', '$hash', '$schema_sql', '$answers_sql', DATE_ADD(NOW(), INTERVAL 14 DAY))"))
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotEdit"]);
    }
    $id_form = (int)$Database->insert_id;
    require_once (__DIR__."/document_workflow.php");
    $created_tasks = document_workflow_ensure_form_tasks([
        "id" => $id_form,
        "id_user" => $id_user,
        "id_creator" => $id_creator,
        "fields" => $schema_json,
        "completed_at" => NULL,
        "revoked_at" => NULL,
    ]);
    if (document_workflow_form_role_is_required($id_user, $role_definition, $id_creator))
    {
        $has_task = false;
        foreach ($created_tasks as $created_task)
            if (($created_task["task_action"] ?? "") === "fill"
                && ($created_task["role"] ?? "") === $form_role)
            {
                $has_task = true;
                break ;
            }
        if (!$has_task)
        {
            $Database->query("ROLLBACK");
            return (["ok" => false, "error" => "CannotEdit", "details" => "document task"]);
        }
    }
    if ($Database->query("COMMIT") === false)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotEdit"]);
    }

    return ([
        "ok" => true,
        "id" => $id_form,
        "token" => $token,
        "url" => registration_form_public_url($token),
        "student" => $user,
        "recipient" => $recipient,
        "schema" => $schema,
        "document_form" => true,
    ]);
}

function registration_form_revoke_token($token)
{
    global $Database;

    if (!public_invitation_token_is_valid($token))
        return (false);
    $hash = $Database->real_escape_string(registration_form_token_hash($token));
    $row = db_select_one("id FROM user_form WHERE token_hash = '$hash'");
    if ($row == NULL || $Database->query("START TRANSACTION") === false)
        return (false);
    if (!registration_form_revoke_rows([(int)$row["id"]]))
    {
        $Database->query("ROLLBACK");
        return (false);
    }
    if ($Database->query("COMMIT") === false)
    {
        $Database->query("ROLLBACK");
        return (false);
    }
    return (true);
}

function registration_form_store_png($source, $target)
{
    return (user_identity_store_signature_png($source, $target, true));
}

function registration_form_handle_signatures(array $student, array $groups, array &$answers, array $delete_groups = [])
{
    $delete_groups = array_flip($delete_groups);
    $uploaded_groups = [];
    foreach ($groups as $group)
    {
        $target = registration_form_signature_path($student, $group, $answers);
        $signature_field = $group.".Signature";
        $was_bound_to_invitation = isset($answers[$signature_field]);
        $uploaded = false;
        $deleted = false;
        if (isset($delete_groups[$group]))
        {
            if (is_file($target))
                @unlink($target);
            unset($answers[$signature_field]);
            $deleted = true;
        }
        $key = preg_replace('/[^a-zA-Z0-9_-]+/', "_", $group);
        if (isset($_FILES["signature"]["tmp_name"][$key]) && $_FILES["signature"]["error"][$key] != UPLOAD_ERR_NO_FILE)
        {
            if ($_FILES["signature"]["error"][$key] != UPLOAD_ERR_OK)
                return (["ok" => false, "error" => "RegistrationFormInvalidSignature"]);
            $saved = registration_form_store_png($_FILES["signature"]["tmp_name"][$key], $target);
            if (!$saved["ok"])
                return ($saved);
            $uploaded = true;
            $uploaded_groups[] = $group;
            $deleted = false;
        }
        // Une signature déjà présente sur le profil ne doit pas être réutilisée
        // silencieusement pour une nouvelle validation. Elle n'est liée à cette
        // invitation que si elle a été tracée pendant ce formulaire (éventuellement
        // lors d'un brouillon précédent).
        if (is_file($target) && ($uploaded || $was_bound_to_invitation))
            $answers[$signature_field] = $target;
        else if (!$uploaded && !$was_bound_to_invitation)
            unset($answers[$signature_field]);
        if ($deleted)
            $deleted_paths[] = $signature_field;
    }
    return ([
        "ok" => true,
        "deleted_paths" => $deleted_paths ?? [],
        "uploaded_groups" => $uploaded_groups,
    ]);
}

function registration_form_save_document_invitation(array $invitation, array $answers, $finalize)
{
    global $Database;

    $schema = $invitation["schema"] ?? [];
    $document = $schema["document"] ?? NULL;
    if (!is_array($document))
        return (["ok" => false, "error" => "DabsicFormChanged"]);
    $reference = $document["reference"] ?? "";
    $output_key = $document["output"] ?? "";
    $chain = $document["chain"] ?? "";
    $id_user = (int)$invitation["id_user"];

    $resolved_reference = dabsic_editor_resolve_file($reference, false);
    if (!$resolved_reference["ok"])
        return ($resolved_reference);
    $reference_content = @file_get_contents($resolved_reference["absolute"]);
    if ($reference_content === false)
        return (["ok" => false, "error" => "DabsicEditorCannotRead"]);
    $expected_reference_hash = strtolower(trim((string)($document["reference_hash"] ?? "")));
    if ($expected_reference_hash != "" &&
        (!preg_match('/^[a-f0-9]{64}$/D', $expected_reference_hash) ||
         !hash_equals($expected_reference_hash, hash("sha256", $reference_content))))
        return (["ok" => false, "error" => "DabsicFormChanged"]);

    $output = dabsic_form_resolve_output($output_key, false, $id_user);
    if (!$output["ok"])
        return ($output);
    $loaded = dabsic_form_load_output_values($output);
    if (!$loaded["ok"])
        return ($loaded);
    $overrides = dabsic_form_load_overrides($output);
    if (!$overrides["ok"])
        return ($overrides);

    // Rebuild the current declarative form model. The reference hash above
    // protects against model changes; the explicit field list gives a second,
    // human-readable invariant for persisted invitations.
    $discovery = dabsic_form_discover_fields($reference, "docbuilder", $chain);
    if (!$discovery["ok"])
        return ($discovery);
    $form_metadata = $discovery["form_metadata"] ?? dabsic_form_empty_form_metadata();
    $current_fields = array_keys($form_metadata["fields"] ?? []);
    natcasesort($current_fields);
    $current_fields = array_values($current_fields);
    if (isset($document["form_fields"]) && is_array($document["form_fields"]))
    {
        $expected_fields = $document["form_fields"];
        natcasesort($expected_fields);
        $expected_fields = array_values($expected_fields);
        if ($expected_fields !== $current_fields)
            return (["ok" => false, "error" => "DabsicFormChanged"]);
    }

    // Only fields editable by the recipient participate in concurrency
    // protection. Read-only groups are allowed to evolve on the staff side.
    $concurrent = [];
    $invitation_values = $invitation["answers_data"] ?? [];
    $prefilled = dabsic_form_prefill_from_chain($chain);
    foreach (($schema["fields"] ?? []) as $field => $definition)
    {
        if (empty($definition["editable"]))
            continue ;
        $invitation_value = form_field_storage_value($definition, $invitation_values[$field] ?? NULL);
        $current_values = array_merge($prefilled, $loaded["values"]);
        $current_value = form_field_storage_value($definition,
            dabsic_form_field_value($current_values, $field, $definition));
        if ($definition["type"] === "checkbox")
        {
            sort($invitation_value, SORT_STRING);
            sort($current_value, SORT_STRING);
        }
        if ($invitation_value !== $current_value)
            $concurrent[] = $field;
    }
    if (count($concurrent))
        return ([
            "ok" => false,
            "error" => "DocumentFormConcurrentEdit",
            "details" => implode("\n", $concurrent)
        ]);

    $recipient_role = trim((string)($schema["document"]["form_role"] ?? ""));
    $editable_answers = [];
    foreach (($schema["fields"] ?? []) as $field => $definition)
        if (!empty($definition["editable"]) && array_key_exists($field, $answers))
            $editable_answers[$field] = $answers[$field];

    $effective_values = array_merge($prefilled, $loaded["values"], $editable_answers);
    if ($finalize)
    {
        $missing = dabsic_form_missing_required_fields($form_metadata, $effective_values, $recipient_role);
        if (count($missing))
            return ([
                "ok" => false,
                "error" => "DocumentRequiredFields",
                "details" => implode("\n", array_values($missing))
            ]);
    }

    $saved = dabsic_form_save(
        $reference,
        $output_key,
        $editable_answers,
        $overrides["values"],
        hash("sha256", $reference_content),
        $loaded["hash"],
        $output["exists"] ? "1" : "0",
        $overrides["hash"],
        $overrides["exists"] ? "1" : "0",
        "docbuilder",
        $chain,
        $id_user,
        true,
        $recipient_role
    );
    if (!$saved["ok"])
        return ($saved);

    $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $answers_sql = $Database->real_escape_string($answers_json ?: "{}");
    $id = (int)$invitation["id"];
    if ($finalize)
    {
        $profile_saved = registration_form_persist_document_profile_answers($invitation, $schema, $editable_answers);
        if ($profile_saved->is_error())
            return (["ok" => false, "error" => $profile_saved->label, "details" => $profile_saved->details]);
    }
    $completed = $finalize ? ", completed_at = NOW()" : "";
    if (!$Database->query("UPDATE user_form SET answers = '$answers_sql', last_saved_at = NOW()$completed WHERE id = $id"))
        return (["ok" => false, "error" => "CannotEdit"]);
    if ($finalize)
    {
        require_once (__DIR__."/document_workflow.php");
        $recipient_role = trim((string)($schema["document"]["form_role"] ?? ""));
        if ($recipient_role != "")
        {
            $recipient_user_id = (int)($schema["document"]["recipient_user_id"] ?? $id_user);
            $task = document_task_complete_form_role(
                $id,
                $recipient_role,
                $recipient_user_id,
                $recipient_user_id
            );
            if ($task->is_error())
                add_log(REPORT, "Cannot complete document fill task $recipient_role for form $id: ".strval($task), $id_user);
        }
        $notification_form = $invitation;
        $notification_form["schema"] = $schema;
        $acknowledgement = document_workflow_notify_document_form_recipient_completed($notification_form);
        if ($acknowledgement->is_error())
            add_log(REPORT, "Cannot acknowledge document form completion to recipient: ".strval($acknowledgement), $id_user);
        $notification = document_workflow_notify_document_form_completed($notification_form);
        if ($notification->is_error())
            add_log(REPORT, "Cannot notify school after document form completion: ".strval($notification), $id_user);
    }
    add_log(EDITING_OPERATION,
        "Document form ".$reference." saved by user ".$id_user.($finalize ? " and finalized" : ""),
        $id_user
    );
    return ([
        "ok" => true,
        "completed" => (bool)$finalize,
        "refresh" => false,
        "answers" => $answers,
    ]);
}

function registration_form_persist_document_profile_answers(array $invitation, array $schema, array $editable_answers)
{
    require_once (__DIR__."/user_identity.php");
    $document = is_array($schema["document"] ?? NULL) ? $schema["document"] : [];
    $role = trim((string)($document["form_role"] ?? ""));
    $roles = is_array($document["form_roles"] ?? NULL) ? $document["form_roles"] : [];
    $definition = is_array($roles[$role] ?? NULL) ? $roles[$role] : [];
    $scopes = is_array($definition["profile_scopes"] ?? NULL) ? $definition["profile_scopes"] : [];
    $legacy_scope = trim((string)($definition["profile_scope"] ?? ""));
    if ($legacy_scope != "")
        $scopes[] = $legacy_scope;
    $scopes = array_values(array_unique(array_filter(array_map("strval", $scopes))));
    if (!count($scopes))
        return (new ValueResponse(true));

    $owner_id = (int)($invitation["id_user"] ?? 0);
    $primary_id = (int)($document["primary_recipient_user_id"] ?? 0);
    if ($primary_id <= 0)
        $primary_id = (int)($document["recipient_user_id"] ?? 0);
    foreach ($scopes as $scope)
    {
        $scope = trim($scope);
        if ($scope == "")
            continue ;
        $updates = [];
        $prefix = $scope.".";
        foreach ($editable_answers as $path => $value)
            if (strncmp((string)$path, $prefix, strlen($prefix)) === 0)
            {
                $profile_field = substr((string)$path, strlen($prefix));
                // Document contexts expose Courriel as a French alias of Mail.
                // Persist a correction back to the actual account field.
                if ($profile_field === "Courriel")
                    $profile_field = "Mail";
                $updates["Signatories.Student.".$profile_field] = $value;
            }
        if (!count($updates))
            continue ;
        // Emergency belongs to the learner's administrative context. Identity
        // scopes belong to their semantic contact even when the learner had to
        // complete the form because that contact had no usable mailbox.
        if ($scope === "Emergency")
        {
            $updates = [];
            foreach ($editable_answers as $path => $value)
                if (strncmp((string)$path, "Emergency.", 10) === 0)
                    $updates[(string)$path] = $value;
            $target_id = $owner_id;
        }
        else
            $target_id = $primary_id;
        if ($target_id <= 0 || !count($updates))
            continue ;
        $saved = user_identity_update_registration_answers($target_id, $updates);
        if ($saved->is_error())
            return ($saved);
    }
    return (new ValueResponse(true));
}

function registration_form_save_invitation($token, array $submitted, $finalize = false, array $delete_signatures = [], $signature_consent = false, $reuse_profile_signature = false)
{
    global $Database;
    $loaded = registration_form_fetch_invitation($token, false);
    if (!$loaded["ok"])
        return ($loaded);
    $invitation = $loaded["invitation"];
    if (!empty($invitation["event_public"]))
    {
        if (!$finalize)
            return (["ok" => false, "error" => "CommunicationEventDraftUnavailable"]);
        return (communication_event_finalize($invitation["event"], $submitted));
    }
    $schema = $invitation["schema"];
    $allowed = $schema["fields"] ?? [];
    $is_document_form = registration_form_is_document_kind($invitation["kind"] ?? "");
    $is_document_signature = registration_form_is_document_signature_kind($invitation["kind"] ?? "");
    $owner_user = db_select_one("* FROM user WHERE id = ".(int)$invitation["id_user"]." AND authority != -1");
    if ($owner_user == NULL)
        return (["ok" => false, "error" => "UserNotFound"]);
    foreach ($submitted as $field => $value)
        if (!isset($allowed[$field]) || ($is_document_form && empty($allowed[$field]["editable"])))
            return (["ok" => false, "error" => "DabsicFormChanged", "details" => $field]);
    // DOC-* invitations are contribution-only.  A paraphe, when the document
    // model uses one, is collected later by the dedicated SIG-* invitation.
    $requires_paraph = registration_form_requires_paraph($invitation["kind"] ?? "", $schema);
    if ($requires_paraph)
    {
        $paraph = registration_form_handle_paraph($invitation, $owner_user);
        if (!$paraph["ok"])
            return ($paraph);
    }

    $answers = $invitation["answers_data"];
    foreach ($submitted as $field => $value)
    {
        $definition = $allowed[$field] ?? [];
        if (form_field_is_common_type($definition["type"] ?? ""))
            $answers[$field] = form_field_storage_value($definition, $value);
        else
            $answers[$field] = is_scalar($value) || $value === NULL ? (string)$value : "";
    }
    if ($is_document_signature)
        return (registration_form_save_document_signature_invitation($invitation, $finalize, $signature_consent, $reuse_profile_signature));
    if (registration_form_is_document_kind($invitation["kind"] ?? ""))
        return (registration_form_save_document_invitation($invitation, $answers, $finalize));
    if (registration_form_is_profile_kind($invitation["kind"] ?? ""))
    {
        $student = db_select_one("* FROM user WHERE id = ".(int)$invitation["id_user"]." AND authority != -1");
        if ($student == NULL)
            return (["ok" => false, "error" => "UserNotFound"]);
    }
    else
    {
        $student_result = registration_form_student($invitation["id_user"]);
        if (!$student_result["ok"])
            return ($student_result);
        $student = $student_result["student"];
    }

    if ($finalize && registration_form_is_profile_kind($invitation["kind"] ?? "")
        && registration_form_required_profile_signature($schema) && !$signature_consent)
        return (["ok" => false, "error" => "RegistrationFormSignatureConsentRequired"]);

    $signature_result = registration_form_handle_signatures($student, $schema["signature_groups"] ?? [], $answers, $delete_signatures);
    if (!$signature_result["ok"])
        return ($signature_result);
    if ($finalize && registration_form_is_profile_kind($invitation["kind"] ?? "")
        && registration_form_required_profile_signature($schema)
        && (!isset($answers["Signatories.Student.Signature"])
            || !is_file(registration_form_signature_path($student, "Signatories.Student", $answers))))
        return (["ok" => false, "error" => "RegistrationFormSignatureRequired"]);
    $touched_users = [];
    foreach (registration_form_partition_paths($student, $signature_result["deleted_paths"] ?? []) as $id_user => $paths)
    {
        $removed = user_identity_delete_registration_paths($id_user, $paths);
        if ($removed->is_error())
            return (["ok" => false, "error" => $removed->label ?? "CannotEdit", "details" => strval($removed)]);
        $touched_users[(int)$id_user] = true;
    }
    foreach (registration_form_partition_answers($student, $answers) as $id_user => $user_answers)
    {
        $updated = user_identity_update_registration_answers($id_user, $user_answers);
        if ($updated->is_error())
            return (["ok" => false, "error" => $updated->label ?? "CannotEdit", "details" => strval($updated)]);
        $touched_users[(int)$id_user] = true;
    }
    foreach (($schema["signature_groups"] ?? []) as $group)
    {
        $signature_user = registration_form_signature_user($student, $group, $answers);
        if (isset($signature_user["id"]))
            $touched_users[(int)$signature_user["id"]] = true;
    }
    foreach (array_keys($touched_users) as $id_user)
    {
        $identity = user_identity_write_identity_dabsic($id_user);
        if ($identity->is_error())
            return (["ok" => false, "error" => $identity->label ?? "CannotWriteFile", "details" => strval($identity)]);
    }

    $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $answers_sql = $Database->real_escape_string($answers_json ?: "{}");
    $id = (int)$invitation["id"];
    if (!$Database->query("UPDATE user_form SET answers = '$answers_sql', last_saved_at = NOW() WHERE id = $id"))
        return (["ok" => false, "error" => "CannotEdit"]);

    if (registration_form_is_profile_kind($invitation["kind"] ?? ""))
    {
        $evidence_hash = "";
        if ($finalize && !is_file(registration_form_paraph_path($invitation, $owner_user)))
            return (["ok" => false, "error" => "RegistrationFormParaphRequired"]);
        if ($finalize && registration_form_required_profile_signature($schema))
        {
            $evidence = registration_form_record_profile_signature_evidence($invitation, $student, $schema, $answers);
            if (!$evidence["ok"])
                return ($evidence);
            $evidence_hash = $evidence["evidence_sha256"] ?? "";
        }
        if ($finalize)
        {
            $paraph_evidence = registration_form_record_paraph_evidence($invitation, $owner_user, $answers);
            if (!$paraph_evidence["ok"])
                return ($paraph_evidence);
            if (!$Database->query("UPDATE user_form SET completed_at = NOW() WHERE id = $id"))
                return (["ok" => false, "error" => "CannotEdit"]);
        }
        return ([
            "ok" => true,
            "completed" => $finalize,
            "refresh" => false,
            "answers" => $answers,
            "evidence_sha256" => $evidence_hash
        ]);
    }

    // A Dabsic condition can reveal new variables only after an earlier answer
    // has been supplied. Re-run mergeconf after every save and extend the
    // invitation schema instead of freezing the first warning list forever.
    $student_result = registration_form_student($invitation["id_user"]);
    if (!$student_result["ok"])
        return ($student_result);
    $analysis = registration_form_analyse(
        $student_result["student"],
        $invitation["kind"],
        $answers,
        $schema["signature_groups"] ?? []
    );
    if (!$analysis["ok"])
        return ($analysis);
    if (count($analysis["blocked"]))
        return ([
            "ok" => false,
            "error" => "RegistrationFormInternalFieldsMissing",
            "details" => implode("\n", $analysis["blocked"])
        ]);

    $schema_result = registration_form_refresh_schema($id, $schema, $analysis["editable"]);
    if (!$schema_result["ok"])
        return ($schema_result);

    if ($finalize && count($analysis["editable"]))
        return ([
            "ok" => true,
            "completed" => false,
            "refresh" => true,
            "pending" => true,
            "details" => implode("\n", $analysis["editable"]),
            "answers" => $answers
        ]);

    if ($finalize)
    {
        if (!is_file(registration_form_paraph_path($invitation, $owner_user)))
            return (["ok" => false, "error" => "RegistrationFormParaphRequired"]);
        $paraph_evidence = registration_form_record_paraph_evidence($invitation, $owner_user, $answers);
        if (!$paraph_evidence["ok"])
            return ($paraph_evidence);
        if (!$Database->query("UPDATE user_form SET completed_at = NOW() WHERE id = $id"))
            return (["ok" => false, "error" => "CannotEdit"]);
    }
    return ([
        "ok" => true,
        "completed" => $finalize,
        "refresh" => $schema_result["changed"],
        "answers" => $answers
    ]);
}

function registration_form_create_document_signature_invitation($owner_user_id, $instance_id, $role, $signatory_user_id, $id_creator = 1)
{
    global $Database;

    require_once (__DIR__."/document_workflow.php");
    if (!document_signature_slot_is_valid($role))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "role"]);
    $loaded = document_workflow_find_instance((int)$owner_user_id, $instance_id);
    if ($loaded->is_error())
        return (["ok" => false, "error" => $loaded->label ?? "InvalidFile", "details" => strval($loaded)]);
    $instance = $loaded->value["data"];
    if (($instance["Status"] ?? "") != "AwaitingSignature")
        return (["ok" => false, "error" => "InvalidParameter", "details" => "instance status"]);
    if (!isset($instance["Signatures"][$role]) || !is_array($instance["Signatures"][$role]))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "signature role"]);
    if (($instance["Signatures"][$role]["Status"] ?? "Pending") == "Signed")
        return (["ok" => false, "error" => "RegistrationFormCompleted"]);
    $signature_definition = $instance["Signatures"][$role];
    $semantic_role = document_workflow_signature_semantic_role($role, $signature_definition);
    $role_label = document_workflow_signature_role_label($role, $signature_definition);

    $user = db_select_one("* FROM user WHERE id = ".(int)$signatory_user_id." AND authority != -1");
    if ($user == NULL)
        return (["ok" => false, "error" => "UserNotFound"]);
    if (trim((string)($user["mail"] ?? "")) == "")
        return (["ok" => false, "error" => "MissingField", "details" => "mail"]);

    $schema = [
        "requested_fields" => [],
        "fields" => [],
        "groups" => ["DocumentSignature"],
        "signature_groups" => ["DocumentSignature"],
        "document_signature" => [
            "owner_user_id" => (int)$owner_user_id,
            "instance_id" => (string)$instance_id,
            "role" => (string)$role,
            "semantic_role" => $semantic_role,
            "role_label" => $role_label,
            "frozen_hash" => (string)($instance["FrozenHash"] ?? ""),
            "model" => (string)($instance["Model"] ?? ""),
            // Standard DocBuilder workflows keep the exact frozen Dabsic and
            // can therefore render handwritten paraphes/signatures into the
            // final PDF. Other legacy/specialized workflows keep their
            // historical signature-only page.
            "require_initials" => !empty($instance["FrozenDabsicFile"]) ? 1 : 0,
        ],
    ];
    $schema_json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($schema_json === false)
        return (["ok" => false, "error" => "CannotEdit"]);

    $signature_base_url = registration_form_document_signature_base_url($instance, $owner_user_id);
    if ($signature_base_url == "")
        return ([
            "ok" => false,
            "error" => "InvalidSchoolBaseUrl",
            "details" => "A complete school base_url is required for background signature invitations.",
        ]);

    $token = public_invitation_generate_token();
    $hash = $Database->real_escape_string(registration_form_token_hash($token));
    $schema_sql = $Database->real_escape_string($schema_json);
    $kind = "SIG-".substr(hash("sha256", $owner_user_id."|".$instance_id."|".$role), 0, 28);
    $kind_sql = $Database->real_escape_string($kind);
    $recipient_mail = $Database->real_escape_string((string)$user["mail"]);
    $recipient_name = $Database->real_escape_string(trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? "")));
    $Database->query("UPDATE user_form SET revoked_at = NOW()
        WHERE id_user = ".(int)$signatory_user_id." AND kind = '$kind_sql' AND completed_at IS NULL AND revoked_at IS NULL");
    if (!$Database->query("INSERT INTO user_form
        (id_user, id_creator, recipient_mail, recipient_name, kind, token_hash, fields, answers, expires_at)
        VALUES (".(int)$signatory_user_id.", ".(int)$id_creator.", '$recipient_mail', '$recipient_name', '$kind_sql', '$hash', '$schema_sql', '{}', DATE_ADD(NOW(), INTERVAL 14 DAY))"))
        return (["ok" => false, "error" => "CannotEdit"]);

    // Compatibility for instances frozen before document_task existed: the
    // invitation itself is enough to materialize the missing signature task.
    $task_definition = [
        "action" => "sign",
        "role" => $semantic_role,
        "id_assignee_user" => (int)$signatory_user_id,
    ];
    $task_key = document_task_plan_key("instance", $instance_id, $role, $task_definition);
    $task = document_task_create(
        $task_key,
        (int)$owner_user_id,
        0,
        $instance_id,
        "sign",
        $semantic_role,
        $role_label,
        (int)$signatory_user_id,
        !empty($signature_definition["Required"]),
        [
            "source" => (string)($signature_definition["Source"] ?? ""),
            "slot" => (string)$role,
            "assignee_label" => trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? "")),
        ]
    );
    if ($task->is_error())
        add_log(REPORT, "Cannot materialize signature task $semantic_role/$role for instance $instance_id: ".strval($task), (int)$owner_user_id);

    return ([
        "ok" => true,
        "id" => (int)$Database->insert_id,
        "token" => $token,
        "url" => registration_form_public_url(
            $token,
            $signature_base_url
        ),
        "user" => $user,
        "schema" => $schema,
    ]);
}

function registration_form_document_signature_pdf($token)
{
    require_once (__DIR__."/document_workflow.php");
    $loaded = registration_form_fetch_invitation($token, true);
    if (!$loaded["ok"])
        return ($loaded);
    $invitation = $loaded["invitation"];
    if (!registration_form_is_document_signature_kind($invitation["kind"] ?? ""))
        return (["ok" => false, "error" => "RegistrationFormInvalidToken"]);
    $meta = $invitation["schema"]["document_signature"] ?? [];
    $instance = document_workflow_find_instance((int)($meta["owner_user_id"] ?? 0), $meta["instance_id"] ?? "");
    if ($instance->is_error())
        return (["ok" => false, "error" => $instance->label ?? "InvalidFile"]);
    $data = $instance->value["data"];
    $pdf = $instance->value["directory"].($data["FrozenFile"] ?? "frozen.pdf");
    if (!is_file($pdf) || hash_file("sha256", $pdf) !== (string)($meta["frozen_hash"] ?? ""))
        return (["ok" => false, "error" => "InvalidFile", "details" => "Frozen document hash mismatch"]);
    return (["ok" => true, "file" => $pdf]);
}

/**
 * A handwritten Student signature on an enrolment/training contract becomes
 * the student's canonical profile signature once the signing action itself has
 * been durably recorded.  Keep this deliberately narrow: staff, financial
 * contacts and signatures made on unrelated documents must never overwrite a
 * student's profile handwriting.
 */
function registration_form_document_signature_updates_student_profile(array $instance, $owner_user_id, array $user, $semantic_role, $signature_input)
{
    if ((string)$signature_input !== "Drawn"
        || strcasecmp(trim((string)$semantic_role), "Student") !== 0
        || (int)($user["id"] ?? 0) <= 0
        || (int)($user["id"] ?? 0) !== (int)$owner_user_id)
        return (false);

    $model = trim((string)($instance["Model"] ?? ""));
    if ($model == "")
        return (false);
    $basename = strtolower(pathinfo(str_replace("\\", "/", $model), PATHINFO_FILENAME));
    return (in_array($basename, [
        "ecl", "contrat_ecole", "contract_school",
        "of", "contrat_of", "contrat_of_hors_alternance",
        "ofa", "contrat_of_alternance",
        "cfa", "contrat_cfa",
    ], true));
}

function registration_form_promote_document_signature_to_profile(array $user, $signature_file)
{
    $target = user_identity_signature_file($user);
    if ($target == "" || !is_file($signature_file))
        return (["ok" => false, "error" => "CannotWriteFile", "details" => "profile signature"]);

    // The immutable per-document signature remains in the workflow directory.
    // Normalize/copy it separately to admin/signature.png for later documents
    // such as session sign-in sheets.
    $saved = user_identity_store_signature_png($signature_file, $target, false);
    if (!$saved["ok"])
        return ($saved);

    // identity.dab caches the canonical signature path for document contexts.
    // Refresh it immediately so subsequent document generation sees the newly
    // acquired profile signature without waiting for another profile update.
    $identity = user_identity_write_identity_dabsic((int)$user["id"]);
    return ([
        "ok" => true,
        "file" => $target,
        "hash" => (string)($saved["hash"] ?? ""),
        "identity_updated" => !$identity->is_error(),
        "identity_error" => $identity->is_error() ? strval($identity) : "",
    ]);
}

function registration_form_promote_document_initials_to_profile(array $user, $initials_file)
{
    $target = user_identity_initials_file($user);
    if ($target == "" || !is_file($initials_file))
        return (["ok" => false, "error" => "CannotWriteFile", "details" => "profile initials"]);
    $saved = user_identity_store_signature_png($initials_file, $target, false);
    if (!$saved["ok"])
        return ($saved);
    $identity = user_identity_write_identity_dabsic((int)$user["id"]);
    return ([
        "ok" => true,
        "file" => $target,
        "hash" => (string)($saved["hash"] ?? ""),
        "identity_updated" => !$identity->is_error(),
        "identity_error" => $identity->is_error() ? strval($identity) : "",
    ]);
}

function registration_form_save_document_signature_invitation(array $invitation, $finalize, $signature_consent, $reuse_profile_signature = false)
{
    global $Database;

    require_once (__DIR__."/document_workflow.php");
    if (!$finalize)
        return (["ok" => true, "completed" => false, "refresh" => false]);
    if (!$signature_consent)
        return (["ok" => false, "error" => "RegistrationFormSignatureConsentRequired"]);

    $meta = $invitation["schema"]["document_signature"] ?? [];
    $owner_user_id = (int)($meta["owner_user_id"] ?? 0);
    $instance_id = (string)($meta["instance_id"] ?? "");
    $role = (string)($meta["role"] ?? "");
    if (!document_signature_slot_is_valid($role))
        return (["ok" => false, "error" => "InvalidParameter", "details" => "role"]);
    $loaded = document_workflow_find_instance($owner_user_id, $instance_id);
    if ($loaded->is_error())
        return (["ok" => false, "error" => $loaded->label ?? "InvalidFile", "details" => strval($loaded)]);
    $instance = $loaded->value["data"];
    $directory = $loaded->value["directory"];
    if (($instance["Status"] ?? "") != "AwaitingSignature")
        return (["ok" => false, "error" => "RegistrationFormCompleted"]);
    if (!isset($instance["Signatures"][$role]) || !is_array($instance["Signatures"][$role]))
        return (["ok" => false, "error" => "DabsicFormChanged"]);
    $semantic_role = document_workflow_signature_semantic_role($role, $instance["Signatures"][$role]);
    $role_label = document_workflow_signature_role_label($role, $instance["Signatures"][$role]);
    $pdf = $directory.($instance["FrozenFile"] ?? "frozen.pdf");
    $frozen_hash = is_file($pdf) ? hash_file("sha256", $pdf) : false;
    if ($frozen_hash === false || !hash_equals((string)($instance["FrozenHash"] ?? ""), $frozen_hash)
        || !hash_equals((string)($meta["frozen_hash"] ?? ""), $frozen_hash))
        return (["ok" => false, "error" => "InvalidFile", "details" => "Frozen document hash mismatch"]);

    $user = db_select_one("* FROM user WHERE id = ".(int)$invitation["id_user"]." AND authority != -1");
    if ($user == NULL)
        return (["ok" => false, "error" => "UserNotFound"]);

    $key = "DocumentSignature";
    $signature_file = document_workflow_signature_file($instance, $directory, $role);
    if ($signature_file === NULL)
        return (["ok" => false, "error" => "InvalidParameter", "details" => "role"]);

    $signature_input = "Drawn";
    if ($reuse_profile_signature)
    {
        $profile_signature = function_exists("user_identity_document_signature_file")
            ? user_identity_document_signature_file($user) : "";
        if ($profile_signature == "" || !is_file($profile_signature))
            return (["ok" => false, "error" => "RegistrationFormSignatureRequired"]);
        $saved = user_identity_store_signature_png($profile_signature, $signature_file, false);
        $signature_input = "Profile";
    }
    else
    {
        if (!isset($_FILES["signature"]["tmp_name"][$key]) || $_FILES["signature"]["error"][$key] != UPLOAD_ERR_OK)
            return (["ok" => false, "error" => "RegistrationFormSignatureRequired"]);
        $saved = registration_form_store_png($_FILES["signature"]["tmp_name"][$key], $signature_file);
    }
    if (!$saved["ok"])
        return ($saved);
    $signature_hash = hash_file("sha256", $signature_file);
    if ($signature_hash === false)
        return (["ok" => false, "error" => "CannotWriteFile"]);

    $initials_hash = "";
    $initials_file = "";
    if (!empty($meta["require_initials"]))
    {
        // The paraphe is a distinct act from the signature and is displayed in
        // the footer of Contract documents. Store a frozen copy beside the
        // signature so later form cleanup cannot alter the signed document.
        $paraph_file = registration_form_paraph_path($invitation, $user);
        if ($paraph_file == "" || !is_file($paraph_file))
            return (["ok" => false, "error" => "RegistrationFormParaphRequired"]);
        $initials_file = document_workflow_initials_file($instance, $directory, $role);
        if ($initials_file === NULL)
            return (["ok" => false, "error" => "InvalidParameter", "details" => "role"]);
        // $paraph_file has already been validated and normalized from the HTTP
        // upload by registration_form_handle_paraph().  It is now an internal
        // server-side file, so is_uploaded_file() must not be required again.
        $initials_saved = user_identity_store_signature_png($paraph_file, $initials_file, false);
        if (!$initials_saved["ok"])
            return ($initials_saved);
        $initials_hash = hash_file("sha256", $initials_file);
        if ($initials_hash === false)
            return (["ok" => false, "error" => "CannotWriteFile"]);
    }

    $now = new DateTimeImmutable("now");
    $consent = registration_form_document_signature_consent_text();
    $evidence = [
        "version" => "document-signature-v1",
        "instance_id" => $instance_id,
        "owner_user_id" => $owner_user_id,
        "slot" => $role,
        "role" => $semantic_role,
        "role_label" => $role_label,
        "frozen_sha256" => $frozen_hash,
        "signatory_user_id" => (int)$user["id"],
        "signatory_name" => trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? "")),
        "recipient_mail" => (string)($invitation["recipient_mail"] ?? $user["mail"] ?? ""),
        "signed_at" => $now->format("Y-m-d\\TH:i:s.uP"),
        "client_ip" => function_exists("get_client_ip") ? get_client_ip() : ($_SERVER["REMOTE_ADDR"] ?? ""),
        "user_agent" => (string)($_SERVER["HTTP_USER_AGENT"] ?? ""),
        "consent" => $consent,
        "signature_input" => $signature_input,
        "signature_sha256" => $signature_hash,
    ];
    if ($initials_hash != "")
        $evidence["initials_sha256"] = $initials_hash;
    $evidence_json = json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $evidence_hash = hash("sha256", $evidence_json ?: "");
    $evidence["evidence_sha256"] = $evidence_hash;
    $evidence_file = document_workflow_evidence_file($directory, $role);
    $written = generate_dabsic($evidence, $evidence_file);
    if ($written->is_error())
        return (["ok" => false, "error" => $written->label ?? "CannotWriteFile", "details" => strval($written)]);

    $signature = &$instance["Signatures"][$role];
    $signature["Status"] = "Signed";
    $signature["SignatoryUserId"] = (int)$user["id"];
    $signature["SignedAt"] = $now->format("Y-m-d H:i:s.u");
    $signature["SignatureSha256"] = $signature_hash;
    if ($initials_hash != "")
        $signature["InitialsSha256"] = $initials_hash;
    $signature["SignatureInput"] = $signature_input;
    $signature["EvidenceSha256"] = $evidence_hash;
    if (document_workflow_all_required_signed($instance))
    {
        $instance["Status"] = "Signed";
        $instance["ReadyForSealAt"] = $now->format("Y-m-d H:i:s");
    }
    $write_instance = document_workflow_write_instance($loaded->value["file"], $instance);
    if ($write_instance->is_error())
        return (["ok" => false, "error" => $write_instance->label ?? "CannotWriteFile", "details" => strval($write_instance)]);

    $task = document_task_complete_instance_slot($instance_id, $role, (int)$user["id"], (int)$user["id"]);
    if (!$task->is_error() && !empty($task->value["missing"]))
        $task = document_task_complete_instance_role($instance_id, $semantic_role, (int)$user["id"], (int)$user["id"]);
    if ($task->is_error())
        add_log(REPORT, "Cannot complete document signature task $semantic_role/$role for instance $instance_id: ".strval($task), (int)$user["id"]);

    $id = (int)$invitation["id"];
    if (!$Database->query("UPDATE user_form SET completed_at = NOW(), last_saved_at = NOW() WHERE id = $id"))
        return (["ok" => false, "error" => "CannotEdit"]);
    add_log(EDITING_OPERATION, "Document instance $instance_id signed as $semantic_role ($role) by user ".(int)$user["id"], (int)$user["id"]);

    if (registration_form_document_signature_updates_student_profile(
        $instance, $owner_user_id, $user, $semantic_role, $signature_input
    ))
    {
        $profile_signature = registration_form_promote_document_signature_to_profile($user, $signature_file);
        if (empty($profile_signature["ok"]))
            add_log(REPORT,
                "Cannot promote contract signature to profile for user ".(int)$user["id"].": ".
                (string)($profile_signature["error"] ?? "CannotWriteFile").
                (!empty($profile_signature["details"]) ? " (".$profile_signature["details"].")" : ""),
                (int)$user["id"]
            );
        else
        {
            add_log(EDITING_OPERATION,
                "Profile signature updated from signed contract instance $instance_id",
                (int)$user["id"]
            );
            if (empty($profile_signature["identity_updated"]))
                add_log(REPORT,
                    "Profile signature saved but identity.dab refresh failed for user ".(int)$user["id"].
                    (!empty($profile_signature["identity_error"]) ? ": ".$profile_signature["identity_error"] : ""),
                    (int)$user["id"]
                );
        }
    }

    if ($initials_hash != "" && $initials_file != "")
    {
        $profile_initials = registration_form_promote_document_initials_to_profile($user, $initials_file);
        if (empty($profile_initials["ok"]))
            add_log(REPORT,
                "Cannot promote document initials to profile for user ".(int)$user["id"].": ".
                (string)($profile_initials["error"] ?? "CannotWriteFile"),
                (int)$user["id"]
            );
        else
            add_log(EDITING_OPERATION,
                "Profile initials updated from signed document instance $instance_id",
                (int)$user["id"]
            );
    }

    // The last required signature is the natural trigger for delivery.  Do
    // not make the beneficiary wait for the periodic Albedo pass when the
    // establishment does not use the optional PdfSign sealing layer.
    if (($instance["Status"] ?? "") === "Signed")
    {
        $completed = document_workflow_finalize_signed_without_pdfsign($loaded->value["file"]);
        if ($completed->is_error())
            add_log(REPORT, "Cannot complete signed document workflow $instance_id: ".strval($completed), $owner_user_id);

        $archive = document_workflow_archive_completed_instance($loaded->value["file"]);
        if ($archive->is_error())
            add_log(REPORT, "Cannot archive completed document workflow $instance_id: ".strval($archive), $owner_user_id);

        $delivered = document_workflow_deliver_completed_instance($loaded->value["file"], false);
        if ($delivered->is_error())
            add_log(REPORT, "Cannot deliver completed document workflow $instance_id: ".strval($delivered), $owner_user_id);

        $printed = document_workflow_queue_completed_instance_for_print($loaded->value["file"]);
        if ($printed->is_error())
            add_log(REPORT, "Cannot queue completed document workflow $instance_id for print: ".strval($printed), $owner_user_id);
    }

    return (["ok" => true, "completed" => true, "refresh" => false, "evidence_sha256" => $evidence_hash]);
}
