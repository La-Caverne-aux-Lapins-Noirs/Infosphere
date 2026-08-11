<?php
require_once ("tools/correction_catalog.php");

function correction_is_admin($resource_id = -1)
{
    return (is_admin());
}

function CorrectionErrorResponse($error)
{
    $error = (string)$error;
    $separator = strpos($error, ": ");
    if ($separator === false)
        return (new ErrorResponse($error));
    return (new ErrorResponse(
        substr($error, 0, $separator),
        substr($error, $separator + 2)
    ));
}

function CorrectionDisplay($id, $data, $method, $output, $module)
{
    global $CorrectionSyncStatus;
    if ($id != -1)
        bad_request();
    ob_start();
    require ("./pages/correction/catalog.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function CorrectionCreate($id, $data, $method, $output, $module)
{
    global $Database;
    if ($id != -1)
        bad_request();
    $action = $data["action"] ?? "upload";
    if ($action == "category")
    {
        $name = trim((string)($data["name"] ?? ""));
        $codename = correction_safe_codename($data["codename"] ?? $name);
        $parent = isset($data["id_parent"]) && $data["id_parent"] !== "" ? (int)$data["id_parent"] : "NULL";
        if ($name == "" || $codename == "")
            return (new ErrorResponse("InvalidCategory"));
        if ($parent !== "NULL" && correction_get_category($parent) == NULL)
            return (new ErrorResponse("UnknownCategory"));
        $ename = $Database->real_escape_string($name);
        $ecode = $Database->real_escape_string($codename);
        $parent_condition = $parent === "NULL" ? "id_parent IS NULL" : "id_parent = $parent";
        $res = $Database->query(
            "SELECT id, deleted FROM correction_category " .
            "WHERE $parent_condition AND codename = '$ecode' ORDER BY deleted IS NULL DESC LIMIT 1"
        );
        $existing = $res == NULL ? NULL : $res->fetch_assoc();
        if ($existing != NULL && $existing["deleted"] === NULL)
            return (new ErrorResponse("CategoryAlreadyExists"));
        if ($existing != NULL)
        {
            $category_id = (int)$existing["id"];
            if ($Database->query("UPDATE correction_category SET name = '$ename', deleted = NULL WHERE id = $category_id") === false)
                return (new ErrorResponse("CannotCreateDirectory"));
        }
        else if ($Database->query("INSERT INTO correction_category (id_parent, codename, name) VALUES ($parent, '$ecode', '$ename')") === false)
            return (new ErrorResponse("CategoryAlreadyExists"));
    }
    else if ($action == "upload")
    {
        $category = (int)($data["id_category"] ?? 1);
        $files = [];
        if (isset($_FILES["file"]))
        {
            $upload = $_FILES["file"];
            if (is_array($upload["name"]))
                for ($i = 0; $i < count($upload["name"]); ++$i)
                    $files[] = ["name" => $upload["name"][$i], "tmp" => $upload["tmp_name"][$i], "error" => $upload["error"][$i], "content" => NULL];
            else
                $files[] = ["name" => $upload["name"], "tmp" => $upload["tmp_name"], "error" => $upload["error"], "content" => NULL];
        }
        else if (isset($data["file"]) && is_array($data["file"]))
            foreach ($data["file"] as $file)
                $files[] = ["name" => $file["name"], "tmp" => NULL, "error" => UPLOAD_ERR_OK, "content" => base64_decode($file["content"], true)];
        if (!count($files))
            return (new ErrorResponse("MissingFile"));
        foreach ($files as $file)
        {
            if ($file["error"] != UPLOAD_ERR_OK || $file["content"] === false)
                return (new ErrorResponse("InvalidFile"));
            $ret = correction_store_asset($category, $file["name"], $file["tmp"], $file["content"]);
            if (!$ret["ok"])
                return (CorrectionErrorResponse($ret["error"]));
        }
    }
    else if ($action == "upload_tree")
    {
        $base_category = (int)($data["id_category"] ?? 1);
        $upload = $_FILES["file"] ?? NULL;
        $relative_paths = $data["relative_path"] ?? [];
        if ($upload == NULL || !is_array($upload["name"]))
            return (new ErrorResponse("MissingFile"));
        for ($i = 0; $i < count($upload["name"]); ++$i)
        {
            $relative = str_replace('\\', '/', (string)($relative_paths[$i] ?? $upload["name"][$i]));
            $relative = ltrim($relative, '/');
            $filename = basename($relative);
            if (in_array(strtolower($filename), ['.htaccess', '.user.ini'], true))
                continue;
            $directory = dirname($relative);
            if ($directory == '.')
                $directory = '';
            $category = correction_ensure_category_path($base_category, $directory);
            if (!$category["ok"])
                return (CorrectionErrorResponse($category["error"]));
            $ret = correction_store_asset($category["id"], $filename, $upload["tmp_name"][$i], NULL);
            if (!$ret["ok"])
                return (CorrectionErrorResponse($ret["error"]));
        }
    }
    else if ($action == "create_file")
    {
        $ret = correction_create_dabsic_file((int)($data["id_category"] ?? 0), $data["filename"] ?? "");
        if (!$ret["ok"])
            return (CorrectionErrorResponse($ret["error"]));
    }
    else if ($action == "inventory")
    {
        global $CorrectionSyncStatus;
        $CorrectionSyncStatus = correction_compare_inventory();
    }
    else if ($action == "synchronize")
    {
        global $CorrectionSyncStatus;
        $CorrectionSyncStatus = correction_synchronize();
        if ($CorrectionSyncStatus["ok"] ?? false)
        {
            $operation = $CorrectionSyncStatus;
            $comparison = correction_compare_inventory();
            if ($comparison["ok"] ?? false)
                $CorrectionSyncStatus = array_merge($comparison, [
                    "synchronized" => !empty($operation["synchronized"]),
                    "up_to_date" => !empty($operation["up_to_date"]),
                    "sent" => $operation["sent"] ?? [],
                    "reused" => $operation["reused"] ?? 0,
                    "validation" => $operation["validation"] ?? [],
                    "previous_available" => $operation["previous_available"] ?? false
                ]);
        }
    }
    else if ($action == "rollback")
    {
        global $CorrectionSyncStatus;
        $CorrectionSyncStatus = correction_rollback();
    }
    else
        bad_request();
    $response = CorrectionDisplay(-1, [], "GET", $output, $module);
    $response->value["msg"] = "Saved";
    return ($response);
}


function CorrectionUpdate($id, $data, $method, $output, $module)
{
    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $action = $data["action"] ?? "move";
    if ($action == "move")
        $ret = correction_move_asset_to_category($id, (int)($data["id_category"] ?? 0));
    else if ($action == "move_node")
    {
        $type = $data["node_type"] ?? "asset";
        $target = (int)($data["target_category"] ?? 0);
        if ($type == "asset")
            $ret = correction_move_asset_to_category($id, $target);
        else if ($type == "category")
            $ret = correction_move_category($id, $target);
        else
            bad_request();
    }
    else
        bad_request();
    if (!$ret["ok"])
        return (CorrectionErrorResponse($ret["error"]));
    $response = CorrectionDisplay(-1, [], "GET", $output, $module);
    $response->value["msg"] = "Moved";
    return ($response);
}

function CorrectionDelete($id, $data, $method, $output, $module)
{
    global $Database;
    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $action = $data["action"] ?? "asset";
    if ($action == "category")
    {
        $ret = correction_delete_category($id);
        if (!$ret["ok"])
            return (CorrectionErrorResponse($ret["error"]));
    }
    else if ($action == "asset")
    {
        $res = $Database->query("SELECT * FROM correction_asset WHERE id = $id AND deleted IS NULL");
        $asset = $res == NULL ? NULL : $res->fetch_assoc();
        if ($asset == NULL)
            not_found();
        $path = correction_root_dir()."/".$asset["relative_path"];
        if (file_exists($path) && !@unlink($path))
            return (new ErrorResponse("CannotDeleteFile"));
        $Database->query("UPDATE correction_asset SET deleted = NOW() WHERE id = $id");
        $Database->query("DELETE FROM correction_symbol WHERE id_asset = $id");
    }
    else
        bad_request();
    $response = CorrectionDisplay(-1, [], "GET", $output, $module);
    $response->value["msg"] = "Deleted";
    return ($response);
}

function CorrectionDownload($id, $data, $method, $output, $module)
{
    global $Database;
    $action = $data["action"] ?? "";
    if ($action == "download_asset")
    {
        $id = (int)$id;
        $res = $Database->query("SELECT * FROM correction_asset WHERE id = $id AND deleted IS NULL");
        $asset = $res == NULL ? NULL : $res->fetch_assoc();
        if ($asset == NULL)
            not_found();
        $path = correction_root_dir()."/".$asset["relative_path"];
        if (!is_file($path))
            not_found();
        return (new ValueResponse(["filename" => $asset["filename"], "content_type" => "application/octet-stream", "content" => file_get_contents($path)]));
    }
    if ($action == "download_category")
    {
        $category = correction_get_category((int)$id);
        if ($category == NULL)
            not_found();
        $ret = correction_download_archive(correction_collect_category_assets((int)$id), $category["codename"].".zip");
    }
    else if ($action == "download_selection")
    {
        $assets = [];
        foreach ((array)($data["asset"] ?? []) as $asset_id)
        {
            $asset_id = (int)$asset_id;
            $res = $Database->query("SELECT * FROM correction_asset WHERE id = $asset_id AND deleted IS NULL");
            if ($res != NULL && ($row = $res->fetch_assoc()))
                $assets[$row['id']] = $row;
        }
        foreach ((array)($data["category"] ?? []) as $category_id)
            foreach (correction_collect_category_assets((int)$category_id) as $row)
                $assets[$row['id']] = $row;
        $ret = correction_download_archive(array_values($assets), "corrections-selection.zip");
    }
    else
        bad_request();
    if (!$ret["ok"])
        return (CorrectionErrorResponse($ret["error"]));
    return (new ValueResponse(["filename" => $ret["filename"], "content_type" => "application/zip", "content" => $ret["content"]]));
}

$Tab = [
    "GET" => [
        "" => ["correction_is_admin", "CorrectionDisplay"],
        "download_asset" => ["correction_is_admin", "CorrectionDownload"],
        "download_category" => ["correction_is_admin", "CorrectionDownload"]
    ],
    "POST" => [
        "upload" => ["correction_is_admin", "CorrectionCreate"],
        "category" => ["correction_is_admin", "CorrectionCreate"],
        "create_file" => ["correction_is_admin", "CorrectionCreate"],
        "upload_tree" => ["correction_is_admin", "CorrectionCreate"],
        "download_selection" => ["correction_is_admin", "CorrectionDownload"],
        "inventory" => ["correction_is_admin", "CorrectionCreate"],
        "synchronize" => ["correction_is_admin", "CorrectionCreate"],
        "rollback" => ["correction_is_admin", "CorrectionCreate"]
    ],
    "PUT" => [
        "move" => ["correction_is_admin", "CorrectionUpdate"],
        "move_node" => ["correction_is_admin", "CorrectionUpdate"]
    ],
    "DELETE" => [
        "asset" => ["correction_is_admin", "CorrectionDelete"],
        "category" => ["correction_is_admin", "CorrectionDelete"],
        "" => ["correction_is_admin", "CorrectionDelete"]
    ]
];
