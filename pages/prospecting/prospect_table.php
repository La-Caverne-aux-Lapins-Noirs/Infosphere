<?php

function prospecting_class_levels()
{
    return ([
        "Autre", "CM1", "CM2", "6ème",
        "5ème", "4ème", "3ème",
        "Secnd", "Prem", "Term", "Bac+1",
        "Bac+2", "Bac+3", "Bac+4", "Bac+5",
        "Bac+6", "Bac+7", "Bac+8", "Reconv", "?"
    ]);
}

function prospecting_target_classes()
{
    return ([
        "/", "EF1",
        "EF2", "EF3",
        "EF4", "EF5",
        "EF2X", "EF3X",
    ]);
}

function prospecting_target_entries()
{
    return ([
        "Septembre",
        "Janvier",
        "Avril",
    ]);
}

function prospecting_table_fields()
{
    global $Dictionnary;
    global $Configuration;

    $class_level = prospecting_class_levels();
    $target_class = prospecting_target_classes();
    $target_entry = prospecting_target_entries();

    return ([
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
            "raw"   => fn($p) => ($p["first_name"] ?? "").".".($p["family_name"] ?? ""),
            "render"=> function($p)
            {
                $a = htmlspecialchars($p["first_name"] ?? "");
                $b = htmlspecialchars($p["family_name"] ?? "");
                $id = (int)$p["id"];
                $registration = isset($p["registration_date"])
                    ? datex("d/m/Y", $p["registration_date"])
                    : "";
                $registration = htmlspecialchars($registration);
                return (
                    "<a target='_blank' href='?p=ProfileMenu&amp;a=$id'>$a $b</a>".
                    ($registration != ""
                        ? "<span class='prospect_registration_date'>Inscrit le $registration</span>"
                        : "")
                );
            },
            "cell_class" => "dynamic_table_name prospect_identity_cell"
        ],
        [
            "name"  => "contact",
            "label" => "Contact",
            "type"  => "misc",
            "width" => "145px",
            "filter" => false,
            "render"=> function($p)
            {
                $mail = trim((string)($p["mail"] ?? ""));
                $phone = trim((string)($p["phone"] ?? ""));
                $formatted_phone = $phone != "" ? format_phone_number($phone) : "";
                $parts = [];

                if ($mail != "")
                {
                    $parts[] = "<button type='button' class='prospect_contact_copy prospect_contact_mail' "
                        ."data-prospect-copy-value='".htmlspecialchars($mail, ENT_QUOTES)."' "
                        ."title='Copier l’adresse électronique'>"
                        .htmlspecialchars($mail)."</button>";
                }
                if ($phone != "")
                {
                    $parts[] = "<button type='button' class='prospect_contact_copy prospect_contact_phone' "
                        ."data-prospect-copy-value='".htmlspecialchars($formatted_phone, ENT_QUOTES)."' "
                        ."title='Copier le numéro de téléphone'>"
                        .htmlspecialchars($formatted_phone)."</button>";
                }
                if (!count($parts))
                    return ("<span class='prospect_contact_empty'>—</span>");
                return ("<div class='prospect_contact'>".implode("", $parts)."</div>");
            },
            "cell_class" => "prospect_contact_cell",
        ],
        [
            "name"    => "target_class",
            "label"   => $Dictionnary["TargetedClass"],
            "type"    => "select",
            "width"   => "74px",
            "options" => $target_class,
            "raw"     => fn($p) => isset($p["target_class"]) ? (int)$p["target_class"] : 0,
            "render"  => function($p) use ($target_class, $class_level)
            {
                $target = isset($p["target_class"]) ? (int)$p["target_class"] : 0;
                $current = isset($p["current_class"]) ? (int)$p["current_class"] : 19;
                $current = real_class_level($p["registration_date"], $current);
                $target_label = htmlspecialchars($target_class[$target] ?? "/");
                $current_label = htmlspecialchars($class_level[$current + 9] ?? "?");
                return (
                    "<strong class='prospect_target_class'>$target_label</strong>".
                    "<span class='prospect_current_class'>Niveau actuel : $current_label</span>"
                );
            },
            "cell_class" => "prospect_target_class_cell",
        ],
        [
            "name"    => "target_entry",
            "label"   => $Dictionnary["TargetedEntry"],
            "type"    => "select",
            "width"   => "90px",
            "options" => $target_entry,
            "raw"     => fn($p) => isset($p["target_entry"]) ? (int)$p["target_entry"] : 0,
            "render"  => function($p) use ($target_entry)
            {
                $v = isset($p["target_entry"]) ? (int)$p["target_entry"] : 0;
                return ucfirst($p["last_school"] ?? "")."<br/>".htmlspecialchars($target_entry[$v] ?? "Septembre");
            },
        ],
        [
            "name"   => "actions_1",
            "label"  => $Dictionnary["Actions"],
            "type"   => "misc",
            "width"  => "600px",
            "cell_class" => "prospect_action_history_cell",
            "render" => function($p) {
                global $Dictionnary;
                global $one_day;

                ob_start();
                require (__DIR__."/actions.php");
                return (ob_get_clean());
            }
        ],
        [
            "name"   => "actions_2",
            "label"  => $Dictionnary["Actions"],
            "type"   => "misc",
            "width"  => "195px",
            "render" => function($p) {
                global $Dictionnary;
                global $Configuration;

                ob_start();
                require (__DIR__."/files.php");
                return (ob_get_clean());
            }
        ],
        [
            "name"    => "actions_3",
            "label"   => "",
            "type"    => "misc",
            "width"   => "180px",
            "cell_class" => "prospect_document_actions_cell",
            "render"  => function($p) {
                global $Dictionnary;

                ob_start();
                require (__DIR__."/buttons.php");
                return (ob_get_clean());
            }
        ],
    ]);
}

function prospecting_render_table($prospects)
{
    render_dynamic_table("prospects_table", prospecting_table_fields(), $prospects);
}
