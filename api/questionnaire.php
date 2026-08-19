<?php

require_once ("./tools/questionnaire.php");
require_once ("./tools/quiz_resources.php");

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

$Tab = [
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
    ],
    "PUT" => [
        "tree_move" => ["QuestionnaireTreeAccess", "QuestionnaireTreeMove"],
    ],
    "DELETE" => [
        "tree_delete" => ["QuestionnaireTreeAccess", "QuestionnaireTreeDelete"],
    ],
];
