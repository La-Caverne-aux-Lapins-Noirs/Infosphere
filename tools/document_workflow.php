<?php

require_once (__DIR__."/document_signatures.php");
require_once (__DIR__."/generate_dabsic.php");

/**
 * Generic persisted state for a generated document.
 *
 * This layer deliberately does not send signature requests yet. It freezes the
 * exact PDF that will later be submitted to signatories and records the
 * expected roles. Albedo will own the following state transitions.
 */

function document_workflow_root()
{
    return (document_builder_documentation_file_root()."/instances");
}

function document_workflow_new_id()
{
    try
    {
        $random = bin2hex(random_bytes(8));
    }
    catch (Throwable $e)
    {
        $random = substr(sha1(uniqid("document", true).mt_rand()), 0, 16);
    }
    return (date("YmdHis")."_".$random);
}

function document_workflow_actor_id()
{
    global $OriginalUser;
    global $User;

    if (isset($OriginalUser["id"]))
        return ((int)$OriginalUser["id"]);
    if (isset($User["id"]))
        return ((int)$User["id"]);
    return (0);
}

function document_workflow_school_codename_for_user($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (NULL);
    $school = db_select_one("school.codename AS codename FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user AND school.deleted IS NULL
        ORDER BY user_school.id ASC LIMIT 1");
    if ($school == NULL || trim((string)($school["codename"] ?? "")) == "")
        return (NULL);
    return ((string)$school["codename"]);
}

function document_workflow_instance_directory($user_codename, $instance_id)
{
    global $Configuration;

    if (!preg_match('/^[A-Za-z0-9_-]+$/D', (string)$instance_id))
        return (NULL);
    return ($Configuration->UsersDir($user_codename).document_workflow_root()."/".$instance_id."/");
}

function document_workflow_signature_state(array $schema, array $bindings)
{
    $out = [];
    foreach ($schema as $role => $definition)
    {
        if (!document_signature_slot_is_valid($role))
            continue ;
        $out[$role] = [
            "required" => !empty($definition["required"]),
            "source" => isset($bindings[$role]) ? (string)$bindings[$role] : "",
            "status" => "Pending",
        ];
    }
    return ($out);
}

function document_workflow_create_frozen_instance($id_user, $model_reference, $model_file, $target_year, $signature_bindings, $pdf_content)
{
    global $Configuration;

    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (new ErrorResponse("InvalidParameter", "id_user"));
    if (!is_string($pdf_content) || substr($pdf_content, 0, 4) !== "%PDF")
        return (new ErrorResponse("InvalidParameter", "pdf"));

    $target_user = db_select_one("codename FROM user WHERE id = $id_user AND authority != -1");
    if ($target_user == NULL || !isset($target_user["codename"]))
        return (new ErrorResponse("UserNotFound"));

    $schema = document_signature_model_slots($model_file);
    $bindings = document_signature_normalize_bindings($signature_bindings, array_keys($schema));
    $missing = document_signature_missing_required_bindings($schema, $bindings);
    if (count($missing))
        return (new ErrorResponse("DocumentRequiredSignatories", implode(", ", $missing)));

    $instance_id = document_workflow_new_id();
    $directory = document_workflow_instance_directory($target_user["codename"], $instance_id);
    if ($directory === NULL)
        return (new ErrorResponse("CannotWriteFile", "document instance"));
    if (($mkdir = new_directory($directory."index.php"))->is_error())
        return ($mkdir);

    $frozen_pdf = $directory."frozen.pdf";
    if (file_put_contents($frozen_pdf, $pdf_content, LOCK_EX) !== strlen($pdf_content))
        return (new ErrorResponse("CannotWriteFile", $frozen_pdf));
    @chmod($frozen_pdf, 0640);

    $has_signatures = count($schema) > 0;
    $school_codename = document_workflow_school_codename_for_user($id_user);
    $metadata = [
        "id" => $instance_id,
        "owner_user_id" => $id_user,
        "school_codename" => $school_codename ?? "",
        "model" => (string)$model_reference,
        "target_year" => max(0, min(5, (int)$target_year)),
        "status" => $has_signatures ? "AwaitingSignature" : "Completed",
        "created_at" => date("Y-m-d H:i:s"),
        "created_by" => document_workflow_actor_id(),
        "frozen_hash" => hash("sha256", $pdf_content),
        "frozen_file" => "frozen.pdf",
        "signatures" => document_workflow_signature_state($schema, $bindings),
    ];

    $metadata_file = $directory."instance.dab";
    $generated = generate_dabsic($metadata, $metadata_file);
    if ($generated->is_error())
    {
        @unlink($frozen_pdf);
        return ($generated);
    }
    @chmod($metadata_file, 0640);

    return (new ValueResponse([
        "id" => $instance_id,
        "status" => $metadata["status"],
        "hash" => $metadata["frozen_hash"],
        "directory" => $directory,
        "pdf" => $frozen_pdf,
        "metadata" => $metadata_file,
    ]));
}

function document_workflow_load_instance($directory)
{
    $file = rtrim((string)$directory, "/")."/instance.dab";
    if (!is_file($file))
        return (new ErrorResponse("MissingFile", $file));
    $loaded = load_configuration($file, [], false);
    if ($loaded->is_error() || !is_array($loaded->value))
        return ($loaded->is_error() ? $loaded : new ErrorResponse("InvalidFile", $file));
    return (new ValueResponse(["file" => $file, "directory" => dirname($file)."/", "data" => $loaded->value]));
}

function document_workflow_write_instance($file, array $data)
{
    $ret = generate_dabsic($data, $file);
    if (!$ret->is_error())
        @chmod($file, 0640);
    return ($ret);
}

function document_workflow_find_instance($owner_user_id, $instance_id)
{
    $owner_user_id = (int)$owner_user_id;
    $user = db_select_one("codename FROM user WHERE id = $owner_user_id AND authority != -1");
    if ($user == NULL)
        return (new ErrorResponse("UserNotFound"));
    $directory = document_workflow_instance_directory($user["codename"], $instance_id);
    if ($directory === NULL)
        return (new ErrorResponse("InvalidParameter", "instance_id"));
    return (document_workflow_load_instance($directory));
}

function document_workflow_source_user_id($owner_user_id, $source, $created_by = 0)
{
    $owner_user_id = (int)$owner_user_id;
    $source = trim((string)$source);
    if ($source == "Student")
        return ($owner_user_id);
    if ($source == "Generator")
        return ((int)$created_by > 0 ? (int)$created_by : NULL);

    if ($source == "Director")
    {
        $school = db_select_one("id_school FROM user_school WHERE id_user = $owner_user_id ORDER BY id_school ASC");
        if ($school == NULL)
            return (NULL);
        $row = db_select_one("user.id AS id FROM user_school LEFT JOIN user ON user.id = user_school.id_user
            WHERE user_school.id_school = ".(int)$school["id_school"]."
              AND (user_school.authority = 'DIRECTOR' OR user_school.authority = 1)
              AND user.id IS NOT NULL AND user.authority != -1
            ORDER BY user.id ASC");
        return ($row == NULL ? NULL : (int)$row["id"]);
    }

    if (in_array($source, ["Legal1", "Legal2", "Finance"], true))
    {
        $relation = $source == "Finance" ? "financial" : "legal";
        $wanted = $source == "Legal2" ? 1 : 0;
        $rows = db_select_all("user.id AS id, parent_child.relation AS relation FROM parent_child
            LEFT JOIN user ON user.id = parent_child.id_parent
            WHERE parent_child.id_child = $owner_user_id AND user.id IS NOT NULL AND user.authority != -1
            ORDER BY parent_child.id ASC");
        $matches = [];
        foreach ($rows as $row)
            if (function_exists("user_relation_has") && user_relation_has($row["relation"] ?? "", $relation))
                $matches[] = $row;
        if (isset($matches[$wanted]))
            return ((int)$matches[$wanted]["id"]);
        // Le bénéficiaire est son propre responsable financier lorsqu'aucune
        // relation financière distincte n'est déclarée.
        if ($source == "Finance")
            return ($owner_user_id);
        return (NULL);
    }
    return (NULL);
}

function document_workflow_signature_file(array $instance, $directory, $role)
{
    if (!document_signature_slot_is_valid($role))
        return (NULL);
    return (rtrim((string)$directory, "/")."/signatures/".$role."/signature.png");
}

function document_workflow_evidence_file($directory, $role)
{
    if (!document_signature_slot_is_valid($role))
        return (NULL);
    return (rtrim((string)$directory, "/")."/signatures/".$role."/evidence.dab");
}

function document_workflow_all_required_signed(array $instance)
{
    foreach (($instance["Signatures"] ?? []) as $role => $signature)
        if (is_array($signature) && !empty($signature["Required"])
            && (($signature["Status"] ?? "") != "Signed"))
            return (false);
    return (true);
}

/**
 * Schools whose document workflows may be monitored by the current user.
 * NULL means unrestricted (global administrator); an empty array means none.
 *
 * Do not use get_user_school() here: that helper currently contains a legacy
 * fallback school and must never grant access to confidential documents.
 */
function document_workflow_monitor_school_codenames()
{
    global $User;

    if (!isset($User["id"]) || (int)$User["id"] <= 0)
        return ([]);
    if (is_admin())
        return (NULL);

    $rows = db_select_all("school.codename AS codename, user_school.authority AS authority
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = ".(int)$User["id"]."
          AND school.id IS NOT NULL AND school.deleted IS NULL");
    $out = [];
    $numeric = [1 => "DIRECTOR", 2 => "SECRETARIAT", 3 => "COMMERCIAL"];
    foreach ($rows as $row)
    {
        $authority = $row["authority"] ?? "";
        if (is_numeric($authority))
            $authority = $numeric[(int)$authority] ?? "";
        else
            $authority = strtoupper(trim((string)$authority));
        if (!in_array($authority, ["DIRECTOR", "SECRETARIAT", "COMMERCIAL"], true))
            continue ;
        $codename = trim((string)($row["codename"] ?? ""));
        if ($codename != "")
            $out[$codename] = true;
    }
    return (array_keys($out));
}

function document_workflow_can_monitor()
{
    $schools = document_workflow_monitor_school_codenames();
    return ($schools === NULL || count($schools) > 0);
}

function document_workflow_instance_school_codenames(array $instance)
{
    $school = trim((string)($instance["SchoolCodename"] ?? ""));
    if ($school != "")
        return ([$school]);

    // Compatibility for instances frozen before SchoolCodename was persisted.
    $owner = (int)($instance["OwnerUserId"] ?? 0);
    if ($owner <= 0)
        return ([]);
    $rows = db_select_all("school.codename AS codename FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $owner
          AND school.id IS NOT NULL AND school.deleted IS NULL");
    $out = [];
    foreach ($rows as $row)
    {
        $codename = trim((string)($row["codename"] ?? ""));
        if ($codename != "")
            $out[$codename] = true;
    }
    return (array_keys($out));
}

function document_workflow_can_view_instance(array $instance)
{
    $schools = document_workflow_monitor_school_codenames();
    if ($schools === NULL)
        return (true);
    if (!count($schools))
        return (false);
    return (count(array_intersect($schools, document_workflow_instance_school_codenames($instance))) > 0);
}

function document_workflow_signature_progress(array $instance)
{
    $total = 0;
    $signed = 0;
    foreach (($instance["Signatures"] ?? []) as $signature)
    {
        if (!is_array($signature) || empty($signature["Required"]))
            continue ;
        $total += 1;
        if (($signature["Status"] ?? "") === "Signed")
            $signed += 1;
    }
    return (["signed" => $signed, "total" => $total]);
}

/**
 * Return workflow instances visible to the current staff member.
 * Instances remain filesystem-backed; instance.dab is always read through
 * the normal Dabsic/mergeconf configuration loader.
 */
function document_workflow_visible_instances($limit = 1000)
{
    global $Configuration;

    if (!document_workflow_can_monitor())
        return ([]);
    $limit = max(1, min(5000, (int)$limit));
    $pattern = rtrim($Configuration->UsersDir(), "/")."/*/".
        trim(document_workflow_root(), "/")."/*/instance.dab";
    $files = glob($pattern);
    if (!is_array($files))
        return ([]);

    $instances = [];
    $owner_ids = [];
    foreach ($files as $file)
    {
        $loaded = document_workflow_load_instance(dirname($file));
        if ($loaded->is_error())
            continue ;
        $instance = $loaded->value["data"];
        if (!is_array($instance) || !document_workflow_can_view_instance($instance))
            continue ;
        $owner = (int)($instance["OwnerUserId"] ?? 0);
        if ($owner > 0)
            $owner_ids[$owner] = true;
        $progress = document_workflow_signature_progress($instance);
        $instances[] = [
            "instance" => $instance,
            "directory" => $loaded->value["directory"],
            "owner_user_id" => $owner,
            "signature_signed" => $progress["signed"],
            "signature_total" => $progress["total"],
        ];
    }

    $owners = [];
    if (count($owner_ids))
    {
        $ids = implode(",", array_map("intval", array_keys($owner_ids)));
        foreach (db_select_all("id, codename, first_name, family_name FROM user WHERE id IN ($ids)") as $row)
            $owners[(int)$row["id"]] = $row;
    }
    foreach ($instances as &$entry)
        $entry["owner"] = $owners[$entry["owner_user_id"]] ?? [];
    unset($entry);

    usort($instances, function($a, $b) {
        $ad = (string)($a["instance"]["CreatedAt"] ?? "");
        $bd = (string)($b["instance"]["CreatedAt"] ?? "");
        if ($ad === $bd)
            return (strcmp((string)($b["instance"]["Id"] ?? ""), (string)($a["instance"]["Id"] ?? "")));
        return (strcmp($bd, $ad));
    });
    if (count($instances) > $limit)
        $instances = array_slice($instances, 0, $limit);
    return ($instances);
}

function document_workflow_pdf_file_for_view(array $instance, $directory)
{
    $directory = rtrim((string)$directory, "/")."/";
    $candidates = [
        ["file" => $instance["FinalFile"] ?? "", "hash" => $instance["FinalHash"] ?? ""],
        ["file" => $instance["SealedFile"] ?? "", "hash" => $instance["SealedHash"] ?? ""],
        ["file" => $instance["FrozenFile"] ?? "frozen.pdf", "hash" => $instance["FrozenHash"] ?? ""],
    ];
    foreach ($candidates as $candidate)
    {
        $name = basename((string)$candidate["file"]);
        if ($name == "" || !preg_match('/^[A-Za-z0-9_.-]+$/D', $name))
            continue ;
        $file = $directory.$name;
        if (!is_file($file))
            continue ;
        $expected = strtolower(trim((string)$candidate["hash"]));
        if ($expected != "" && hash_file("sha256", $file) !== $expected)
            return (new ErrorResponse("DocumentHashMismatch"));
        return (new ValueResponse($file));
    }
    return (new ErrorResponse("MissingFile", "document workflow PDF"));
}
