<?php
$role_labels = [
    "contact" => $Dictionnary["EnterpriseContact"] ?? "Contact",
    "representative" => $Dictionnary["EnterpriseRepresentative"] ?? "Représentant",
    "tutor" => $Dictionnary["EnterpriseTutor"] ?? "Tuteur",
    "billing" => $Dictionnary["EnterpriseBillingContact"] ?? "Facturation",
];
$contact_candidates = enterprise_contact_candidates($enterprise["id"]);
$contact_datalist_id = "enterprise_contact_users_".(int)$enterprise["id"];
$contact_target_id = "enterprise_contacts_dynamic_".(int)$enterprise["id"];
?>

<div class="enterprise_contacts_layout">
    <section class="enterprise_admin_card enterprise_admin_block enterprise_contact_add_block">
        <h3>Ajouter un contact</h3>
        <form
            method="post"
            action="/api/enterprise/<?=$enterprise["id"]; ?>/contact"
            class="enterprise_contact_form enterprise_admin_form"
            onsubmit="return silent_submitf(this, {tofill: '<?=$contact_target_id; ?>', clear_form: true});"
        >
            <div class="enterprise_contact_add_grid">
                <label class="enterprise_contact_existing_user">
                    Utilisateur déjà enregistré
                    <input
                        type="text"
                        name="user"
                        list="<?=$contact_datalist_id; ?>"
                        placeholder="Nom, codename ou e-mail"
                        autocomplete="off"
                        oninput="enterprise_contact_existing_changed(this);"
                    />
                    <datalist id="<?=$contact_datalist_id; ?>">
                        <?php foreach ($contact_candidates as $candidate) { ?>
                            <?php
                                $identity = trim(($candidate["first_name"] ?? "")." ".($candidate["family_name"] ?? ""));
                                if ($identity == "")
                                    $identity = $candidate["codename"];
                                $candidate_label = $identity." — ".$candidate["codename"];
                                if (trim((string)($candidate["mail"] ?? "")) != "")
                                    $candidate_label .= " — ".$candidate["mail"];
                                $candidate_label .= " — #".(int)$candidate["id"];
                            ?>
                            <option value="<?=htmlspecialchars($candidate["codename"], ENT_QUOTES); ?>" label="<?=htmlspecialchars($candidate_label, ENT_QUOTES); ?>"></option>
                        <?php } ?>
                    </datalist>
                    <small>Choisis un compte existant ; sinon laisse vide pour créer un nouveau contact.</small>
                </label>

                <label class="enterprise_contact_position">
                    Rôle / fonction dans l'organisation
                    <input type="text" name="position" placeholder="Ex: Responsable RH, CTO, tuteur technique, comptable..." />
                    <small>Champ libre : il décrit le rôle réel de cette personne dans l'organisation.</small>
                </label>

                <fieldset class="enterprise_contact_document_roles">
                    <legend>Usages documentaires</legend>
                    <?php foreach (enterprise_document_roles() as $role) { ?>
                        <label>
                            <input
                                type="checkbox"
                                name="document_role_<?=$role; ?>"
                                value="1"
                                <?=$role == "contact" ? "checked" : ""; ?>
                            />
                            <?=htmlspecialchars($role_labels[$role] ?? $role); ?>
                        </label>
                    <?php } ?>
                    <small>Une même personne peut cumuler plusieurs usages, par exemple représentant et tuteur.</small>
                </fieldset>

                <div class="enterprise_contact_new_fields" data-enterprise-new-contact-fields>
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
                </div>
            </div>
            <div class="enterprise_actions">
                <input type="submit" value="Ajouter le contact" />
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
                    <?php
                        $position = trim((string)($contact["position"] ?? ""));
                        $document_roles = enterprise_document_role_list($contact["document_role"] ?? "contact");
                        $document_role_labels = [];
                        foreach ($document_roles as $role)
                            $document_role_labels[] = $role_labels[$role] ?? $role;
                        $document_role_label = implode(" · ", $document_role_labels);
                        $display_role = $position != "" ? $position : $document_role_label;
                    ?>
                    <article class="enterprise_contact_card" id="enterprise_contact_<?=$contact["id_link"]; ?>">
                        <div class="enterprise_contact_identity">
                            <?php display_avatar($contact, 64, true); ?>
                            <div>
                                <strong><?=htmlspecialchars($display_role); ?></strong><br />
                                <?php if ($position != "") { ?>
                                    <small class="enterprise_contact_document_role_label">Usages documentaires : <?=htmlspecialchars($document_role_label); ?></small><br />
                                <?php } ?>
                                <a href="<?=profile($contact["id_user"]); ?>" target="_blank">
                                    <?=htmlspecialchars(trim(($contact["first_name"] ?? "")." ".($contact["family_name"] ?? "")) ?: $contact["codename"]); ?>
                                </a><br />
                                <span><?=htmlspecialchars($contact["mail"] ?? ""); ?></span>
                                <?php if (trim((string)($contact["phone"] ?? "")) != "") { ?>
                                    <br /><span><?=htmlspecialchars(format_phone_number($contact["phone"])); ?></span>
                                <?php } ?>
                            </div>
                        </div>

                        <form
                            method="put"
                            action="/api/enterprise/<?=$enterprise["id"]; ?>/contact/<?=$contact["id_link"]; ?>"
                            onsubmit="return silent_submitf(this, {tofill: '<?=$contact_target_id; ?>'});"
                            class="enterprise_contact_inline_form"
                        >
                            <input type="text" name="position" value="<?=htmlspecialchars($contact["position"] ?? "", ENT_QUOTES); ?>" placeholder="Rôle / fonction libre" />
                            <fieldset class="enterprise_contact_inline_roles">
                                <?php foreach (enterprise_document_roles() as $role) { ?>
                                    <label title="<?=htmlspecialchars($role_labels[$role] ?? $role, ENT_QUOTES); ?>">
                                        <input
                                            type="checkbox"
                                            name="document_role_<?=$role; ?>"
                                            value="1"
                                            <?=enterprise_has_document_role($contact["document_role"] ?? "", $role) ? "checked" : ""; ?>
                                        />
                                        <?=htmlspecialchars($role_labels[$role] ?? $role); ?>
                                    </label>
                                <?php } ?>
                            </fieldset>
                            <input type="submit" value="✓" title="Enregistrer les rôles du contact" />
                        </form>

                        <form
                            method="delete"
                            action="/api/enterprise/<?=$enterprise["id"]; ?>/contact/<?=$contact["id_link"]; ?>"
                            onsubmit="return silent_submitf(this, {tofill: '<?=$contact_target_id; ?>'});"
                            class="enterprise_contact_delete_form"
                        >
                            <input type="submit" value="Retirer" />
                        </form>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</div>
