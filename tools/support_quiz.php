<?php

require_once (__DIR__."/quiz_entrypoint.php");

/*
** Support pre-access convention
** -----------------------------
** dres/support/<category>/<support>/preaccess.dab protects the assets of that
** support. Nothing more generic is inferred from it: the support page and the
** dres bouncer explicitly call these helpers because *their* context gives the
** filename that meaning.
*/

function support_quiz_context($support)
{
    $id_support = is_array($support) ? (int)($support["id"] ?? 0) : (int)$support;
    if ($id_support <= 0)
        return (NULL);
    return (db_select_one("
        support.id,
        support.codename,
        support.id_support_category,
        support_category.codename AS category_codename
        FROM support
        INNER JOIN support_category ON support_category.id = support.id_support_category
        WHERE support.id = $id_support
          AND support.deleted IS NULL
          AND support_category.deleted IS NULL
    "));
}

function support_quiz_entrypoint_path($support)
{
    global $Configuration;

    $context = support_quiz_context($support);
    if (!is_array($context))
        return (NULL);
    $root = rtrim((string)($Configuration->_SupportDir ?? "dres/support/"), "/")."/";
    return ($root.$context["category_codename"]."/".$context["codename"]."/preaccess.dab");
}

function support_quiz_entrypoint_files($support)
{
    $path = support_quiz_entrypoint_path($support);
    if ($path === NULL || !is_file($path))
        return ([]);
    return ([realpath($path) ?: $path]);
}

function support_quiz_entrypoint_quizzes($support)
{
    return (quiz_entrypoint_quizzes_from_files(support_quiz_entrypoint_files($support)));
}

function support_quiz_add($support, $id_quiz)
{
    $path = support_quiz_entrypoint_path($support);
    if ($path === NULL)
        return (["ok" => false, "error" => "QuizSupportEntrypointCannotWrite"]);
    $ret = quiz_entrypoint_add($path, (int)$id_quiz, "Infosphere support pre-access");
    if (!$ret["ok"] && ($ret["error"] ?? "") === "QuizEntrypointCannotWrite")
        $ret["error"] = "QuizSupportEntrypointCannotWrite";
    return ($ret);
}

function support_quiz_remove($support, $id_quiz)
{
    $path = support_quiz_entrypoint_path($support);
    if ($path === NULL)
        return (["ok" => true, "changed" => false]);
    $ret = quiz_entrypoint_remove($path, (int)$id_quiz);
    if (!$ret["ok"] && ($ret["error"] ?? "") === "QuizEntrypointCannotWrite")
        $ret["error"] = "QuizSupportEntrypointCannotWrite";
    return ($ret);
}

function support_quiz_sync($support, array $quiz_ids)
{
    $wanted = [];
    foreach ($quiz_ids as $id_quiz)
    {
        $id_quiz = (int)$id_quiz;
        if ($id_quiz > 0)
            $wanted[$id_quiz] = $id_quiz;
    }
    $current = [];
    foreach (support_quiz_entrypoint_quizzes($support) as $quiz)
        $current[(int)$quiz["id"]] = (int)$quiz["id"];

    foreach ($current as $id_quiz)
        if (!isset($wanted[$id_quiz]))
        {
            $ret = support_quiz_remove($support, $id_quiz);
            if (!$ret["ok"])
                return ($ret);
        }
    foreach ($wanted as $id_quiz)
        if (!isset($current[$id_quiz]))
        {
            $ret = support_quiz_add($support, $id_quiz);
            if (!$ret["ok"])
                return ($ret);
        }
    return (["ok" => true]);
}

function support_preaccess_context_reference($support)
{
    $context = support_quiz_context($support);
    return (is_array($context) ? "support#".(int)$context["id"] : NULL);
}

function support_preaccess_status($support, $id_user)
{
    $context = support_quiz_context($support);
    if (!is_array($context))
        return (["configured" => false, "passed" => true, "quizzes" => [], "next" => NULL]);
    return (quiz_preaccess_context_status(
        support_quiz_entrypoint_quizzes($context),
        (int)$id_user,
        "support_preaccess",
        "support#".(int)$context["id"],
        NULL
    ));
}

function support_preaccess_can_read($support, $id_user)
{
    $status = support_preaccess_status($support, (int)$id_user);
    return (!$status["configured"] || $status["passed"]);
}

function support_quiz_current_user_can_access_support($support)
{
    $context = support_quiz_context($support);
    if (!is_array($context))
        return (false);
    if (can_edit_supports())
        return (true);
    $categories = fetch_my_support_category(false);
    if ($categories == NULL || $categories->is_error())
        return (false);
    foreach ($categories->value as $category)
    {
        if (empty($category["selected"]) || !is_array($category["support"] ?? NULL))
            continue ;
        foreach ($category["support"] as $candidate)
            if (!empty($candidate["selected"]) && (int)($candidate["id"] ?? 0) === (int)$context["id"])
                return (true);
    }
    return (false);
}

function support_preaccess_start($support, $id_user)
{
    $context = support_quiz_context($support);
    $id_user = (int)$id_user;
    if (!is_array($context) || $id_user <= 0 || !support_quiz_current_user_can_access_support($context))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    return (quiz_preaccess_context_start(
        support_quiz_entrypoint_quizzes($context),
        $id_user,
        "support_preaccess",
        "support#".(int)$context["id"],
        NULL
    ));
}


function support_asset_quiz_context($asset)
{
    $id_asset = is_array($asset) ? (int)($asset["id"] ?? 0) : (int)$asset;
    if ($id_asset <= 0)
        return (NULL);
    return (db_select_one("
        support_asset.id,
        support_asset.id_support,
        support_asset.codename,
        support.id_support_category,
        support.codename AS support_codename,
        support_category.codename AS category_codename
        FROM support_asset
        INNER JOIN support ON support.id = support_asset.id_support
        INNER JOIN support_category ON support_category.id = support.id_support_category
        WHERE support_asset.id = $id_asset
          AND support_asset.deleted IS NULL
          AND support.deleted IS NULL
          AND support_category.deleted IS NULL
    "));
}

function support_asset_quiz_entrypoint_path($asset)
{
    global $Configuration;

    $context = support_asset_quiz_context($asset);
    if (!is_array($context))
        return (NULL);
    $root = rtrim((string)($Configuration->_SupportDir ?? "dres/support/"), "/")."/";
    return ($root.$context["category_codename"]."/".$context["support_codename"]."/.asset/".$context["codename"]."/preaccess.dab");
}

function support_asset_quiz_entrypoint_files($asset)
{
    $path = support_asset_quiz_entrypoint_path($asset);
    if ($path === NULL || !is_file($path))
        return ([]);
    return ([realpath($path) ?: $path]);
}

function support_asset_quiz_entrypoint_quizzes($asset)
{
    return (quiz_entrypoint_quizzes_from_files(support_asset_quiz_entrypoint_files($asset)));
}

function support_asset_quiz_add($asset, $id_quiz)
{
    $path = support_asset_quiz_entrypoint_path($asset);
    if ($path === NULL)
        return (["ok" => false, "error" => "QuizSupportAssetEntrypointCannotWrite"]);
    $ret = quiz_entrypoint_add($path, (int)$id_quiz, "Infosphere support asset pre-access");
    if (!$ret["ok"] && ($ret["error"] ?? "") === "QuizEntrypointCannotWrite")
        $ret["error"] = "QuizSupportAssetEntrypointCannotWrite";
    return ($ret);
}

function support_asset_quiz_remove($asset, $id_quiz)
{
    $path = support_asset_quiz_entrypoint_path($asset);
    if ($path === NULL)
        return (["ok" => true, "changed" => false]);
    $ret = quiz_entrypoint_remove($path, (int)$id_quiz);
    if (!$ret["ok"] && ($ret["error"] ?? "") === "QuizEntrypointCannotWrite")
        $ret["error"] = "QuizSupportAssetEntrypointCannotWrite";
    return ($ret);
}

function support_asset_quiz_sync($asset, array $quiz_ids)
{
    $wanted = [];
    foreach ($quiz_ids as $id_quiz)
    {
        $id_quiz = (int)$id_quiz;
        if ($id_quiz > 0)
            $wanted[$id_quiz] = $id_quiz;
    }
    $current = [];
    foreach (support_asset_quiz_entrypoint_quizzes($asset) as $quiz)
        $current[(int)$quiz["id"]] = (int)$quiz["id"];

    foreach ($current as $id_quiz)
        if (!isset($wanted[$id_quiz]))
        {
            $ret = support_asset_quiz_remove($asset, $id_quiz);
            if (!$ret["ok"])
                return ($ret);
        }
    foreach ($wanted as $id_quiz)
        if (!isset($current[$id_quiz]))
        {
            $ret = support_asset_quiz_add($asset, $id_quiz);
            if (!$ret["ok"])
                return ($ret);
        }
    return (["ok" => true]);
}

function support_asset_preaccess_context_reference($asset)
{
    $context = support_asset_quiz_context($asset);
    return (is_array($context) ? "support_asset#".(int)$context["id"] : NULL);
}

function support_asset_preaccess_status($asset, $id_user)
{
    $context = support_asset_quiz_context($asset);
    if (!is_array($context))
        return (["configured" => false, "passed" => true, "quizzes" => [], "next" => NULL]);
    return (quiz_preaccess_context_status(
        support_asset_quiz_entrypoint_quizzes($context),
        (int)$id_user,
        "support_asset_preaccess",
        "support_asset#".(int)$context["id"],
        NULL
    ));
}

function support_asset_preaccess_can_read($asset, $id_user)
{
    $status = support_asset_preaccess_status($asset, (int)$id_user);
    return (!$status["configured"] || $status["passed"]);
}

function support_quiz_current_user_can_access_asset_base($asset)
{
    $context = support_asset_quiz_context($asset);
    if (!is_array($context))
        return (false);
    if (can_edit_supports())
        return (true);
    $categories = fetch_my_support_category(true);
    if ($categories == NULL || $categories->is_error())
        return (false);
    foreach ($categories->value as $category)
    {
        if (empty($category["selected"]) || !is_array($category["support"] ?? NULL))
            continue ;
        foreach ($category["support"] as $support)
        {
            if (empty($support["selected"]) || (int)($support["id"] ?? 0) !== (int)$context["id_support"])
                continue ;
            foreach ((array)($support["asset"] ?? []) as $candidate)
                if (!empty($candidate["selected"]) && (int)($candidate["id"] ?? 0) === (int)$context["id"])
                    return (true);
        }
    }
    return (false);
}

function support_asset_preaccess_start($asset, $id_user)
{
    $context = support_asset_quiz_context($asset);
    $id_user = (int)$id_user;
    if (!is_array($context) || $id_user <= 0)
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (!support_quiz_current_user_can_access_asset_base($context))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    if (!support_preaccess_can_read((int)$context["id_support"], $id_user))
        return (["ok" => false, "error" => "QuizAttemptForbidden"]);
    return (quiz_preaccess_context_start(
        support_asset_quiz_entrypoint_quizzes($context),
        $id_user,
        "support_asset_preaccess",
        "support_asset#".(int)$context["id"],
        NULL
    ));
}

function support_asset_quiz_remove_metadata($asset)
{
    $path = support_asset_quiz_entrypoint_path($asset);
    if ($path === NULL)
        return ;
    if (is_file($path))
        @unlink($path);
    $dir = dirname($path);
    $entries = is_dir($dir) ? @scandir($dir) : false;
    if (is_array($entries) && count(array_diff($entries, [".", ".."])) === 0)
        @rmdir($dir);
    $parent = dirname($dir);
    $entries = is_dir($parent) ? @scandir($parent) : false;
    if (is_array($entries) && count(array_diff($entries, [".", ".."])) === 0)
        @rmdir($parent);
}

function support_quiz_absolute_path($path)
{
    $path = trim((string)$path);
    if ($path === "")
        return (NULL);
    if (substr($path, 0, 1) !== DIRECTORY_SEPARATOR)
        $path = dirname(__DIR__).DIRECTORY_SEPARATOR.ltrim($path, "/");
    $real = realpath($path);
    return ($real === false ? NULL : $real);
}

function support_quiz_path_inside($path, $root)
{
    $path = str_replace("\\", "/", (string)$path);
    $root = rtrim(str_replace("\\", "/", (string)$root), "/");
    return ($path === $root || strncmp($path, $root."/", strlen($root) + 1) === 0);
}

function support_quiz_asset_for_resource_path($absolute, $category_codename = NULL, $support_codename = NULL)
{
    global $Database;

    $absolute = realpath((string)$absolute);
    if ($absolute === false)
        return (NULL);
    $filters = "";
    if ($category_codename !== NULL)
        $filters .= " AND support_category.codename = '".$Database->real_escape_string((string)$category_codename)."' ";
    if ($support_codename !== NULL)
        $filters .= " AND support.codename = '".$Database->real_escape_string((string)$support_codename)."' ";
    $rows = db_select_all("
        support_asset.id,
        support_asset.id_support,
        support_asset.codename,
        support_asset.fr_content,
        support_asset.en_content,
        support.codename AS support_codename,
        support.id_support_category,
        support_category.codename AS category_codename
        FROM support_asset
        INNER JOIN support ON support.id = support_asset.id_support
        INNER JOIN support_category ON support_category.id = support.id_support_category
        WHERE support_asset.deleted IS NULL
          AND support.deleted IS NULL
          AND support_category.deleted IS NULL
          $filters
    ");
    foreach ($rows as $asset)
        foreach (["fr_content", "en_content"] as $field)
        {
            $stored = trim((string)($asset[$field] ?? ""));
            if ($stored === "")
                continue ;
            $source = support_quiz_absolute_path($stored);
            if ($source !== NULL && $source === $absolute)
                return ($asset);

            // HLS derivatives live next to the canonical source and are part of
            // the same pedagogical asset. The source itself may have been
            // replaced, so derive the directory from the stored path even when
            // realpath(source) is unavailable.
            if (function_exists("support_video_hls_directory"))
            {
                $hls = support_video_hls_directory($stored);
                if (substr($hls, 0, 1) !== DIRECTORY_SEPARATOR)
                    $hls = dirname(__DIR__).DIRECTORY_SEPARATOR.ltrim($hls, "/");
                $hls_real = realpath($hls);
                if ($hls_real !== false && support_quiz_path_inside($absolute, $hls_real))
                    return ($asset);
            }
        }
    return (NULL);
}
