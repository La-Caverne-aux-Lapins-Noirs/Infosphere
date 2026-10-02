<?php

require_once ("./pages/configuration/model.php");
require_once ("./pages/configuration/fetch_log.php");
require_once ("./pages/configuration/usual_operation.php");

function EditProperty($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $cnt = 0;
    unset($data["action"]);
    foreach ($data as $codename => $value)
    {
        if (($field = resolve_codename("configuration", $codename, "codename", true))->is_error())
            bad_request();
        $field = $field->value;
        if (configuration_row_readonly($field))
            continue ;
        if (configuration_row_secured($field))
        {
            if (trim((string)$value) == "")
                continue ;
            $value = secure_data($value);
        }
        db_update_one("configuration", $field["id"], ["value" => $value]);
        $cnt += 1;
    }
    if ($cnt == 0)
        return (new ErrorResponse("NothingToBeDone"));
    return (new ValueResponse([
        "msg" => $Dictionnary["Edited"],
    ]));
}

function RenderConfigurationStats($id, $data, $method, $output, $module)
{
    return (new ValueResponse([
        "content" => configuration_render_panel_content("stats_alerts_content.php", $data)
    ]));
}

function RenderConfigurationLogs($id, $data, $method, $output, $module)
{
    return (new ValueResponse([
        "content" => configuration_render_panel_content("logs_panel_content.php", $data)
    ]));
}

function RenderConfigurationFields($id, $data, $method, $output, $module)
{
    return (new ValueResponse([
        "content" => configuration_render_panel_content("configuration_panel_content.php", $data)
    ]));
}


function RenderConfigurationPersocBlacklist($id, $data, $method, $output, $module)
{
    return (new ValueResponse([
        "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", $data)
    ]));
}

function SaveConfigurationPersocBlacklist($id, $data, $method, $output, $module)
{
    $parsed = persoc_deadlist_parse_text($data["deadlist"] ?? "");
    if (count($parsed["invalid"]))
    {
        $errors = [];
        foreach (array_slice($parsed["invalid"], 0, 5) as $invalid)
            $errors[] = "ligne ".((int)$invalid["line"])." : ".$invalid["value"];
        if (count($parsed["invalid"]) > 5)
            $errors[] = "+".(count($parsed["invalid"]) - 5)." autre(s)";
        return (new ValueResponse([
            "msg" => "Blacklist non enregistrée : entrée(s) invalide(s) — ".implode(" ; ", $errors),
            "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", array_merge($data, [
                "deadlist_input" => $data["deadlist"] ?? "",
            ])),
        ]));
    }

    $storage_error = NULL;
    if (!persoc_deadlist_save_entries($parsed["entries"], $storage_error))
        return (new ValueResponse([
            "msg" => "Impossible d'enregistrer la blacklist Persoc : ".($storage_error ?? "erreur inconnue").".",
            "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", array_merge($data, [
                "deadlist_input" => $data["deadlist"] ?? "",
            ])),
        ]));

    add_log(EDITING_OPERATION, "Persoc deadlist saved in configuration: ".count($parsed["entries"])." entries", 1, true);
    $sync = persoc_deadlist_push($parsed["entries"]);
    if (!$sync["ok"])
    {
        add_log(REPORT, "Persoc deadlist synchronization failed: ".$sync["error"], 1, true);
        $msg = "Blacklist enregistrée dans la configuration de l'Infosphère, mais la synchronisation vers Distrans a échoué : ".$sync["error"].".";
    }
    else
    {
        add_log(EDITING_OPERATION, "Persoc deadlist synchronized to Distrans", 1, true);
        $msg = "Blacklist Persoc enregistrée et synchronisée vers Distrans.";
    }

    return (new ValueResponse([
        "msg" => $msg,
        "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", []),
    ]));
}

function SyncConfigurationPersocBlacklist($id, $data, $method, $output, $module)
{
    if (!persoc_deadlist_storage_initialized())
        return (new ValueResponse([
            "msg" => "La blacklist Persoc n'est pas encore initialisée dans la configuration. Enregistre-la ou importe d'abord celle de Distrans.",
            "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", $data),
        ]));

    $entries = persoc_deadlist_local_entries();
    $sync = persoc_deadlist_push($entries);
    if (!$sync["ok"])
    {
        add_log(REPORT, "Persoc deadlist synchronization failed: ".$sync["error"], 1, true);
        $msg = "Synchronisation Persoc/Distrans impossible : ".$sync["error"].".";
    }
    else
    {
        add_log(EDITING_OPERATION, "Persoc deadlist synchronized to Distrans", 1, true);
        $msg = "Blacklist Persoc resynchronisée vers Distrans.";
    }
    return (new ValueResponse([
        "msg" => $msg,
        "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", $data),
    ]));
}

function ImportConfigurationPersocBlacklist($id, $data, $method, $output, $module)
{
    $remote = persoc_deadlist_remote_state();
    if (!$remote["ok"])
    {
        add_log(REPORT, "Persoc deadlist import failed: ".$remote["error"], 1, true);
        return (new ValueResponse([
            "msg" => "Import depuis Distrans impossible : ".$remote["error"].".",
            "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", $data),
        ]));
    }

    $storage_error = NULL;
    if (!persoc_deadlist_save_entries($remote["entries"], $storage_error))
        return (new ValueResponse([
            "msg" => "Impossible d'enregistrer dans la configuration de l'Infosphère la blacklist reçue de Distrans : ".($storage_error ?? "erreur inconnue").".",
            "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", $data),
        ]));

    add_log(EDITING_OPERATION, "Persoc deadlist imported from Distrans: ".count($remote["entries"])." entries", 1, true);
    return (new ValueResponse([
        "msg" => "Blacklist Persoc importée depuis Distrans (".count($remote["entries"])." entrée(s)).",
        "content" => configuration_render_panel_content("persoc_blacklist_panel_content.php", []),
    ]));
}

function RenderConfigurationOperations($id, $data, $method, $output, $module)
{
    return (new ValueResponse([
        "content" => configuration_render_panel_content("operations_panel_content.php", $data)
    ]));
}

function RenderConfigurationQuotes($id, $data, $method, $output, $module)
{
    return (new ValueResponse([
        "content" => configuration_render_panel_content("quotes_panel_content.php", $data)
    ]));
}

function SaveConfigurationQuote($id, $data, $method, $output, $module)
{
    $result = famous_quote_save($data);
    return (new ValueResponse([
        "msg" => $result["msg"],
        "content" => configuration_render_panel_content("quotes_panel_content.php", $data)
    ]));
}

function DeleteConfigurationQuote($id, $data, $method, $output, $module)
{
    if (isset($data["quote"]))
        $id = $data["quote"];
    else if (isset($data["id"]))
        $id = $data["id"];
    $result = famous_quote_delete($id);
    return (new ValueResponse([
        "msg" => $result["msg"],
        "content" => configuration_render_panel_content("quotes_panel_content.php", $data)
    ]));
}

function ExecuteConfigurationOperation($id, $data, $method, $output, $module)
{
    $execution = configuration_execute_operation_by_id($data["operation"] ?? -1, $outfile);
    $result = $execution["result"];
    $operation = $execution["operation"];
    return (new ValueResponse([
        "content" => configuration_render_panel_content("command_result_content.php", [
            "result" => $result,
            "outfile" => $outfile,
            "operation" => $operation
        ])
    ]));
}

$Tab = [
    "GET" => [
        "stats" => [
            "only_admin",
            "RenderConfigurationStats",
        ],
        "logs" => [
            "only_admin",
            "RenderConfigurationLogs",
        ],
        "fields" => [
            "only_admin",
            "RenderConfigurationFields",
        ],
        "persoc_blacklist" => [
            "only_admin",
            "RenderConfigurationPersocBlacklist",
        ],
        "operations" => [
            "only_admin",
            "RenderConfigurationOperations",
        ],
        "quotes" => [
            "only_admin",
            "RenderConfigurationQuotes",
        ],
    ],
    "POST" => [
        "stats" => [
            "only_admin",
            "RenderConfigurationStats",
        ],
        "logs" => [
            "only_admin",
            "RenderConfigurationLogs",
        ],
        "fields" => [
            "only_admin",
            "RenderConfigurationFields",
        ],
        "persoc_blacklist" => [
            "only_admin",
            "RenderConfigurationPersocBlacklist",
        ],
        "persoc_blacklist_save" => [
            "only_admin",
            "SaveConfigurationPersocBlacklist",
        ],
        "persoc_blacklist_sync" => [
            "only_admin",
            "SyncConfigurationPersocBlacklist",
        ],
        "persoc_blacklist_import" => [
            "only_admin",
            "ImportConfigurationPersocBlacklist",
        ],
        "operations" => [
            "only_admin",
            "RenderConfigurationOperations",
        ],
        "quotes" => [
            "only_admin",
            "RenderConfigurationQuotes",
        ],
        "quote" => [
            "only_admin",
            "SaveConfigurationQuote",
        ],
        "quote_delete" => [
            "only_admin",
            "DeleteConfigurationQuote",
        ],
        "operation" => [
            "only_admin",
            "ExecuteConfigurationOperation",
        ],
    ],
    "PUT" => [
        "" => [
            "only_admin",
            "EditProperty",
        ],
    ],
];
