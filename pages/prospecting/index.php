<?php

$class_level = [
    "Autre", "CM1", "CM2", "6ème",
    "5ème", "4ème", "3ème",
    "Seconde", "Première",
    "Terminale", "Bac+1",
    "Bac+2", "Bac+3",
    "Bac+4", "Bac+5",
    "Bac+6", "Bac+7",
    "Bac+8", "Reconversion",
    "?"
];

$target_class = [
    "/", "EF1",
    "EF2", "EF3",
    "EF4", "EF5",
    "EF2X", "EF3X",
];

$target_entry = [
    "Septembre",
    "Janvier",
    "Avril",
];

$fields = [
    [
        "name"  => "id",
        "label" => "#",
        "type"  => "number",
        "width" => "50px",
        "raw"   => fn($p) => $p["id"],
        "render"=> fn($p) => $p["id"],
	"copyable" => true,
    ],
    [
        "name"  => "first_name.family_name",
        "label" => $Dictionnary["Name"],
        "type"  => "text",
        "raw"   => fn($p) => $p["first_name"].".".$p["family_name"] ?? "",
	"copyable" => true,
        "render"=> fn($p) =>
	    htmlspecialchars($p["first_name"] ?? "").
			   " ".
			   htmlspecialchars($p["family_name"] ?? ""),
    ],
    [
        "name"  => "registration_date",
        "label" => $Dictionnary["RegisterDate"],
        "type"  => "number",
        "raw"   => fn($p) => $p["registration_date"] ?? 0,
        "render"=> fn($p) => datex("d/m/Y", $p["registration_date"]),
    ],
    [
        "name"  => "mail",
        "label" => $Dictionnary["Mail"],
        "type"  => "text",
        "raw"   => fn($p) => $p["mail"] ?? "",
        "render"=> fn($p) => htmlspecialchars($p["mail"] ?? ""),
	"copyable" => true,
    ],
    [
        "name"  => "phone",
        "label" => $Dictionnary["Phone"],
        "type"  => "text",
        "raw"   => fn($p) => $p["phone"] ?? "",
        "render"=> fn($p) => htmlspecialchars(format_phone_number($p["phone"] ?? "")),
	"copyable" => true,
    ],
    [
        "name"    => "current_class",
        "label"   => $Dictionnary["CurrentClass"],
        "type"    => "select",
        "options" => array_combine(
            array_map(fn($i) => (string)($i - 9), array_keys($class_level)),
            $class_level
        ),
        "raw"     => fn($p) => isset($p["current_class"]) ? (int)$p["current_class"] : 10,
        "render"  => function($p) use ($class_level)
        {
            $v = isset($p["current_class"]) ? (int)$p["current_class"] : 19;
	    $v = real_class_level($p["registration_date"], $v);
            return htmlspecialchars($class_level[$v + 9] ?? "?");
        },
    ],
    [
        "name"    => "target_class",
        "label"   => $Dictionnary["TargetedClass"],
        "type"    => "select",
        "options" => $target_class,
        "raw"     => fn($p) => isset($p["target_class"]) ? (int)$p["target_class"] : 0,
        "render"  => function($p) use ($target_class)
        {
            $v = isset($p["target_class"]) ? (int)$p["target_class"] : 0;
            return htmlspecialchars($target_class[$v] ?? "/");
        },
    ],
    [
        "name"    => "target_entry",
        "label"   => $Dictionnary["TargetedEntry"],
        "type"    => "select",
        "options" => $target_entry,
        "raw"     => fn($p) => isset($p["target_entry"]) ? (int)$p["target_entry"] : 0,
        "render"  => function($p) use ($target_entry)
        {
            $v = isset($p["target_entry"]) ? (int)$p["target_entry"] : 0;
            return htmlspecialchars($target_entry[$v] ?? "Septembre");
        },
    ],
    [
        "name"   => "actions_1",
        "label"  => $Dictionnary["Actions"],
        "type"   => "misc",
	"width"  => "auto",
        "render" => function($p) {
	    global $Dictionnary;
	    ob_start();
	    require ("actions.php");
	    return (ob_get_clean());
	}
    ],
    [
        "name"    => "actions_2",
        "label"   => "",
        "type"    => "misc",
        "width"   => "170px",
        "render"  => function($p) {
	    global $Dictionnary;
	    ob_start();
	    require ("buttons.php");
	    return (ob_get_clean());
	}
    ],
];

?>

<h1>Gestion des prospects</h1>

<style><?php require ("style.css"); ?></style>
<script><?php require ("script.js"); ?></script>

<?php
render_dynamic_table("prospects_table", $fields, fetch_prospects());


