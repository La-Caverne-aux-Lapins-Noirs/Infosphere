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

function RenderConfigurationOperations($id, $data, $method, $output, $module)
{
    return (new ValueResponse([
        "content" => configuration_render_panel_content("operations_panel_content.php", $data)
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
        "operations" => [
            "only_admin",
            "RenderConfigurationOperations",
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
        "operations" => [
            "only_admin",
            "RenderConfigurationOperations",
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
