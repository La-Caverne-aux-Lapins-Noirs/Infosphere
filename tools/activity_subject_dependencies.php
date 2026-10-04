<?php

require_once (__DIR__."/dabsic_dependencies.php");

function activity_subject_dependency_is_dynamic($content, array $reference)
{
    $offset = (int)($reference["offset"] ?? 0) + (int)($reference["length"] ?? 0);
    $length = strlen((string)$content);
    while ($offset < $length && preg_match('/\s/', $content[$offset]))
        ++$offset;
    return ($offset < $length && $content[$offset] == '#');
}

function activity_subject_dependency_label($path, $configuration_file, $correction_root)
{
    $path = dabsic_dependency_normalize_path((string)$path);
    $configuration_file = dabsic_dependency_normalize_path((string)$configuration_file);
    $correction_root = rtrim(dabsic_dependency_normalize_path((string)$correction_root), "/");

    if ($path === $configuration_file)
        return ("configuration.dab");
    $prefix = $correction_root."/";
    if (strncmp($path, $prefix, strlen($prefix)) == 0)
        return (substr($path, strlen($prefix)));
    return (basename($path));
}

function activity_subject_dependencies($configuration_file, $max_depth = 32, $correction_root = NULL)
{
    $result = [
        "available" => false,
        "missing" => [],
        "resolved" => [],
        "dynamic" => 0,
    ];

    $configuration_file = realpath((string)$configuration_file);
    if ($configuration_file === false || !is_file($configuration_file))
        return ($result);

    $result["available"] = true;
    $configuration_file = dabsic_dependency_normalize_path($configuration_file);
    if ($correction_root === NULL)
        $correction_root = dirname(__DIR__)."/dres/corrections";
    $correction_real = realpath($correction_root);
    if ($correction_real !== false)
        $correction_root = dabsic_dependency_normalize_path($correction_real);
    else
        $correction_root = dabsic_dependency_normalize_path($correction_root);

    $visited = [];
    $missing = [];
    $resolved = [];
    $max_depth = max(0, min(64, (int)$max_depth));

    $walk = function($file, $depth) use (
        &$walk,
        &$visited,
        &$missing,
        &$resolved,
        &$result,
        $configuration_file,
        $correction_root,
        $max_depth
    ) {
        $file = dabsic_dependency_normalize_path($file);
        if (isset($visited[$file]) || $depth > $max_depth)
            return ;
        $visited[$file] = true;

        $content = @file_get_contents($file);
        if ($content === false)
            return ;

        foreach (dabsic_dependency_static_references($content, []) as $reference)
        {
            if (activity_subject_dependency_is_dynamic($content, $reference))
            {
                $result["dynamic"] += 1;
                continue ;
            }

            $requested = (string)$reference["requested_path"];
            $target = dabsic_dependency_resolve($file, $requested, [$correction_root]);
            $source_label = activity_subject_dependency_label(
                $file,
                $configuration_file,
                $correction_root
            );

            if ($target === NULL)
            {
                $key = $source_label."\n".$requested;
                if (!isset($missing[$key]))
                {
                    $missing[$key] = [
                        "requested_path" => $requested,
                        "source" => $source_label,
                        "directive" => (string)$reference["directive"],
                    ];
                }
                continue ;
            }

            $target = dabsic_dependency_normalize_path($target);
            $resolved[$target] = true;
            $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            if (($extension === "dab" || $extension === "dabsic") && $depth < $max_depth)
                $walk($target, $depth + 1);
        }
    };

    $walk($configuration_file, 0);
    $result["missing"] = array_values($missing);
    usort($result["missing"], function($a, $b) {
        $cmp = strcmp($a["requested_path"], $b["requested_path"]);
        if ($cmp != 0)
            return ($cmp);
        return (strcmp($a["source"], $b["source"]));
    });
    $result["resolved"] = array_keys($resolved);
    sort($result["resolved"]);
    return ($result);
}

function render_activity_subject_dependencies($configuration_file)
{
    $status = activity_subject_dependencies($configuration_file);
    if (!$status["available"])
        return ("");

    ob_start();
    if (!count($status["missing"]))
    {
        ?>
<div class="activity_subject_dependencies activity_subject_dependencies_ok">
    <strong>✓ Dépendances du sujet complètes</strong>
    <?php if (count($status["resolved"])) { ?>
        <span><?=count($status["resolved"]); ?> fichier(s) résolu(s)</span>
    <?php } ?>
    <?php if ($status["dynamic"] > 0) { ?>
        <small><?=$status["dynamic"]; ?> référence(s) calculée(s) non vérifiable(s) statiquement</small>
    <?php } ?>
</div>
        <?php
    }
    else
    {
        ?>
<details class="activity_subject_dependencies activity_subject_dependencies_error" open>
    <summary>
        <strong>⚠ Dépendances du sujet incomplètes</strong>
        — <?=count($status["missing"]); ?> fichier(s) introuvable(s)
    </summary>
    <ul>
        <?php foreach ($status["missing"] as $missing) { ?>
        <li>
            <code><?=htmlspecialchars($missing["requested_path"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            <span>
                depuis
                <code><?=htmlspecialchars($missing["source"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></code>
            </span>
        </li>
        <?php } ?>
    </ul>
    <?php if ($status["dynamic"] > 0) { ?>
        <small><?=$status["dynamic"]; ?> référence(s) calculée(s) ne peuvent pas être vérifiée(s) statiquement.</small>
    <?php } ?>
</details>
        <?php
    }
    return (ob_get_clean());
}
