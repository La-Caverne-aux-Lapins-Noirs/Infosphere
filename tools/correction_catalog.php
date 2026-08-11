<?php

function correction_root_dir()
{
    // $BaseDir is an URL/include prefix ("../" from /api), not a
    // filesystem root. api/index.php already changes the current directory
    // to the Infosphere root, and dirname(__DIR__) remains reliable from CLI.
    $root = dirname(__DIR__)."/dres/corrections";
    if (!is_dir($root))
        @mkdir($root, 0750, true);
    // Uploaded resources may contain PHP or other source files for exercises.
    // They must remain data, never web-executable content.
    $protection = $root."/.htaccess";
    if (is_dir($root) && !file_exists($protection))
        @file_put_contents($protection,
            "Options -ExecCGI\n".
            "RemoveHandler .php .php3 .php4 .php5 .php7 .php8 .phtml .phar .cgi .pl .py .sh .bash\n".
            "RemoveType .php .php3 .php4 .php5 .php7 .php8 .phtml .phar .cgi .pl .py .sh .bash\n".
            "<FilesMatch \"(?i)^(?:\.htaccess|\.user\.ini)$|\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|bash)$\">\n".
            "  Require all denied\n".
            "  Deny from all\n".
            "</FilesMatch>\n",
            LOCK_EX
        );
    if (file_exists($protection))
        @chmod($protection, 0640);
    return ($root);
}

function correction_safe_codename($value)
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value);
    $value = trim($value, '_-');
    return ($value);
}

function correction_safe_filename($value)
{
    $value = basename((string)$value);
    if ($value === '' || !preg_match('/^[A-Za-z0-9_.+@()\[\] -]+$/u', $value) || $value === '.' || $value === '..')
        return (NULL);
    return ($value);
}

function correction_fetch_all($query)
{
    global $Database;
    $ret = [];
    if (($res = $Database->query($query)) == NULL)
        return ($ret);
    while (($row = $res->fetch_assoc()) != false)
        $ret[] = $row;
    return ($ret);
}

function correction_get_category($id)
{
    global $Database;
    $id = (int)$id;
    $res = $Database->query("SELECT * FROM correction_category WHERE id = $id AND deleted IS NULL");
    if ($res == NULL)
        return (NULL);
    return ($res->fetch_assoc() ?: NULL);
}

function correction_categories()
{
    return (correction_fetch_all(
        "SELECT c.*, p.name AS parent_name, p.codename AS parent_codename " .
        "FROM correction_category c " .
        "LEFT JOIN correction_category p ON p.id = c.id_parent " .
        "WHERE c.deleted IS NULL ORDER BY COALESCE(p.name, ''), c.name"
    ));
}

function correction_category_path($id)
{
    $parts = [];
    $seen = [];
    while ($id !== NULL)
    {
        $id = (int)$id;
        if (isset($seen[$id]))
            return (NULL);
        $seen[$id] = true;
        if (($cat = correction_get_category($id)) == NULL)
            return (NULL);
        array_unshift($parts, $cat["codename"]);
        $id = $cat["id_parent"] === NULL ? NULL : (int)$cat["id_parent"];
    }
    return (implode("/", $parts));
}


function correction_ensure_category_path($base_category_id, $relative_directory)
{
    global $Database;
    $parent = (int)$base_category_id;
    if (correction_get_category($parent) == NULL)
        return (["ok" => false, "error" => "UnknownCategory"]);
    $relative_directory = str_replace('\\', '/', trim((string)$relative_directory, '/'));
    if ($relative_directory == '')
        return (["ok" => true, "id" => $parent]);
    foreach (explode('/', $relative_directory) as $part)
    {
        if ($part == '' || $part == '.' || $part == '..')
            return (["ok" => false, "error" => "InvalidCategory"]);
        $codename = correction_safe_codename($part);
        if ($codename == '')
            return (["ok" => false, "error" => "InvalidCategory"]);
        $ecode = $Database->real_escape_string($codename);
        $res = $Database->query("SELECT id, deleted FROM correction_category WHERE id_parent = $parent AND codename = '$ecode' ORDER BY deleted IS NULL DESC LIMIT 1");
        $row = $res == NULL ? NULL : $res->fetch_assoc();
        if ($row == NULL)
        {
            $ename = $Database->real_escape_string($part);
            if ($Database->query("INSERT INTO correction_category (id_parent, codename, name) VALUES ($parent, '$ecode', '$ename')") == NULL)
                return (["ok" => false, "error" => "CannotCreateDirectory"]);
            $parent = (int)$Database->insert_id;
        }
        else
        {
            $parent = (int)$row['id'];
            if ($row['deleted'] !== NULL)
                $Database->query("UPDATE correction_category SET name = '".$Database->real_escape_string($part)."', deleted = NULL WHERE id = $parent");
        }
    }
    return (["ok" => true, "id" => $parent]);
}

function correction_collect_category_assets($category_id)
{
    $ids = [(int)$category_id];
    for ($i = 0; $i < count($ids); ++$i)
        foreach (correction_fetch_all("SELECT id FROM correction_category WHERE deleted IS NULL AND id_parent = ".(int)$ids[$i]) as $row)
            $ids[] = (int)$row['id'];
    if (!count($ids))
        return ([]);
    return (correction_fetch_all("SELECT * FROM correction_asset WHERE deleted IS NULL AND id_category IN (".implode(',', $ids).") ORDER BY relative_path"));
}

function correction_download_archive($assets, $archive_name)
{
    if (!class_exists('ZipArchive'))
        return (["ok" => false, "error" => "ZipUnavailable"]);
    $tmp = tempnam(sys_get_temp_dir(), 'correction-zip-');
    $zip = new ZipArchive;
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true)
        return (["ok" => false, "error" => "CannotCreateArchive"]);
    $root = correction_root_dir();
    foreach ($assets as $asset)
    {
        $path = $root.'/'.$asset['relative_path'];
        if (is_file($path))
            $zip->addFile($path, $asset['relative_path']);
    }
    $zip->close();
    $content = file_get_contents($tmp);
    @unlink($tmp);
    if ($content === false)
        return (["ok" => false, "error" => "CannotCreateArchive"]);
    return (["ok" => true, "filename" => $archive_name, "content" => $content]);
}

function correction_run_command($argv)
{
    $command = implode(' ', array_map('escapeshellarg', $argv));
    $pipes = [];
    $proc = @proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ], $pipes);
    if (!is_resource($proc))
        return (["status" => 127, "stdout" => "", "stderr" => "Unable to execute $argv[0]"]);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return (["status" => proc_close($proc), "stdout" => $stdout, "stderr" => $stderr]);
}

function correction_analyze_library($path)
{
    $analysis = [
        "symbols" => [],
        "elf_class" => NULL,
        "elf_machine" => NULL,
        "elf_type" => NULL,
        "error" => NULL
    ];

    $readelf = correction_run_command(["readelf", "-h", $path]);
    if ($readelf["status"] != 0)
    {
        $analysis["error"] = trim($readelf["stderr"] ?: $readelf["stdout"]);
        return ($analysis);
    }
    foreach (explode("\n", $readelf["stdout"]) as $line)
    {
        if (preg_match('/^\s*Class:\s*(.+)$/', $line, $m))
            $analysis["elf_class"] = trim($m[1]);
        else if (preg_match('/^\s*Machine:\s*(.+)$/', $line, $m))
            $analysis["elf_machine"] = trim($m[1]);
        else if (preg_match('/^\s*Type:\s*(.+)$/', $line, $m))
            $analysis["elf_type"] = trim($m[1]);
    }

    // readelf is preferred over nm here: its symbol table output is stable and
    // lets us reject undefined/local symbols without depending on nm flavours.
    $symbols = correction_run_command(["readelf", "--wide", "--dyn-syms", $path]);
    if ($symbols["status"] != 0)
    {
        $analysis["error"] = trim($symbols["stderr"] ?: $symbols["stdout"]);
        return ($analysis);
    }
    foreach (explode("\n", $symbols["stdout"]) as $line)
    {
        if (!preg_match('/^\s*\d+:\s+[0-9a-fA-F]+\s+\d+\s+FUNC\s+GLOBAL\s+DEFAULT\s+\S+\s+(evaluate_[A-Za-z_][A-Za-z0-9_]*)\s*$/', $line, $m))
            continue;
        $analysis["symbols"][$m[1]] = substr($m[1], strlen("evaluate_"));
    }
    ksort($analysis["symbols"]);
    return ($analysis);
}

function correction_asset_by_path($relative_path)
{
    global $Database;
    $relative_path = $Database->real_escape_string($relative_path);
    $res = $Database->query("SELECT * FROM correction_asset WHERE relative_path = '$relative_path' AND deleted IS NULL");
    if ($res == NULL)
        return (NULL);
    return ($res->fetch_assoc() ?: NULL);
}

function correction_store_asset($category_id, $name, $tmp_path, $content = NULL)
{
    global $Database;
    $category_id = (int)$category_id;
    if (($category_path = correction_category_path($category_id)) === NULL)
        return (["ok" => false, "error" => "UnknownCategory"]);
    if (($name = correction_safe_filename($name)) === NULL)
        return (["ok" => false, "error" => "InvalidFile"]);
    if (in_array(strtolower($name), [".htaccess", ".user.ini"], true))
        return (["ok" => false, "error" => "ForbiddenControlFile"]);

    $kind = substr($name, -3) == ".so" ? "library" : (substr($name, -4) == ".dab" ? "dabsic" : "resource");
    $relative_path = $category_path."/".$name;
    $root = correction_root_dir();
    $directory = $root."/".$category_path;
    if (!is_dir($directory) && !@mkdir($directory, 0770, true))
        return (["ok" => false, "error" => "CannotCreateDirectory"]);
    $target = $root."/".$relative_path;
    $temporary = $target.".upload.".bin2hex(random_bytes(6));

    if ($content !== NULL)
        $written = @file_put_contents($temporary, $content, LOCK_EX);
    else if ($tmp_path !== NULL && (is_uploaded_file($tmp_path) || file_exists($tmp_path)))
        $written = @copy($tmp_path, $temporary) ? filesize($temporary) : false;
    else
        $written = false;
    if ($written === false)
        return (["ok" => false, "error" => "CannotStoreFile"]);

    @chmod($temporary, 0640);
    $sha256 = hash_file("sha256", $temporary);
    $size = filesize($temporary);
    $analysis = $kind == "library" ? correction_analyze_library($temporary) : [
        "symbols" => [], "elf_class" => NULL, "elf_machine" => NULL,
        "elf_type" => NULL, "error" => NULL
    ];
    if ($kind == "library" && $analysis["error"] !== NULL)
    {
        @unlink($temporary);
        return (["ok" => false, "error" => "InvalidLibrary: ".$analysis["error"]]);
    }

    // A symbol may only have one active owner. Refuse before replacing anything.
    if ($kind == "library" && count($analysis["symbols"]))
    {
        $escaped = array_map(function ($name) use ($Database) {
            return ("'".$Database->real_escape_string($name)."'");
        }, array_values($analysis["symbols"]));
        $current = correction_asset_by_path($relative_path);
        $except = $current == NULL ? "" : " AND a.id != ".(int)$current["id"];
        $duplicates = correction_fetch_all(
            "SELECT s.function_name, a.relative_path FROM correction_symbol s " .
            "JOIN correction_asset a ON a.id = s.id_asset " .
            "WHERE a.deleted IS NULL $except AND s.function_name IN (".implode(",", $escaped).")"
        );
        if (count($duplicates))
        {
            @unlink($temporary);
            $names = array_map(function ($row) {
                return ($row["function_name"]." (".$row["relative_path"].")");
            }, $duplicates);
            return (["ok" => false, "error" => "DuplicateEvaluator: ".implode(", ", $names)]);
        }
    }

    if (!@rename($temporary, $target))
    {
        @unlink($temporary);
        return (["ok" => false, "error" => "CannotCommitFile"]);
    }

    $epath = $Database->real_escape_string($relative_path);
    $efilename = $Database->real_escape_string($name);
    $ekind = $Database->real_escape_string($kind);
    $esha = $Database->real_escape_string($sha256);
    $eclass = $Database->real_escape_string((string)$analysis["elf_class"]);
    $emachine = $Database->real_escape_string((string)$analysis["elf_machine"]);
    $etype = $Database->real_escape_string((string)$analysis["elf_type"]);
    // relative_path is unique even for soft-deleted rows. Recreating a file at
    // the same place must revive its previous row instead of issuing an INSERT.
    $res = $Database->query("SELECT * FROM correction_asset WHERE relative_path = '$epath' LIMIT 1");
    $existing = $res == NULL ? NULL : $res->fetch_assoc();
    if ($existing == NULL)
    {
        if ($Database->query(
            "INSERT INTO correction_asset " .
            "(id_category, kind, filename, relative_path, sha256, size, elf_class, elf_machine, elf_type) VALUES " .
            "($category_id, '$ekind', '$efilename', '$epath', '$esha', ".(int)$size.", " .
            "NULLIF('$eclass',''), NULLIF('$emachine',''), NULLIF('$etype',''))"
        ) === false)
        {
            @unlink($target);
            return (["ok" => false, "error" => "CannotRegisterCorrectionFile"]);
        }
        $asset_id = (int)$Database->insert_id;
    }
    else
    {
        $asset_id = (int)$existing["id"];
        if ($Database->query(
            "UPDATE correction_asset SET id_category = $category_id, kind = '$ekind', filename = '$efilename', " .
            "relative_path = '$epath', sha256 = '$esha', size = ".(int)$size.", elf_class = NULLIF('$eclass',''), " .
            "elf_machine = NULLIF('$emachine',''), elf_type = NULLIF('$etype',''), analysis_error = NULL, " .
            "source_type = 'upload', source_reference = NULL, deleted = NULL " .
            "WHERE id = $asset_id"
        ) === false)
        {
            @unlink($target);
            return (["ok" => false, "error" => "CannotRegisterCorrectionFile"]);
        }
        $Database->query("DELETE FROM correction_symbol WHERE id_asset = $asset_id");
    }
    foreach ($analysis["symbols"] as $symbol => $function)
    {
        $symbol = $Database->real_escape_string($symbol);
        $function = $Database->real_escape_string($function);
        $Database->query("INSERT INTO correction_symbol (id_asset, symbol, function_name) VALUES ($asset_id, '$symbol', '$function')");
    }
    return (["ok" => true, "asset_id" => $asset_id]);
}

function correction_assets()
{
    return (correction_fetch_all(
        "SELECT a.*, c.name AS category_name, c.codename AS category_codename " .
        "FROM correction_asset a JOIN correction_category c ON c.id = a.id_category " .
        "WHERE a.deleted IS NULL AND c.deleted IS NULL ORDER BY a.relative_path"
    ));
}

function correction_libraries()
{
    $libraries = correction_fetch_all(
        "SELECT a.*, c.name AS category_name FROM correction_asset a " .
        "JOIN correction_category c ON c.id = a.id_category " .
        "WHERE a.kind = 'library' AND a.deleted IS NULL ORDER BY a.relative_path"
    );
    foreach ($libraries as &$library)
        $library["symbols"] = correction_fetch_all(
            "SELECT * FROM correction_symbol WHERE id_asset = ".(int)$library["id"]." ORDER BY function_name"
        );
    unset($library);
    return ($libraries);
}

function correction_functions()
{
    $functions = correction_fetch_all(
        "SELECT s.*, a.relative_path AS library_path, a.filename AS library_filename, " .
        "a.id_category, c.name AS category_name, c.codename AS category_codename " .
        "FROM correction_symbol s JOIN correction_asset a ON a.id = s.id_asset " .
        "JOIN correction_category c ON c.id = a.id_category " .
        "WHERE a.deleted IS NULL ORDER BY s.function_name"
    );
    $assets = correction_assets();
    $by_path = [];
    foreach ($assets as $asset)
        $by_path[$asset["relative_path"]] = $asset;
    foreach ($functions as &$function)
    {
        $prefix = correction_category_path($function["id_category"]);
        $base = $prefix."/".$function["function_name"];
        $function["definition"] = $by_path[$base.".dab"] ?? NULL;
        $function["exercise"] = $by_path[$base."_exercise.dab"] ?? NULL;
        $function["complete"] = $function["definition"] !== NULL && $function["exercise"] !== NULL;
    }
    unset($function);
    return ($functions);
}


function correction_normalize_relative_path($path)
{
    $path = str_replace('\\', '/', trim((string)$path));
    $parts = [];
    foreach (explode('/', $path) as $part)
    {
        if ($part == '' || $part == '.')
            continue;
        if ($part == '..')
        {
            if (!count($parts))
                return (NULL);
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }
    return (implode('/', $parts));
}

function correction_parse_dabsic_references($content)
{
    $references = [];
    if (!is_string($content) || $content == '')
        return ($references);
    if (preg_match_all('/@(insert|push)\\s+(["\'])([^"\']+\\.dab)\\2/i', $content, $matches, PREG_SET_ORDER))
        foreach ($matches as $match)
            $references[] = [
                'directive' => strtolower($match[1]),
                'requested_path' => str_replace('\\', '/', trim($match[3]))
            ];
    return ($references);
}

function correction_resolve_dabsic_reference($source_path, $requested_path, $assets_by_path)
{
    $source_dir = dirname(str_replace('\\', '/', $source_path));
    if ($source_dir == '.')
        $source_dir = '';
    $relative = correction_normalize_relative_path(($source_dir == '' ? '' : $source_dir.'/').$requested_path);
    $rooted = correction_normalize_relative_path($requested_path);
    $prefer_relative = preg_match('#^(\\./|\\.\\./)#', $requested_path);
    $candidates = $prefer_relative ? [$relative, $rooted] : [$rooted, $relative];
    foreach (array_values(array_unique(array_filter($candidates, function ($path) { return ($path !== NULL && $path !== ''); }))) as $candidate)
        if (isset($assets_by_path[$candidate]) && $assets_by_path[$candidate]['kind'] == 'dabsic')
            return ($candidate);
    return (NULL);
}

function correction_catalog_validation()
{
    $issues = [];
    foreach (correction_functions() as $function)
    {
        if ($function['definition'] === NULL)
            $issues[] = [
                'type' => 'missing_definition',
                'asset' => $function['library_path'],
                'function' => $function['function_name'],
                'message' => $function['function_name'].'.dab est absent pour '.$function['symbol']
            ];
        if ($function['exercise'] === NULL)
            $issues[] = [
                'type' => 'missing_evaluation',
                'asset' => $function['library_path'],
                'function' => $function['function_name'],
                'message' => $function['function_name'].'_exercise.dab est absent pour '.$function['symbol']
            ];
    }
    foreach (correction_dabsic_files() as $file)
        foreach ($file['missing_dependencies'] as $dependency)
            $issues[] = [
                'type' => 'missing_dependency',
                'asset' => $file['relative_path'],
                'function' => NULL,
                'message' => $file['relative_path'].' référence un fichier absent : '.$dependency['requested_path']
            ];
    return ($issues);
}

function correction_dabsic_files()
{
    $files = correction_fetch_all(
        "SELECT a.*, c.name AS category_name FROM correction_asset a " .
        "JOIN correction_category c ON c.id = a.id_category " .
        "WHERE a.kind = 'dabsic' AND a.deleted IS NULL ORDER BY a.relative_path"
    );
    $all_assets = correction_assets();
    $assets_by_path = [];
    foreach ($all_assets as $asset)
        $assets_by_path[$asset['relative_path']] = $asset;

    $function_names = [];
    foreach (correction_fetch_all(
        "SELECT DISTINCT s.function_name FROM correction_symbol s " .
        "JOIN correction_asset a ON a.id = s.id_asset WHERE a.deleted IS NULL"
    ) as $function)
        $function_names[$function['function_name']] = true;

    $referenced_by = [];
    foreach ($files as &$file)
    {
        $base = preg_replace('/\.dab$/', '', $file['filename']);
        $path = correction_root_dir().'/'.$file['relative_path'];
        $content = @file_get_contents($path);
        $file['dependencies'] = [];
        $file['missing_dependencies'] = [];
        foreach (correction_parse_dabsic_references($content === false ? '' : $content) as $reference)
        {
            $reference['resolved_path'] = correction_resolve_dabsic_reference(
                $file['relative_path'],
                $reference['requested_path'],
                $assets_by_path
            );
            $reference['missing'] = $reference['resolved_path'] === NULL;
            $file['dependencies'][] = $reference;
            if ($reference['missing'])
                $file['missing_dependencies'][] = $reference;
            else
                $referenced_by[$reference['resolved_path']][] = [
                    'source_path' => $file['relative_path'],
                    'directive' => $reference['directive']
                ];
        }

        if (substr($base, -9) == '_exercise')
        {
            $target = substr($base, 0, -9);
            $file['role'] = isset($function_names[$target]) ? 'evaluation' : 'scenario';
            $file['target_function'] = isset($function_names[$target]) ? $target : NULL;
        }
        else if (isset($function_names[$base]))
        {
            $file['role'] = 'definition';
            $file['target_function'] = $base;
        }
        else
        {
            $chains_exercises = false;
            foreach ($file['dependencies'] as $dependency)
                if (preg_match('/_exercise\.dab$/', (string)$dependency['requested_path']))
                {
                    $chains_exercises = true;
                    break;
                }
            $file['role'] = $chains_exercises ? 'scenario' : 'standalone';
            $file['target_function'] = NULL;
        }
    }
    unset($file);
    foreach ($files as &$file)
        $file['referenced_by'] = $referenced_by[$file['relative_path']] ?? [];
    unset($file);
    return ($files);
}

function correction_scenarios()
{
    return (array_values(array_filter(correction_dabsic_files(), function ($file) {
        return ($file["role"] == "scenario" || $file["role"] == "standalone");
    })));
}

function correction_remote_inventory()
{
    require_once (__DIR__."/hand_request.php");
    $response = hand_request([
        "command" => "correction_sync_inventory"
    ]);
    if ($response === false)
        return (["ok" => false, "error" => "DistransUnavailable"]);
    if (!is_array($response) || ($response["result"] ?? "ko") != "ok")
    {
        $message = "InvalidDistransResponse";
        foreach (["message", "msg", "error", "content"] as $field)
            if (isset($response[$field]) && trim((string)$response[$field]) != "")
            {
                $message = trim((string)$response[$field]);
                break;
            }
        return (["ok" => false, "error" => $message, "response" => $response]);
    }
    $files = [];
    foreach (($response["files"] ?? []) as $file)
    {
        if (!is_array($file) || !isset($file["path"], $file["sha256"]))
            continue;
        $path = str_replace('\\', '/', ltrim((string)$file["path"], '/'));
        if ($path == "" || strpos($path, "../") !== false)
            continue;
        $files[$path] = $file;
    }
    return ([
        "ok" => true,
        "root" => $response["root"] ?? NULL,
        "files" => $files,
        "warnings" => $response["warnings"] ?? []
    ]);
}

function correction_compare_inventory($remote = NULL)
{
    if ($remote === NULL)
        $remote = correction_remote_inventory();
    if (!($remote["ok"] ?? false))
        return ($remote);

    $local = [];
    foreach (correction_assets() as $asset)
        $local[$asset["relative_path"]] = $asset;

    $state = [
        "ok" => true,
        "root" => $remote["root"] ?? NULL,
        "warnings" => $remote["warnings"] ?? [],
        "same" => [],
        "upload" => [],
        "replace" => [],
        "delete" => []
    ];
    foreach ($local as $path => $asset)
    {
        if (!isset($remote["files"][$path]))
            $state["upload"][] = $asset;
        else if (!hash_equals((string)$asset["sha256"], (string)$remote["files"][$path]["sha256"]))
            $state["replace"][] = ["local" => $asset, "remote" => $remote["files"][$path]];
        else
            $state["same"][] = $asset;
    }
    foreach ($remote["files"] as $path => $file)
        if (!isset($local[$path]))
            $state["delete"][] = $file;
    return ($state);
}

function correction_sync_manifest()
{
    $manifest = [];
    foreach (correction_assets() as $asset)
        $manifest[] = [
            "path" => $asset["relative_path"],
            "sha256" => $asset["sha256"],
            "size" => (int)$asset["size"]
        ];
    return ($manifest);
}

function correction_distrans_error($response, $fallback = "InvalidDistransResponse")
{
    if ($response === false)
        return ("DistransUnavailable");
    if (is_array($response))
        foreach (["message", "msg", "error", "content"] as $field)
            if (isset($response[$field]) && trim((string)$response[$field]) != "")
                return (trim((string)$response[$field]));
    return ($fallback);
}

function correction_synchronize($maximum_files = NULL)
{
    $issues = correction_catalog_validation();
    if (count($issues))
        return (["ok" => false, "error" => "CorrectionCatalogIncomplete", "issues" => $issues]);
    $manifest = correction_sync_manifest();
    $begin = hand_request([
        "command" => "correction_sync_begin",
        "manifest" => $manifest
    ]);
    if (!is_array($begin) || ($begin["result"] ?? "ko") != "ok")
        return (["ok" => false, "error" => correction_distrans_error($begin)]);
    if (!empty($begin["up_to_date"]))
        return ([
            "ok" => true,
            "up_to_date" => true,
            "sent" => [],
            "remaining" => 0,
            "reused" => (int)($begin["reused"] ?? 0)
        ]);
    if (!isset($begin["token"]))
        return (["ok" => false, "error" => "InvalidDistransResponse"]);

    $token = (string)$begin["token"];
    $missing = array_values(array_unique(array_map("strval", $begin["missing"] ?? [])));
    $assets = [];
    foreach (correction_assets() as $asset)
        $assets[$asset["relative_path"]] = $asset;

    $to_send = $missing;
    if ($maximum_files !== NULL)
        $to_send = array_slice($to_send, 0, max(0, (int)$maximum_files));
    $sent = [];
    foreach ($to_send as $path)
    {
        if (!isset($assets[$path]))
        {
            hand_request(["command" => "correction_sync_abort", "token" => $token]);
            return (["ok" => false, "error" => "UnknownLocalCorrectionFile: ".$path]);
        }
        $local_path = correction_root_dir()."/".$path;
        $content = @file_get_contents($local_path);
        if ($content === false)
        {
            hand_request(["command" => "correction_sync_abort", "token" => $token]);
            return (["ok" => false, "error" => "CannotReadCorrectionFile: ".$path]);
        }
        $put = hand_request([
            "command" => "correction_sync_put",
            "token" => $token,
            "path" => $path,
            "content" => base64_encode($content)
        ]);
        unset($content);
        if (!is_array($put) || ($put["result"] ?? "ko") != "ok")
        {
            hand_request(["command" => "correction_sync_abort", "token" => $token]);
            return (["ok" => false, "error" => correction_distrans_error($put, "CannotTransferCorrectionFile: ".$path)]);
        }
        $sent[] = $path;
    }

    $remaining = count($missing) - count($sent);
    if ($remaining > 0)
        return ([
            "ok" => true,
            "pending" => true,
            "token" => $token,
            "sent" => $sent,
            "remaining" => $remaining,
            "reused" => (int)($begin["reused"] ?? 0),
            "resumed" => (bool)($begin["resumed"] ?? false)
        ]);

    $commit = hand_request([
        "command" => "correction_sync_commit",
        "token" => $token
    ]);
    if (!is_array($commit) || ($commit["result"] ?? "ko") != "ok")
    {
        hand_request(["command" => "correction_sync_abort", "token" => $token]);
        return (["ok" => false, "error" => correction_distrans_error($commit, "CannotActivateCorrectionGeneration")]);
    }
    return ([
        "ok" => true,
        "synchronized" => true,
        "sent" => $sent,
        "reused" => (int)($begin["reused"] ?? 0),
        "resumed" => (bool)($begin["resumed"] ?? false),
        "root" => $commit["root"] ?? NULL,
        "previous_available" => (bool)($commit["previous_available"] ?? false),
        "validation" => $commit["validation"] ?? []
    ]);
}

function correction_rollback()
{
    $response = hand_request(["command" => "correction_sync_rollback"]);
    if (!is_array($response) || ($response["result"] ?? "ko") != "ok")
        return (["ok" => false, "error" => correction_distrans_error($response, "CannotRollbackCorrectionGeneration")]);
    $status = correction_compare_inventory();
    $status["rolled_back"] = true;
    return ($status);
}

function correction_category_children_map()
{
    $children = [];
    foreach (correction_categories() as $category)
    {
        $parent = $category['id_parent'] === NULL ? 0 : (int)$category['id_parent'];
        if (!isset($children[$parent]))
            $children[$parent] = [];
        $children[$parent][] = $category;
    }
    return ($children);
}

function correction_assets_by_category()
{
    $assets = [];
    foreach (correction_assets() as $asset)
    {
        $category = (int)$asset['id_category'];
        if (!isset($assets[$category]))
            $assets[$category] = [];
        $assets[$category][] = $asset;
    }
    return ($assets);
}

function correction_category_is_descendant($candidate_id, $ancestor_id)
{
    $candidate_id = (int)$candidate_id;
    $ancestor_id = (int)$ancestor_id;
    $seen = [];
    while ($candidate_id > 0)
    {
        if ($candidate_id == $ancestor_id)
            return (true);
        if (isset($seen[$candidate_id]))
            return (true);
        $seen[$candidate_id] = true;
        $category = correction_get_category($candidate_id);
        if ($category == NULL || $category['id_parent'] === NULL)
            return (false);
        $candidate_id = (int)$category['id_parent'];
    }
    return (false);
}

function correction_move_asset_to_category($asset_id, $category_id)
{
    global $Database;
    $asset_id = (int)$asset_id;
    $category_id = (int)$category_id;
    $res = $Database->query("SELECT * FROM correction_asset WHERE id = $asset_id AND deleted IS NULL");
    $asset = $res == NULL ? NULL : $res->fetch_assoc();
    if ($asset == NULL)
        return (["ok" => false, "error" => "UnknownCorrectionFile"]);
    if ($category_id <= 0 || correction_get_category($category_id) == NULL)
        return (["ok" => false, "error" => "UnknownCategory"]);
    if ($category_id == (int)$asset['id_category'])
        return (["ok" => true]);

    $category_path = correction_category_path($category_id);
    $relative_path = $category_path.'/'.$asset['filename'];
    if (correction_asset_by_path($relative_path) != NULL)
        return (["ok" => false, "error" => "CorrectionFileAlreadyExists"]);

    $root = correction_root_dir();
    $source = $root.'/'.$asset['relative_path'];
    $directory = $root.'/'.$category_path;
    $target = $root.'/'.$relative_path;
    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory))
        return (["ok" => false, "error" => "CannotCreateDirectory: ".$directory]);
    if (file_exists($target))
        return (["ok" => false, "error" => "CorrectionFileAlreadyExists"]);
    if (!is_file($source) || !@rename($source, $target))
        return (["ok" => false, "error" => "CannotMoveFile"]);

    $epath = $Database->real_escape_string($relative_path);
    if ($Database->query("UPDATE correction_asset SET id_category = $category_id, relative_path = '$epath' WHERE id = $asset_id") == NULL)
    {
        @rename($target, $source);
        return (["ok" => false, "error" => "CannotMoveFile"]);
    }
    return (["ok" => true]);
}

function correction_move_category($category_id, $target_parent_id)
{
    global $Database;
    $category_id = (int)$category_id;
    $target_parent_id = (int)$target_parent_id;
    $category = correction_get_category($category_id);
    if ($category == NULL)
        return (["ok" => false, "error" => "UnknownCategory"]);
    if ($category_id == 1)
        return (["ok" => false, "error" => "CannotMoveGeneralCategory"]);
    if ($target_parent_id > 0 && correction_get_category($target_parent_id) == NULL)
        return (["ok" => false, "error" => "UnknownCategory"]);
    if ($target_parent_id == $category_id || ($target_parent_id > 0 && correction_category_is_descendant($target_parent_id, $category_id)))
        return (["ok" => false, "error" => "InvalidCategoryMove"]);
    $current_parent = $category['id_parent'] === NULL ? 0 : (int)$category['id_parent'];
    if ($current_parent == $target_parent_id)
        return (["ok" => true]);

    $old_path = correction_category_path($category_id);
    $new_parent_path = $target_parent_id > 0 ? correction_category_path($target_parent_id) : '';
    $new_path = ($new_parent_path == '' ? '' : $new_parent_path.'/').$category['codename'];
    $parent_sql = $target_parent_id > 0 ? (string)$target_parent_id : 'NULL';
    $ecode = $Database->real_escape_string($category['codename']);
    $duplicate = $Database->query("SELECT id FROM correction_category WHERE deleted IS NULL AND codename = '$ecode' AND ".($target_parent_id > 0 ? "id_parent = $target_parent_id" : "id_parent IS NULL")." AND id != $category_id");
    if ($duplicate != NULL && $duplicate->fetch_assoc())
        return (["ok" => false, "error" => "CategoryAlreadyExists"]);

    $root = correction_root_dir();
    $source = $root.'/'.$old_path;
    $target = $root.'/'.$new_path;
    $target_parent = dirname($target);
    if (!is_dir($target_parent) && !@mkdir($target_parent, 0770, true) && !is_dir($target_parent))
        return (["ok" => false, "error" => "CannotCreateDirectory: ".$target_parent]);
    if (file_exists($target))
        return (["ok" => false, "error" => "CategoryAlreadyExists"]);
    if (is_dir($source) && !@rename($source, $target))
        return (["ok" => false, "error" => "CannotMoveDirectory"]);

    $old_escaped = $Database->real_escape_string($old_path);
    $new_escaped = $Database->real_escape_string($new_path);
    $Database->query("START TRANSACTION");
    $ok = $Database->query("UPDATE correction_category SET id_parent = $parent_sql WHERE id = $category_id") !== NULL;
    if ($ok)
        $ok = $Database->query("UPDATE correction_asset SET relative_path = CONCAT('$new_escaped', SUBSTRING(relative_path, ".(strlen($old_path) + 1).")) WHERE deleted IS NULL AND (relative_path = '$old_escaped' OR relative_path LIKE CONCAT('$old_escaped', '/%'))") !== NULL;
    if ($ok)
        $Database->query("COMMIT");
    else
    {
        $Database->query("ROLLBACK");
        if (is_dir($target))
            @rename($target, $source);
        return (["ok" => false, "error" => "CannotMoveDirectory"]);
    }
    return (["ok" => true]);
}

function correction_create_dabsic_file($category_id, $filename)
{
    $filename = trim((string)$filename);
    if (!preg_match('/^[A-Za-z0-9_.+-]+\.dab$/', $filename))
        return (["ok" => false, "error" => "InvalidDabsicFilename"]);
    return (correction_store_asset((int)$category_id, $filename, NULL, ""));
}

function correction_delete_category($category_id)
{
    global $Database;
    $category_id = (int)$category_id;
    if ($category_id <= 0 || correction_get_category($category_id) == NULL)
        return (["ok" => false, "error" => "UnknownCategory"]);
    if ($category_id == 1)
        return (["ok" => false, "error" => "CannotDeleteGeneralCategory"]);

    $path = correction_category_path($category_id);
    $ids = [];
    $queue = [$category_id];
    while (count($queue))
    {
        $current = array_pop($queue);
        $ids[] = $current;
        $res = $Database->query("SELECT id FROM correction_category WHERE deleted IS NULL AND id_parent = ".(int)$current);
        if ($res != NULL)
            while ($row = $res->fetch_assoc())
                $queue[] = (int)$row['id'];
    }
    $id_list = implode(',', array_map('intval', $ids));
    $assets = correction_fetch_all("SELECT * FROM correction_asset WHERE deleted IS NULL AND id_category IN ($id_list)");
    foreach ($assets as $asset)
    {
        $file = correction_root_dir().'/'.$asset['relative_path'];
        if (is_file($file) && !@unlink($file))
            return (["ok" => false, "error" => "CannotDeleteFile: ".$asset['relative_path']]);
    }
    $Database->query("START TRANSACTION");
    $ok = $Database->query("DELETE FROM correction_symbol WHERE id_asset IN (SELECT id FROM correction_asset WHERE id_category IN ($id_list))") !== NULL;
    if ($ok)
        $ok = $Database->query("UPDATE correction_asset SET deleted = NOW() WHERE deleted IS NULL AND id_category IN ($id_list)") !== NULL;
    if ($ok)
        $ok = $Database->query("UPDATE correction_category SET deleted = NOW() WHERE deleted IS NULL AND id IN ($id_list)") !== NULL;
    if ($ok)
        $Database->query("COMMIT");
    else
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotDeleteDirectory"]);
    }
    $directory = correction_root_dir().'/'.$path;
    if (is_dir($directory))
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry)
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        @rmdir($directory);
    }
    return (["ok" => true]);
}
