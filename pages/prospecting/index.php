<?php


if ($Position == "CampaignMenu")
{
    require_once ("campaign.php");
    return ;
}

$class_level = [
    "Autre", "CM1", "CM2", "6ème",
    "5ème", "4ème", "3ème",
    "Secnd", "Prem",
    "Term", "Bac+1",
    "Bac+2", "Bac+3",
    "Bac+4", "Bac+5",
    "Bac+6", "Bac+7",
    "Bac+8", "Reconv",
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


function prospecting_age_level($p)
{
    $registration = date_to_timestamp($p["registration_date"] ?? NULL);

    if ($registration == NULL)
        return (0);

    $days = max(0, floor((now() - $registration) / (60 * 60 * 24)));

    // Six niveaux mensuels: < 1 mois, 1 mois, 2 mois, 3 mois, 4 mois, 5 mois et plus.
    return (min(5, (int)floor($days / 30)));
}

function prospecting_age_class($p)
{
    return ("prospecting_age_".prospecting_age_level($p));
}

$fields = [
    [
        "name"  => "id",
        "label" => "#",
        "type"  => "number",
        "width" => "50px",
        "raw"   => fn($p) => $p["id"],
        "render"=> fn($p) => $p["id"],
        "cell_class" => fn($p) => prospecting_age_class($p),
	"copyable" => true,
    ],
    [
        "name"  => "first_name.family_name",
        "label" => $Dictionnary["Name"],
        "type"  => "text",
        "raw"   => fn($p) => $p["first_name"].".".$p["family_name"] ?? "",
        "render"=> function($p)
	{
	    $a = htmlspecialchars($p["first_name"] ?? "");
	    $b = htmlspecialchars($p["family_name"] ?? "");
	    $id = $p["id"];
	    return ("<a target='_blank' href='?p=ProfileMenu&amp;a=$id'>$a $b</a>");
	},
        "cell_class" => fn($p) => prospecting_age_class($p),
    ],
    [
        "name"  => "registration_date",
        "label" => $Dictionnary["RegisterDate"],
        "type"  => "number",
        "raw"   => fn($p) => $p["registration_date"] ?? 0,
        "render"=> fn($p) => datex("d/m/Y", $p["registration_date"]),
        "cell_class" => fn($p) => prospecting_age_class($p),
    ],
    [
        "name"  => "mail",
        "label" => $Dictionnary["Mail"],
        "type"  => "text",
	"width" => "150px",
        "raw"   => fn($p) => $p["mail"] ?? "",
        "render"=> fn($p) => htmlspecialchars($p["mail"] ?? ""),
	"copyable" => true,
    ],
    [
        "name"  => "phone",
        "label" => $Dictionnary["Phone"],
        "type"  => "text",
	"width" => "150px",
        "raw"   => fn($p) => $p["phone"] ?? "",
        "render"=> fn($p) => htmlspecialchars(format_phone_number($p["phone"] ?? "")),
	"copyable" => true,
    ],
    [
        "name"    => "current_class",
        "label"   => $Dictionnary["CurrentClass"],
        "type"    => "select",
	"width"   => "70px",
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
	"width"   => "50px",
        "options" => $target_class,
        "raw"     => fn($p) => isset($p["target_class"]) ? (int)$p["target_class"] : 0,
        "render"  => function($p) use ($target_class)
        {
            $v = isset($p["target_class"]) ? (int)$p["target_class"] : 0;
            return (htmlspecialchars($target_class[$v] ?? "/"));
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
            return ucfirst($p["last_school"])."<br/>".htmlspecialchars($target_entry[$v] ?? "Septembre");
        },
    ],
    [
        "name"   => "actions_1",
        "label"  => $Dictionnary["Actions"],
        "type"   => "misc",
	"width"  => "auto",
        "render" => function($p) {
	    global $Dictionnary;
	    global $one_day;

	    ob_start();
	    require ("actions.php");
	    return (ob_get_clean());
	}
    ],
    [
        "name"   => "actions_2",
        "label"  => $Dictionnary["Actions"],
        "type"   => "misc",
	"width"  => "200px",
        "render" => function($p) {
	    global $Dictionnary;
	    global $Configuration;

	    ob_start();
	    require ("files.php");
	    return (ob_get_clean());
	}
    ],
    [
        "name"    => "actions_3",
        "label"   => "",
        "type"    => "misc",
        "width"   => "120px",
        "render"  => function($p) {
	    global $Dictionnary;

	    ob_start();
	    require ("buttons.php");
	    return (ob_get_clean());
	}
    ],
];

?>

<h1 style="display: inline-block; width: auto; margin-right: 50px;">Gestion des prospects</h1>

<style><?php require ("style.css"); ?></style>
<script><?php require ("script.js"); ?></script>
<input
    type="button"
    onclick="document.location='?p=Subscribe&amp;prospect=1&amp;pp=<?=$Position; ?>'"
    value="<?=$Dictionnary["AddProspect"]; ?>"
    style="width: 300px; background-color: #00FF00; color: black; font-weight: bold;"
/>
<input
    type="button"
    onclick="document.location='?p=CampaignMenu&amp;pp=<?=$Position; ?>'"
    value="Campagnes de prospection"
    style="width: 300px; background-color: #FFD700; color: black; font-weight: bold;"
/>

<?php
render_dynamic_table("prospects_table", $fields, fetch_prospects());


