<?php

require_once ("./tools/dabsic_editor.php");
require_once ("./tools/dabsic_form.php");
require_once ("./tools/registration_form.php");

function SaveDabsicFile($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $result = dabsic_editor_save_file(
        $data["file"] ?? "",
        $data["content"] ?? NULL,
        $data["hash"] ?? ""
    );
    if (!$result["ok"])
        return (new ErrorResponse(
            $result["error"],
            $result["details"] ?? ""
        ));

    add_log(EDITING_OPERATION, "Dabsic file ".$result["relative"]." edited");
    return (new ValueResponse([
        "msg" => $Dictionnary["DabsicEditorSaved"],
        "hash" => $result["hash"],
        "size" => $result["size"],
        "mtime" => $result["mtime"]
    ]));
}

function SaveDabsicForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $values = $data["values"] ?? NULL;
    if (is_string($values))
        $values = json_decode($values, true);

    $result = dabsic_form_save(
        $data["reference"] ?? "",
        $data["output"] ?? "",
        $values,
        is_string($data["overrides"] ?? NULL) ? json_decode($data["overrides"], true) : ($data["overrides"] ?? []),
        $data["reference_hash"] ?? "",
        $data["output_hash"] ?? "",
        $data["output_exists"] ?? "0",
        $data["overrides_hash"] ?? hash("sha256", ""),
        $data["overrides_exists"] ?? "0",
        $data["mode"] ?? "dabsic",
        $data["chain"] ?? ""
    );
    if (!$result["ok"])
        return (new ErrorResponse(
            $result["error"],
            $result["details"] ?? ""
        ));

    add_log(EDITING_OPERATION,
        "Dabsic form ".$result["reference"]." saved into ".$result["output"]);
    return (new ValueResponse([
        "msg" => $Dictionnary["DabsicFormSaved"],
        "hash" => $result["hash"],
        "exists" => true,
        "overrides_hash" => $result["overrides_hash"],
        "overrides_exists" => $result["overrides_exists"],
        "mtime" => $result["mtime"]
    ]));
}

function SaveRegistrationForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $values = registration_form_decode_json($data["values"] ?? "{}", []);
    $deleted = registration_form_decode_json($data["delete_signatures"] ?? "[]", []);
    $result = registration_form_save_invitation($data["token"] ?? "", $values, false, $deleted);
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));
    return (new ValueResponse([
        "msg" => $Dictionnary["RegistrationFormDraftSaved"] ?? "Brouillon sauvegardé.",
        "completed" => false,
        "refresh" => (bool)($result["refresh"] ?? false)
    ]));
}

function FinalizeRegistrationForm($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    $values = registration_form_decode_json($data["values"] ?? "{}", []);
    $deleted = registration_form_decode_json($data["delete_signatures"] ?? "[]", []);
    $consent = in_array(strtolower(trim((string)($data["signature_consent"] ?? ""))), ["1", "true", "yes", "oui", "on"], true);
    $result = registration_form_save_invitation($data["token"] ?? "", $values, true, $deleted, $consent);
    if (!$result["ok"])
        return (new ErrorResponse($result["error"], $result["details"] ?? ""));
    $completed = (bool)($result["completed"] ?? false);
    return (new ValueResponse([
        "msg" => $completed
            ? ($Dictionnary["RegistrationFormFinalized"] ?? "Dossier validé.")
            : ($Dictionnary["RegistrationFormNewFields"] ?? "De nouvelles informations sont nécessaires."),
        "completed" => $completed,
        "refresh" => (bool)($result["refresh"] ?? false),
        "details" => $result["details"] ?? ""
    ]));
}

$Tab = [
    "POST" => [
        "save" => [
            "only_admin",
            "SaveDabsicFile"
        ],
        "form" => [
            "only_admin",
            "SaveDabsicForm"
        ],
        "registration" => [
            "everybody",
            "SaveRegistrationForm"
        ],
        "registration_finalize" => [
            "everybody",
            "FinalizeRegistrationForm"
        ]
    ]
];
