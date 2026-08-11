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

    function school_admin_member_card($school_id, $endpoint, array $member, $can_delete, $actions = [])
    {
        $id = school_admin_member_id($member, $endpoint);
        $codename = $member["codename"] ?? (string)$id;
        ?>
        <article class="school_member_card">
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
