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
    <input type="hidden" name="queue_for_print" value="0" />
    <select name="document" required title="Document à générer" onchange="prospect_document_update_print_button(this.form);">
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
        <optgroup label="Convocations d’admission">
            <option value="convocation:motivation-theory" title="Convocation à l’entretien de motivation et au test théorique">Entretien + test théorique</option>
            <option value="convocation:practical" title="Convocation à l’épreuve pratique d’admission">Épreuve pratique</option>
        </optgroup>
        <optgroup label="Qualiopi">
            <option value="post-interview-report" title="Compléter ou reprendre le compte rendu post-entretien">Compte rendu post-entretien</option>
        </optgroup>
    </select>
    <input
        type="button"
        value="Générer"
        class="prospect_document_generate"
        onclick="if (this.form.reportValidity()) { this.form.queue_for_print.value='0'; <?=$document_js; ?> }"
    />
    <input
        type="button"
        value="Générer + impression"
        class="prospect_document_generate prospect_document_print"
        title="Générer le document et l'ajouter à la file d'impression"
        disabled
        onclick="if (this.form.reportValidity()) { this.form.queue_for_print.value='1'; <?=$document_js; ?> }"
    />
</form>
<script>
function prospect_document_update_print_button(form)
{
    var select = form ? form.querySelector('[name="document"]') : null;
    var button = form ? form.querySelector('.prospect_document_print') : null;
    if (!select || !button)
        return;
    button.disabled = !/^(?:admission|contract):/.test(select.value || '');
}
</script>
