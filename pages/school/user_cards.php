<?php

if (!function_exists("school_admin_member_card"))
{
    function school_admin_member_id(array $member, $endpoint)
    {
        foreach (["id_".$endpoint, "id_user", "id"] as $field)
            if (isset($member[$field]) && $member[$field] !== "")
                return ((int)$member[$field]);
        return (-1);
    }

    function school_admin_member_responsibilities(array $member)
    {
        $values = $member["school_responsibilities"] ?? [];
        if (!is_array($values))
            $values = [];
        return (array_values(array_unique(array_map(static function($value) {
            return strtoupper(trim((string)$value));
        }, $values))));
    }

    function school_admin_fire_safety_control($school_id, $id_user, array $member)
    {
        $school_id = (int)$school_id;
        $id_user = (int)$id_user;
        if ($school_id <= 0 || $id_user <= 0)
            return ;
        $definition = school_responsibility_definition($school_id, "FIRE_SAFETY");
        if ($definition === NULL)
            return ;
        $assigned = in_array("FIRE_SAFETY", school_admin_member_responsibilities($member), true);
        $can_manage = is_director_for_school($school_id);
        if (!$assigned && !$can_manage)
            return ;
        $method = $assigned ? "delete" : "put";
        $icon = trim((string)($definition["icon"] ?? ""));
        if ($icon == "")
            $icon = "🔥";
        ?>
        <form
            class="school_member_fire_form"
            method="<?=$method; ?>"
            action="api/school/<?=$school_id; ?>/responsibility"
            onsubmit="return false;"
        >
            <input type="hidden" name="user" value="<?=$id_user; ?>" />
            <input type="hidden" name="responsibility" value="<?=(int)$definition["id"]; ?>" />
            <button
                type="button"
                class="school_member_fire_badge <?=$assigned ? "active" : "inactive"; ?>"
                title="<?=htmlspecialchars($assigned
                    ? ($can_manage ? "Sécurité incendie attribuée — cliquer pour retirer" : "Responsable sécurité incendie")
                    : "Attribuer la responsabilité sécurité incendie", ENT_QUOTES); ?>"
                aria-label="<?=htmlspecialchars($definition["label"], ENT_QUOTES); ?>"
                aria-pressed="<?=$assigned ? "true" : "false"; ?>"
                <?=$can_manage ? "" : "disabled"; ?>
                onclick="<?=$can_manage ? "silent_submitf(this.form, {after_success: refresh});" : ""; ?>"
            ><span aria-hidden="true"><?=htmlspecialchars($icon); ?></span></button>
        </form>
        <?php
    }

    function school_responsibility_member_card($school_id, array $definition, array $member, $can_manage)
    {
        $id = school_admin_member_id($member, "user");
        if ($id <= 0)
            return ;
        $codename = $member["codename"] ?? (string)$id;
        $display_name = school_responsibility_member_label($member);
        if ($display_name === "")
            $display_name = $codename;
        ?>
        <article class="school_member_card school_responsibility_member_card">
            <div class="school_member_avatar">
                <?php display_avatar($member, 64, true); ?>
            </div>
            <a
                class="school_member_codename"
                href="<?=profile($id); ?>"
                title="<?=htmlspecialchars($codename, ENT_QUOTES); ?>"
            ><?=htmlspecialchars($display_name); ?></a>
            <?php if ($can_manage) { ?>
                <form method="delete" action="api/school/<?=(int)$school_id; ?>/responsibility" onsubmit="return false;">
                    <input type="hidden" name="user" value="<?=$id; ?>" />
                    <input type="hidden" name="responsibility" value="<?=(int)$definition["id"]; ?>" />
                    <input
                        type="button"
                        onclick="silent_submitf(this.form, {after_success: refresh});"
                        value="✗"
                        title="Retirer cette responsabilité"
                    />
                </form>
            <?php } ?>
        </article>
        <?php
    }

    function school_responsibility_add_form($school_id, array $definition, $can_manage, $placeholder = "Ajouter un référent (codename)")
    {
        if (!$can_manage)
            return ;
        ?>
        <form
            method="put"
            action="api/school/<?=(int)$school_id; ?>/responsibility"
            class="school_member_add_form school_responsibility_add_form"
            onsubmit="return silent_submitf(this, {after_success: refresh});"
        >
            <input type="hidden" name="responsibility" value="<?=(int)$definition["id"]; ?>" />
            <input
                type="text"
                name="user"
                placeholder="<?=htmlspecialchars($placeholder, ENT_QUOTES); ?>"
                title="<?=htmlspecialchars($definition["label"], ENT_QUOTES); ?>"
                onkeypress="return event.keyCode != 13 ? true : silent_submitf(this.form, {after_success: refresh});"
            />
            <input
                type="button"
                onclick="silent_submitf(this.form, {after_success: refresh});"
                value="✓"
            />
        </form>
        <?php
    }

    function school_responsibility_delete_definition_form($school_id, array $definition, $can_manage)
    {
        if (!$can_manage || !empty($definition["builtin"]))
            return ;
        ?>
        <form
            method="delete"
            action="api/school/<?=(int)$school_id; ?>/responsibility_definition"
            class="school_responsibility_definition_delete"
            onsubmit="return false;"
        >
            <input type="hidden" name="responsibility" value="<?=(int)$definition["id"]; ?>" />
            <button
                type="button"
                onclick="if (confirm('Supprimer cette responsabilité personnalisée ? Les affectations resteront historisées.')) silent_submitf(this.form, {after_success: refresh});"
                title="Supprimer cette responsabilité personnalisée"
            >✗</button>
        </form>
        <?php
    }

    function school_admin_member_card($school_id, $endpoint, array $member, $can_delete, $actions = [])
    {
        $id = school_admin_member_id($member, $endpoint);
        $codename = $member["codename"] ?? (string)$id;
        ?>
        <article class="school_member_card">
            <?php school_admin_fire_safety_control($school_id, $id, $member); ?>
            <div class="school_member_avatar">
                <?php display_avatar($member, 72, true); ?>
            </div>
            <a class="school_member_codename" href="<?=profile($id); ?>">
                <?=htmlspecialchars($codename); ?>
            </a>
            <?php if (is_array($actions) && count($actions)) { ?>
                <div class="school_member_actions">
                    <?php foreach ($actions as $action) { ?>
                        <input
                            type="button"
                            class="<?=htmlspecialchars($action["class"] ?? "", ENT_QUOTES); ?>"
                            value="<?=htmlspecialchars($action["label"] ?? "Action", ENT_QUOTES); ?>"
                            title="<?=htmlspecialchars($action["title"] ?? ($action["label"] ?? ""), ENT_QUOTES); ?>"
                            onclick="<?=htmlspecialchars($action["onclick"] ?? "", ENT_QUOTES); ?>"
                        />
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($can_delete && $id > 0) { ?>
                <form method="delete" action="api/school/<?=$school_id; ?>/<?=$endpoint; ?>/<?=$id; ?>" onsubmit="return false;">
                    <input
                        type="button"
                        onclick="silent_submitf(this.form, {after_success: refresh});"
                        value="✗"
                        title="Retirer"
                    />
                </form>
            <?php } ?>
        </article>
        <?php
    }

    function school_admin_member_nfc_program_action(array $member, $endpoint = "user")
    {
        $id = school_admin_member_id($member, $endpoint);
        if ($id <= 0 || !can_manage_user_credentials($id))
            return (NULL);
        $nfc = nfc_card_info_for_user($id);
        if (empty($nfc["eligible"]))
            return (NULL);
        return ([
            "class" => "school_member_nfc_button",
            "label" => "📳 NFC",
            "title" => "Programmer la carte NFC de ".($member["codename"] ?? (string)$id),
            "onclick" => "infosphere_nfc_program(this, ".$id.");",
        ]);
    }

    function school_admin_member_add_form($school_id, $endpoint, $label, $can_add)
    {
        if (!$can_add)
            return ;
        ?>
        <form
            method="put"
            action="api/school/<?=$school_id; ?>/<?=$endpoint; ?>"
            class="school_member_add_form"
            onsubmit="return silent_submitf(this, {after_success: refresh});"
        >
            <input
                type="text"
                name="<?=$endpoint; ?>"
                placeholder="Ajouter <?=htmlspecialchars(mb_strtolower($label)); ?>"
                onkeypress="return event.keyCode != 13 ? true : silent_submitf(this.form, {after_success: refresh});"
            />
            <input
                type="button"
                onclick="silent_submitf(this.form, {after_success: refresh});"
                value="✓"
            />
        </form>
        <?php
    }
}
