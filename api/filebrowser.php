<?php

function ExportFileBrowserSelection($id, $data, $method, $output, $module)
{
    $page = $_GET["page"] ?? "";
    $context_id = $_GET["id"] ?? -1;
    $type = $_GET["type"] ?? "";
    $language = $_GET["language"] ?? "";
    $encoded = $_GET["selection"] ?? "";

    if (!is_string($encoded) || $encoded === "")
        bad_request();
    $encoded = strtr($encoded, "-_", "+/");
    $padding = strlen($encoded) % 4;
    if ($padding)
        $encoded .= str_repeat("=", 4 - $padding);
    $json = base64_decode($encoded, true);
    $selection = $json === false ? NULL : json_decode($json, true);
    if (!is_array($selection))
        bad_request();

    return (path_browser_transfer_export($page, $context_id, $type, $language, $selection));
}

$Tab = [
    "GET" => [
        "export" => [
            "logged_in",
            "ExportFileBrowserSelection"
        ]
    ]
];
