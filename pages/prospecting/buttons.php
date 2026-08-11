<?php global $User; ?>
<?php if ($p["password"]) return ; ?>
<div class="prospect_decision_toolbar">
<?php if ($p["deleted"] === NULL) { ?>
    <?php $js = "silent_submitf(this, {toremove: 'prospects_table".$p["id"]."'});"; ?>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>" class="decision_buttons" onsubmit="return <?=$js; ?>">
        <input type="hidden" name="decision" value="remove" />
        <input type="button" value="X" style="color: red;" onclick="confirm('<?=$Dictionnary["Deleted"]; ?>') && <?=$js; ?>" />
    </form>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>/transform" class="decision_buttons" onsubmit="return <?=$js; ?>">
        <input type="button" value="USR" style="color: orange; font-weight: bold;" title="<?=$Dictionnary["TransformProspectIntoUser"]; ?>" onclick="confirm('<?=htmlspecialchars($Dictionnary["ConfirmProspectTransformation"], ENT_QUOTES); ?>') && <?=$js; ?>" />
    </form>
    <input
        type="button"
        value="+ Relation"
        class="prospect_add_relation"
        title="Ajouter un responsable ou contact lié à ce prospect"
        onclick="document.location='?p=Subscribe&amp;relation=<?=$p["id"]; ?>&amp;pp=ProspectingMenu';"
    />
<?php } else { ?>
    <?php $js = "silent_submit(this);"; ?>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>" class="decision_buttons" onsubmit="return <?=$js; ?>">
        <input type="hidden" name="decision" value="restore" />
        <input type="button" value="&#8634;" style="color: green;" onclick="<?=$js; ?>" />
    </form>
<?php } ?>
</div>

<?php $registration_js = "silent_submitf(this, {after_success: open_registration_form});"; ?>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>/registration" class="decision_buttons prospect_registration_form">
    <select name="kind" title="<?=$Dictionnary["RegistrationFormKind"] ?? "Type de contrat"; ?>">
        <option value="ECL">ECL</option>
        <option value="OF">OF</option>
        <option value="OFA">OFA</option>
        <option value="CFA">CFA</option>
    </select>
    <input
        type="button"
        value="✉ FORM"
        class="prospect_registration_send"
        title="<?=$Dictionnary["SendRegistrationForm"] ?? "Envoyer le formulaire d'inscription"; ?>"
        onclick="confirm('<?=htmlspecialchars($Dictionnary["RegistrationFormConfirmSend"] ?? "Envoyer ce formulaire au prospect ?", ENT_QUOTES); ?>') && <?=$registration_js; ?>"
    />
</form>

<?php $document_js = "silent_submitf(this, {after_success: open_generated_document});"; ?>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>/document" class="decision_buttons prospect_document_form">
    <select name="document" required title="Document à générer">
        <option value="">Document…</option>
        <optgroup label="Contrats">
            <option value="contract:ECL">Contrat ECL</option>
            <option value="contract:OF">Contrat OF</option>
            <option value="contract:OFA">Contrat OFA</option>
            <option value="contract:CFA">Contrat CFA</option>
        </optgroup>
        <optgroup label="Attestations">
            <option value="admission:domestic" title="Attestation d’admission définitive — étudiant français">Admission FR</option>
            <option value="admission:foreign" title="Attestation d’admission définitive — étudiant étranger">Admission</option>
        </optgroup>
        <optgroup label="Qualiopi">
            <option value="needs-analysis" title="Compléter ou reprendre l'analyse du besoin">Analyse du besoin</option>
        </optgroup>
    </select>
    <?php
    $needs_school_codename = "";
    $needs_school = document_context_first_school_for_user((int)$p["id"]);
    if (is_array($needs_school) && isset($needs_school["id_school"]))
    {
        $needs_school_full = fetch_school((int)$needs_school["id_school"]);
        if (is_array($needs_school_full))
            $needs_school_codename = $needs_school_full["codename"] ?? "";
    }
    $needs_classes = admission_certificate_target_classes();
    $needs_class = $needs_classes[(int)($p["target_class"] ?? 0)] ?? NULL;
    $needs_target_training = is_array($needs_class) ? ($needs_class["label"] ?? "") : "";
    $needs_target_level = is_array($needs_class) ? admission_certificate_year_label((int)$needs_class["year"]) : "";
    $needs_entries = admission_certificate_entry_specs();
    $needs_target_entry = $needs_entries[(int)($p["target_entry"] ?? -1)]["label"] ?? "";
    ?>
    <input
        type="button"
        value="Générer"
        class="prospect_document_generate"
        onclick='this.form.reportValidity() && prospect_document_action(this, <?=json_encode((int)$p["id"]); ?>, <?=json_encode($p["codename"] ?? (string)$p["id"]); ?>, <?=json_encode($needs_school_codename); ?>, <?=json_encode((int)($User["id"] ?? 0)); ?>, <?=json_encode($needs_target_training); ?>, <?=json_encode($needs_target_level); ?>, <?=json_encode($needs_target_entry); ?>, <?=json_encode(date("d/m/Y")); ?>);'
    />
</form>
