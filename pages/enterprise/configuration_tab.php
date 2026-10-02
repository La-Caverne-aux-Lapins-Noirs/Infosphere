<form
    method="put"
    action="/api/enterprise/<?=$enterprise["id"]; ?>"
    class="enterprise_admin_card enterprise_admin_form enterprise_admin_block enterprise_admin_full_block"
    onsubmit="return silent_submitf(this, {});"
>
    <?php if (!empty($enterprise["id_school"])) { ?>
        <p class="enterprise_add_notice">
            Cette organisation est liée à l'école
            <a href="index.php?p=SchoolMenu&amp;a=<?=$enterprise["id_school"]; ?>"><?=htmlspecialchars($enterprise["school_codename"] ?? $enterprise["codename"]); ?></a>.
            Les logos école restent gérés sur la page École.
        </p>
    <?php } ?>

    <div class="enterprise_config_columns">
        <section class="enterprise_config_section">
            <h3>Identité</h3>
            <label>
                Nom courant
                <input type="text" name="name" value="<?=htmlspecialchars($enterprise["name"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                <?=$Dictionnary["LegalName"]; ?>
                <input type="text" name="legal_name" value="<?=htmlspecialchars($enterprise["legal_name"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                SIRET
                <input type="text" name="siret" value="<?=htmlspecialchars($enterprise["siret"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                N° TVA intracommunautaire
                <input type="text" name="vat_number" placeholder="Ex : FR12345678901" value="<?=htmlspecialchars($enterprise["vat_number"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Registre d'immatriculation
                <input type="text" name="registration_registry" placeholder="Ex : RCS de Créteil" value="<?=htmlspecialchars($enterprise["registration_registry"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Numéro d'immatriculation
                <input type="text" name="registration_number" placeholder="Ex : 909 292 773" value="<?=htmlspecialchars($enterprise["registration_number"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Capital social
                <input type="text" name="share_capital" placeholder="Ex : 1 000 €" value="<?=htmlspecialchars($enterprise["share_capital"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                <?=$Dictionnary["Phone"]; ?>
                <input type="text" name="phone" value="<?=htmlspecialchars($enterprise["phone"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                <?=$Dictionnary["Mail"]; ?>
                <input type="text" name="mail" value="<?=htmlspecialchars($enterprise["mail"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Site web
                <input type="text" name="website" value="<?=htmlspecialchars($enterprise["website"] ?? "", ENT_QUOTES); ?>" />
            </label>
        </section>

        <section class="enterprise_config_section">
            <h3>Siège social</h3>
            <label>
                Adresse — ligne 1
                <input type="text" name="head_office_address_line1" value="<?=htmlspecialchars($enterprise["head_office_address_line1"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Adresse — ligne 2
                <input type="text" name="head_office_address_line2" value="<?=htmlspecialchars($enterprise["head_office_address_line2"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <div class="enterprise_small_line">
                <label>
                    Code postal
                    <input type="text" name="head_office_zipcode" value="<?=htmlspecialchars($enterprise["head_office_zipcode"] ?? "", ENT_QUOTES); ?>" />
                </label>
                <label>
                    Ville
                    <input type="text" name="head_office_city" value="<?=htmlspecialchars($enterprise["head_office_city"] ?? "", ENT_QUOTES); ?>" />
                </label>
            </div>
            <label>
                Pays
                <input type="text" name="head_office_country" value="<?=htmlspecialchars($enterprise["head_office_country"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label class="enterprise_main_info">
                Adresse légale reconstruite
                <textarea readonly><?=htmlspecialchars(enterprise_legal_address($enterprise), ENT_QUOTES); ?></textarea>
            </label>
        </section>

        <section class="enterprise_config_section">
            <h3>Facturation électronique</h3>
            <p class="enterprise_add_notice">Ces champs servent au routage. Laissez-les vides tant que l'organisation n'a pas communiqué d'adresse spécifique.</p>
            <label>
                Adresse électronique de facturation (EndpointID)
                <input type="text" name="electronic_invoice_address" value="<?=htmlspecialchars($enterprise["electronic_invoice_address"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Schéma de l'adresse
                <input type="text" name="electronic_invoice_address_scheme" placeholder="Ex : 0002, 0009…" value="<?=htmlspecialchars($enterprise["electronic_invoice_address_scheme"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Code de routage
                <input type="text" name="electronic_invoice_routing_code" value="<?=htmlspecialchars($enterprise["electronic_invoice_routing_code"] ?? "", ENT_QUOTES); ?>" />
            </label>
            <label>
                Schéma du code de routage
                <input type="text" name="electronic_invoice_routing_scheme" value="<?=htmlspecialchars($enterprise["electronic_invoice_routing_scheme"] ?? "", ENT_QUOTES); ?>" />
            </label>
        </section>

        <section class="enterprise_config_section">
            <h3><?=$Dictionnary["BillingPayerProfile"] ?? "Facturation / financement"; ?></h3>
            <p class="enterprise_add_notice">
                <?=$Dictionnary["BillingPayerProfileHint"] ?? "Ces réglages décrivent le rôle de l'organisation lorsqu'elle paie une facture de scolarité. Ils ne changent pas la nature juridique de l'organisation."; ?>
            </p>
            <label>
                <?=$Dictionnary["BillingPayerKind"] ?? "Type de payeur"; ?>
                <select name="billing_payer_kind">
                    <?php
                    $payer_kind = enterprise_billing_payer_kind($enterprise["billing_payer_kind"] ?? "direct");
                    $payer_kind_labels = [
                        "direct" => $Dictionnary["BillingPayerKindDirect"] ?? "Entreprise / financeur direct",
                        "opco" => $Dictionnary["BillingPayerKindOPCO"] ?? "OPCO",
                        "institutional" => $Dictionnary["BillingPayerKindInstitutional"] ?? "Financeur institutionnel",
                        "other" => $Dictionnary["BillingPayerKindOther"] ?? "Autre financeur",
                    ];
                    foreach (enterprise_billing_payer_kinds() as $kind) { ?>
                        <option value="<?=htmlspecialchars($kind, ENT_QUOTES); ?>" <?=$payer_kind === $kind ? "selected" : ""; ?>><?=htmlspecialchars($payer_kind_labels[$kind] ?? $kind); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <?=$Dictionnary["BillingReminderPolicy"] ?? "Relances de paiement"; ?>
                <select name="billing_reminder_enabled">
                    <option value="1" <?=enterprise_billing_reminder_enabled_value($enterprise["billing_reminder_enabled"] ?? 1, $payer_kind) ? "selected" : ""; ?>><?=$Dictionnary["BillingReminderAllowed"] ?? "Autoriser les relances"; ?></option>
                    <option value="0" <?=!enterprise_billing_reminder_enabled_value($enterprise["billing_reminder_enabled"] ?? 1, $payer_kind) ? "selected" : ""; ?>><?=$Dictionnary["BillingReminderDisabled"] ?? "Ne pas relancer"; ?></option>
                </select>
                <small><?=$Dictionnary["BillingReminderOPCOHint"] ?? "Une organisation déclarée OPCO n'est jamais proposée dans les relances, quel que soit ce réglage."; ?></small>
            </label>
        </section>

        <section class="enterprise_config_section">
            <h3>Compléments</h3>
            <label class="enterprise_main_info">
                Information principale société reconstruite
                <textarea readonly><?=htmlspecialchars(enterprise_main_info($enterprise), ENT_QUOTES); ?></textarea>
            </label>
            <label>
                Activité / service d'accueil
                <textarea name="activity"><?=htmlspecialchars($enterprise["activity"] ?? "", ENT_QUOTES); ?></textarea>
            </label>
            <label>
                Informations de paiement / RIB
                <textarea name="billing_information" placeholder="IBAN, BIC, modalités ou référence de paiement"><?=htmlspecialchars($enterprise["billing_information"] ?? "", ENT_QUOTES); ?></textarea>
            </label>
            <label>
                Notes internes
                <textarea name="notes"><?=htmlspecialchars($enterprise["notes"] ?? "", ENT_QUOTES); ?></textarea>
            </label>
            <div class="enterprise_small_line">
                <label>
                    Logo site organisation optionnel
                    <input type="file" name="icon" accept="image/png,image/jpeg" />
                </label>
                <label>
                    Logo document organisation optionnel
                    <input type="file" name="document_logo" accept="image/png,image/jpeg" />
                </label>
            </div>
        </section>
    </div>

    <div class="enterprise_actions">
        <input type="button" onclick="return silent_submitf(this.form, {});" value="<?=$Dictionnary["Save"]; ?>" />
    </div>
</form>

<section class="enterprise_admin_card enterprise_admin_block enterprise_admin_narrow_block enterprise_admin_danger">
    <h3>Zone dangereuse</h3>
    <form method="delete" action="/api/enterprise/<?=$enterprise["id"]; ?>" onsubmit="return false;">
        <input
            type="button"
            onclick="if (confirm('<?=$Dictionnary["ConfirmDeletion"]; ?>')) silent_submitf(this.form, {after_success: function(){document.location='index.php?p=EnterpriseMenu';}});"
            value="Supprimer l'organisation"
        />
    </form>
</section>
