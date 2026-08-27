<?php

require_once (__DIR__."/build_document.php");
require_once (__DIR__."/admission_certificate.php");
require_once (__DIR__."/school_mailbox.php");

function post_interview_report_output_key($id_prospect)
{
    return ("post-interview-report:".(int)$id_prospect);
}

function post_interview_report_model_reference()
{
    return ("res/docs/fr/compte_rendu_post_entretien.dab");
}

function post_interview_report_model_file()
{
    $root = dabsic_editor_project_root();
    if ($root === false)
        return (NULL);
    $file = realpath($root.DIRECTORY_SEPARATOR.post_interview_report_model_reference());
    return ($file !== false && is_file($file) ? $file : NULL);
}

function post_interview_report_school($id_prospect)
{
    $school_link = document_context_first_school_for_user((int)$id_prospect);
    $id_school = is_array($school_link) ? (int)($school_link["id_school"] ?? 0) : 0;
    if ($id_school <= 0)
        return (NULL);
    $school = fetch_school($id_school);
    return (is_array($school) && count($school) ? $school : NULL);
}

function post_interview_report_chain($id_prospect, $id_analyst)
{
    $id_prospect = (int)$id_prospect;
    $id_analyst = (int)$id_analyst;
    $chain = [];
    $school = post_interview_report_school($id_prospect);
    if (is_array($school))
        $chain[] = ["type" => "school", "prefix" => "School", "id" => (int)$school["id"]];
    $chain[] = ["type" => "user", "prefix" => "Student", "id" => $id_prospect];
    if ($id_analyst > 0)
        $chain[] = ["type" => "user", "prefix" => "Analyst", "id" => $id_analyst];

    $prospect = db_select_one("target_class, target_entry FROM user WHERE id = $id_prospect AND profile_status = 'prospect'");
    if ($prospect != NULL)
    {
        $classes = admission_certificate_target_classes();
        $class = $classes[(int)($prospect["target_class"] ?? 0)] ?? NULL;
        if (is_array($class))
        {
            $chain[] = ["type" => "field", "key" => "NeedsAnalysis.TargetTraining", "value" => $class["label"] ?? ""];
            $chain[] = ["type" => "field", "key" => "NeedsAnalysis.TargetLevel", "value" => admission_certificate_year_label((int)($class["year"] ?? 0))];
        }
        $entries = admission_certificate_entry_specs();
        $entry = $entries[(int)($prospect["target_entry"] ?? -1)] ?? NULL;
        if (is_array($entry))
            $chain[] = ["type" => "field", "key" => "NeedsAnalysis.TargetEntry", "value" => $entry["label"] ?? ""];
    }
    $chain[] = ["type" => "field", "key" => "NeedsAnalysis.AnalysisDate", "value" => date("d/m/Y")];
    return ($chain);
}

function post_interview_report_workspace($id_prospect, $id_actor = 0, $create = false)
{
    $output = dabsic_form_resolve_output(post_interview_report_output_key($id_prospect), $create);
    if (!$output["ok"])
        return ($output);
    $loaded = dabsic_form_load_workspace($output);
    if (!$loaded["ok"])
        return ($loaded);

    $workspace = $loaded["data"] ?? [];
    if (!$loaded["exists"] && $create)
    {
        $workspace = [
            "version" => 1,
            "kind" => "post_interview_report",
            "prospect_id" => (int)$id_prospect,
            "created_by" => (int)$id_actor,
            "status" => "Draft",
        ];
        $saved = dabsic_form_save_workspace($output, $workspace);
        if (!$saved["ok"])
            return ($saved);
        $workspace = $saved["data"];
    }
    return (["ok" => true, "output" => $output, "workspace" => $workspace, "exists" => $loaded["exists"]]);
}

function post_interview_report_access($id_prospect)
{
    return (dabsic_form_user_can_access_output(post_interview_report_output_key((int)$id_prospect)));
}

function post_interview_report_start($id_prospect, $id_actor)
{
    $id_prospect = (int)$id_prospect;
    $id_actor = (int)$id_actor;
    if ($id_prospect <= 0 || $id_actor <= 0)
        return (new ErrorResponse("InvalidParameter", "prospect / analyst"));
    if (!post_interview_report_access($id_prospect))
        return (new ErrorResponse("PermissionDenied"));

    $state = post_interview_report_workspace($id_prospect, $id_actor, true);
    if (!$state["ok"])
        return (new ErrorResponse($state["error"], $state["details"] ?? ""));
    $workspace = $state["workspace"];
    $id_analyst = (int)($workspace["created_by"] ?? 0);
    if ($id_analyst <= 0)
    {
        $id_analyst = $id_actor;
        $workspace["created_by"] = $id_actor;
        $saved = dabsic_form_save_workspace($state["output"], $workspace);
        if (!$saved["ok"])
            return (new ErrorResponse($saved["error"], $saved["details"] ?? ""));
    }

    $chain = post_interview_report_chain($id_prospect, $id_analyst);
    $url = "index.php?p=DabsicFormMenu".
        "&file=".rawurlencode(post_interview_report_model_reference()).
        "&output=".rawurlencode(post_interview_report_output_key($id_prospect)).
        "&mode=docbuilder&form_role=Etablissement".
        "&chain=".rawurlencode(json_encode($chain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return (new ValueResponse([
        "msg" => "Compte rendu ouvert.",
        "content" => $url,
        "created_by" => $id_analyst,
    ]));
}

function post_interview_report_signature_file($id_user)
{
    $user = db_select_one("codename FROM user WHERE id = ".(int)$id_user." AND authority != -1");
    if ($user == NULL)
        return ("");
    $file = user_identity_signature_file($user);
    return ($file != "" && is_file($file) ? $file : "");
}

function post_interview_report_required_values($model, $output, $chain_json)
{
    $discovery = dabsic_form_discover_fields(post_interview_report_model_reference(), "docbuilder", $chain_json);
    if (!$discovery["ok"])
        return ($discovery);
    $loaded = dabsic_form_load_output_values($output);
    if (!$loaded["ok"])
        return ($loaded);
    $values = array_merge(dabsic_form_prefill_from_chain($chain_json), $loaded["values"]);
    foreach (dabsic_form_default_values($model, "Etablissement") as $field => $value)
        if (!array_key_exists($field, $values) || trim((string)$values[$field]) === "")
            $values[$field] = $value;
    $missing = dabsic_form_missing_required_fields($discovery["form_metadata"], $values, "Etablissement");
    if (count($missing))
        return ([
            "ok" => false,
            "error" => "DabsicFormStillMissing",
            "details" => implode("\n", array_values($missing)),
        ]);
    return (["ok" => true, "values" => $values]);
}

function post_interview_report_add_interview_done_action($id_prospect, $id_analyst, $pdf_hash)
{
    global $Database;

    $action = db_select_one("id FROM action WHERE name = 'InterviewDone'");
    if ($action == NULL || (int)($action["id"] ?? 0) <= 0)
        return (new ErrorResponse("NotFound", "InterviewDone"));
    $id_action = (int)$action["id"];
    $id_prospect = (int)$id_prospect;
    $id_analyst = (int)$id_analyst;

    // One semantic interview-done action is enough. A corrected PDF remains
    // traceable through the report workspace hashes without multiplying the
    // prospecting timeline entries.
    $existing = db_select_one("prospection.id FROM prospection
        LEFT JOIN action ON action.id = prospection.id_action
        WHERE prospection.id_user = $id_prospect
          AND action.name = 'InterviewDone'
          AND prospection.comment LIKE 'Compte rendu post-entretien finalisé%'");
    if ($existing != NULL)
        return (new Response);

    $comment = "Compte rendu post-entretien finalisé — PDF SHA-256 ".$pdf_hash;
    $comment = $Database->real_escape_string($comment);
    if (!$Database->query("INSERT INTO prospection (id_user, id_prospector, id_action, comment)
        VALUES ($id_prospect, $id_analyst, $id_action, '$comment')"))
        return (new ErrorResponse("CannotEdit"));
    return (new Response);
}

function post_interview_report_finalize($id_prospect, $id_actor)
{
    $id_prospect = (int)$id_prospect;
    $id_actor = (int)$id_actor;
    if ($id_prospect <= 0 || $id_actor <= 0)
        return (new ErrorResponse("InvalidParameter", "prospect / analyst"));
    if (!post_interview_report_access($id_prospect))
        return (new ErrorResponse("PermissionDenied"));

    $state = post_interview_report_workspace($id_prospect, $id_actor, true);
    if (!$state["ok"])
        return (new ErrorResponse($state["error"], $state["details"] ?? ""));
    $output = $state["output"];
    $workspace = $state["workspace"];
    if (!is_file($output["absolute"]))
        return (new ErrorResponse("MissingFile", "compte rendu sauvegardé"));

    $id_analyst = (int)($workspace["created_by"] ?? 0);
    if ($id_analyst <= 0)
        $id_analyst = $id_actor;
    $signature = post_interview_report_signature_file($id_analyst);
    if ($signature == "")
        return (new ErrorResponse("MissingFile", "signature de la personne ayant démarré le compte rendu"));

    $school = post_interview_report_school($id_prospect);
    if (!is_array($school))
        return (new ErrorResponse("NotFound", "école du prospect"));
    $stamp = school_stamp_path($school, true);
    if ($stamp == "" || !is_file($stamp))
        return (new ErrorResponse("MissingFile", "tampon de l'école (à ajouter dans la configuration de l'établissement)"));

    $prospect = db_select_one("id, codename, first_name, family_name, mail FROM user
        WHERE id = $id_prospect AND profile_status = 'prospect' AND authority != -1");
    if ($prospect == NULL)
        return (new ErrorResponse("UserNotFound"));
    $mail = trim((string)($prospect["mail"] ?? ""));
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
        return (new ErrorResponse("BadMail", $mail));

    $model = post_interview_report_model_file();
    if ($model == NULL)
        return (new ErrorResponse("MissingFile", post_interview_report_model_reference()));

    $chain = post_interview_report_chain($id_prospect, $id_analyst);
    $chain_json = json_encode($chain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $required = post_interview_report_required_values($model, $output, $chain_json);
    if (!$required["ok"])
        return (new ErrorResponse($required["error"], $required["details"] ?? ""));

    $overrides = dabsic_form_load_overrides($output);
    if (!$overrides["ok"])
        return (new ErrorResponse($overrides["error"], $overrides["details"] ?? ""));
    $validation = dabsic_form_validate_resolution(
        $model,
        $output["absolute"],
        $overrides["values"] ?? [],
        "docbuilder",
        $chain_json
    );
    if (!$validation["ok"])
        return (new ErrorResponse($validation["error"], $validation["details"] ?? ""));

    $source_hash = hash("sha256", (string)@file_get_contents($output["absolute"]));
    $pdf = dirname($output["absolute"]).DIRECTORY_SEPARATOR."compte_rendu_post_entretien.pdf";
    if (($workspace["status"] ?? "") === "Finalized" &&
        ($workspace["source_output_hash"] ?? "") === $source_hash && is_file($pdf) &&
        preg_match('/^[a-f0-9]{64}$/D', (string)($workspace["pdf_hash"] ?? "")) &&
        hash_equals((string)$workspace["pdf_hash"], hash_file("sha256", $pdf)))
    {
        $content = @file_get_contents($pdf);
        if ($content !== false && substr($content, 0, 4) === "%PDF")
        {
            $action = post_interview_report_add_interview_done_action(
                $id_prospect, $id_analyst, (string)$workspace["pdf_hash"]
            );
            if ($action->is_error())
                return ($action);
            return (new ValueResponse([
                "filename" => basename($pdf),
                "content_type" => "application/pdf",
                "disposition" => "inline",
                "content" => $content,
            ]));
        }
    }

    $fields = [];
    $files = [];
    $temporary = [];
    document_context_apply_chain($fields, $chain, $files, $temporary);
    $parts = [["file" => $model]];
    foreach ($files as $file)
        $parts[] = ["file" => $file];
    foreach ($fields as $field)
        $parts[] = $field;
    $parts[] = ["file" => $output["absolute"]];

    $built = build_document_from_parts($pdf, $parts);
    foreach ($temporary as $file)
        @unlink($file);
    if ($built->is_error())
        return ($built);
    @chmod($pdf, 0640);
    $pdf_content = @file_get_contents($pdf);
    if ($pdf_content === false || substr($pdf_content, 0, 4) !== "%PDF")
        return (new ErrorResponse("CannotReadFile", $pdf));

    $dabsic_hash = (string)($built->value["dabsic_hash"] ?? "");
    $pdf_hash = hash("sha256", $pdf_content);
    $prospect_name = trim((string)($prospect["first_name"] ?? "")." ".(string)($prospect["family_name"] ?? ""));
    $school_name = trim((string)($school["name"] ?? ($school["fr_name"] ?? "EFRITS")));
    $subject = "Compte rendu de votre entretien avec ".$school_name;
    $body = "Bonjour".($prospect_name != "" ? " ".$prospect_name : "").",\n\n".
        "Vous trouverez en pièce jointe le compte rendu de votre entretien avec ".$school_name.".\n\n".
        "Empreinte SHA-256 du Dabsic résolu : ".$dabsic_hash."\n\n".
        "N'hésitez pas à revenir vers nous si vous souhaitez faire corriger ou préciser un élément du compte rendu.\n\n".
        "Bonne journée,";
    $sender = school_mailbox_resolve((int)$school["id"], "admission", true);
    try
    {
        $sent = send_mail(
            $mail,
            $subject,
            $body,
            NULL,
            [basename($pdf) => $pdf_content],
            true,
            $sender != "" ? $sender : NULL
        );
    }
    catch (Throwable $exception)
    {
        return (new ErrorResponse("CannotSendMail", $exception->getMessage()));
    }
    if (is_object($sent) && $sent->is_error())
        return ($sent);

    $workspace["created_by"] = $id_analyst;
    $workspace["status"] = "Finalized";
    $workspace["finalized_at"] = date("Y-m-d H:i:s");
    $workspace["finalized_by"] = $id_actor;
    $workspace["sent_at"] = $workspace["finalized_at"];
    $workspace["sent_to"] = $mail;
    $workspace["source_output_hash"] = $source_hash;
    $workspace["resolved_dabsic_hash"] = $dabsic_hash;
    $workspace["pdf_hash"] = $pdf_hash;
    $workspace["pdf_file"] = basename($pdf);
    $saved = dabsic_form_save_workspace($output, $workspace);
    if (!$saved["ok"])
        return (new ErrorResponse($saved["error"], $saved["details"] ?? ""));

    $action = post_interview_report_add_interview_done_action($id_prospect, $id_analyst, $pdf_hash);
    if ($action->is_error())
        return ($action);

    add_log(EDITING_OPERATION,
        "Post-interview report finalized for prospect $id_prospect by analyst $id_analyst, Dabsic SHA-256 $dabsic_hash");
    return (new ValueResponse([
        "filename" => basename($pdf),
        "content_type" => "application/pdf",
        "disposition" => "inline",
        "content" => $pdf_content,
    ]));
}
