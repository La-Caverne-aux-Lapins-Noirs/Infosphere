<?php

require_once (__DIR__."/document_context.php");
require_once (__DIR__."/dabsic_form.php");

function prospect_convocation_specs()
{
    return ([
        "motivation-theory" => [
            "model" => "res/docs/fr/convocation_entretien_motivation_test_theorique.dab",
            "output" => "convocation_entretien_motivation_test_theorique.dab",
            "label" => "Convocation - entretien de motivation et test théorique",
        ],
        "practical" => [
            "model" => "res/docs/fr/convocation_epreuve_pratique.dab",
            "output" => "convocation_epreuve_pratique.dab",
            "label" => "Convocation - épreuve pratique",
        ],
    ]);
}

function prospect_convocation_spec($kind)
{
    $specs = prospect_convocation_specs();
    $kind = trim((string)$kind);
    return ($specs[$kind] ?? NULL);
}

function prospect_convocation_output_key($id_prospect, $kind)
{
    return ("prospect-convocation:".trim((string)$kind).":".(int)$id_prospect);
}

function prospect_convocation_start($id_prospect, $kind)
{
    $id_prospect = (int)$id_prospect;
    $spec = prospect_convocation_spec($kind);
    if ($id_prospect <= 0 || $spec == NULL)
        return (new ErrorResponse("InvalidParameter", "prospect / convocation"));

    $prospect = document_context_user($id_prospect);
    if (!is_array($prospect) || ($prospect["profile_status"] ?? "") != "prospect")
        return (new ErrorResponse("UserNotFound"));

    $output_key = prospect_convocation_output_key($id_prospect, $kind);
    if (!dabsic_form_user_can_access_output($output_key))
        return (new ErrorResponse("PermissionDenied"));

    $root = dabsic_editor_project_root();
    if ($root === false || !is_file($root.DIRECTORY_SEPARATOR.$spec["model"]))
        return (new ErrorResponse("MissingFile", $spec["model"]));

    // Force creation of the protected prospect-side workspace now, so an
    // invalid relation or permissions problem is reported before opening the
    // editor in a new tab.
    $output = dabsic_form_resolve_output($output_key, true);
    if (!$output["ok"])
        return (new ErrorResponse($output["error"], $output["details"] ?? ""));

    $bindings = ["Student" => (string)$id_prospect];
    $url = "index.php?p=DabsicFormMenu".
        "&file=".rawurlencode($spec["model"]).
        "&output=".rawurlencode($output_key).
        "&mode=docbuilder".
        "&context_bindings=".rawurlencode(document_context_bindings_json($bindings));

    return (new ValueResponse([
        "msg" => "Convocation ouverte.",
        "content" => $url,
    ]));
}
