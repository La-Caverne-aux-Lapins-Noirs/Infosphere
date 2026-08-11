<?php
$role_labels = [
    "contact" => $Dictionnary["EnterpriseContact"] ?? "Contact",
    "representative" => $Dictionnary["EnterpriseRepresentative"] ?? "Représentant",
    "tutor" => $Dictionnary["EnterpriseTutor"] ?? "Tuteur",
];
?>

<div class="enterprise_contacts_layout">
    <section class="enterprise_admin_card enterprise_admin_block enterprise_contact_add_block">
        <h3>Ajouter un contact</h3>
        <form
            method="post"
            action="/api/enterprise/<?=$enterprise["id"]; ?>/contact"
            class="enterprise_contact_form enterprise_admin_form"
            onsubmit="return silent_submitf(this, {after_success: refresh, clear_form: true});"
        >
            <div class="enterprise_contact_add_grid">
                <label>
                    Utilisateur existant facultatif
                    <input type="text" name="user" placeholder="codename ou id" />
                </label>
                <label>
                    Rôle document
                    <select name="document_role">
                        <?php foreach (enterprise_document_roles() as $role) { ?>
                            <option value="<?=$role; ?>"><?=htmlspecialchars($role_labels[$role] ?? $role); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>
                    <?=$Dictionnary["FirstName"]; ?>
                    <input type="text" name="first_name" />
                </label>
                <label>
                    <?=$Dictionnary["FamilyName"]; ?>
                    <input type="text" name="family_name" />
                </label>
                <label>
                    <?=$Dictionnary["Mail"]; ?>
                    <input type="text" name="mail" />
                </label>
                <label>
                    <?=$Dictionnary["Phone"]; ?>
                    <input type="text" name="phone" />
                </label>
                <label class="enterprise_contact_position">
                    Poste / fonction libre
                    <input type="text" name="position" placeholder="Ex: Responsable RH, CTO, tuteur technique..." />
                </label>
            </div>
            <div class="enterprise_actions">
                <input type="button" onclick="silent_submitf(this.form, {after_success: refresh, clear_form: true});" value="Ajouter le contact" />
            </div>
        </form>
    </section>

    <section class="enterprise_admin_card enterprise_admin_block enterprise_contacts_list_block">
        <h3>Contacts</h3>
        <?php if (!count($enterprise["contacts"])) { ?>
            <p>Aucun contact renseigné.</p>
        <?php } else { ?>
            <div class="enterprise_contact_cards">
                <?php foreach ($enterprise["contacts"] as $contact) { ?>
                    <article class="enterprise_contact_card">
                        <div class="enterprise_contact_identity">
                            <?php display_avatar($contact, 64, true); ?>
                            <div>
                                <strong><?=htmlspecialchars($role_labels[$contact["document_role"]] ?? $contact["document_role"]); ?></strong><br />
                                <a href="<?=profile($contact["id_user"]); ?>" target="_blank">
                                    <?=htmlspecialchars(trim(($contact["first_name"] ?? "")." ".($contact["family_name"] ?? "")) ?: $contact["codename"]); ?>
                                </a><br />
                                <span><?=htmlspecialchars($contact["mail"] ?? ""); ?></span>
                                <?php if (trim((string)($contact["phone"] ?? "")) != "") { ?>
                                    <br /><span><?=htmlspecialchars(format_phone_number($contact["phone"])); ?></span>
                                <?php } ?>
                            </div>
                        </div>

                        <form method="put" action="/api/enterprise/<?=$enterprise["id"]; ?>/contact/<?=$contact["id_link"]; ?>" onsubmit="return silent_submitf(this, {after_success: refresh});" class="enterprise_contact_inline_form">
                            <select name="document_role">
                                <?php foreach (enterprise_document_roles() as $role) { ?>
                                    <option value="<?=$role; ?>" <?=$role == $contact["document_role"] ? "selected" : ""; ?>><?=htmlspecialchars($role_labels[$role] ?? $role); ?></option>
                                <?php } ?>
                            </select>
                            <input type="text" name="position" value="<?=htmlspecialchars($contact["position"] ?? "", ENT_QUOTES); ?>" placeholder="Poste" />
                            <input type="button" onclick="silent_submitf(this.form, {after_success: refresh});" value="✓" />
                        </form>

                        <form method="delete" action="/api/enterprise/<?=$enterprise["id"]; ?>/contact/<?=$contact["id_link"]; ?>" onsubmit="return false;" class="enterprise_contact_delete_form">
                            <input type="button" onclick="silent_submitf(this.form, {after_success: refresh});" value="Retirer" />
                        </form>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</div>
