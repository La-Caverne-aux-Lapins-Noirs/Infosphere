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


function EnterpriseContactsResponse($id, $msg)
{
    global $Dictionnary;

    $enterprise = fetch_enterprises($id);
    if (!is_array($enterprise) || !count($enterprise))
        return (new ErrorResponse("CannotRetrieveContent"));
    ob_start();
    require ("./pages/enterprise/contacts_content.php");
    return (new ValueResponse([
        "msg" => $msg,
        "content" => ob_get_clean(),
    ]));
}

function AddEnterpriseContact($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();
    if (($ret = set_enterprise_contact($id, $data))->is_error())
        return ($ret);
    return (EnterpriseContactsResponse($id, $Dictionnary["Edited"]));
}

function EditEnterpriseContact($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;

    if ($id == -1 || $SUBID == -1)
        bad_request();
    if (($ret = edit_enterprise_contact($id, $SUBID, $data))->is_error())
        return ($ret);
    return (EnterpriseContactsResponse($id, $Dictionnary["Edited"]));
}

function DeleteEnterpriseContact($id, $data, $method, $output, $module)
{
    global $SUBID;
    global $Dictionnary;

    if ($id == -1 || $SUBID == -1)
        bad_request();
    if (($ret = delete_enterprise_contact($id, $SUBID))->is_error())
        return ($ret);
    return (EnterpriseContactsResponse($id, $Dictionnary["Deleted"]));
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
            ["only_admin", "am_i_director", "am_i_commercial"],
            "DisplayEnterprise"
        ],
        "dabsic" => [
            ["only_admin", "am_i_director", "am_i_commercial"],
            "ExportEnterpriseDabsic"
        ],
    ],
    "POST" => [
        "" => [
            ["only_admin", "am_i_director", "am_i_commercial"],
            "AddEnterprise"
        ],
        "contact" => [
            ["only_admin", "am_i_director", "am_i_commercial"],
            "AddEnterpriseContact"
        ],
    ],
    "PUT" => [
        "" => [
            ["only_admin", "am_i_director", "am_i_commercial"],
            "EditEnterprise"
        ],
        "contact" => [
            ["only_admin", "am_i_director", "am_i_commercial"],
            "EditEnterpriseContact"
        ],
    ],
    "DELETE" => [
        "" => [
            ["only_admin", "am_i_director", "am_i_commercial"],
            "DeleteEnterprise"
        ],
        "contact" => [
            ["only_admin", "am_i_director", "am_i_commercial"],
            "DeleteEnterpriseContact"
        ],
    ],
];
