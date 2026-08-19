<?php

require_once (__DIR__."/questionnaire.php");

/*
** Generic resources used by quiz/form definitions live in dres/quiz.  Only
** autonomous quiz definitions have a row in SQL; helper Dabsic fragments and
** other resources deliberately remain filesystem-only so @include/@insert can
** be used without duplicating the Dabsic composition model in the database.
*/

function quiz_resource_school($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0 || !questionnaire_school_can_manage($id_school))
        return (NULL);
    $school = db_select_one("id, codename FROM school WHERE id = $id_school AND deleted IS NULL");
    return (is_array($school) ? $school : NULL);
}

function quiz_resource_root($id_school)
{
    global $Configuration;

    $school = quiz_resource_school($id_school);
    if ($school == NULL)
        return (NULL);
    $codename = questionnaire_safe_codename($school["codename"] ?? "");
    if ($codename == "")
        return (NULL);

    if (is_object($Configuration) && method_exists($Configuration, "QuizDir"))
    {
        $absolute = $Configuration->QuizDir($codename);
        if (is_string($absolute) && $absolute != "" && substr($absolute, 0, 1) != DIRECTORY_SEPARATOR)
            $absolute = dirname(__DIR__)."/".$absolute;
    }
    else
    {
        $absolute = dirname(__DIR__)."/dres/quiz/".$codename."/";
        foreach ([$absolute, $absolute."quiz/", $absolute."rubrics/"] as $directory)
            if (!is_dir($directory))
                @mkdir($directory, 0750, true);
    }
    if (!is_string($absolute) || !is_dir($absolute))
        return (NULL);
    $real = realpath($absolute);
    if ($real === false)
        return (NULL);
    return ([
        "school" => $school,
        "absolute" => rtrim($real, DIRECTORY_SEPARATOR),
        "reference" => "dres/quiz/".$codename,
    ]);
}

function quiz_resource_safe_name($name)
{
    $name = trim((string)$name);
    if ($name == "" || $name == "." || $name == ".." || substr($name, 0, 1) == ".")
        return (NULL);
    if (strpos($name, "\0") !== false || strpos($name, "/") !== false || strpos($name, "\\") !== false)
        return (NULL);
    if (!preg_match('/^[\pL\pN _+@()\[\].-]+$/u', $name))
        return (NULL);
    $lower = strtolower($name);
    if (in_array($lower, [".htaccess", ".user.ini", "index.php"], true)
        || in_array(strtolower(pathinfo($lower, PATHINFO_EXTENSION)), ["php", "phtml", "phar", "cgi"], true))
        return (NULL);
    return ($name);
}

function quiz_resource_normalize_relative($relative, $allow_empty = true)
{
    $relative = trim(str_replace("\\", "/", (string)$relative), "/");
    if ($relative == "")
        return ($allow_empty ? "" : NULL);
    $parts = [];
    foreach (explode("/", $relative) as $part)
    {
        $safe = quiz_resource_safe_name($part);
        if ($safe === NULL)
            return (NULL);
        $parts[] = $safe;
    }
    return (implode("/", $parts));
}

function quiz_resource_resolve($id_school, $relative, $must_exist = true, $directory = NULL)
{
    $root = quiz_resource_root($id_school);
    if ($root == NULL)
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $relative = quiz_resource_normalize_relative($relative, true);
    if ($relative === NULL)
        return (["ok" => false, "error" => "QuizResourceInvalidPath"]);
    $candidate = $root["absolute"].($relative == "" ? "" : DIRECTORY_SEPARATOR.str_replace("/", DIRECTORY_SEPARATOR, $relative));

    if ($must_exist)
    {
        $absolute = realpath($candidate);
        if ($absolute === false || ($absolute !== $root["absolute"] && strncmp($absolute, $root["absolute"].DIRECTORY_SEPARATOR, strlen($root["absolute"]) + 1) !== 0))
            return (["ok" => false, "error" => "QuizResourceNotFound"]);
        if (is_link($candidate))
            return (["ok" => false, "error" => "QuizResourceInvalidPath"]);
        if ($directory === true && !is_dir($absolute))
            return (["ok" => false, "error" => "QuizResourceNotDirectory"]);
        if ($directory === false && !is_file($absolute))
            return (["ok" => false, "error" => "QuizResourceNotFile"]);
    }
    else
    {
        $parent = realpath(dirname($candidate));
        if ($parent === false || ($parent !== $root["absolute"] && strncmp($parent, $root["absolute"].DIRECTORY_SEPARATOR, strlen($root["absolute"]) + 1) !== 0))
            return (["ok" => false, "error" => "QuizResourceInvalidPath"]);
        $absolute = $candidate;
    }
    return ([
        "ok" => true,
        "root" => $root,
        "relative" => $relative,
        "absolute" => $absolute,
    ]);
}

function quiz_resource_project_reference($id_school, $relative)
{
    $root = quiz_resource_root($id_school);
    $relative = quiz_resource_normalize_relative($relative, true);
    if ($root == NULL || $relative === NULL)
        return (NULL);
    return ($root["reference"].($relative == "" ? "" : "/".$relative));
}

function quiz_resource_registered_map($id_school)
{
    $id_school = (int)$id_school;
    $map = [];
    foreach (db_select_all("quiz.* FROM quiz WHERE id_school = $id_school") as $row)
        $map[str_replace("\\", "/", trim((string)$row["reference"], "/"))] = $row;
    return ($map);
}

function quiz_resource_kind($filename)
{
    $extension = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    if ($extension == "dab")
        return ("dabsic");
    if (in_array($extension, ["png", "jpg", "jpeg", "gif", "svg", "webp"], true))
        return ("image");
    return ("resource");
}

function quiz_resource_scan_directory($id_school, $absolute, $relative, array $registered)
{
    $nodes = [];
    $entries = @scandir($absolute);
    if (!is_array($entries))
        return ($nodes);
    foreach ($entries as $name)
    {
        if ($name == "." || $name == ".." || substr($name, 0, 1) == ".")
            continue ;
        $path = $absolute.DIRECTORY_SEPARATOR.$name;
        if (is_link($path))
            continue ;
        $child_relative = ltrim($relative."/".$name, "/");
        if (is_dir($path))
        {
            $nodes[] = [
                "type" => "directory",
                "name" => $name,
                "relative" => $child_relative,
                "children" => quiz_resource_scan_directory($id_school, $path, $child_relative, $registered),
            ];
            continue ;
        }
        if (!is_file($path))
            continue ;
        $reference = quiz_resource_project_reference($id_school, $child_relative);
        $quiz = $reference !== NULL && isset($registered[$reference]) ? $registered[$reference] : NULL;
        $nodes[] = [
            "type" => "file",
            "name" => $name,
            "relative" => $child_relative,
            "kind" => quiz_resource_kind($name),
            "size" => @filesize($path) ?: 0,
            "quiz" => $quiz,
        ];
    }
    usort($nodes, function($a, $b) {
        if ($a["type"] != $b["type"])
            return ($a["type"] == "directory" ? -1 : 1);
        return (strnatcasecmp($a["name"], $b["name"]));
    });
    return ($nodes);
}

function quiz_resource_tree($id_school)
{
    $root = quiz_resource_root($id_school);
    if ($root == NULL)
        return (NULL);
    return ([
        "root" => $root,
        "nodes" => quiz_resource_scan_directory((int)$id_school, $root["absolute"], "", quiz_resource_registered_map((int)$id_school)),
    ]);
}

function quiz_resource_directory_list($id_school)
{
    $root = quiz_resource_root($id_school);
    if ($root == NULL)
        return ([]);
    $out = [""];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root["absolute"], FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry)
    {
        if (!$entry->isDir() || $entry->isLink() || substr($entry->getFilename(), 0, 1) == ".")
            continue ;
        $relative = substr($entry->getPathname(), strlen($root["absolute"]) + 1);
        $relative = str_replace(DIRECTORY_SEPARATOR, "/", $relative);
        if (quiz_resource_normalize_relative($relative, false) !== NULL)
            $out[] = $relative;
    }
    natcasesort($out);
    return (array_values($out));
}

function quiz_resource_create_directory($id_school, $parent, $name)
{
    $parent = quiz_resource_resolve($id_school, $parent, true, true);
    $name = quiz_resource_safe_name($name);
    if (!$parent["ok"] || $name === NULL)
        return ($parent["ok"] ? ["ok" => false, "error" => "QuizResourceInvalidName"] : $parent);
    $target = $parent["absolute"].DIRECTORY_SEPARATOR.$name;
    if (file_exists($target) || is_link($target))
        return (["ok" => false, "error" => "QuizResourceAlreadyExists"]);
    if (!@mkdir($target, 0750, false))
        return (["ok" => false, "error" => "QuizResourceCannotCreateDirectory"]);
    return (["ok" => true]);
}

function quiz_resource_create_dabsic($id_school, $directory, $filename)
{
    $directory = quiz_resource_resolve($id_school, $directory, true, true);
    if (!$directory["ok"])
        return ($directory);
    $filename = quiz_resource_safe_name($filename);
    if ($filename === NULL)
        return (["ok" => false, "error" => "QuizResourceInvalidName"]);
    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) != "dab")
        $filename .= ".dab";
    $target = $directory["absolute"].DIRECTORY_SEPARATOR.$filename;
    if (file_exists($target) || is_link($target))
        return (["ok" => false, "error" => "QuizResourceAlreadyExists"]);
    if (@file_put_contents($target, "", LOCK_EX) === false)
        return (["ok" => false, "error" => "QuizResourceCannotCreateFile"]);
    @chmod($target, 0640);
    return (["ok" => true, "relative" => ltrim($directory["relative"]."/".$filename, "/")]);
}

function quiz_resource_store_file($id_school, $directory, $name, $tmp_path = NULL, $content = NULL)
{
    $directory = quiz_resource_resolve($id_school, $directory, true, true);
    $name = quiz_resource_safe_name($name);
    if (!$directory["ok"] || $name === NULL)
        return ($directory["ok"] ? ["ok" => false, "error" => "QuizResourceInvalidName"] : $directory);
    $target = $directory["absolute"].DIRECTORY_SEPARATOR.$name;
    if (file_exists($target) || is_link($target))
        return (["ok" => false, "error" => "QuizResourceAlreadyExists"]);
    $temporary = $target.".upload.".bin2hex(random_bytes(5));
    if ($content !== NULL)
        $ok = @file_put_contents($temporary, $content, LOCK_EX) !== false;
    else
        $ok = is_string($tmp_path) && file_exists($tmp_path) && @copy($tmp_path, $temporary);
    if (!$ok)
    {
        @unlink($temporary);
        return (["ok" => false, "error" => "QuizResourceCannotStoreFile"]);
    }
    @chmod($temporary, 0640);
    if (!@rename($temporary, $target))
    {
        @unlink($temporary);
        return (["ok" => false, "error" => "QuizResourceCannotStoreFile"]);
    }
    return (["ok" => true]);
}

function quiz_resource_ensure_relative_directory($id_school, $base, $relative)
{
    $base = quiz_resource_resolve($id_school, $base, true, true);
    if (!$base["ok"])
        return ($base);
    $relative = quiz_resource_normalize_relative($relative, true);
    if ($relative === NULL)
        return (["ok" => false, "error" => "QuizResourceInvalidPath"]);
    $current = $base["relative"];
    if ($relative == "")
        return (["ok" => true, "relative" => $current]);
    foreach (explode("/", $relative) as $part)
    {
        $next = ltrim($current."/".$part, "/");
        $resolved = quiz_resource_resolve($id_school, $next, true, true);
        if (!$resolved["ok"])
        {
            $created = quiz_resource_create_directory($id_school, $current, $part);
            if (!$created["ok"])
                return ($created);
        }
        $current = $next;
    }
    return (["ok" => true, "relative" => $current]);
}

function quiz_resource_registered_under($id_school, $relative)
{
    $relative = quiz_resource_normalize_relative($relative, true);
    if ($relative === NULL)
        return ([]);
    $root = quiz_resource_root($id_school);
    if ($root == NULL)
        return ([]);
    $prefix = $root["reference"].($relative == "" ? "" : "/".$relative);
    $rows = [];
    foreach (db_select_all("quiz.* FROM quiz WHERE id_school = ".(int)$id_school) as $row)
    {
        $reference = str_replace("\\", "/", trim((string)$row["reference"], "/"));
        if ($reference === $prefix || strncmp($reference, rtrim($prefix, "/")."/", strlen(rtrim($prefix, "/")) + 1) === 0)
            $rows[] = $row;
    }
    return ($rows);
}

function quiz_resource_restore_legacy_aliases(array $aliases)
{
    foreach (array_reverse($aliases) as $alias)
    {
        @unlink($alias["path"]);
        @symlink($alias["target"], $alias["path"]);
    }
}

function quiz_resource_update_registered_paths($id_school, $old_relative, $new_relative)
{
    global $Database;

    $root = quiz_resource_root($id_school);
    if ($root == NULL)
        return (false);
    $old_prefix = $root["reference"].($old_relative == "" ? "" : "/".$old_relative);
    $new_prefix = $root["reference"].($new_relative == "" ? "" : "/".$new_relative);
    $aliases = [];
    foreach (db_select_all("id, codename, reference FROM quiz WHERE id_school = ".(int)$id_school) as $row)
    {
        $reference = str_replace("\\", "/", trim((string)$row["reference"], "/"));
        if ($reference !== $old_prefix && strncmp($reference, rtrim($old_prefix, "/")."/", strlen(rtrim($old_prefix, "/")) + 1) !== 0)
            continue ;
        $suffix = substr($reference, strlen($old_prefix));
        $updated = $new_prefix.$suffix;

        // V1/V2 storage migration leaves a compatibility symlink at the old
        // dres/questionnaire path. If the canonical file is reorganized in
        // the tree, keep that alias aimed at the new location so historical
        // @include paths do not break.
        $legacy = dirname(__DIR__)."/dres/questionnaire/".questionnaire_safe_codename($root["school"]["codename"] ?? "")."/".questionnaire_safe_codename($row["codename"] ?? "").".dab";
        if (is_link($legacy))
        {
            $old_target = readlink($legacy);
            $new_target = "../../".substr($updated, strlen("dres/"));
            @unlink($legacy);
            if (!@symlink($new_target, $legacy))
            {
                @symlink($old_target, $legacy);
                quiz_resource_restore_legacy_aliases($aliases);
                return (false);
            }
            $aliases[] = ["path" => $legacy, "target" => $old_target];
        }

        $escaped = $Database->real_escape_string($updated);
        if ($Database->query("UPDATE quiz SET reference = '$escaped', updated_at = CURRENT_TIMESTAMP WHERE id = ".(int)$row["id"]) === false)
        {
            quiz_resource_restore_legacy_aliases($aliases);
            return (false);
        }
    }
    return (true);
}

function quiz_resource_move($id_school, $source_relative, $target_directory)
{
    global $Database;

    $source = quiz_resource_resolve($id_school, $source_relative, true, NULL);
    $target = quiz_resource_resolve($id_school, $target_directory, true, true);
    if (!$source["ok"])
        return ($source);
    if (!$target["ok"])
        return ($target);
    if ($source["relative"] == "")
        return (["ok" => false, "error" => "QuizResourceInvalidPath"]);
    if (is_dir($source["absolute"]) && ($target["relative"] === $source["relative"] || strncmp($target["relative"], $source["relative"]."/", strlen($source["relative"]) + 1) === 0))
        return (["ok" => false, "error" => "QuizResourceMoveIntoSelf"]);

    $source_path = $source["root"]["absolute"].DIRECTORY_SEPARATOR.str_replace("/", DIRECTORY_SEPARATOR, $source["relative"]);
    $new_relative = ltrim($target["relative"]."/".basename($source["relative"]), "/");
    if ($new_relative === $source["relative"])
        return (["ok" => true, "relative" => $new_relative]);
    $new_absolute = $target["absolute"].DIRECTORY_SEPARATOR.basename($source["absolute"]);

    // Moving an item back to a previous location is allowed when that location
    // only contains the compatibility symlink created by an earlier move.
    $destination_alias_target = NULL;
    if (is_link($new_absolute))
    {
        $resolved_alias = realpath($new_absolute);
        if ($resolved_alias === false || $resolved_alias !== $source["absolute"])
            return (["ok" => false, "error" => "QuizResourceAlreadyExists"]);
        $destination_alias_target = readlink($new_absolute);
        if ($destination_alias_target === false || !@unlink($new_absolute))
            return (["ok" => false, "error" => "QuizResourceCannotMove"]);
    }
    else if (file_exists($new_absolute))
        return (["ok" => false, "error" => "QuizResourceAlreadyExists"]);

    $restore_destination_alias = function() use ($new_absolute, $destination_alias_target) {
        if ($destination_alias_target !== NULL && !file_exists($new_absolute) && !is_link($new_absolute))
            @symlink($destination_alias_target, $new_absolute);
    };

    $Database->query("START TRANSACTION");
    if (!@rename($source["absolute"], $new_absolute))
    {
        $Database->query("ROLLBACK");
        $restore_destination_alias();
        return (["ok" => false, "error" => "QuizResourceCannotMove"]);
    }
    if (!quiz_resource_update_registered_paths($id_school, $source["relative"], $new_relative))
    {
        $Database->query("ROLLBACK");
        @rename($new_absolute, $source_path);
        $restore_destination_alias();
        return (["ok" => false, "error" => "QuizResourceCannotMove"]);
    }

    // Filesystem paths are part of the Dabsic composition API. Keep the old
    // path as an internal compatibility alias so an existing @include/@insert
    // does not break merely because the resource was reorganized in the GUI.
    $alias_target = dabsic_dependency_relative_path($source_path, $new_absolute);
    if (!@symlink($alias_target, $source_path))
    {
        $Database->query("ROLLBACK");
        @rename($new_absolute, $source_path);
        $restore_destination_alias();
        return (["ok" => false, "error" => "QuizResourceCannotMove"]);
    }
    if ($Database->query("COMMIT") === false)
    {
        @unlink($source_path);
        $Database->query("ROLLBACK");
        @rename($new_absolute, $source_path);
        $restore_destination_alias();
        return (["ok" => false, "error" => "QuizResourceCannotMove"]);
    }
    return (["ok" => true, "relative" => $new_relative]);
}

function quiz_resource_collect_aliases_to($root, $target)
{
    $aliases = [];
    $target = rtrim((string)$target, DIRECTORY_SEPARATOR);
    $walk = function($directory) use (&$walk, &$aliases, $target) {
        $entries = @scandir($directory);
        if (!is_array($entries))
            return ;
        foreach ($entries as $entry)
        {
            if ($entry == "." || $entry == "..")
                continue ;
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (is_link($path))
            {
                $resolved = realpath($path);
                if ($resolved !== false && ($resolved === $target || strncmp($resolved, $target.DIRECTORY_SEPARATOR, strlen($target) + 1) === 0))
                    $aliases[] = $path;
                continue ;
            }
            if (is_dir($path))
                $walk($path);
        }
    };
    $walk($root);
    return ($aliases);
}

function quiz_resource_remove_directory($absolute)
{
    $entries = @scandir($absolute);
    if (!is_array($entries))
        return (false);
    foreach ($entries as $entry)
    {
        if ($entry == "." || $entry == "..")
            continue ;
        $path = $absolute.DIRECTORY_SEPARATOR.$entry;
        if (is_link($path))
        {
            if (!@unlink($path))
                return (false);
            continue ;
        }
        if (is_dir($path))
        {
            if (!quiz_resource_remove_directory($path))
                return (false);
        }
        else if (!@unlink($path))
            return (false);
    }
    return (@rmdir($absolute));
}

function quiz_resource_delete($id_school, $relative)
{
    $entry = quiz_resource_resolve($id_school, $relative, true, NULL);
    if (!$entry["ok"])
        return ($entry);
    if ($entry["relative"] == "")
        return (["ok" => false, "error" => "QuizResourceInvalidPath"]);
    if (count(quiz_resource_registered_under($id_school, $entry["relative"])))
        return (["ok" => false, "error" => "QuizResourceRegistered"]);
    $aliases = quiz_resource_collect_aliases_to($entry["root"]["absolute"], $entry["absolute"]);
    if (is_dir($entry["absolute"]))
        $ok = quiz_resource_remove_directory($entry["absolute"]);
    else
        $ok = @unlink($entry["absolute"]);
    if ($ok)
        foreach ($aliases as $alias)
            @unlink($alias);
    return ($ok ? ["ok" => true] : ["ok" => false, "error" => "QuizResourceCannotDelete"]);
}

function quiz_resource_collect_files($id_school, array $selection)
{
    $files = [];
    foreach ($selection as $relative)
    {
        $entry = quiz_resource_resolve($id_school, $relative, true, NULL);
        if (!$entry["ok"] || $entry["relative"] == "")
            continue ;
        if (is_file($entry["absolute"]))
            $files[$entry["relative"]] = $entry["absolute"];
        else if (is_dir($entry["absolute"]))
        {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entry["absolute"], FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file)
            {
                if (!$file->isFile() || $file->isLink() || substr($file->getFilename(), 0, 1) == ".")
                    continue ;
                $relative_file = substr($file->getPathname(), strlen($entry["root"]["absolute"]) + 1);
                $relative_file = str_replace(DIRECTORY_SEPARATOR, "/", $relative_file);
                $files[$relative_file] = $file->getPathname();
            }
        }
    }
    return ($files);
}

function quiz_resource_download($id_school, array $selection)
{
    $files = quiz_resource_collect_files($id_school, $selection);
    if (!count($files))
        return (["ok" => false, "error" => "QuizResourceNothingSelected"]);
    if (count($selection) == 1)
    {
        $single = quiz_resource_resolve($id_school, $selection[0], true, false);
        if ($single["ok"])
            return ([
                "ok" => true,
                "filename" => basename($single["relative"]),
                "content_type" => "application/octet-stream",
                "content" => file_get_contents($single["absolute"]),
            ]);
    }
    if (!class_exists("ZipArchive"))
        return (["ok" => false, "error" => "ZipUnavailable"]);
    $tmp = tempnam(sys_get_temp_dir(), "quiz-res-");
    $zip = new ZipArchive;
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true)
        return (["ok" => false, "error" => "CannotCreateArchive"]);
    foreach ($files as $relative => $absolute)
        $zip->addFile($absolute, $relative);
    $zip->close();
    $content = file_get_contents($tmp);
    @unlink($tmp);
    if ($content === false)
        return (["ok" => false, "error" => "CannotCreateArchive"]);
    return (["ok" => true, "filename" => "quiz-resources.zip", "content_type" => "application/zip", "content" => $content]);
}
