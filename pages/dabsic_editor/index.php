<?php

require_once (__DIR__."/../../tools/questionnaire.php");
require_once (__DIR__."/../../tools/correction_catalog.php");
require_once (__DIR__."/../../tools/dabsic_editor_component.php");

$dabsic_editor_requested_file = $_GET["file"] ?? "";
if (!dabsic_editor_user_can_access($dabsic_editor_requested_file, true))
{
    if (!headers_sent())
        http_response_code(404);
    die();
}

dabsic_editor_component($dabsic_editor_requested_file, [
    "embedded" => false,
    "show_title" => true,
    "show_path" => true,
    "autofocus" => true,
]);
