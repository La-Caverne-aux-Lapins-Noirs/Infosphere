<?php

require_once (__DIR__."/campaign_registration.php");

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

function prospecting_current_class_options()
{
    return ([
        -9 => "Autre",
        -8 => "CM1",
        -7 => "CM2",
        -6 => "6ème",
        -5 => "5ème",
        -4 => "4ème",
        -3 => "3ème",
        -2 => "Seconde",
        -1 => "Première",
         0 => "Terminale",
         1 => "Bac+1",
         2 => "Bac+2",
         3 => "Bac+3",
         4 => "Bac+4",
         5 => "Bac+5",
         6 => "Bac+6",
         7 => "Bac+7",
         8 => "Bac+8",
         9 => "En reconversion",
        10 => "?",
    ]);
}

function prospecting_inline_select($prospect, $field, $value, $options, $label, $class = "")
{
    $id = (int)($prospect["id"] ?? 0);
    $widget_id = "prospect_inline_".$id."_".$field;
    $classes = trim("prospect_inline_editor ".$class);

    ob_start();
    ?>
    <div class="<?=htmlspecialchars($classes); ?>" id="<?=htmlspecialchars($widget_id); ?>">
        <button
            type="button"
            class="prospect_inline_toggle"
            title="Cliquer pour modifier"
        ><?=htmlspecialchars($label); ?></button>
        <form
            method="put"
            action="/api/prospect/<?=$id; ?>/orientation"
            onsubmit="return prospecting_inline_editor_submit(this);"
        >
            <select name="<?=htmlspecialchars($field); ?>" aria-label="<?=htmlspecialchars($label); ?>">
                <?php foreach ($options as $option_value => $option_label) { ?>
                    <option value="<?=htmlspecialchars((string)$option_value); ?>"<?=$option_value == $value ? " selected" : ""; ?>><?=htmlspecialchars($option_label); ?></option>
                <?php } ?>
            </select>
            <button type="submit" class="prospect_inline_confirm" title="Enregistrer" aria-label="Enregistrer">&#10003;</button>
            <button type="button" class="prospect_inline_cancel" title="Annuler" aria-label="Annuler">&#10007;</button>
        </form>
    </div>
    <?php
    return (ob_get_clean());
}

function prospecting_table_fields()
{
    global $Dictionnary;
    global $Configuration;

    $class_level = prospecting_class_levels();
    $current_class_options = prospecting_current_class_options();
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
                    ? prospecting_campaign_registration_editor($p)
                    : "";
                return (
                    "<a target='_blank' href='?p=ProfileMenu&amp;a=$id'>$a $b</a>".
                    ($registration != "" ? "<div class='prospect_registration_date'>$registration</div>" : "")
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
            "render"  => function($p) use ($target_class, $current_class_options)
            {
                $target = isset($p["target_class"]) ? (int)$p["target_class"] : 0;
                $stored_current = isset($p["current_class"]) ? (int)$p["current_class"] : 10;
                $current = real_class_level($p["registration_date"], $stored_current);

                return (
                    prospecting_inline_select(
                        $p,
                        "target_class",
                        $target,
                        $target_class,
                        $target_class[$target] ?? "/",
                        "prospect_target_class"
                    ).
                    prospecting_inline_select(
                        $p,
                        "current_class",
                        $current,
                        $current_class_options,
                        "Niveau actuel : ".($current_class_options[$current] ?? "?"),
                        "prospect_current_class"
                    )
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
                $school = trim((string)($p["last_school"] ?? ""));
                $school = $school != ""
                    ? "<span class='prospect_last_school'>".htmlspecialchars(ucfirst($school))."</span>"
                    : "";
                return ($school.prospecting_inline_select(
                    $p,
                    "target_entry",
                    $v,
                    $target_entry,
                    $target_entry[$v] ?? "Septembre",
                    "prospect_target_entry"
                ));
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
