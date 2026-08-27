<?php

require_once ("./tools/questionnaire.php");
require_once ("./tools/quiz_resources.php");
require_once ("./tools/quiz_attempt.php");

function QuestionnaireSourceSave($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $result = questionnaire_save_source(
        (int)$id,
        $data["content"] ?? NULL,
        $data["hash"] ?? ""
    );
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));

    add_log(EDITING_OPERATION, "Questionnaire #".(int)$id." Dabsic source edited");
    return (new ValueResponse([
        "msg" => $Dictionnary["DabsicEditorSaved"] ?? "Enregistré.",
        "hash" => $result["hash"],
        "size" => $result["size"],
        "mtime" => $result["mtime"],
    ]));
}


function QuestionnaireAttemptAccess($id)
{
    $quiz = questionnaire_get((int)$id);
    return (is_array($quiz) && questionnaire_can_manage($quiz));
}

function QuestionnaireAttemptStart($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $ret = quiz_attempt_create_self_test((int)$id);
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"] ?? "QuizAttemptCannotCreate", $ret["details"] ?? ""));
    add_log(EDITING_OPERATION, "Quiz #".(int)$id." test attempt #".(int)$ret["id"]." created");
    return (new ValueResponse([
        "msg" => $Dictionnary["QuizAttemptCreated"] ?? "Tentative créée.",
        "content" => "index.php?p=QuizAttemptMenu&a=".(int)$ret["id"],
    ]));
}

function QuestionnaireTreeAccess($id)
{
    return (quiz_resource_school((int)$id) != NULL);
}

function QuestionnaireTreeDisplay($id, $message = "")
{
    global $Dictionnary;
    $questionnaire_tree_school_id = (int)$id;
    ob_start();
    require ("./pages/questionnaire/tree.php");
    return (new ValueResponse([
        "content" => ob_get_clean(),
        "msg" => $message,
    ]));
}

function QuestionnaireTreeError($result)
{
    return (new ErrorResponse($result["error"] ?? "QuizResourceError", $result["details"] ?? ""));
}

function QuestionnaireTreeCreateDirectory($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $ret = quiz_resource_create_directory((int)$id, $data["parent"] ?? "", $data["name"] ?? "");
    if (!$ret["ok"])
        return (QuestionnaireTreeError($ret));
    add_log(EDITING_OPERATION, "Quiz resource directory created for school #".(int)$id);
    return (QuestionnaireTreeDisplay($id, $Dictionnary["QuizResourceDirectoryCreated"] ?? "Dossier créé."));
}

function QuestionnaireTreeCreateFile($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $ret = quiz_resource_create_dabsic((int)$id, $data["directory"] ?? "", $data["filename"] ?? "");
    if (!$ret["ok"])
        return (QuestionnaireTreeError($ret));
    add_log(EDITING_OPERATION, "Quiz Dabsic resource ".($ret["relative"] ?? "")." created");
    return (QuestionnaireTreeDisplay($id, $Dictionnary["QuizResourceFileCreated"] ?? "Fichier Dabsic créé."));
}

function QuestionnaireTreeUpload($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $directory = $data["directory"] ?? "";
    $upload = $_FILES["file"] ?? NULL;
    if ($upload == NULL)
        return (new ErrorResponse("MissingFile"));

    $files = [];
    if (is_array($upload["name"]))
        for ($i = 0; $i < count($upload["name"]); ++$i)
            $files[] = [
                "name" => $upload["name"][$i],
                "tmp" => $upload["tmp_name"][$i],
                "error" => $upload["error"][$i],
            ];
    else
        $files[] = ["name" => $upload["name"], "tmp" => $upload["tmp_name"], "error" => $upload["error"]];

    foreach ($files as $file)
    {
        if ($file["error"] != UPLOAD_ERR_OK)
            return (new ErrorResponse("InvalidFile"));
        $ret = quiz_resource_store_file((int)$id, $directory, $file["name"], $file["tmp"], NULL);
        if (!$ret["ok"])
            return (QuestionnaireTreeError($ret));
    }
    add_log(EDITING_OPERATION, count($files)." quiz resource file(s) uploaded for school #".(int)$id);
    return (QuestionnaireTreeDisplay($id, $Dictionnary["QuizResourceUploaded"] ?? "Ressource(s) importée(s)."));
}

function QuestionnaireTreeUploadFolder($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $base = $data["directory"] ?? "";
    $upload = $_FILES["file"] ?? NULL;
    $relative_paths = $data["relative_path"] ?? [];
    if ($upload == NULL || !is_array($upload["name"]))
        return (new ErrorResponse("MissingFile"));

    for ($i = 0; $i < count($upload["name"]); ++$i)
    {
        if (($upload["error"][$i] ?? UPLOAD_ERR_NO_FILE) != UPLOAD_ERR_OK)
            return (new ErrorResponse("InvalidFile"));
        $relative = trim(str_replace("\\", "/", (string)($relative_paths[$i] ?? $upload["name"][$i])), "/");
        $filename = basename($relative);
        if (quiz_resource_safe_name($filename) === NULL)
            return (new ErrorResponse("QuizResourceInvalidName"));
        $subdirectory = dirname($relative);
        if ($subdirectory == ".")
            $subdirectory = "";
        $ensured = quiz_resource_ensure_relative_directory((int)$id, $base, $subdirectory);
        if (!$ensured["ok"])
            return (QuestionnaireTreeError($ensured));
        $ret = quiz_resource_store_file((int)$id, $ensured["relative"], $filename, $upload["tmp_name"][$i], NULL);
        if (!$ret["ok"])
            return (QuestionnaireTreeError($ret));
    }
    add_log(EDITING_OPERATION, "Quiz resource folder uploaded for school #".(int)$id);
    return (QuestionnaireTreeDisplay($id, $Dictionnary["QuizResourceUploaded"] ?? "Ressource(s) importée(s)."));
}

function QuestionnaireTreeMove($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $ret = quiz_resource_move((int)$id, $data["source"] ?? "", $data["target"] ?? "");
    if (!$ret["ok"])
        return (QuestionnaireTreeError($ret));
    add_log(EDITING_OPERATION, "Quiz resource ".($data["source"] ?? "")." moved to ".($ret["relative"] ?? ""));
    return (QuestionnaireTreeDisplay($id, $Dictionnary["QuizResourceMoved"] ?? "Ressource déplacée."));
}

function QuestionnaireTreeDelete($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $ret = quiz_resource_delete((int)$id, $data["path"] ?? "");
    if (!$ret["ok"])
        return (QuestionnaireTreeError($ret));
    add_log(EDITING_OPERATION, "Quiz resource ".($data["path"] ?? "")." deleted");
    return (QuestionnaireTreeDisplay($id, $Dictionnary["QuizResourceDeleted"] ?? "Ressource supprimée."));
}

function QuestionnaireTreeDownload($id, $data, $method, $output, $module)
{
    $selection = $data["path"] ?? [];
    if (!is_array($selection))
        $selection = [$selection];
    $ret = quiz_resource_download((int)$id, $selection);
    if (!$ret["ok"])
        return (QuestionnaireTreeError($ret));
    return (new ValueResponse([
        "filename" => $ret["filename"],
        "content_type" => $ret["content_type"],
        "content" => $ret["content"],
    ]));
}


function QuestionnaireInviteExternal($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $ret = quiz_attempt_create_external(
        (int)$id,
        $data["recipient_name"] ?? "",
        $data["recipient_mail"] ?? "",
        $data["expires_days"] ?? 14,
        quiz_attempt_current_user_id(),
        !empty($data["send_mail"])
    );
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"] ?? "QuizInvitationCannotCreate", $ret["details"] ?? ""));
    add_log(CREATIVE_OPERATION, "External quiz attempt #".(int)$ret["id"]." created for quiz #".(int)$id);
    return (new ValueResponse([
        "msg" => !empty($ret["sent"])
            ? ($Dictionnary["QuizInvitationSent"] ?? "Invitation envoyée.")
            : (!empty($ret["mail_error"])
                ? ($Dictionnary["QuizInvitationCreatedMailFailed"] ?? "Le lien a été créé, mais le courriel n’a pas été envoyé.")
                : ($Dictionnary["QuizInvitationCreated"] ?? "Lien de questionnaire créé.")),
        "content" => $ret["url"],
        "url" => $ret["url"],
        "attempt_id" => (int)$ret["id"],
    ]));
}

function QuestionnaireInviteUser($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $ret = quiz_attempt_create_internal_assignment(
        (int)$id,
        $data["recipient"] ?? "",
        quiz_attempt_current_user_id(),
        $data["expires_days"] ?? 14,
        !empty($data["send_mail"])
    );
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"] ?? "QuizInvitationCannotCreate", $ret["details"] ?? ""));
    add_log(CREATIVE_OPERATION, "Direct quiz attempt #".(int)$ret["id"]." assigned for quiz #".(int)$id);
    return (new ValueResponse([
        "msg" => !empty($ret["sent"])
            ? ($Dictionnary["QuizInvitationSent"] ?? "Invitation envoyée.")
            : (!empty($ret["mail_error"])
                ? ($Dictionnary["QuizInvitationAssignedMailFailed"] ?? "Le questionnaire est attribué, mais le courriel n’a pas été envoyé.")
                : ($Dictionnary["QuizInvitationAssigned"] ?? "Questionnaire attribué à l’utilisateur.")),
        "content" => $ret["url"],
        "url" => $ret["url"],
        "attempt_id" => (int)$ret["id"],
    ]));
}

function QuestionnaireRevokeInvitation($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $attempt = quiz_attempt_get((int)($data["attempt_id"] ?? 0));
    if (!is_array($attempt) || (int)$attempt["id_quiz"] !== (int)$id)
        return (new ErrorResponse("QuizAttemptNotFound"));
    $ret = quiz_attempt_revoke_external((int)$attempt["id"]);
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"] ?? "QuizInvitationCannotRevoke", $ret["details"] ?? ""));
    return (new ValueResponse(["msg" => $Dictionnary["QuizInvitationRevoked"] ?? "Invitation révoquée."]));
}

function QuestionnaireRefreshInvitation($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $attempt = quiz_attempt_get((int)($data["attempt_id"] ?? 0));
    if (!is_array($attempt) || (int)$attempt["id_quiz"] !== (int)$id)
        return (new ErrorResponse("QuizAttemptNotFound"));
    $ret = quiz_attempt_refresh_invitation(
        (int)$attempt["id"],
        $data["expires_days"] ?? 14,
        !empty($data["send_mail"])
    );
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"] ?? "QuizInvitationCannotRefresh", $ret["details"] ?? ""));
    add_log(EDITING_OPERATION, "Quiz invitation attempt #".(int)$attempt["id"]." token rotated");
    return (new ValueResponse([
        "msg" => !empty($ret["sent"])
            ? ($Dictionnary["QuizInvitationRefreshedSent"] ?? "Invitation renouvelée et envoyée.")
            : (!empty($ret["mail_error"])
                ? ($Dictionnary["QuizInvitationRefreshedMailFailed"] ?? "Le lien a été renouvelé, mais le courriel n’a pas été envoyé.")
                : ($Dictionnary["QuizInvitationRefreshed"] ?? "Lien renouvelé.")),
        "content" => $ret["url"],
        "url" => $ret["url"],
        "attempt_id" => (int)$ret["id"],
    ]));
}

function QuestionnaireExportCsv($id, $data, $method, $output, $module)
{
    $ret = quiz_attempt_export_csv(
        (int)$id,
        (int)($data["snapshot_id"] ?? 0),
        $data["context_type"] ?? "manual"
    );
    if (!$ret["ok"])
        return (new ErrorResponse($ret["error"] ?? "QuizResultCannotExport", $ret["details"] ?? ""));
    return (new ValueResponse([
        "filename" => $ret["filename"],
        "content_type" => $ret["content_type"],
        "content" => $ret["content"],
    ]));
}

$Tab = [
    "GET" => [
        "export_csv" => ["QuestionnaireAttemptAccess", "QuestionnaireExportCsv"],
    ],
    "POST" => [
        "source" => [
            "logged_in",
            "QuestionnaireSourceSave",
        ],
        "tree_directory" => ["QuestionnaireTreeAccess", "QuestionnaireTreeCreateDirectory"],
        "tree_file" => ["QuestionnaireTreeAccess", "QuestionnaireTreeCreateFile"],
        "tree_upload" => ["QuestionnaireTreeAccess", "QuestionnaireTreeUpload"],
        "tree_upload_folder" => ["QuestionnaireTreeAccess", "QuestionnaireTreeUploadFolder"],
        "tree_download" => ["QuestionnaireTreeAccess", "QuestionnaireTreeDownload"],
        "attempt" => ["QuestionnaireAttemptAccess", "QuestionnaireAttemptStart"],
        "invite_external" => ["QuestionnaireAttemptAccess", "QuestionnaireInviteExternal"],
        "invite_user" => ["QuestionnaireAttemptAccess", "QuestionnaireInviteUser"],
        "revoke_invitation" => ["QuestionnaireAttemptAccess", "QuestionnaireRevokeInvitation"],
        "refresh_invitation" => ["QuestionnaireAttemptAccess", "QuestionnaireRefreshInvitation"],
    ],
    "PUT" => [
        "tree_move" => ["QuestionnaireTreeAccess", "QuestionnaireTreeMove"],
    ],
    "DELETE" => [
        "tree_delete" => ["QuestionnaireTreeAccess", "QuestionnaireTreeDelete"],
    ],
];
