<?php

require ("cycles.php");


function cycle_file_root($id)
{
    global $Configuration;

    $id = (int)$id;
    $cycle = db_select_one("codename FROM cycle WHERE id = $id AND deleted IS NULL");
    if ($cycle == NULL || trim((string)($cycle["codename"] ?? "")) == "")
        not_found();
    return ($Configuration->CyclesDir($cycle["codename"]));
}

function cycle_file_relative_path($path)
{
    $relative = path_browser_transfer_normalize_relative($path);
    if ($relative === NULL)
        bad_request();
    return ($relative);
}

function GetCycleFileDir($id, $data, $method, $output, $module, $msg = "")
{
    if ($id == -1)
        bad_request();
    $root = cycle_file_root($id);
    $relative = cycle_file_relative_path($data["path"] ?? "");
    $path = $relative == "" ? "/" : $relative;
    $fbid = trim((string)($data["fbid"] ?? "cycle_document_file_browser_".(int)$id));
    $language = (string)($data["language"] ?? "");
    $html = get_dir($root, $path, "cycle", (int)$id, "file", $fbid, true, $language, false, "", true);
    return (new ValueResponse(array_merge($msg != "" ? ["msg" => $msg] : [], ["content" => $html])));
}

function AddCycleFile($id, $data, $method, $output, $module)
{
    if ($id == -1 || !isset($data["file"]))
        bad_request();
    $root = cycle_file_root($id);
    $relative = cycle_file_relative_path($data["path"] ?? "");
    $target = rtrim($root, "/").($relative != "" ? "/".$relative : "")."/";
    new_directory($target."index.php");

    foreach ($data["file"] as $file)
    {
        if (!isset($file["name"]) || !isset($file["content"]))
            bad_request();
        $name = basename(str_replace(" ", "_", (string)$file["name"]));
        $name = ltrim($name, ".");
        if ($name == "" || in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ["php", "sh", "pl"], true))
            forbidden();
        $content = base64_decode((string)$file["content"], true);
        if ($content === false || file_put_contents($target.$name, $content, LOCK_EX) !== strlen($content))
            return (new ErrorResponse("CannotWriteFile", $name));
        @chmod($target.$name, 0640);
    }
    $data["path"] = $relative;
    return (GetCycleFileDir($id, $data, "GET", $output, $module, "FileAdded"));
}

function RemoveCycleFile($id, $data, $method, $output, $module)
{
    if ($id == -1 || !isset($data["file"]))
        bad_request();
    $root = cycle_file_root($id);
    $file = (string)$data["file"];
    if ($file != "" && $file[0] == "-")
        $file = substr($file, 1);
    $file = str_replace("@", "/", $file);
    $root_normalized = rtrim(str_replace("\\", "/", $root), "/")."/";
    $file_normalized = str_replace("\\", "/", $file);
    if (strncmp($file_normalized, $root_normalized, strlen($root_normalized)) != 0)
        bad_request();
    $relative = path_browser_transfer_normalize_relative(substr($file_normalized, strlen($root_normalized)));
    if ($relative === NULL || $relative === "" || basename($relative) === "index.php")
        forbidden();
    $target = realpath($root.$relative);
    $real_root = realpath($root);
    if ($target === false || $real_root === false || ($target !== $real_root && strncmp($target, $real_root.DIRECTORY_SEPARATOR, strlen($real_root) + 1) !== 0))
        forbidden();
    if (is_dir($target))
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry)
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        @rmdir($target);
    }
    else
        @unlink($target);
    return (GetCycleFileDir($id, $data, "GET", $output, $module, "FileRemoved"));
}

$Tab = [
    "GET" => [
	"" => [
	    "everybody",
	    "DisplayCycles",
	],
    ],
    "POST" => [
	"" => [
	    "am_i_director",
	    "AddCycle",
	],
	"activity" => [
	    "is_director_for_cycle",
	    "SetMatter",
	],
	"user" => [
	    "is_director_for_cycle",
	    "SetUser",
	],
	"mail" => [
	    "is_director_for_cycle",
	    "SendCycleMail",
	],
	"attendance-register" => [
	    "is_director_for_cycle",
	    "GenerateAttendanceRegister",
	],
        "attendance-register-workflow" => [
            "is_director_for_cycle",
            "GenerateAttendanceRegisterWorkflow",
        ],
        "file" => [
            "is_director_for_cycle",
            "AddCycleFile",
        ],
    ],
    "PUT" => [
	"" => [
	    "is_director_for_cycle",
	    "EditCycle",
	],
	"teacher" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"activity" => [
	    "is_director_for_cycle",
	    "SetMatterProps",
	],
	"laboratory" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"user" => [
	    "is_director_for_cycle",
	    "SetUserProps",
	],
	"school" => [
	    "is_director_for_cycle",
	    "SetSchool",
	],
        "file" => [
            "is_director_for_cycle",
            "GetCycleFileDir",
        ],
    ],
    "DELETE" => [
	"" => [
	    "is_director_for_cycle",
	    "DeleteCycle",
	],
	"user" => [
	    "is_director_for_cycle",
	    "SetUser",
	],
	"activity" => [
	    "is_director_for_cycle",
	    "SetMatter",
	],
	"teacher" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"laboratory" => [
	    "is_director_for_cycle",
	    "SetCycleTeacher",
	],
	"school" => [
	    "is_director_for_cycle",
	    "SetSchool",
	],
        "file" => [
            "is_director_for_cycle",
            "RemoveCycleFile",
        ],
    ],
];
