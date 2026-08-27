<?php

require_once (__DIR__."/dabsic_form.php");
require_once (__DIR__."/dabsic_editor.php");
require_once (__DIR__."/dabsic_dependencies.php");

/*
** Questionnaire definitions deliberately stay Dabsic-first.  SQL only keeps
** the Infosphere identity of the definition (school, codename, source file).
** The visual editor is therefore a Dabsic editor, not a second persistence
** model for questions.
*/

function questionnaire_safe_codename($value)
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value);
    return (trim((string)$value, '_-'));
}

function questionnaire_safe_symbol($value, $fallback = "Item")
{
    $value = trim((string)$value);
    $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value);
    $value = trim((string)$value, '_');
    if ($value == "" || !preg_match('/^[A-Za-z_]/D', $value))
        $value = $fallback;
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value))
        $value = preg_replace('/[^A-Za-z0-9_]/', '_', $value);
    return ($value);
}

function questionnaire_school_can_manage($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0 || !logged_in())
        return (false);
    return (is_assistant_for_school($id_school));
}

function questionnaire_manageable_school_ids()
{
    if (!logged_in())
        return ([]);
    $ids = [];
    foreach (db_select_all("id FROM school WHERE deleted IS NULL ORDER BY id") as $school)
        if (questionnaire_school_can_manage((int)$school["id"]))
            $ids[] = (int)$school["id"];
    return ($ids);
}

function questionnaire_can_access_page()
{
    return (count(questionnaire_manageable_school_ids()) != 0);
}

function questionnaire_manageable_schools()
{
    $schools = function_exists("fetch_school") ? fetch_school() : [];
    if (!is_array($schools))
        return ([]);
    return (array_values(array_filter($schools, function($school) {
        return (is_array($school) && questionnaire_school_can_manage($school["id"] ?? -1));
    })));
}

function questionnaire_root_dir()
{
    global $Configuration;

    if (is_object($Configuration) && method_exists($Configuration, "QuizDir"))
    {
        $root = rtrim((string)$Configuration->QuizDir(), "/");
        if ($root != "" && substr($root, 0, 1) != DIRECTORY_SEPARATOR)
            $root = dirname(__DIR__)."/".$root;
    }
    else
        $root = dirname(__DIR__)."/dres/quiz";
    if (!is_dir($root))
        @mkdir($root, 0750, true);

    return ($root);
}

function questionnaire_school_dir($school_codename)
{
    global $Configuration;

    $school_codename = questionnaire_safe_codename($school_codename);
    if ($school_codename == "")
        return (NULL);
    if (is_object($Configuration) && method_exists($Configuration, "QuizDir"))
    {
        $dir = $Configuration->QuizDir($school_codename);
        if (is_string($dir) && $dir != "" && substr($dir, 0, 1) != DIRECTORY_SEPARATOR)
            $dir = dirname(__DIR__)."/".$dir;
    }
    else
    {
        $dir = questionnaire_root_dir()."/".$school_codename."/";
        foreach ([$dir, $dir."quiz/", $dir."rubrics/"] as $path)
            if (!is_dir($path))
                @mkdir($path, 0750, true);
    }
    return (is_string($dir) && is_dir($dir) ? rtrim($dir, "/") : NULL);
}

function questionnaire_safe_resource_directory($directory)
{
    $directory = trim(str_replace("\\", "/", (string)$directory), "/");
    if ($directory == "")
        return ("");
    $parts = [];
    foreach (explode("/", $directory) as $part)
    {
        $part = trim($part);
        if ($part == "" || $part == "." || $part == ".." || substr($part, 0, 1) == "."
            || !preg_match('/^[\pL\pN _+@()\[\].-]+$/u', $part))
            return (NULL);
        $parts[] = $part;
    }
    return (implode("/", $parts));
}

function questionnaire_reference_for($school_codename, $codename, $directory = "quiz")
{
    $school_codename = questionnaire_safe_codename($school_codename);
    $codename = questionnaire_safe_codename($codename);
    $directory = questionnaire_safe_resource_directory($directory);
    if ($school_codename == "" || $codename == "" || $directory === NULL)
        return (NULL);
    return ("dres/quiz/".$school_codename."/".($directory == "" ? "" : $directory."/").$codename.".dab");
}

function questionnaire_restore_legacy_storage($old_absolute, $new_absolute, $new_preexisting)
{
    if (is_link($old_absolute))
        @unlink($old_absolute);
    if ($new_preexisting)
    {
        if (!is_file($new_absolute))
            return (false);
        if (@copy($new_absolute, $old_absolute))
        {
            @chmod($old_absolute, 0640);
            return (true);
        }
        return (false);
    }
    if (!is_file($new_absolute))
        return (is_file($old_absolute));
    if (@rename($new_absolute, $old_absolute))
    {
        @chmod($old_absolute, 0640);
        return (true);
    }
    if (@copy($new_absolute, $old_absolute))
    {
        @chmod($old_absolute, 0640);
        @unlink($new_absolute);
        return (true);
    }
    return (false);
}

function questionnaire_migrate_legacy_storage()
{
    global $Database;
    static $done = false;

    if ($done)
        return ([]);
    $done = true;
    $warnings = [];
    if (!isset($Database))
        return ($warnings);
    $res = @$Database->query(
        "SELECT quiz.id, quiz.codename, quiz.reference, school.codename AS school_codename " .
        "FROM quiz LEFT JOIN school ON school.id = quiz.id_school " .
        "WHERE quiz.reference LIKE 'dres/questionnaire/%'"
    );
    if ($res == NULL)
        return ($warnings);
    while (($row = $res->fetch_assoc()) != false)
    {
        $school = questionnaire_safe_codename($row["school_codename"] ?? "");
        $codename = questionnaire_safe_codename($row["codename"] ?? "");
        if ($school == "" || $codename == "")
            continue ;
        $old_reference = str_replace("\\", "/", trim((string)$row["reference"], "/"));
        $old_absolute = dirname(__DIR__)."/".$old_reference;
        $new_reference = questionnaire_reference_for($school, $codename, "quiz");
        $school_dir = questionnaire_school_dir($school);
        $new_directory = $school_dir === NULL ? NULL : $school_dir."/quiz";
        $new_absolute = $new_directory === NULL ? NULL : $new_directory."/".$codename.".dab";
        if ($new_reference === NULL || $new_absolute === NULL)
        {
            $warnings[] = $old_reference;
            continue ;
        }
        $new_preexisting = is_file($new_absolute);
        if (!is_dir($new_directory))
            @mkdir($new_directory, 0750, true);
        if (is_file($old_absolute) && !file_exists($new_absolute))
        {
            if (!@rename($old_absolute, $new_absolute))
            {
                if (!@copy($old_absolute, $new_absolute))
                {
                    $warnings[] = $old_reference;
                    continue ;
                }
                @unlink($old_absolute);
            }
            @chmod($new_absolute, 0640);
        }
        else if (is_file($old_absolute) && is_file($new_absolute))
        {
            if (@hash_file("sha256", $old_absolute) !== @hash_file("sha256", $new_absolute))
            {
                $warnings[] = $old_reference;
                continue ;
            }
            @unlink($old_absolute);
        }
        else if (!is_file($new_absolute))
        {
            $warnings[] = $old_reference;
            continue ;
        }

        // Keep legacy @include paths alive. V1/V2 allowed authors to reference
        // dres/questionnaire directly; a relative symlink makes those old
        // activity configurations resolve to the new canonical file instead of
        // silently becoming stale copies.
        if (!file_exists($old_absolute) && !is_link($old_absolute))
        {
            $legacy_target = "../../quiz/".$school."/quiz/".$codename.".dab";
            if (!@symlink($legacy_target, $old_absolute))
            {
                // Do not leave pre-existing @include paths broken merely to
                // complete the migration. Restore the legacy source while
                // preserving a destination file that existed before migration.
                questionnaire_restore_legacy_storage($old_absolute, $new_absolute, $new_preexisting);
                $warnings[] = $old_reference." (legacy alias)";
                continue ;
            }
        }
        $escaped = $Database->real_escape_string($new_reference);
        if ($Database->query("UPDATE quiz SET reference = '$escaped', updated_at = CURRENT_TIMESTAMP WHERE id = ".(int)$row["id"]) === false)
        {
            // SQL is still authoritative for where the definition lives. If
            // persistence fails, put the old path back into a usable state as
            // well instead of leaving filesystem and database out of sync.
            questionnaire_restore_legacy_storage($old_absolute, $new_absolute, $new_preexisting);
            $warnings[] = $old_reference;
        }
    }
    return ($warnings);
}

function questionnaire_get($id, $include_deleted = false)
{
    global $Database;

    questionnaire_migrate_legacy_storage();
    $id = (int)$id;
    if ($id <= 0)
        return (NULL);
    $deleted = $include_deleted ? "" : " AND quiz.deleted IS NULL ";
    $row = db_select_one("
        quiz.*,
        school.codename AS school_codename
        FROM quiz
        LEFT JOIN school ON school.id = quiz.id_school
        WHERE quiz.id = $id
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
          $deleted
    ");
    return (is_array($row) ? $row : NULL);
}

function questionnaire_can_manage($id)
{
    $questionnaire = is_array($id) ? $id : questionnaire_get($id);
    return (is_array($questionnaire)
        && questionnaire_school_can_manage((int)($questionnaire["id_school"] ?? 0)));
}

function questionnaire_list($include_deleted = false)
{
    global $User;

    questionnaire_migrate_legacy_storage();
    if (!questionnaire_can_access_page())
        return ([]);
    $where = "";
    if (!is_admin())
    {
        $ids = questionnaire_manageable_school_ids();
        if (!count($ids))
            return ([]);
        $where = " AND quiz.id_school IN (".implode(",", $ids).") ";
    }
    $deleted = $include_deleted
        ? " AND quiz.deleted IS NOT NULL "
        : " AND quiz.deleted IS NULL ";
    return (db_select_all("
        quiz.*,
        school.codename AS school_codename
        FROM quiz
        LEFT JOIN school ON school.id = quiz.id_school
        WHERE school.deleted IS NULL
          $deleted
          $where
        ORDER BY quiz.updated_at DESC, quiz.id DESC
    "));
}

function questionnaire_activity_rows()
{
    global $Language;

    $language = preg_match('/^[a-z]{2}$/D', (string)$Language) ? (string)$Language : "fr";
    $name_field = $language."_name";
    return (db_select_all("
        activity.id,
        activity.codename,
        activity.is_template,
        activity.id_template,
        activity.parent_activity,
        activity.`$name_field` AS localized_name
        FROM activity
        WHERE activity.deleted IS NULL
        ORDER BY activity.is_template DESC, activity.codename ASC
    "));
}

function questionnaire_activity_language_options()
{
    global $LanguageList;

    $options = ["NA" => "Sans langue / commun"];
    if (is_array($LanguageList))
        foreach ($LanguageList as $code => $label)
            if (preg_match('/^[A-Za-z0-9_-]+$/D', (string)$code))
                $options[(string)$code] = (string)$label;
    return ($options);
}

function questionnaire_activity_entrypoint_files(array $activity)
{
    global $Configuration;

    $root = $Configuration->ActivitiesDir($activity["codename"], "");
    $files = [];
    $languages = questionnaire_activity_language_options();
    foreach (["configuration" => "configuration.dab", "preaccess" => "preaccess.dab", "satisfaction" => "satisfaction.dab", "rubric" => "rubric.dab"] as $kind => $basename)
    {
        $neutral = $root.$basename;
        if (is_file($neutral))
            $files[] = [
                "file" => realpath($neutral) ?: $neutral,
                "language" => "NA",
                "kind" => $kind,
            ];
        foreach ((array)glob($root."*/".$basename) as $file)
        {
            if (!is_file($file))
                continue ;
            $language = basename(dirname($file));
            if (!isset($languages[$language]))
                continue ;
            $files[] = [
                "file" => realpath($file) ?: $file,
                "language" => $language,
                "kind" => $kind,
            ];
        }
    }
    return ($files);
}

function questionnaire_activity_configuration_files(array $activity)
{
    $files = [];
    foreach (questionnaire_activity_entrypoint_files($activity) as $entry)
        if (($entry["kind"] ?? "") === "configuration")
            $files[$entry["file"]] = $entry["language"];
    return ($files);
}

function questionnaire_source_absolute(array $quiz)
{
    $path = dabsic_dependency_project_root()."/".ltrim((string)$quiz["reference"], "/");
    $real = realpath($path);
    return (dabsic_dependency_normalize_path($real !== false ? $real : $path));
}

function questionnaire_activity_usages($only_quiz_id = 0)
{
    $only_quiz_id = (int)$only_quiz_id;
    $quizzes = $only_quiz_id > 0 ? [questionnaire_get($only_quiz_id)] : questionnaire_list();
    $targets = [];
    foreach ($quizzes as $quiz)
    {
        if (!is_array($quiz))
            continue ;
        $absolute = questionnaire_source_absolute($quiz);
        $targets[$absolute] = $quiz;
    }
    if (!count($targets))
        return ([]);

    $usages = [];
    foreach (questionnaire_activity_rows() as $activity)
        foreach (questionnaire_activity_entrypoint_files($activity) as $entrypoint)
        {
            $configuration = $entrypoint["file"];
            $language = $entrypoint["language"];
            $kind = $entrypoint["kind"];
            foreach (dabsic_dependency_walk($configuration) as $edge)
            {
                $resolved = $edge["resolved_path"] ?? NULL;
                if ($resolved === NULL || !isset($targets[$resolved]))
                    continue ;
                $quiz = $targets[$resolved];
                $key = (int)$quiz["id"].":".(int)$activity["id"].":".$language.":".$configuration.":".$resolved;
                if (isset($usages[$key]))
                {
                    if ((int)$edge["depth"] < (int)$usages[$key]["depth"])
                        $usages[$key]["depth"] = (int)$edge["depth"];
                    continue ;
                }
                $chain = [];
                foreach ((array)($edge["chain"] ?? []) as $chain_file)
                    $chain[] = questionnaire_relative_project_path($chain_file);
                $usages[$key] = [
                    "quiz" => $quiz,
                    "activity" => $activity,
                    "language" => $language,
                    "configuration" => questionnaire_relative_project_path($configuration),
                    "entrypoint" => $kind,
                    "depth" => (int)($edge["depth"] ?? 0),
                    "directive" => $edge["directive"] ?? "include",
                    "requested_path" => $edge["requested_path"] ?? "",
                    "chain" => $chain,
                ];
            }
        }
    return (array_values($usages));
}


function questionnaire_support_usages($only_quiz_id = 0)
{
    global $Configuration;
    global $Database;

    $only_quiz_id = (int)$only_quiz_id;
    $quizzes = $only_quiz_id > 0 ? [questionnaire_get($only_quiz_id)] : questionnaire_list();
    $targets = [];
    foreach ($quizzes as $quiz)
        if (is_array($quiz))
            $targets[questionnaire_source_absolute($quiz)] = $quiz;
    if (!count($targets))
        return ([]);

    $root = rtrim((string)($Configuration->_SupportDir ?? "dres/support/"), "/")."/";
    $usages = [];
    foreach ((array)glob($root."*/*/preaccess.dab") as $entrypoint)
    {
        if (!is_file($entrypoint))
            continue ;
        $support_codename = basename(dirname($entrypoint));
        $category_codename = basename(dirname(dirname($entrypoint)));
        $support = db_select_one("
            support.id,
            support.codename,
            support.id_support_category,
            support.fr_name,
            support.en_name,
            support_category.codename AS category_codename
            FROM support
            INNER JOIN support_category ON support_category.id = support.id_support_category
            WHERE support.codename = '".$Database->real_escape_string($support_codename)."'
              AND support_category.codename = '".$Database->real_escape_string($category_codename)."'
              AND support.deleted IS NULL
              AND support_category.deleted IS NULL
        ");
        if (!is_array($support))
            continue ;
        $configuration = realpath($entrypoint) ?: $entrypoint;
        foreach (dabsic_dependency_walk($configuration) as $edge)
        {
            $resolved = $edge["resolved_path"] ?? NULL;
            if ($resolved === NULL || !isset($targets[$resolved]))
                continue ;
            $quiz = $targets[$resolved];
            $key = (int)$quiz["id"].":support:".(int)$support["id"].":".$configuration.":".$resolved;
            if (isset($usages[$key]))
            {
                if ((int)$edge["depth"] < (int)$usages[$key]["depth"])
                    $usages[$key]["depth"] = (int)$edge["depth"];
                continue ;
            }
            $chain = [];
            foreach ((array)($edge["chain"] ?? []) as $chain_file)
                $chain[] = questionnaire_relative_project_path($chain_file);
            $usages[$key] = [
                "quiz" => $quiz,
                "context" => "support",
                "support" => $support,
                "language" => "NA",
                "configuration" => questionnaire_relative_project_path($configuration),
                "entrypoint" => "preaccess",
                "depth" => (int)($edge["depth"] ?? 0),
                "directive" => $edge["directive"] ?? "include",
                "requested_path" => $edge["requested_path"] ?? "",
                "chain" => $chain,
            ];
        }
    }
    return (array_values($usages));
}

function questionnaire_support_asset_usages($only_quiz_id = 0)
{
    global $Configuration;
    global $Database;

    $only_quiz_id = (int)$only_quiz_id;
    $quizzes = $only_quiz_id > 0 ? [questionnaire_get($only_quiz_id)] : questionnaire_list();
    $targets = [];
    foreach ($quizzes as $quiz)
        if (is_array($quiz))
            $targets[questionnaire_source_absolute($quiz)] = $quiz;
    if (!count($targets))
        return ([]);

    $root = rtrim((string)($Configuration->_SupportDir ?? "dres/support/"), "/")."/";
    $usages = [];
    foreach ((array)glob($root."*/*/.asset/*/preaccess.dab") as $entrypoint)
    {
        if (!is_file($entrypoint))
            continue ;
        $asset_codename = basename(dirname($entrypoint));
        $support_dir = dirname(dirname(dirname($entrypoint)));
        $support_codename = basename($support_dir);
        $category_codename = basename(dirname($support_dir));
        $asset = db_select_one("
            support_asset.id,
            support_asset.codename,
            support_asset.id_support,
            support_asset.fr_name,
            support_asset.en_name,
            support.codename AS support_codename,
            support.id_support_category,
            support.fr_name AS support_fr_name,
            support.en_name AS support_en_name,
            support_category.codename AS category_codename
            FROM support_asset
            INNER JOIN support ON support.id = support_asset.id_support
            INNER JOIN support_category ON support_category.id = support.id_support_category
            WHERE support_asset.codename = '".$Database->real_escape_string($asset_codename)."'
              AND support.codename = '".$Database->real_escape_string($support_codename)."'
              AND support_category.codename = '".$Database->real_escape_string($category_codename)."'
              AND support_asset.deleted IS NULL
              AND support.deleted IS NULL
              AND support_category.deleted IS NULL
        ");
        if (!is_array($asset))
            continue ;
        $configuration = realpath($entrypoint) ?: $entrypoint;
        foreach (dabsic_dependency_walk($configuration) as $edge)
        {
            $resolved = $edge["resolved_path"] ?? NULL;
            if ($resolved === NULL || !isset($targets[$resolved]))
                continue ;
            $quiz = $targets[$resolved];
            $key = (int)$quiz["id"].":support_asset:".(int)$asset["id"].":".$configuration.":".$resolved;
            if (isset($usages[$key]))
                continue ;
            $chain = [];
            foreach ((array)($edge["chain"] ?? []) as $chain_file)
                $chain[] = questionnaire_relative_project_path($chain_file);
            $usages[$key] = [
                "quiz" => $quiz,
                "context" => "support_asset",
                "asset" => $asset,
                "language" => "NA",
                "configuration" => questionnaire_relative_project_path($configuration),
                "entrypoint" => "preaccess",
                "depth" => (int)($edge["depth"] ?? 0),
                "directive" => $edge["directive"] ?? "include",
                "requested_path" => $edge["requested_path"] ?? "",
                "chain" => $chain,
            ];
        }
    }
    return (array_values($usages));
}

function questionnaire_usages($only_quiz_id = 0)
{
    $activity = questionnaire_activity_usages((int)$only_quiz_id);
    foreach ($activity as &$usage)
        $usage["context"] = "activity";
    unset($usage);
    return (array_merge(
        $activity,
        questionnaire_support_usages((int)$only_quiz_id),
        questionnaire_support_asset_usages((int)$only_quiz_id)
    ));
}

function questionnaire_relative_project_path($path)
{
    $root = rtrim(dabsic_dependency_project_root(), "/")."/";
    $path = dabsic_dependency_normalize_path((string)$path);
    if (strncmp($path, $root, strlen($root)) === 0)
        return (substr($path, strlen($root)));
    return ($path);
}

function questionnaire_simple_scalar($value, $fallback = "")
{
    return (is_scalar($value) || $value === NULL ? ($value === NULL ? $fallback : $value) : $fallback);
}

function questionnaire_simple_list($value)
{
    if ($value === NULL || $value === "")
        return (true);
    if (!is_array($value))
        return (is_scalar($value));
    foreach ($value as $entry)
        if (!is_scalar($entry) && $entry !== NULL)
            return (false);
    return (true);
}

function questionnaire_string_list($value)
{
    if ($value === NULL || $value === "")
        return ([]);
    if (!is_array($value))
        $value = [$value];
    $out = [];
    foreach ($value as $entry)
    {
        if (is_array($entry) || is_object($entry))
            continue ;
        $entry = trim((string)$entry);
        if ($entry !== "" && !in_array($entry, $out, true))
            $out[] = $entry;
    }
    return ($out);
}

function questionnaire_dabsic_string($value)
{
    $value = str_replace(["\\", "\"", "\r", "\n", "\t"], ["\\\\", "\\\"", "", "\\n", "\\t"], (string)$value);
    return ('"'.$value.'"');
}

function questionnaire_dabsic_number($value)
{
    if (!is_numeric($value))
        return ("0");
    $value = (float)$value;
    if (floor($value) == $value)
        return ((string)(int)$value);
    return (rtrim(rtrim(sprintf('%.6F', $value), '0'), '.'));
}

function questionnaire_dabsic_list($name, array $values, $indent = "  ")
{
    $values = questionnaire_string_list($values);
    if (!count($values))
        return ("");
    $out = $indent."{".$name."\n";
    foreach ($values as $i => $value)
        $out .= $indent."  ".questionnaire_dabsic_string($value).($i + 1 < count($values) ? "," : "")."\n";
    return ($out.$indent."}\n");
}

function questionnaire_default_model($codename = "questionnaire", $name = "Nouveau questionnaire", $description = "")
{
    return ([
        "format_version" => 2,
        "codename" => questionnaire_safe_codename($codename) ?: "questionnaire",
        "name" => trim((string)$name) ?: "Nouveau questionnaire",
        "description" => (string)$description,
        "minimum_percent" => 100,
        "medals" => [],
        "groups" => [[
            "key" => "General",
            "label" => "Général",
            "minimum_percent" => 100,
            "medals" => [],
            "questions" => [],
        ]],
        "advanced" => false,
        "advanced_reasons" => [],
    ]);
}

function questionnaire_normalize_percent($value)
{
    if (!is_numeric($value))
        return (0);
    return (max(0, min(100, (float)$value)));
}

function questionnaire_normalize_model(array $model, $forced_codename = NULL)
{
    $base = questionnaire_default_model(
        $forced_codename !== NULL ? $forced_codename : ($model["codename"] ?? "questionnaire"),
        $model["name"] ?? "Nouveau questionnaire",
        $model["description"] ?? ""
    );
    $base["minimum_percent"] = questionnaire_normalize_percent($model["minimum_percent"] ?? 0);
    $base["medals"] = questionnaire_string_list($model["medals"] ?? []);
    $base["groups"] = [];

    $seen_groups = [];
    $seen_questions = [];
    $groups = isset($model["groups"]) && is_array($model["groups"]) ? array_slice($model["groups"], 0, 128) : [];
    foreach ($groups as $gi => $group)
    {
        if (!is_array($group))
            continue ;
        $key = questionnaire_safe_symbol($group["key"] ?? ("Group".($gi + 1)), "Group".($gi + 1));
        $original = $key;
        for ($suffix = 2; isset($seen_groups[$key]); ++$suffix)
            $key = $original.$suffix;
        $seen_groups[$key] = true;
        $normalized_group = [
            "key" => $key,
            "label" => trim((string)($group["label"] ?? $key)) ?: $key,
            "minimum_percent" => questionnaire_normalize_percent($group["minimum_percent"] ?? 0),
            "medals" => questionnaire_string_list($group["medals"] ?? []),
            "questions" => [],
        ];
        $questions = isset($group["questions"]) && is_array($group["questions"])
            ? array_slice($group["questions"], 0, 512) : [];
        foreach ($questions as $qi => $question)
        {
            if (!is_array($question))
                continue ;
            $fallback = "Question".($qi + 1);
            $qkey = questionnaire_safe_symbol($question["key"] ?? $fallback, $fallback);
            $original_qkey = $qkey;
            for ($suffix = 2; isset($seen_questions[$qkey]); ++$suffix)
                $qkey = $original_qkey.$suffix;
            $seen_questions[$qkey] = true;
            $type = strtolower(trim((string)($question["type"] ?? "text")));
            if (!in_array($type, ["text", "textarea", "radio", "checkbox", "scale"], true))
                $type = "text";
            $policy = strtolower(trim((string)($question["policy"] ?? "exact")));
            if (!in_array($policy, ["exact", "penalty"], true))
                $policy = "exact";
            $field_definition = form_field_definition([
                "type" => $type,
                "required" => !empty($question["required"]),
                "choices" => form_field_sequence($question["choices"] ?? []),
                "choice_values" => form_field_sequence($question["choice_values"] ?? []),
            ]);
            $choices = $field_definition["choices"];
            $choice_values = $field_definition["choice_values"];
            // Keep the visual model literal here. Validation, not
            // normalization, owns semantic mistakes such as an unknown stable
            // value or several correct values for a radio question.
            $correct = form_field_sequence($question["correct"] ?? []);
            if (!in_array($type, ["radio", "checkbox", "scale"], true))
            {
                $choices = [];
                $choice_values = [];
            }
            if ($type == "scale")
                $correct = [];
            $normalized_group["questions"][] = [
                "key" => $qkey,
                "label" => trim((string)($question["label"] ?? $qkey)) ?: $qkey,
                "type" => $type,
                "required" => !empty($question["required"]),
                "points" => is_numeric($question["points"] ?? NULL) ? max(0, (float)$question["points"]) : 1,
                "policy" => $policy,
                "penalty" => is_numeric($question["penalty"] ?? NULL) ? max(0, (float)$question["penalty"]) : 1,
                "choices" => $choices,
                "choice_values" => $choice_values,
                "correct" => $correct,
                "medals" => questionnaire_string_list($question["medals"] ?? []),
            ];
        }
        $base["groups"][] = $normalized_group;
    }
    if (!count($base["groups"]))
        $base["groups"] = questionnaire_default_model()["groups"];
    return ($base);
}

function questionnaire_serialize_model(array $model)
{
    $model = questionnaire_normalize_model($model, $model["codename"] ?? NULL);
    $out = "' Questionnaire Infosphere - définition Dabsic canonique\n";
    $out .= "' Les réponses et l'exploitation sont volontairement stockées ailleurs.\n\n";
    $out .= "[Questionnaire\n";
    $out .= "  FormatVersion = 2\n";
    $out .= "  Codename = ".questionnaire_dabsic_string($model["codename"])."\n";
    $out .= "  Name = ".questionnaire_dabsic_string($model["name"])."\n";
    $out .= "  Description = ".questionnaire_dabsic_string($model["description"])."\n";
    $out .= "  MinimumPercent = ".questionnaire_dabsic_number($model["minimum_percent"])."\n";
    $out .= questionnaire_dabsic_list("Medals", $model["medals"], "  ");
    $out .= "]\n\n";
    $out .= "[FormGroup\n";
    foreach ($model["groups"] as $group)
    {
        $out .= "  [".$group["key"]."\n";
        $out .= "    Label = ".questionnaire_dabsic_string($group["label"])."\n";
        $out .= "    MinimumPercent = ".questionnaire_dabsic_number($group["minimum_percent"])."\n";
        $out .= questionnaire_dabsic_list("Medals", $group["medals"], "    ");
        $out .= "    [Fields\n";
        foreach ($group["questions"] as $question)
        {
            $out .= "      [".$question["key"]."\n";
            $out .= "        Label = ".questionnaire_dabsic_string($question["label"])."\n";
            $out .= "        Type = ".questionnaire_dabsic_string($question["type"])."\n";
            $out .= "        Required = ".($question["required"] ? "true" : "false")."\n";
            $out .= "        Points = ".questionnaire_dabsic_number($question["points"])."\n";
            $out .= "        Policy = ".questionnaire_dabsic_string($question["policy"])."\n";
            $out .= "        Penalty = ".questionnaire_dabsic_number($question["penalty"])."\n";
            $out .= questionnaire_dabsic_list("Choices", $question["choices"], "        ");
            $out .= questionnaire_dabsic_list("ChoiceValues", $question["choice_values"], "        ");
            $out .= questionnaire_dabsic_list("Correct", $question["correct"], "        ");
            $out .= questionnaire_dabsic_list("Medals", $question["medals"], "        ");
            $out .= "      ]\n";
        }
        $out .= "    ]\n";
        $out .= "  ]\n";
    }
    $out .= "]\n";
    return ($out);
}

function questionnaire_mergeconf_scope($content, $scope, $reference = "")
{
    $fragment = dabsic_form_extract_root_scope($content, $scope);
    if ($fragment === NULL)
        return (["ok" => true, "root" => []]);

    $cmd = "mergeconf";
    $include_reference = $reference;
    if ($include_reference == "")
        $include_reference = dirname(__DIR__)."/dummy.dab";
    foreach (dabsic_form_include_paths($include_reference) as $path)
        $cmd .= " -I ".escapeshellarg($path);
    $cmd .= " -if .dabsic -of .json";
    $process = dabsic_form_process($cmd, $fragment."\n");
    if ($process["status"] !== 0)
        return ([
            "ok" => false,
            "error" => "QuestionnaireInvalidDabsic",
            "details" => dabsic_form_clean_diagnostic($process["stderr"]),
        ]);
    $data = json_decode((string)$process["stdout"], true);
    if (!is_array($data) || !isset($data[$scope]) || !is_array($data[$scope]))
        return ([
            "ok" => false,
            "error" => "QuestionnaireInvalidDabsic",
            "details" => "mergeconf n'a pas produit le scope ".$scope." attendu.",
        ]);
    return (["ok" => true, "root" => $data[$scope]]);
}

function questionnaire_unknown_keys(array $tree, array $allowed)
{
    $unknown = [];
    foreach (array_keys($tree) as $key)
        if (!in_array((string)$key, $allowed, true))
            $unknown[] = (string)$key;
    return ($unknown);
}

function questionnaire_raw_scope_has_nonliteral_assignment($content, $scope)
{
    $fragment = dabsic_form_extract_root_scope((string)$content, (string)$scope);
    if (!is_string($fragment))
        return (false);

    // mergeconf evaluates Dabsic expressions before returning JSON.  The
    // visual editor cannot reconstruct the original expression from the
    // resulting scalar, so only literal assignments are safe to round-trip.
    // Expressions remain fully usable through the embedded source editor.
    if (!preg_match_all('/^[\t ]*([A-Za-z_][A-Za-z0-9_]*)[\t ]*=[\t ]*(.+?)[\t ]*$/m', $fragment, $matches, PREG_SET_ORDER))
        return (false);
    foreach ($matches as $match)
    {
        $rhs = trim((string)$match[2]);
        if (preg_match('/^(?:true|false|NULL)$/D', $rhs))
            continue ;
        if (preg_match('/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D', $rhs))
            continue ;
        if (preg_match('/^"(?:\\\\.|[^"\\\\])*"$/D', $rhs))
            continue ;
        return (true);
    }
    return (false);
}

function questionnaire_unmanaged_top_level_content($content)
{
    $remaining = (string)$content;
    foreach (["Questionnaire", "FormGroup"] as $scope)
    {
        $fragment = dabsic_form_extract_root_scope($remaining, $scope);
        if ($fragment === NULL)
            continue ;
        $position = strpos($remaining, $fragment);
        if ($position !== false)
            $remaining = substr($remaining, 0, $position)
                .str_repeat(" ", strlen($fragment))
                .substr($remaining, $position + strlen($fragment));
    }

    // Dabsic comments begin with an apostrophe.  Ignore comment-only lines and
    // whitespace; everything else is content the V2 visual serializer does not
    // own and therefore must never overwrite.
    $remaining = preg_replace('/^[\t ]*\'.*$/m', '', $remaining);
    return (trim((string)$remaining));
}

function questionnaire_parse_content($content, $reference = "")
{
    $content = (string)$content;
    $meta = questionnaire_mergeconf_scope($content, "Questionnaire", $reference);
    if (!$meta["ok"])
        return ($meta);
    $groups = questionnaire_mergeconf_scope($content, "FormGroup", $reference);
    if (!$groups["ok"])
        return ($groups);
    if (!count($meta["root"]))
        return (["ok" => false, "error" => "QuestionnaireMissingDefinition", "details" => "Scope [Questionnaire] absent."]);

    $root = $meta["root"];
    $codename = questionnaire_safe_codename(questionnaire_simple_scalar($root["Codename"] ?? "questionnaire", "questionnaire")) ?: "questionnaire";
    $model = questionnaire_default_model(
        $codename,
        questionnaire_simple_scalar($root["Name"] ?? $codename, $codename),
        questionnaire_simple_scalar($root["Description"] ?? "", "")
    );
    $model["format_version"] = (int)questionnaire_simple_scalar($root["FormatVersion"] ?? 0, 0);
    $model["minimum_percent"] = questionnaire_normalize_percent(questionnaire_simple_scalar($root["MinimumPercent"] ?? 0, 0));
    $model["medals"] = questionnaire_string_list($root["Medals"] ?? []);
    $model["groups"] = [];
    $reasons = [];
    if ($model["format_version"] !== 2)
        $reasons[] = "Questionnaire.FormatVersion.unsupported";

    if (questionnaire_unmanaged_top_level_content($content) !== "")
        $reasons[] = "TopLevel.unmanaged";

    // mergeconf deliberately expands @include/@insert.  The visual editor must
    // not silently flatten that factorisation when it serializes the model.
    // Such a file remains fully editable in the embedded source editor.
    foreach (["Questionnaire", "FormGroup"] as $scope_name)
    {
        $raw_scope = dabsic_form_extract_root_scope($content, $scope_name);
        if (is_string($raw_scope) && preg_match('/(^|[\s\[\{])@[A-Za-z_][A-Za-z0-9_]*/m', $raw_scope))
            $reasons[] = $scope_name.".@directive";
    }

    // Expressions are valid Dabsic and intentionally supported in source mode,
    // but mergeconf only gives PHP their evaluated value.  Flag them as
    // advanced so a visual save can never flatten them into literals.
    foreach (["Questionnaire", "FormGroup"] as $scope_name)
        if (questionnaire_raw_scope_has_nonliteral_assignment($content, $scope_name))
            $reasons[] = $scope_name.".expression";

    foreach (["FormatVersion", "Codename", "Name", "Description", "MinimumPercent"] as $key)
        if (array_key_exists($key, $root) && !is_scalar($root[$key]) && $root[$key] !== NULL)
            $reasons[] = "Questionnaire.".$key.".expression";
    if (!questionnaire_simple_list($root["Medals"] ?? []))
        $reasons[] = "Questionnaire.Medals.expression";

    foreach (questionnaire_unknown_keys($root, ["FormatVersion", "Codename", "Name", "Description", "MinimumPercent", "Medals"]) as $key)
        $reasons[] = "Questionnaire.".$key;

    foreach ($groups["root"] as $group_key => $tree)
    {
        $group_key = (string)$group_key;
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $group_key) || !is_array($tree))
        {
            $reasons[] = "FormGroup.".$group_key;
            continue ;
        }
        foreach (questionnaire_unknown_keys($tree, ["Label", "MinimumPercent", "Medals", "Fields"]) as $key)
            $reasons[] = "FormGroup.".$group_key.".".$key;
        foreach (["Label", "MinimumPercent"] as $key)
            if (array_key_exists($key, $tree) && !is_scalar($tree[$key]) && $tree[$key] !== NULL)
                $reasons[] = "FormGroup.".$group_key.".".$key.".expression";
        if (!questionnaire_simple_list($tree["Medals"] ?? []))
            $reasons[] = "FormGroup.".$group_key.".Medals.expression";
        $group = [
            "key" => $group_key,
            "label" => trim((string)questionnaire_simple_scalar($tree["Label"] ?? $group_key, $group_key)) ?: $group_key,
            "minimum_percent" => questionnaire_normalize_percent(questionnaire_simple_scalar($tree["MinimumPercent"] ?? 0, 0)),
            "medals" => questionnaire_string_list($tree["Medals"] ?? []),
            "questions" => [],
        ];
        $fields = $tree["Fields"] ?? [];
        if (!is_array($fields))
        {
            $reasons[] = "FormGroup.".$group_key.".Fields";
            $fields = [];
        }
        foreach ($fields as $question_key => $question_tree)
        {
            $question_key = (string)$question_key;
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $question_key) || !is_array($question_tree))
            {
                $reasons[] = "FormGroup.".$group_key.".Fields.".$question_key;
                continue ;
            }
            foreach (questionnaire_unknown_keys($question_tree, ["Label", "Type", "Required", "Points", "Policy", "Penalty", "Choices", "ChoiceValues", "Correct", "Medals"]) as $key)
                $reasons[] = "FormGroup.".$group_key.".Fields.".$question_key.".".$key;
            foreach (["Label", "Type", "Required", "Points", "Policy", "Penalty"] as $key)
                if (array_key_exists($key, $question_tree) && !is_scalar($question_tree[$key]) && $question_tree[$key] !== NULL)
                    $reasons[] = "FormGroup.".$group_key.".Fields.".$question_key.".".$key.".expression";
            foreach (["Choices", "ChoiceValues", "Correct", "Medals"] as $key)
                if (!questionnaire_simple_list($question_tree[$key] ?? []))
                    $reasons[] = "FormGroup.".$group_key.".Fields.".$question_key.".".$key.".expression";
            // Nested scopes inside a field are a valid advanced Dabsic use,
            // but the V2 visual editor must not flatten/destroy them.
            foreach ($question_tree as $key => $value)
                if (is_array($value) && !in_array((string)$key, ["Choices", "ChoiceValues", "Correct", "Medals"], true))
                    $reasons[] = "FormGroup.".$group_key.".Fields.".$question_key.".".$key;

            $type = strtolower(trim((string)questionnaire_simple_scalar($question_tree["Type"] ?? "text", "text")));
            if (!in_array($type, ["text", "textarea", "radio", "checkbox", "scale"], true))
            {
                $reasons[] = "Type:".$question_key;
                $type = "text";
            }
            $policy = strtolower(trim((string)questionnaire_simple_scalar($question_tree["Policy"] ?? "exact", "exact")));
            if (!in_array($policy, ["exact", "penalty"], true))
            {
                $reasons[] = "Policy:".$question_key;
                $policy = "exact";
            }
            $group["questions"][] = [
                "key" => $question_key,
                "label" => trim((string)questionnaire_simple_scalar($question_tree["Label"] ?? $question_key, $question_key)) ?: $question_key,
                "type" => $type,
                "required" => !empty(questionnaire_simple_scalar($question_tree["Required"] ?? false, false)),
                "points" => is_numeric(questionnaire_simple_scalar($question_tree["Points"] ?? NULL, NULL)) ? (float)$question_tree["Points"] : 1,
                "policy" => $policy,
                "penalty" => is_numeric(questionnaire_simple_scalar($question_tree["Penalty"] ?? NULL, NULL)) ? (float)$question_tree["Penalty"] : 1,
                // Keep the two V2 sequences literal here. The common field
                // contract applies the sole implicit convention (an entirely
                // omitted scale means 1..5) and rejects incomplete pairings.
                "choices" => form_field_sequence($question_tree["Choices"] ?? []),
                "choice_values" => form_field_sequence($question_tree["ChoiceValues"] ?? []),
                "correct" => $type == "scale" ? [] : form_field_sequence($question_tree["Correct"] ?? []),
                "medals" => questionnaire_string_list($question_tree["Medals"] ?? []),
            ];
        }
        $model["groups"][] = $group;
    }
    if (!count($model["groups"]))
        $model["groups"] = questionnaire_default_model()["groups"];
    foreach ($model["groups"] as &$parsed_group)
        foreach ($parsed_group["questions"] as &$parsed_question)
        {
            $field_definition = form_field_definition($parsed_question);
            $parsed_question["choices"] = $field_definition["choices"];
            $parsed_question["choice_values"] = $field_definition["choice_values"];
            if (!$field_definition["valid"])
                foreach ($field_definition["errors"] as $field_error)
                    $reasons[] = "FormGroup.".$parsed_group["key"].".Fields.".$parsed_question["key"].".".$field_error;
            if (in_array($parsed_question["type"], ["radio", "checkbox"], true))
            {
                $correct_values = form_field_sequence($parsed_question["correct"] ?? []);
                if (count($correct_values) !== count(array_unique($correct_values, SORT_STRING)))
                    $reasons[] = "FormGroup.".$parsed_group["key"].".Fields.".$parsed_question["key"].".Correct.duplicate";
                if ($parsed_question["type"] === "radio" && count($correct_values) > 1)
                    $reasons[] = "FormGroup.".$parsed_group["key"].".Fields.".$parsed_question["key"].".Correct.multiple";
                foreach ($correct_values as $correct_value)
                    if (!in_array($correct_value, $parsed_question["choice_values"], true))
                        $reasons[] = "FormGroup.".$parsed_group["key"].".Fields.".$parsed_question["key"].".Correct.unknown_value";
            }
        }
    unset($parsed_group, $parsed_question);
    $model["advanced"] = count($reasons) > 0;
    $model["advanced_reasons"] = array_values(array_unique($reasons));
    return (["ok" => true, "model" => $model]);
}

function questionnaire_load_model($questionnaire)
{
    if (!is_array($questionnaire))
        $questionnaire = questionnaire_get($questionnaire);
    if (!is_array($questionnaire))
        return (["ok" => false, "error" => "QuestionnaireNotFound"]);
    $reference = (string)$questionnaire["reference"];
    $resolved = dabsic_editor_resolve_file($reference, false, ["dab"]);
    if (!$resolved["ok"])
        return ($resolved);
    $content = @file_get_contents($resolved["absolute"]);
    if ($content === false)
        return (["ok" => false, "error" => "DabsicEditorCannotRead"]);
    $parsed = questionnaire_parse_content($content, $resolved["absolute"]);
    if (!$parsed["ok"])
        return ($parsed);
    $parsed["hash"] = hash("sha256", $content);
    $parsed["content"] = $content;
    $parsed["reference"] = $reference;
    return ($parsed);
}

function questionnaire_create($id_school, $codename, $name, $description = "", $directory = "quiz")
{
    global $Database;
    global $User;

    $id_school = (int)$id_school;
    if (!questionnaire_school_can_manage($id_school))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $codename = questionnaire_safe_codename($codename);
    if ($codename == "")
        return (["ok" => false, "error" => "QuestionnaireInvalidCodename"]);
    $school = db_select_one("id, codename FROM school WHERE id = $id_school AND deleted IS NULL");
    if (!is_array($school))
        return (["ok" => false, "error" => "QuestionnaireInvalidSchool"]);
    $directory = questionnaire_safe_resource_directory($directory);
    $reference = questionnaire_reference_for($school["codename"], $codename, $directory === NULL ? "quiz" : $directory);
    $absolute_dir = questionnaire_school_dir($school["codename"]);
    if ($reference === NULL || $absolute_dir === NULL || $directory === NULL)
        return (["ok" => false, "error" => "QuestionnaireCannotCreate"]);
    $school_root = realpath($absolute_dir);
    $absolute_dir .= ($directory == "" ? "" : "/".$directory);
    if (!is_dir($absolute_dir) && !@mkdir($absolute_dir, 0750, true))
        return (["ok" => false, "error" => "QuestionnaireCannotCreate"]);
    $real_directory = realpath($absolute_dir);
    if ($school_root === false || $real_directory === false
        || ($real_directory !== $school_root && strncmp($real_directory, rtrim($school_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, strlen(rtrim($school_root, DIRECTORY_SEPARATOR)) + 1) !== 0))
        return (["ok" => false, "error" => "QuestionnaireCannotCreate"]);
    $absolute_dir = $real_directory;
    $absolute = $absolute_dir."/".$codename.".dab";
    if (file_exists($absolute))
        return (["ok" => false, "error" => "QuestionnaireAlreadyExists"]);
    $ecode = $Database->real_escape_string($codename);
    if (db_select_one("id FROM quiz WHERE id_school = $id_school AND codename = '$ecode' AND deleted IS NULL") != NULL)
        return (["ok" => false, "error" => "QuestionnaireAlreadyExists"]);

    $model = questionnaire_default_model($codename, $name, $description);
    $content = questionnaire_serialize_model($model);
    $validation = dabsic_editor_validate_content($content);
    if (!$validation["ok"])
        return ($validation);
    if (@file_put_contents($absolute, $content, LOCK_EX) === false)
        return (["ok" => false, "error" => "QuestionnaireCannotCreate"]);
    @chmod($absolute, 0640);

    $eref = $Database->real_escape_string($reference);
    $creator = is_array($User) ? (int)$User["id"] : 0;
    $query = "INSERT INTO quiz (id_school, codename, reference, id_creator) VALUES ($id_school, '$ecode', '$eref', ".($creator > 0 ? $creator : "NULL").")";
    if ($Database->query($query) === NULL)
    {
        @unlink($absolute);
        return (["ok" => false, "error" => "QuestionnaireCannotCreate"]);
    }
    return (["ok" => true, "id" => (int)$Database->insert_id]);
}


function questionnaire_validate_model(array $model)
{
    foreach ($model["groups"] ?? [] as $group)
        foreach ($group["questions"] ?? [] as $question)
        {
            $field = form_field_definition($question);
            if (!$field["valid"])
                return ([
                    "ok" => false,
                    "error" => "QuestionnaireInvalidChoices",
                    "details" => (string)($question["key"] ?? "?").": ".implode(", ", $field["errors"]),
                ]);
            if (in_array($field["type"], ["radio", "checkbox"], true))
            {
                $correct_values = form_field_sequence($question["correct"] ?? []);
                if (count($correct_values) !== count(array_unique($correct_values, SORT_STRING)))
                    return ([
                        "ok" => false,
                        "error" => "QuestionnaireInvalidChoices",
                        "details" => (string)($question["key"] ?? "?").": duplicate correct value",
                    ]);
                if ($field["type"] === "radio" && count($correct_values) > 1)
                    return ([
                        "ok" => false,
                        "error" => "QuestionnaireInvalidChoices",
                        "details" => (string)($question["key"] ?? "?").": a radio question can have at most one correct value",
                    ]);
                foreach ($correct_values as $correct)
                    if (!in_array($correct, $field["choice_values"], true))
                        return ([
                            "ok" => false,
                            "error" => "QuestionnaireInvalidChoices",
                            "details" => (string)($question["key"] ?? "?").": correct value ".$correct." is not a ChoiceValue",
                        ]);
            }
        }
    return (["ok" => true]);
}

function questionnaire_save_visual($id, array $model, $expected_hash)
{
    global $Database;

    $questionnaire = questionnaire_get($id);
    if (!questionnaire_can_manage($questionnaire))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $current = questionnaire_load_model($questionnaire);
    if (!$current["ok"])
        return ($current);
    if (!empty($current["model"]["advanced"]))
        return (["ok" => false, "error" => "QuestionnaireAdvancedSource"]);
    if (!is_string($expected_hash) || !hash_equals(strtolower($current["hash"]), strtolower($expected_hash)))
        return (["ok" => false, "error" => "DabsicEditorConflict"]);

    $model = questionnaire_normalize_model($model, $questionnaire["codename"]);
    $validation = questionnaire_validate_model($model);
    if (!$validation["ok"])
        return ($validation);
    $content = questionnaire_serialize_model($model);
    $result = dabsic_editor_save_file($questionnaire["reference"], $content, $current["hash"]);
    if (!$result["ok"])
        return ($result);
    $Database->query("UPDATE quiz SET updated_at = CURRENT_TIMESTAMP WHERE id = ".(int)$questionnaire["id"]);
    $result["model"] = $model;
    return ($result);
}

function questionnaire_save_source($id, $content, $expected_hash)
{
    global $Database;

    $questionnaire = questionnaire_get($id);
    if (!questionnaire_can_manage($questionnaire))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $absolute_reference = dirname(__DIR__)."/".$questionnaire["reference"];
    $parsed = questionnaire_parse_content((string)$content, $absolute_reference);
    if (!$parsed["ok"])
        return ($parsed);
    $metadata = questionnaire_mergeconf_scope((string)$content, "Questionnaire", $absolute_reference);
    $source_codename = $metadata["ok"]
        ? trim((string)questionnaire_simple_scalar($metadata["root"]["Codename"] ?? "", ""))
        : "";
    if ($source_codename !== $questionnaire["codename"])
        return ([
            "ok" => false,
            "error" => "QuestionnaireCodenameMismatch",
            "details" => "Le Codename du scope [Questionnaire] doit rester ".$questionnaire["codename"].".",
        ]);
    $result = dabsic_editor_save_file($questionnaire["reference"], (string)$content, (string)$expected_hash);
    if (!$result["ok"])
        return ($result);
    $Database->query("UPDATE quiz SET updated_at = CURRENT_TIMESTAMP WHERE id = ".(int)$questionnaire["id"]);
    return ($result);
}

function questionnaire_delete($id)
{
    global $Database;

    $questionnaire = questionnaire_get($id);
    if (!questionnaire_can_manage($questionnaire))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $id = (int)$questionnaire["id"];
    if ($Database->query("UPDATE quiz SET deleted = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = $id AND deleted IS NULL") === NULL)
        return (["ok" => false, "error" => "QuestionnaireCannotDelete"]);
    // Keep the source file: future responses/snapshots and manual recovery may
    // still need the exact definition.  Soft-deletion is deliberate.
    return (["ok" => true]);
}

function questionnaire_restore($id)
{
    global $Database;

    $questionnaire = questionnaire_get($id, true);
    if (!is_array($questionnaire) || empty($questionnaire["deleted"]))
        return (["ok" => false, "error" => "QuestionnaireNotFound"]);
    if (!questionnaire_can_manage($questionnaire))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);

    // Refuse to resurrect an entry whose canonical Dabsic source disappeared.
    $resolved = dabsic_editor_resolve_file((string)$questionnaire["reference"], false, ["dab"]);
    if (!$resolved["ok"])
        return ($resolved);

    $id = (int)$questionnaire["id"];
    if ($Database->query("UPDATE quiz SET deleted = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = $id AND deleted IS NOT NULL") === NULL)
        return (["ok" => false, "error" => "QuestionnaireCannotRestore"]);
    return (["ok" => true, "id" => $id]);
}

function questionnaire_duplicate($id, $new_codename, $new_name = "")
{
    $questionnaire = questionnaire_get($id);
    if (!questionnaire_can_manage($questionnaire))
        return (["ok" => false, "error" => "QuestionnaireForbidden"]);
    $loaded = questionnaire_load_model($questionnaire);
    if (!$loaded["ok"])
        return ($loaded);
    if (!empty($loaded["model"]["advanced"]))
        return (["ok" => false, "error" => "QuestionnaireAdvancedSource"]);

    $new_codename = questionnaire_safe_codename($new_codename);
    if ($new_codename == "")
        return (["ok" => false, "error" => "QuestionnaireInvalidCodename"]);
    $new_name = trim((string)$new_name);
    if ($new_name == "")
        $new_name = ($loaded["model"]["name"] ?? $new_codename)." (copie)";
    $school_prefix = "dres/quiz/".questionnaire_safe_codename($questionnaire["school_codename"] ?? "")."/";
    $directory = "quiz";
    $reference = str_replace("\\", "/", (string)($questionnaire["reference"] ?? ""));
    if (strncmp($reference, $school_prefix, strlen($school_prefix)) === 0)
        $directory = dirname(substr($reference, strlen($school_prefix)));
    if ($directory == ".")
        $directory = "";
    $created = questionnaire_create($questionnaire["id_school"], $new_codename, $new_name, $loaded["model"]["description"] ?? "", $directory);
    if (!$created["ok"])
        return ($created);
    $copy = questionnaire_get($created["id"]);
    $copy_loaded = questionnaire_load_model($copy);
    if (!$copy_loaded["ok"])
    {
        questionnaire_delete($created["id"]);
        return ($copy_loaded);
    }
    $model = $loaded["model"];
    $model["codename"] = $new_codename;
    $model["name"] = $new_name;
    $saved = questionnaire_save_visual($created["id"], $model, $copy_loaded["hash"]);
    if (!$saved["ok"])
    {
        questionnaire_delete($created["id"]);
        return ($saved);
    }
    return (["ok" => true, "id" => (int)$created["id"]]);
}

function questionnaire_reference_can_manage($reference, $for_write = false)
{
    global $Database;

    $reference = dabsic_editor_normalize_requested_path($reference);
    if ($reference === NULL || strncmp($reference, "dres/quiz/", strlen("dres/quiz/")) !== 0)
        return (false);
    $tail = substr($reference, strlen("dres/quiz/"));
    $parts = explode("/", $tail);
    if (count($parts) < 2)
        return (false);
    $school_codename = questionnaire_safe_codename($parts[0]);
    if ($school_codename == "" || $school_codename !== strtolower($parts[0]))
        return (false);

    // The requested alias is not sufficient for authorization: reject a
    // filesystem symlink that would escape the school's quiz resource tree.
    $project_root = dabsic_editor_project_root();
    $school_root = $project_root === false ? false : realpath($project_root."/dres/quiz/".$school_codename);
    $target = $project_root === false ? false : realpath($project_root."/".$reference);
    if ($school_root === false || $target === false
        || ($target !== $school_root && strncmp($target, rtrim($school_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, strlen(rtrim($school_root, DIRECTORY_SEPARATOR)) + 1) !== 0))
        return (false);

    $escaped = $Database->real_escape_string($school_codename);
    $school = db_select_one("id FROM school WHERE codename = '$escaped' AND deleted IS NULL");
    return (is_array($school) && questionnaire_school_can_manage((int)$school["id"]));
}

if (function_exists("dabsic_editor_register_access_resolver"))
    dabsic_editor_register_access_resolver("questionnaire_reference_can_manage");

function questionnaire_model_counts(array $model)
{
    $groups = count($model["groups"] ?? []);
    $questions = 0;
    foreach ($model["groups"] ?? [] as $group)
        $questions += is_array($group["questions"] ?? NULL) ? count($group["questions"]) : 0;
    return (["groups" => $groups, "questions" => $questions]);
}
