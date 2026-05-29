<?php

require_once ("./pages/prospecting/campaign_tools.php");

function DisplayCampaign($id, $data, $method, $output, $module)
{
    if ($output == "json")
        return (new ValueResponse(["content" => json_encode(campaign_fetch_all(), JSON_UNESCAPED_SLASHES)]));

    ob_start();
    require ("./pages/prospecting/campaign_list.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function ReadCampaignPayload($data, $required = true)
{
    global $Database;

    $payload = [];

    foreach (["name", "start_date", "end_date"] as $field)
        if ($required && (!isset($data[$field]) || trim((string)$data[$field]) == ""))
            bad_request();

    if (isset($data["name"]))
    {
        $payload["name"] = trim($data["name"]);
        if ($payload["name"] == "")
            bad_request();
    }
    if (isset($data["start_date"]))
    {
        if (!campaign_date_is_valid($data["start_date"]))
            bad_request();
        $payload["start_date"] = $data["start_date"];
    }
    if (isset($data["end_date"]))
    {
        if (!campaign_date_is_valid($data["end_date"]))
            bad_request();
        $payload["end_date"] = $data["end_date"];
    }
    if (isset($data["description"]))
        $payload["description"] = trim($data["description"]);

    if (isset($payload["start_date"]) && isset($payload["end_date"])
        && campaign_date_cmp($payload["start_date"], $payload["end_date"]) > 0)
        bad_request();

    return ($payload);
}

function AddCampaign($id, $data, $method, $output, $module)
{
    global $Database;
    global $Dictionnary;

    if ($id != -1)
        bad_request();

    $payload = ReadCampaignPayload($data, true);
    $name = $Database->real_escape_string($payload["name"]);
    $start = $Database->real_escape_string($payload["start_date"]);
    $end = $Database->real_escape_string($payload["end_date"]);
    $description = $Database->real_escape_string($payload["description"] ?? "");

    if ($Database->query("
        INSERT INTO campaign (name, start_date, end_date, description)
        VALUES ('$name', '$start', '$end', '$description')
    ") == NULL)
        return (new ErrorResponse("CannotRegister"));

    $ret = DisplayCampaign($id, $data, "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Added"];
    return ($ret);
}

function EditCampaign($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    if (campaign_fetch_one($id) == NULL)
        return (new ErrorResponse("NotFound"));

    $payload = ReadCampaignPayload($data, false);
    if (isset($payload["start_date"]) && isset($payload["end_date"])
        && campaign_date_cmp($payload["start_date"], $payload["end_date"]) > 0)
        bad_request();

    if (count($payload) && db_update_one("campaign", $id, $payload) == NULL)
        return (new ErrorResponse("CannotUpdate"));

    $ret = DisplayCampaign(-1, $data, "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Edited"];
    return ($ret);
}

function DeleteCampaign($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    if (db_update_one("campaign", $id, ["deleted" => dbnow()]) == NULL)
        return (new ErrorResponse("CannotUpdate"));

    $ret = DisplayCampaign(-1, $data, "GET", $output, $module);
    $ret->value["msg"] = $Dictionnary["Deleted"];
    return ($ret);
}

$Tab = [
    "GET" => [
        "" => [
            "is_commercial,is_secretariat",
            "DisplayCampaign",
        ],
    ],
    "POST" => [
        "" => [
            "is_commercial,is_secretariat",
            "AddCampaign",
        ],
    ],
    "PUT" => [
        "" => [
            "is_commercial,is_secretariat",
            "EditCampaign",
        ],
    ],
    "DELETE" => [
        "" => [
            "is_commercial,is_secretariat",
            "DeleteCampaign",
        ],
    ],
];
