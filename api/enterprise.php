<?php

function DisplayEnterprise($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $enterprises = fetch_enterprises($id);
    if ($output == "json")
        return (new ValueResponse(["content" => json_encode($enterprises, JSON_UNESCAPED_SLASHES)]));
    ob_start();
    require ("./pages/enterprise/list.php");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function AddEnterprise($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id != -1 || !isset($data["enterprises"]))
        bad_request();
    foreach ($data["enterprises"] as $enterprise)
        if (($ret = add_enterprise($enterprise))->is_error())
            return ($ret);
    $ret = DisplayEnterprise(-1, [], "GET", $output, $module);
    $ret->value = array_merge(["msg" => $Dictionnary["Added"]], $ret->value);
    return ($ret);
}

function EditEnterprise($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    if (($ret = edit_enterprise($id, $data))->is_error())
        return ($ret);
    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
}

function DeleteEnterprise($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    if (($ret = delete_enterprise($id))->is_error())
        return ($ret);
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}

function AddEnterpriseContact($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    if (($ret = set_enterprise_contact($id, $data))->is_error())
        return ($ret);
    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
}

function EditEnterpriseContact($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;

    if ($id == -1 || $SUBID == -1)
        bad_request();
    if (($ret = edit_enterprise_contact($id, $SUBID, $data))->is_error())
        return ($ret);
    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
}

function DeleteEnterpriseContact($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;

    if ($id == -1 || $SUBID == -1)
        bad_request();
    if (($ret = delete_enterprise_contact($id, $SUBID))->is_error())
        return ($ret);
    return (new ValueResponse(["msg" => $Dictionnary["Deleted"]]));
}

function ExportEnterpriseDabsic($id, $data, $method, $output, $module)
{
    if ($id == -1)
        bad_request();
    $enterprise = fetch_enterprises($id);
    if (!is_array($enterprise) || !count($enterprise))
        return (new ErrorResponse("CannotRetrieveContent"));
    if (($ret = refresh_enterprise($enterprise))->is_error())
        return ($ret);
    $file = organization_dir($enterprise["codename"])."description.dab";
    if (!file_exists($file))
        return (new ErrorResponse("MissingFile", $file));
    return (new ValueResponse([
        "filename" => "description.dab",
        "content" => file_get_contents($file),
    ]));
}

$Tab = [
    "GET" => [
        "" => [
            ["is_admin", "is_director", "is_commercial"],
            "DisplayEnterprise"
        ],
        "dabsic" => [
            ["is_admin", "is_director", "is_commercial"],
            "ExportEnterpriseDabsic"
        ],
    ],
    "POST" => [
        "" => [
            ["is_admin", "is_director", "is_commercial"],
            "AddEnterprise"
        ],
        "contact" => [
            ["is_admin", "is_director", "is_commercial"],
            "AddEnterpriseContact"
        ],
    ],
    "PUT" => [
        "" => [
            ["is_admin", "is_director", "is_commercial"],
            "EditEnterprise"
        ],
        "contact" => [
            ["is_admin", "is_director", "is_commercial"],
            "EditEnterpriseContact"
        ],
    ],
    "DELETE" => [
        "" => [
            ["is_admin", "is_director", "is_commercial"],
            "DeleteEnterprise"
        ],
        "contact" => [
            ["is_admin", "is_director", "is_commercial"],
            "DeleteEnterpriseContact"
        ],
    ],
];
