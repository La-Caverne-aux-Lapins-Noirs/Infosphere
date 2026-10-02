<?php

function DisplayUser($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    // Ce moyen ne permet pas de récupérer beaucoup d'informations.
    // Seulement celle par défaut de fetch_user
    // Car les préférences utilisateurs n'impactent pas cette page.
    $users = fetch_users([], $id);
    if ($output == "json")
	return (new ValueResponse(["content" => json_encode($users, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    if (count($users) == 0)
	echo $Dictionnary["NoUser"];
    else
	foreach ($users as $user)
	    require ("./pages/$module/display_user.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

// Formulaire le plus souple pour l'ajout d'users: login custom, possibilité d'omettre des champs, etc.
function SubscribeUser($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1 || !isset($data["users"]))
	bad_request();
    $cnt = 0;
    $subs = [];
    foreach ($data["users"] as $usr)
    {
	$profile_status = user_profile_status($usr["profile_status"] ?? ((isset($usr["prospect"]) && !!$usr["prospect"]) ? "prospect" : "member"));
	$fake = user_profile_status_is_fake($profile_status);
	
	if (($request = @subscribe($usr["login"], @$usr["mail"], NULL, false, $fake, $profile_status))->is_error())
	{
	    ob_end_clean();
	    return ($request);
	}
	$id_user = $request->value["id"];
	$request = @set_user_data($usr["login"], [
	    "first_name" => trim((string)@$usr["first_name"]),
	    "family_name" => trim((string)@$usr["family_name"]),
	    "birth_date" => db_form_date(@$usr["birth_date"]),
	    "phone" => @$usr["phone"],
	    "objectives" => $Dictionnary["DefaultUserObjectives"],
	]);
	if ($request->is_error())
	{
	    ob_end_clean();
	    return ($request);
	}
	if (($request = add_default_user_todolist($id_user))->is_error())
	{
	    ob_end_clean();
	    return ($request);
	}
	$subs[] = $usr["login"];
	$cnt += 1;
    }
    $ret = DisplayUser(implode(";", $subs), [], "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["UserAdded"].": $cnt";
    return ($ret);
}

function SetStatus($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if ($id == 1)
	forbidden();
    if (($request = set_user_data($id, ["authority" => $data["authority"]]))->is_error())
	return ($request);
    return ($request = new ValueResponse([
	"msg" => $Dictionnary["UserModified"]
    ]));
}

function RegeneratePassword($id, $data, $method, $output, $module)
{
    global $Dictionnary;
	    
    if ($id == -1)
	bad_request();
    if (($request = set_user_attributes($id, ["password" => generate_password()]))->is_error())
	return ($request);
    return (new ValueResponse([
	"msg" => $Dictionnary["PasswordEdited"]
    ]));
}

function DownloadNfcCard($id, $data, $method, $output, $module)
{
    $user = nfc_card_user($id);
    if ($user == NULL)
        return (new ErrorResponse("UserNotFound"));
    $ret = nfc_card_ensure_for_user($id);
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"]));
    return (new ValueResponse([
        "filename" => nfc_card_download_name($user),
        "content_type" => "application/octet-stream",
        "content" => $ret["content"],
    ]));
}

function RegenerateNfcCard($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $ret = nfc_card_regenerate_for_user($id);
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"]));
    add_log(CRITICAL_USER_DATA, "NFC access token regenerated", (int)$id);
    return (new ValueResponse([
        "msg" => $Dictionnary["NfcCardRegenerated"],
    ]));
}

function CheckNfcCardOwner($id, $data, $method, $output, $module)
{
    $id = (int)$id;
    if ($id <= 0)
        bad_request();

    $owner = nfc_card_owner_lookup($data["token"] ?? "", $id);
    if ($owner === NULL)
        bad_request();
    return (new ValueResponse($owner));
}

function GetNfcAutoContext($id, $data, $method, $output, $module)
{
    $school_id = nfc_card_notification_school_id();

    if ($school_id <= 0)
        return (new ValueResponse([
            "enabled" => false,
            "school_id" => 0,
            "identify_url" => "",
        ]));
    return (new ValueResponse([
        "enabled" => true,
        "school_id" => $school_id,
        "identify_url" => "/api/school/".$school_id."/nfc_card_owner",
    ]));
}

function GenerateUserDiploma($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (!isset($data["id_title"], $data["id_school"]))
        return (new ErrorResponse("InvalidParameter"));
    $promotion_year = isset($data["promotion_year"]) ? (int)$data["promotion_year"] : 0;
    if ($promotion_year != 0 && ($promotion_year < 1900 || $promotion_year > 2200))
        return (new ErrorResponse("InvalidParameter", "promotion_year"));
    $ret = diploma_generate_for_user(
        (int)$id,
        (int)$data["id_title"],
        (int)$data["id_school"],
        [
            "promotion_year" => $promotion_year,
            // The nickname is opt-in. Callers that omit the checkbox must not
            // add it to the diploma implicitly.
            "include_nickname" => array_key_exists("include_nickname", $data)
                ? !empty($data["include_nickname"]) : false,
        ]
    , $data["issue_date"] ?? NULL);
    if ($ret->is_error())
        return ($ret);
    add_log(CREATIVE_OPERATION, "User diploma generated: ".$ret->value["path"], (int)$id);
    return (new ValueResponse([
        "msg" => $Dictionnary["DiplomaGenerated"] ?? "Diplôme généré",
        "stored_file" => $ret->value["path"],
    ]));
}

function GenerateUserIdCard($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (!isset($data["id_school"]))
        return (new ErrorResponse("InvalidParameter", "id_school"));
    $study_year = array_key_exists("study_year", $data) ? $data["study_year"] : NULL;
    $ret = id_card_generate_for_user((int)$id, (int)$data["id_school"], $study_year);
    if ($ret->is_error())
        return ($ret);
    add_log(CREATIVE_OPERATION, "User student card generated: ".$ret->value["path"], (int)$id);
    return (new ValueResponse([
        "msg" => $Dictionnary["IdCardGenerated"] ?? "Carte étudiante générée",
        "stored_file" => $ret->value["path"],
    ]));
}

function GenerateScolarityContract($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    if (($extra = document_builder_request_extra_fields($data))->is_error())
	return ($extra);

    $ret = build_user_contract($id, document_builder_contract_kind($data), $extra->value);
    if ($ret->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => "Contrat généré",
	"content" => document_builder_public_url($ret->value["output"])
    ]));
}

function GenerateUserLetter($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    if (!isset($data["model"]) || trim((string)$data["model"]) == "")
	return (new ErrorResponse("InvalidParameter", "model"));
    if (($extra = document_builder_request_extra_fields($data))->is_error())
	return ($extra);

    $ret = build_user_letter($id, $data["model"], $extra->value);
    if ($ret->is_error())
	return ($ret);
    return (new ValueResponse([
	"msg" => "Lettre générée",
	"content" => document_builder_public_url($ret->value["output"])
    ]));
}


function ManageUserDocumentWorkspace($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    require_once (__DIR__."/../tools/registration_form.php");
    require_once (__DIR__."/../tools/document_workflow.php");

    $id = (int)$id;
    if (!can_manage_student_documents($id))
        forbidden();
    $target = db_select_one("* FROM user WHERE id = $id AND authority != -1");
    if ($target == NULL)
        return (new ErrorResponse("UserNotFound"));

    $operation = strtolower(trim((string)($data["operation"] ?? "start")));
    if (!in_array($operation, ["start", "release", "reset", "abandon"], true))
        return (new ErrorResponse("InvalidParameter", "operation"));
    $reference = trim((string)($data["document_file"] ?? ""));
    $model_hash = strtolower(trim((string)($data["model_hash"] ?? "")));
    $target_year = (int)($data["target_year"] ?? 0);
    if (!preg_match('/^[a-f0-9]{32}$/D', $model_hash) || $target_year < 0 || $target_year > 5)
        return (new ErrorResponse("InvalidParameter", "document workspace"));

    $resolved = dabsic_editor_resolve_file($reference, false);
    if (!$resolved["ok"])
        return (new ErrorResponse($resolved["error"], $resolved["details"] ?? ""));
    $source_reference = function_exists("document_reference_from_editor_path")
        ? (string)(document_reference_from_editor_path($resolved["relative"]) ?? "") : "";
    if ($source_reference == "" || !hash_equals(md5($source_reference), $model_hash))
        return (new ErrorResponse("InvalidParameter", "model_hash"));

    $form_metadata = dabsic_form_form_metadata($resolved["absolute"]);
    $initial_fields = [];
    if ($operation === "start")
    {
        if (isset($form_metadata["fields"]["Student.SchoolPeriod"]))
        {
            $school_period = trim((string)($data["school_period"] ?? ""));
            if (!preg_match('/^([0-9]{4})-([0-9]{4})$/D', $school_period, $period_match)
                || (int)$period_match[2] !== (int)$period_match[1] + 1)
                return (new ErrorResponse("InvalidParameter", "school_period"));
            $initial_fields["Student.SchoolPeriod"] = $school_period;
        }
        if (isset($form_metadata["fields"]["Student.Month"]))
        {
            $entry_month = trim((string)($data["entry_month"] ?? ""));
            if (!in_array($entry_month, ["September", "January", "April", "Other"], true))
                return (new ErrorResponse("InvalidParameter", "entry_month"));
            $initial_fields["Student.Month"] = $entry_month;
        }
    }

    $context_bindings = $data["context_bindings"] ?? [];
    $bundle = registration_form_document_bundle(
        $resolved["absolute"],
        $target,
        isset($User["id"]) ? (int)$User["id"] : 0,
        $target_year,
        $context_bindings,
        true
    );
    if (!$bundle["ok"])
        return (new ErrorResponse("MissingField", "Contexts.".implode(", Contexts.", $bundle["missing"])));
    $context_bindings = $bundle["bindings"];
    $signature_bindings = $bundle["signature_bindings"];
    $chain = $bundle["chain"];
    $chain_json = json_encode($chain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($chain_json === false)
        return (new ErrorResponse("CannotEdit"));

    $output_key = "user-document:".$id.":".$model_hash.":".$target_year;
    $form_output = dabsic_form_resolve_output($output_key, true, $id);
    if (!$form_output["ok"])
        return (new ErrorResponse($form_output["error"], $form_output["details"] ?? ""));
    $staff_role = dabsic_form_staff_role($form_metadata);

    if ($operation === "start")
    {
        foreach (db_select_all("* FROM user_form WHERE id_user = $id AND kind LIKE 'DOC-%' AND revoked_at IS NULL
            AND (completed_at IS NOT NULL OR expires_at >= NOW())") as $form)
        {
            $document = document_workflow_document_form_metadata($form);
            if (trim((string)($document["output"] ?? "")) === $output_key
                && trim((string)($document["processed_at"] ?? "")) === "")
                return (new ErrorResponse("DocumentWorkspaceAlreadyActive"));
        }
        foreach (document_workflow_visible_instances(1000) as $entry)
        {
            if ((int)($entry["owner_user_id"] ?? 0) !== $id)
                continue ;
            $instance = $entry["instance"] ?? [];
            if (in_array((string)($instance["Status"] ?? ""), ["Completed", "Expired"], true))
                continue ;
            if ((string)($instance["Model"] ?? "") === $source_reference
                && (int)($instance["TargetYear"] ?? 0) === $target_year)
                return (new ErrorResponse("DocumentWorkspaceAlreadyActive"));
        }
        $existing_workspace = dabsic_form_load_workspace($form_output);
        if (!$existing_workspace["ok"])
            return (new ErrorResponse($existing_workspace["error"], $existing_workspace["details"] ?? ""));
        if ($existing_workspace["exists"])
            return (new ErrorResponse("DocumentWorkspaceAlreadyActive"));
        $reset = dabsic_form_reset_output_values($form_output);
        if (!$reset["ok"])
            return (new ErrorResponse($reset["error"], $reset["details"] ?? ""));
        if (count($initial_fields))
        {
            $seeded = dabsic_form_write_output_values($form_output, $initial_fields);
            if (!$seeded["ok"])
                return (new ErrorResponse($seeded["error"], $seeded["details"] ?? ""));
        }
        $workspace = [
            "version" => 1,
            "reference" => $resolved["relative"],
            "source_reference" => $source_reference,
            "model_hash" => $model_hash,
            "target_year" => $target_year,
            "context_bindings" => $context_bindings,
            "signature_bindings" => $signature_bindings,
            "chain" => $chain_json,
            "staff_role" => $staff_role,
            "initial_fields" => $initial_fields,
            "created_by" => isset($User["id"]) ? (int)$User["id"] : 0,
        ];
        $saved = dabsic_form_save_workspace($form_output, $workspace);
        if (!$saved["ok"])
        {
            dabsic_form_reset_output_values($form_output);
            return (new ErrorResponse($saved["error"], $saved["details"] ?? ""));
        }
        add_log(EDITING_OPERATION, "Document workspace started for user $id: $source_reference", $id);

        // Some administrative documents are already complete at creation time:
        // no staff editor and no contributor has anything to fill in.  Their
        // Dabsic model may opt into immediate freezing with Workflow.AutoFinalize.
        // The signature remains an explicit act: after freezing, send the normal
        // signature invitation immediately instead of showing an otherwise useless
        // manual "Finaliser" step on the student's profile.
        $workspace_roles = is_array($form_metadata["roles"] ?? NULL) ? $form_metadata["roles"] : [];
        if (document_workflow_model_auto_finalize($resolved["absolute"])
            && $staff_role == "" && !count($workspace_roles))
        {
            $workspace["released_at"] = date("Y-m-d H:i:s");
            $workspace["auto_finalize"] = 1;
            $saved = dabsic_form_save_workspace($form_output, $workspace);
            if (!$saved["ok"])
                return (new ErrorResponse($saved["error"], $saved["details"] ?? ""));

            require_once (__DIR__."/doc.php");
            $key = "start_".$model_hash;
            $generated = _GenerateDoc(0, [
                "doc_".$key => 1,
                "docref_".$key => $source_reference,
                "form_output" => $output_key,
                "save_user_document" => $id,
                "finalize_document" => 1,
                "target_year" => $target_year,
                "context_bindings" => document_context_bindings_json($context_bindings),
            ], "POST", NULL, NULL);
            if ($generated->is_error())
            {
                add_log(REPORT, "Immediate document finalization failed for $output_key: ".strval($generated), $id);
                return ($generated);
            }

            $generated_instance = is_array($generated->value["document_instance"] ?? NULL)
                ? $generated->value["document_instance"] : [];
            $instance_id = trim((string)($generated_instance["id"] ?? ""));
            if ($instance_id == "")
                $instance_id = document_workflow_processed_instance_for_output($id, $output_key);

            $signature_message = "";
            if ($instance_id != "")
            {
                $signature_mail = document_workflow_send_initial_signature_requests($id, $instance_id);
                if ($signature_mail->is_error())
                {
                    // The frozen instance already exists.  Do not report the whole
                    // creation as failed (which would encourage a duplicate retry):
                    // Albedo will retry the invitation and the profile exposes the
                    // signature delivery error in the meantime.
                    add_log(REPORT, "Immediate signature invitation failed for document instance $instance_id: ".strval($signature_mail), $id);
                    $signature_message = " Le document est prêt à signer ; l'envoi du mail de signature sera retenté automatiquement.";
                }
                else if ((int)($signature_mail->value["sent"] ?? 0) > 0)
                    $signature_message = " La demande de signature a été envoyée immédiatement.";
            }

            return (new ValueResponse([
                "msg" => ($Dictionnary["DocumentAutoFinalized"] ?? "Document préparé automatiquement.").$signature_message,
                "content" => "",
                "instance_id" => $instance_id,
            ]));
        }

        $review_url = "";
        if ($staff_role != "")
        {
            $context_fields = $initial_fields;
            if ($target_year >= 1 && $target_year <= 5)
                $context_fields = array_merge($context_fields, [
                    "Student.ChosenClass" => "EF".$target_year,
                    "Student.CurrentYear" => (string)$target_year,
                ]);
            $review_url = "index.php?p=DabsicFormMenu".
                "&file=".rawurlencode($resolved["relative"]).
                "&output=".rawurlencode($output_key).
                "&mode=docbuilder".
                "&form_role=".rawurlencode($staff_role).
                "&context_bindings=".rawurlencode(document_context_bindings_json($context_bindings)).
                "&context_fields=".rawurlencode(document_context_fields_json($context_fields));
        }
        return (new ValueResponse([
            "msg" => $Dictionnary["DocumentWorkspaceStarted"] ?? "Document démarré.",
            "content" => $review_url,
        ]));
    }

    $workspace = dabsic_form_load_workspace($form_output);
    if (!$workspace["ok"])
        return (new ErrorResponse($workspace["error"], $workspace["details"] ?? ""));
    if (!$workspace["exists"])
        return (new ErrorResponse("DocumentWorkspaceNotFound"));
    $workspace_data = $workspace["data"];
    if (($workspace_data["model_hash"] ?? "") !== $model_hash
        || (int)($workspace_data["target_year"] ?? -1) !== $target_year
        || ($workspace_data["reference"] ?? "") !== $resolved["relative"])
        return (new ErrorResponse("DabsicFormChanged"));

    if ($operation === "release")
    {
        if (!empty($workspace_data["released_at"]))
            return (new ValueResponse([
                "msg" => "Les demandes de complément ont déjà été envoyées."
            ]));
        $semantic_bindings = document_workflow_workspace_semantic_bindings($workspace_data);
        $states = document_workflow_form_role_states(
            $id, $output_key, $form_metadata["roles"] ?? [],
            (int)($workspace_data["created_by"] ?? 0), $semantic_bindings
        );
        $sent_count = 0;
        foreach ($states as $role => $state)
        {
            if (!empty($state["completed"]))
                continue ;
            $recipient_id = (int)($state["recipient_user_id"] ?? 0);
            if ($recipient_id <= 0)
            {
                if (!empty($state["required"]))
                    return (new ErrorResponse("DocumentRoleNoRecipient", (string)$role));
                continue ;
            }
            $result = registration_form_create_document_invitation(
                $id,
                $workspace_data["reference"],
                $model_hash,
                $target_year,
                document_title_from_file($resolved["absolute"], document_title_fallback($resolved["relative"])),
                (int)($workspace_data["created_by"] ?? (int)$User["id"]),
                $workspace_data["context_bindings"] ?? [],
                $role,
                $recipient_id
            );
            if (!$result["ok"])
                return (new ErrorResponse($result["error"], $result["details"] ?? $role));
            if (!empty($result["skipped"]))
            {
                if (!isset($workspace_data["skipped_roles"]) || !is_array($workspace_data["skipped_roles"]))
                    $workspace_data["skipped_roles"] = [];
                $workspace_data["skipped_roles"][(string)$role] = date("Y-m-d H:i:s");
                continue ;
            }
            $recipient = $result["recipient"] ?? [];
            $name = trim((string)($recipient["first_name"] ?? "")." ".(string)($recipient["family_name"] ?? ""));
            $label = trim((string)($result["schema"]["document"]["label"] ?? ""));
            $role_label = trim((string)($state["label"] ?? ""));
            if ($role_label == "")
                $role_label = (string)$role;
            $title = sprintf(
                $Dictionnary["DocumentFormRoleMailTitle"] ?? "Document à compléter : %s — rôle : %s",
                $label != "" ? $label : "document", $role_label
            );
            $body = sprintf(
                $Dictionnary["DocumentFormRoleMailContent"] ?? "Bonjour %s,\n\nL'établissement vous demande de compléter le document « %s » en tant que %s :\n%s\n\nLe lien est valable quatorze jours. Vous pouvez sauvegarder un brouillon avant la validation définitive.",
                $name, $label != "" ? $label : "document", $role_label, $result["url"]
            );
            $mail = send_mail((string)($recipient["mail"] ?? ""), $title, $body);
            if ($mail->is_error())
            {
                registration_form_revoke_token($result["token"]);
                return ($mail);
            }
            ++$sent_count;
        }
        $workspace_data["released_at"] = date("Y-m-d H:i:s");
        $workspace_data["auto_finalize"] = 1;
        $saved = dabsic_form_save_workspace($form_output, $workspace_data);
        if (!$saved["ok"])
            return (new ErrorResponse($saved["error"], $saved["details"] ?? ""));
        add_log(EDITING_OPERATION, "Document workspace released to contributors for user $id: $source_reference", $id);
        return (new ValueResponse([
            "msg" => $sent_count > 0
                ? "Les parties à compléter ont été envoyées automatiquement aux personnes concernées."
                : "Aucun formulaire externe n'était nécessaire ; le document poursuivra automatiquement son workflow."
        ]));
    }

    if ($operation === "reset")
    {
        $active_form_ids = [];
        foreach (document_workflow_active_forms_for_output($id, $output_key) as $active_form)
            $active_form_ids[] = (int)$active_form["id"];
        // Resetting starts a new contribution cycle. Every old public link and
        // its task must become historical before the shared values disappear.
        if (!registration_form_revoke_rows($active_form_ids))
            return (new ErrorResponse("CannotEdit", "document invitations"));
        $reset = dabsic_form_reset_output_values_and_sessions($form_output, $output_key, "document workspace reset");
        if (!$reset["ok"])
            return (new ErrorResponse($reset["error"], $reset["details"] ?? ""));
        $workspace_initial_fields = is_array($workspace_data["initial_fields"] ?? NULL)
            ? $workspace_data["initial_fields"] : [];
        if (count($workspace_initial_fields))
        {
            $seeded = dabsic_form_write_output_values($form_output, $workspace_initial_fields);
            if (!$seeded["ok"])
                return (new ErrorResponse($seeded["error"], $seeded["details"] ?? ""));
        }
        unset($workspace_data["released_at"], $workspace_data["auto_finalize"], $workspace_data["skipped_roles"]);
        $saved = dabsic_form_save_workspace($form_output, $workspace_data);
        if (!$saved["ok"])
            return (new ErrorResponse($saved["error"], $saved["details"] ?? ""));
        add_log(EDITING_OPERATION, "Document workspace reset for user $id: $source_reference", $id);
        return (new ValueResponse([
            "msg" => $Dictionnary["DocumentWorkspaceResetDone"] ?? "Les informations enregistrées ont été remises à zéro."
        ]));
    }

    if ($operation === "abandon")
    {
        // Un brouillon purement local peut être supprimé. Dès qu'une demande a été
        // envoyée ou qu'une instance existe, l'historique doit être conservé et le
        // workflow doit être périmé via les actions dédiées.
        foreach (db_select_all("* FROM user_form WHERE id_user = $id AND kind LIKE 'DOC-%' AND revoked_at IS NULL
            AND (completed_at IS NOT NULL OR expires_at >= NOW())") as $form)
        {
            $document = document_workflow_document_form_metadata($form);
            if (trim((string)($document["output"] ?? "")) === $output_key
                && trim((string)($document["processed_at"] ?? "")) === "")
                return (new ErrorResponse("DocumentWorkspaceCannotAbandon"));
        }
        foreach (document_workflow_visible_instances(1000) as $entry)
        {
            if ((int)($entry["owner_user_id"] ?? 0) !== $id)
                continue ;
            $instance = $entry["instance"] ?? [];
            if (in_array((string)($instance["Status"] ?? ""), ["Completed", "Expired"], true))
                continue ;
            if ((string)($instance["Model"] ?? "") === $source_reference
                && (int)($instance["TargetYear"] ?? 0) === $target_year)
                return (new ErrorResponse("DocumentWorkspaceCannotAbandon"));
        }

        $reset = dabsic_form_reset_output_values_and_sessions($form_output, $output_key, "document workspace abandoned");
        if (!$reset["ok"])
            return (new ErrorResponse($reset["error"], $reset["details"] ?? ""));
        if (!dabsic_form_delete_workspace($form_output))
            return (new ErrorResponse("DabsicFormCannotSave"));

        add_log(EDITING_OPERATION, "Document workspace abandoned for user $id: $source_reference", $id);
        return (new ValueResponse([
            "msg" => $Dictionnary["DocumentWorkspaceAbandoned"] ?? "Le document démarré a été abandonné."
        ]));
    }

}

function SendUserDocumentForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $id = (int)$id;
    if (!can_manage_student_documents($id))
        forbidden();
    $target = db_select_one("* FROM user WHERE id = $id AND authority != -1");
    if ($target == NULL)
        return (new ErrorResponse("UserNotFound"));

    $result = registration_form_create_document_invitation(
        $id,
        $data["document_file"] ?? "",
        $data["model_hash"] ?? "",
        $data["target_year"] ?? 0,
        $data["document_label"] ?? "",
        (int)$User["id"],
        $data["context_bindings"] ?? [],
        $data["form_role"] ?? "Beneficiaire"
    );
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));
    if (!empty($result["skipped"]))
        return (new ValueResponse([
            "msg" => "Aucun formulaire n'est nécessaire pour ce rôle : aucun champ n'est à compléter."
        ]));

    $recipient = is_array($result["recipient"] ?? NULL) ? $result["recipient"] : $target;
    $name = trim(($recipient["first_name"] ?? "")." ".($recipient["family_name"] ?? ""));
    $label = trim((string)($result["schema"]["document"]["label"] ?? ""));
    $document_schema = $result["schema"]["document"] ?? [];
    $form_role = trim((string)($document_schema["form_role"] ?? ""));
    $role_label = trim((string)($document_schema["form_roles"][$form_role]["label"] ?? ""));
    if ($role_label == "")
        $role_label = $form_role;
    $title = sprintf(
        $Dictionnary["DocumentFormRoleMailTitle"] ?? "Document à compléter : %s — rôle : %s",
        $label != "" ? $label : ($Dictionnary["DocumentFormTitle"] ?? "document"), $role_label
    );
    $body = sprintf(
        $Dictionnary["DocumentFormRoleMailContent"] ?? "Bonjour %s,\n\nL'établissement vous demande de compléter le document « %s » en tant que %s :\n%s\n\nLe lien est valable quatorze jours. Vous pouvez sauvegarder un brouillon avant la validation définitive.",
        $name,
        $label != "" ? $label : ($Dictionnary["DocumentFormTitle"] ?? "document"),
        $role_label,
        $result["url"]
    );
    $sent = send_mail($recipient["mail"], $title, $body);
    if ($sent->is_error())
    {
        registration_form_revoke_token($result["token"]);
        return ($sent);
    }
    add_log(EDITING_OPERATION,
        "Document form sent to user $id for ".($label != "" ? $label : ($data["document_file"] ?? "document")),
        $id
    );
    return (new ValueResponse([
        "msg" => $Dictionnary["DocumentFormSent"] ?? "Formulaire documentaire envoyé.",
        "content" => $result["url"]
    ]));
}

function SendUserAdministrativeForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $id = (int)$id;
    $target = db_select_one("* FROM user WHERE id = $id AND authority != -1");
    if ($target == NULL)
        return (new ErrorResponse("UserNotFound"));
    if (!can_send_user_administrative_form($id))
        forbidden();

    $status = $target["profile_status"] ?? "";
    $is_relation_profile = user_relation_is_administrative_contact($id);
    // Une relation légal/finance est prioritaire sur un ancien profile_status
    // éventuellement resté à "prospect".
    if ($is_relation_profile)
        $result = registration_form_create_profile_invitation($id, (int)$User["id"]);
    else if ($status == "prospect")
        $result = registration_form_create_invitation($id, $data["kind"] ?? "", (int)$User["id"]);
    else
        return (new ErrorResponse("InvalidParameter", "profile_status"));
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));

    $name = trim(($target["first_name"] ?? "")." ".($target["family_name"] ?? ""));
    if ($is_relation_profile)
    {
        $title = $Dictionnary["AdministrativeFormMailTitle"] ?? "Vos informations administratives";
        $body = sprintf(
            $Dictionnary["AdministrativeFormMailContent"] ?? "Bonjour %s,\n\nVous pouvez compléter ou vérifier vos informations administratives avec le lien suivant, valable quatorze jours :\n%s\n\nVous pouvez sauvegarder un brouillon avant la validation définitive.",
            $name,
            $result["url"]
        );
        $message = $Dictionnary["AdministrativeFormSent"] ?? "Formulaire administratif envoyé.";
    }
    else
    {
        $title = $Dictionnary["RegistrationFormMailTitle"] ?? "Votre dossier d'inscription";
        $body = sprintf(
            $Dictionnary["RegistrationFormMailContent"] ?? "Bonjour %s,\n\nVous pouvez compléter votre dossier d'inscription avec le lien suivant, valable quatorze jours :\n%s\n\nVous pouvez sauvegarder un brouillon avant la validation définitive.",
            $name,
            $result["url"]
        );
        $message = $Dictionnary["RegistrationFormSent"] ?? "Formulaire d'inscription envoyé.";
    }

    $sent = send_mail($target["mail"], $title, $body);
    if ($sent->is_error())
    {
        registration_form_revoke_token($result["token"]);
        return ($sent);
    }
    add_log(EDITING_OPERATION,
        "Administrative form sent to user $id".(!$is_relation_profile && $status == "prospect" ? " for ".strtoupper((string)($data["kind"] ?? "")) : ""),
        $id
    );
    return (new ValueResponse(["msg" => $message, "content" => $result["url"]]));
}

function SetUserProperties($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    if ($id == -1)
        bad_request();
    $id = (int)$id;
    $usr = db_select_one("codename, mail FROM user WHERE id = $id");
    if ($usr == NULL)
        bad_request();

    $is_self = is_me($id);
    $relation_identity_authority = function_exists("can_manage_relation_administrative_user")
        && can_manage_relation_administrative_user($id);
    $identity_authority = is_identity_authority_for_user($id) || $relation_identity_authority;
    $action = isset($data["action"]) ? (string)$data["action"] : "";
    $administrative_authority = function_exists("can_manage_user_administrative_profile")
        && can_manage_user_administrative_profile($id);
    if (!$is_self && !$identity_authority
        && !($action == "administrative_data" && $administrative_authority))
        forbidden();

    $codename = $usr["codename"];
    $mail = $usr["mail"];
    unset($data["action"]);

    if ($action == "administrative_data")
    {
        if (!$administrative_authority)
            forbidden();
        $request = user_identity_update_contract_administrative_fields($id, $data);
        if ($request->is_error())
            return ($request);
        refresh_user($id);
        $identity = user_identity_write_identity_dabsic($id);
        if ($identity->is_error())
            return ($identity);
        return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
    }

    if (isset($data["avatar"]))
    {
        if (!isset($data["type"]))
            $data["type"] = "set_avatar";
        if (!is_admin() || $is_self)
            $data["type"] = "set_avatar";
        $target = $Configuration->UsersDir($codename).
            ($data["type"] == "set_photo" ? "admin/photo.png" : "public/avatar.png");
        $data["avatar"] = base64_decode($data["avatar"][0]["content"]);
        if (file_put_contents($target, $data["avatar"]) === false)
            return (new ErrorResponse("CannotWritePngFile"));
        unset($data["type"], $data["avatar"]);
    }
    else
        unset($data["type"]);

    $allowed = ["nickname", "visibility"];
    if ($identity_authority)
        $allowed = array_merge($allowed, [
            "mail", "first_name", "use_name", "family_name", "gender",
            "birth_date", "nationality", "phone", "street_name",
            "postal_code", "city", "country"
        ]);
    foreach (array_keys($data) as $field)
        if (!in_array($field, $allowed, true))
            return (new ErrorResponse("InvalidParameter", $field));

    $mail_marker = false;
    if (isset($data["mail"]) && strcasecmp(trim((string)$data["mail"]), "nomail") == 0)
    {
        // Le marqueur est réservé aux personnes qui administrent réellement
        // ce contact via une relation d'école. Le contact lui-même ne peut pas
        // effacer son mail avec ce raccourci.
        if (!$relation_identity_authority)
            return (new ErrorResponse("InvalidParameter", "mail"));
        $data["mail"] = "";
        $mail_marker = true;
    }
    if (isset($data["mail"]) && $data["mail"] == $mail)
        unset($data["mail"]);
    if (isset($data["birth_date"]))
        $data["birth_date"] = trim((string)$data["birth_date"]) == "" ? NULL : db_form_date($data["birth_date"]);
    if (isset($data["mail"]) && trim((string)$data["mail"]) == "" && !$mail_marker)
        return (new ErrorResponse("InvalidParameter", "mail"));
    if (isset($data["mail"]))
    {
        global $Database;
        $new_mail = $Database->real_escape_string($data["mail"]);
        if (db_select_one("id FROM user WHERE mail = '$new_mail' AND id != $id AND authority != -1"))
            return (new ErrorResponse("MailUsed"));
    }

    if (count($data) && ($request = set_user_data($id, $data))->is_error())
        return ($request);
    refresh_user($id);
    $identity = user_identity_write_identity_dabsic($id);
    if ($identity->is_error())
        return ($identity);
    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
}

function SetUserLink($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    foreach ([
	"user" => ["parent_child", "parent", "child", "Profile"],
	"school" => [],
	"cycle" => []
    ] as $link => $fields)
    {
	if (count($fields))
	{
	    $table = $fields[0];
	    $left = $fields[1];
	    $right = $fields[2];
	    $lnk = $fields[3];
	}
	else
	    $table = $left = $right = $lnk = "";
	
	if ($data["action"] != $link)
	    continue ;

	if (!is_admin() && $link == "school")
	{
	    if (($schools = resolve_codename("school", $data["school"]))->is_error())
		return ($schools);
	    if (!is_array($schools = $schools->value))
		$schools = [$schools];
	    $fnd = false;
	    foreach ($User["school"] as $sc)
	    {
		foreach ($schools as $sc2)
		{
		    if (abs($sc["id_school"]) != abs($sc2))
			continue ;
		    $fnd = true;
		    break 2;
		}
	    }
	    if ($fnd == false)
		forbidden();
	}
	else if (!is_my_director($id))
	    forbidden();
	
	// parent_child est stocké dans le sens responsable -> élève.
	// Depuis le profil d'un élève, l'utilisateur saisi est donc le parent
	// (côté gauche) et l'élève courant est l'enfant (côté droit).
	if ($link == "user")
	    $request = handle_links(
		$data[$link], $id, "user", "user", false,
		$table, false, $left, $right
	    );
	else
	    $request = handle_links(
		$id, $data[$link], "user", $link, false,
		$table, false, $left, $right
	    );
	if ($request->is_error())
	    return ($request);

	$user = fetch_users([$link], $id);
	$user = array_shift($user);
	return (new ValueResponse([
	    "msg" => $Dictionnary["Edited"],
	    "content" => list_of_linksb([
		"hook_name" => "user",
		"hook_id" => $id,
		"linked_name" => $link,
		"linked_elems" => $user[$link],
		"method" => $method,
		"dislay_link" => $lnk,
		"full_formular" => false
	    ])
	]));
    }
    bad_request();
}

function SetUserRelation($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $id = (int)$id;
    if ($id <= 0 || !can_manage_user_relations($id))
        forbidden();
    if (!isset($data["parent"]))
        return (new ErrorResponse("MissingField", "parent"));

    if (($parent = resolve_codename("user", $data["parent"], "codename", true))->is_error())
        return ($parent);
    $id_parent = (int)($parent->value["id"] ?? 0);
    if (!can_assign_user_relation_parent($id, $id_parent))
        forbidden();

    $relations = user_relation_request_values($data);
    if (!count($relations))
        return (new ErrorResponse("MissingField", "relation"));
    if (($request = user_relation_set_existing_parent($id, $id_parent, $relations))->is_error())
        return ($request);

    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
}

function DeleteUserRelation($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $SUBID;

    $id = (int)$id;
    $id_parent = abs((int)$SUBID);
    if ($id <= 0 || $id_parent <= 0 || !can_manage_user_relations($id))
        forbidden();
    if (($request = user_relation_remove_parent($id, $id_parent))->is_error())
        return ($request);
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}

function DeleteUser($id, $data, $method, $output, $module)
{
    if ($id == 1)
	forbidden(); // On ne peut pas bannir Albedo
    return (update_table("user", $id, ["deleted" => db_form_date(now())]));
}

function UndeleteUser($id, $data, $method, $output, $module)
{
    return (update_table("user", $id, ["deleted" => NULL]));
}

function SetTodoEntry($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;
    global $SUBID;
    global $User;
    
    if ($method == "DELETE")
    {
	$SUBID = abs($SUBID);
	$Database->query("
	    DELETE FROM user_todolist WHERE id_user = $id AND id = $SUBID
	");
	$msg = $Dictionnary["Deleted"];
    }
    else
    {
	$cnt = $Database->real_escape_string($data["content"]);
	$Database->query("
	    INSERT INTO user_todolist (id_user, content) VALUE (
		$id, '$cnt'
	    )
	");
	$msg = $Dictionnary["Added"];
    }
    ob_start();
    get_user_public_data($User);
    require_once ("./pages/home/todolist.php");
    return (new ValueResponse([
	"msg" => $msg,
	"content" => ob_get_clean(),
    ]));
}

/*
** Politique d'accès aux fichiers utilisateurs.
** La lecture HTTP est contrôlée par dres/bouncer.php ; les opérations de
** l'API et du path_browser utilisent exactement les mêmes règles.
*/

function file_access($id, $file, $public = false, $read = false)
{
    $file = user_storage_normalize_path($file);
    if ($file === NULL)
        forbidden();

    if ($read)
    {
        if (!user_storage_can_read_path((int)$id, $file))
            forbidden();
    }
    else if (!user_storage_can_write_path((int)$id, $file))
        forbidden();

    return ($file);
}

function user_subscription_file_root()
{
    return ("admin/subscription");
}

function user_file_path_is_under($file, $base)
{
    $file = resolve_path($file);
    $base = resolve_path($base);

    return ($file == $base ||
            strncmp($file, $base."/", strlen($base) + 1) == 0);
}

function subscription_file_access($id, $file, $public = false, $read = false)
{
    $base = user_subscription_file_root();

    $file = resolve_path($file);
    if ($file == "")
	$file = $base;
    else if (!user_file_path_is_under($file, $base))
    {
	$filex = explode("/", $file);
	if (isset($filex[0]) && $filex[0] == "admin")
	    forbidden();
	$file = resolve_path($base."/".$file);
    }
    $file = file_access($id, $file, $public, $read);
    if (!user_file_path_is_under($file, $base))
	forbidden();
    return ($file);
}

function user_documentation_file_root()
{
    return (document_builder_documentation_file_root());
}

function documentation_file_access($id, $file, $public = false, $read = false)
{
    $base = user_documentation_file_root();

    $file = resolve_path($file);
    if ($file == "")
        $file = $base;
    else if (!user_file_path_is_under($file, $base))
    {
        $filex = explode("/", $file);
        if (isset($filex[0]) && $filex[0] == "admin")
            forbidden();
        $file = resolve_path($base."/".$file);
    }
    $file = file_access($id, $file, $public, $read);
    if (!user_file_path_is_under($file, $base))
        forbidden();
    return ($file);
}

function user_letter_file_root()
{
    return (document_builder_letter_file_root());
}

function letter_file_access($id, $file, $public = false, $read = false)
{
    $base = user_letter_file_root();

    $file = resolve_path($file);
    if ($file == "")
	$file = $base;
    else if (!user_file_path_is_under($file, $base))
    {
	$filex = explode("/", $file);
	if (isset($filex[0]) && $filex[0] == "admin")
	    forbidden();
	$file = resolve_path($base."/".$file);
    }
    $file = file_access($id, $file, $public, $read);
    if (!user_file_path_is_under($file, $base))
	forbidden();
    return ($file);
}

function GetUserFileDir($id, $data, $method, $output, $module, $msg, $type, $access_function, $locked_path = "")
{
    global $Configuration;
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if (!isset($data["path"]))
	$data["path"] = "";

    $id = (int)$id;
    if (($user = db_select_one("codename FROM user WHERE id = $id")) == NULL)
	not_found();

    $nocd = false;
    if (isset($data["nocd"]))
	$nocd = !!$data["nocd"];
    $fbid = "file_browser";
    if (isset($data["fbid"]))
	$fbid = $data["fbid"];
    $path_browser_can_cd = !$nocd;
    if (isset($data["path_browser_can_cd"]))
	$path_browser_can_cd = !!$data["path_browser_can_cd"];
    $language = "";
    if (isset($data["language"]))
	$language = $data["language"];

    $file = $access_function($id, $data["path"], false, true);
    $root = $Configuration->UsersDir($user["codename"]);
    $html = get_dir($root, $file, "user", $id, $type, $fbid, true, $language, $nocd, $locked_path, $path_browser_can_cd);
    $msg = $msg ? ["msg" => $msg] : [];
    return (new ValueResponse(array_merge($msg, [
	"content" => $html
    ])));
}

function GetFileDir($id, $data, $method, $output, $module, $msg = "")
{
    return (GetUserFileDir($id, $data, $method, $output, $module, $msg, "file", "file_access"));
}

function GetSubscriptionFileDir($id, $data, $method, $output, $module, $msg = "")
{
    $data["nocd"] = 0;
    $data["path_browser_can_cd"] = 1;
    return (GetUserFileDir(
	$id,
	$data,
	$method,
	$output,
	$module,
	$msg,
	"subscription_file",
	"subscription_file_access",
	user_subscription_file_root()
    ));
}

function GetDocumentationFileDir($id, $data, $method, $output, $module, $msg = "")
{
    $data["nocd"] = 0;
    $data["path_browser_can_cd"] = 1;
    return (GetUserFileDir(
        $id,
        $data,
        $method,
        $output,
        $module,
        $msg,
        "documentation_file",
        "documentation_file_access",
        user_documentation_file_root()
    ));
}

function GetLetterFileDir($id, $data, $method, $output, $module, $msg = "")
{
    $data["nocd"] = 0;
    $data["path_browser_can_cd"] = 1;
    return (GetUserFileDir(
	$id,
	$data,
	$method,
	$output,
	$module,
	$msg,
	"letter_file",
	"letter_file_access",
	user_letter_file_root()
    ));
}

function AddUserFile($id, $data, $method, $output, $module, $access_function, $return_function)
{
    global $Configuration;
    global $User;

    if ($id == -1 || !isset($data["file"]) || !isset($data["path"]))
	bad_request();
    $id = (int)$id;
    $path = $access_function($id, $data["path"], false);
    $data["path"] = $path;
    if (($user = db_select_one("codename FROM user WHERE id = $id")) == NULL)
	not_found();
    $root = $Configuration->UsersDir($user["codename"]);
    $target = $root.$path."/";

    // On vérifie la taille disponible
    $admin_size = intval(shell_exec("du -c $root/admin | tail -n 1"));
    $total_size = intval(shell_exec("du -c $root | tail -n 1"));
    $user_size = $total_size - $admin_size;
    $add_size = 0;
    foreach ($data["file"] as $files)
    {
	if (!isset($files["name"]) || !isset($files["content"]))
	    bad_request();
	if (in_array(pathinfo($files["name"], PATHINFO_EXTENSION), [
	    "php", "sh", "pl"
	]))
	    forbidden();
	$add_size += 0;
    }
    $required = $user_size + $add_size;
    $available = (int)$Configuration->Properties["account_space"];
    if ($required > $available)
    {
	$unit1 = 0;
	$unit2 = 0;
	$size = ["o", "ko", "mo", "go", "to", "po"];
	while ($required > 1024 && $unit1 < count($size) - 1)
	{
	    $unit1 += 1;
	    $required /= 1024;
	}
	while ($available > 1024 && $unit2 < count($size) - 1)
	{
	    $unit2 += 1;
	    $available /= 1024;
	}
	return (new ErrorResponse(
	    "NotEnoughSpace",
	    "Required $required".$size[$unit1],
	    "Available $available".$size[$unit2]
	));
    }

    // C'est parti.
    foreach ($data["file"] as $files)
    {

	$content = base64_decode($files["content"]);
	new_directory($target);
	$files["name"] = str_replace(" ", "_", $files["name"]);
	if ($files["name"][0] == ".")
	    $files["name"] = substr($files["name"], 1);
	file_put_contents($target.$files["name"], $content);
	system("chmod 640 ".$target.$files["name"]);
    }
    return ($return_function($id, $data, "GET", $output, $module, "FileAdded"));
}

function AddFile($id, $data, $method, $output, $module)
{
    return (AddUserFile($id, $data, $method, $output, $module, "file_access", "GetFileDir"));
}

function AddSubscriptionFile($id, $data, $method, $output, $module)
{
    return (AddUserFile($id, $data, $method, $output, $module, "subscription_file_access", "GetSubscriptionFileDir"));
}

function AddDocumentationFile($id, $data, $method, $output, $module)
{
    return (AddUserFile($id, $data, $method, $output, $module, "documentation_file_access", "GetDocumentationFileDir"));
}

function RemoveUserFile($id, $data, $method, $output, $module, $url_key, $access_function, $return_function)
{
    global $Configuration;

    // C'est file parceque c'est /api/user/id/file/etc.
    if ($id == -1 || !isset($data[$url_key]))
	bad_request();
    $id = (int)$id;
    if (($user = db_select_one("codename FROM user WHERE id = $id")) == NULL)
	not_found();
    $root = $Configuration->UsersDir($user["codename"]);
    $file = $data[$url_key];
    if ($file[0] == "-")
	$file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    if (strncmp($root, $file, strlen($root)) != 0)
	bad_request();
    $file = substr($file, strlen($root));
    $file = $access_function($id, $file, false);
    if (strstr($file, "*"))
	forbidden();
    if (strstr($file, "["))
	forbidden();
    
    if (user_storage_is_root_space(resolve_path($file)))
	forbidden();
    if ($access_function == "subscription_file_access" &&
	resolve_path($file) == user_subscription_file_root())
	forbidden();
    if ($access_function == "documentation_file_access" &&
        resolve_path($file) == user_documentation_file_root())
        forbidden();
    if ($access_function == "letter_file_access" &&
	resolve_path($file) == user_letter_file_root())
	forbidden();
    $file = escapeshellarg($root.$file);
    system("rm -r $file");
    return ($return_function($id, $data, "GET", $output, $module, "FileRemoved"));
}

function RemoveFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "file", "file_access", "GetFileDir"));
}

function RemoveSubscriptionFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "subscription_file", "subscription_file_access", "GetSubscriptionFileDir"));
}

function RemoveDocumentationFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "documentation_file", "documentation_file_access", "GetDocumentationFileDir"));
}

function RemoveLetterFile($id, $data, $method, $output, $module)
{
    return (RemoveUserFile($id, $data, $method, $output, $module, "letter_file", "letter_file_access", "GetLetterFileDir"));
}

$Tab = [
    // Récupération d'utilisateur(s)
    "GET" => [
	"" => [
	    "logged_in",
	    "DisplayUser"
	],
	"file" => [
	    "logged_in",
	    "GetFileDir",
	],
        "nfc_card" => [
            "can_manage_user_credentials",
            "DownloadNfcCard",
        ],
        "nfc_auto_context" => [
            "logged_in",
            "GetNfcAutoContext",
        ],
	"subscription_file" => [
	    "is_director_for_student",
	    "GetSubscriptionFileDir",
	],
        "documentation_file" => [
            "is_director_for_student",
            "GetDocumentationFileDir",
        ],
	"letter_file" => [
	    "is_director_for_student",
	    "GetLetterFileDir",
	],
    ],
    "POST" => [
	"" => [
	    "am_i_director",
	    "SubscribeUser"
	],
        "nfc_card_owner" => [
            "can_manage_user_credentials",
            "CheckNfcCardOwner",
        ],
	"diploma" => [
	    "can_generate_user_diploma",
	    "GenerateUserDiploma",
	],
	"student_card" => [
	    "can_generate_user_id_card",
	    "GenerateUserIdCard",
	],
	"todolist" => [
	    "is_me_or_admin",
	    "SetTodoEntry"
	],
	"file" => [
	    // Une vérification supplémentaire doit être faite
	    // car admin/ ne peut etre lu et écrit que par la direction
	    "is_me_or_director_for_student",
	    "AddFile",
	],
	"subscription_file" => [
	    "is_director_for_student",
	    "AddSubscriptionFile",
	],
        "documentation_file" => [
            "is_director_for_student",
            "AddDocumentationFile",
        ],
    ],
    "PUT" => [
	"set_status" => [
	    "only_admin",
	    "SetStatus",
	],
	"new_password" => [
	    "can_manage_user_credentials",
	    "RegeneratePassword"
	],
        "new_nfc_card" => [
            "can_manage_user_credentials",
            "RegenerateNfcCard",
        ],
	"new_contract" => [
	    "only_admin",
	    "GenerateScolarityContract",
	],
	"new_letter" => [
	    "is_director_for_student",
	    "GenerateUserLetter",
	],
	"properties" => [
	    "can_edit_user_profile",
	    "SetUserProperties",
	],
	"registration" => [
	    "can_send_user_administrative_form",
	    "SendUserAdministrativeForm",
	],
        "document_workspace" => [
            "is_director_for_student",
            "ManageUserDocumentWorkspace",
        ],
        "document_form" => [
            "is_director_for_student",
            "SendUserDocumentForm",
        ],
	"administrative_data" => [
	    "can_manage_user_administrative_profile",
	    "SetUserProperties",
	],
	"set_avatar" => [
	    "can_edit_user_profile",
	    "SetUserProperties",
	],
	"user" => [
	    "is_my_director",
	    "SetUserLink",
	],
	"relation" => [
	    "can_manage_user_relations",
	    "SetUserRelation",
	],
	"school" => [
	    "am_i_director", // On est pas directeur avant de s'ajouter directeur
	    "SetUserLink",
	],
	"cycle" => [
	    "is_my_director",
	    "SetUserLink",
	],
	"file" => [
	    // Une vérification supplémentaire doit être faite
	    // car admin/ ne peut etre lu et écrit que par la direction
	    "logged_in",
	    "GetFileDir",
	],
	"subscription_file" => [
	    "is_director_for_student",
	    "GetSubscriptionFileDir",
	],
        "documentation_file" => [
            "is_director_for_student",
            "GetDocumentationFileDir",
        ],
	"letter_file" => [
	    "is_director_for_student",
	    "GetLetterFileDir",
	],
	"" => [
	    "only_admin",
	    "UndeleteUser"
	],
    ],
    "DELETE" => [
	"" => [
	    "only_admin",
	    "DeleteUser"
	],
	"user" => [
	    "is_my_director",
	    "SetUserLink",
	],
	"relation" => [
	    "can_manage_user_relations",
	    "DeleteUserRelation",
	],
	"school" => [
	    "is_my_director",
	    "SetUserLink",
	],
	"cycle" => [
	    "is_my_director",
	    "SetUserLink"
	],
	"todolist" => [
	    "is_me_or_admin",
	    "SetTodoEntry"
	],
	"file" => [
	    // Une vérification supplémentaire doit être faite
	    // car admin/ ne peut etre lu et écrit que par la direction
	    "is_me_or_director_for_student",
	    "RemoveFile",
	],
	"subscription_file" => [
	    "is_director_for_student",
	    "RemoveSubscriptionFile",
	],
        "documentation_file" => [
            "is_director_for_student",
            "RemoveDocumentationFile",
        ],
	"letter_file" => [
	    "is_director_for_student",
	    "RemoveLetterFile",
	],
    ]
];
