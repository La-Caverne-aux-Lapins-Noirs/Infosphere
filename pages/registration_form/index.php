<?php
require_once (__DIR__."/../../tools/registration_form.php");
$token = $_GET["token"] ?? "";
$result = registration_form_fetch_invitation($token, true);
$error = "";
$details = "";
$invitation = NULL;
$student = NULL;
if (!$result["ok"])
{
    $error = $Dictionnary[$result["error"]] ?? $result["error"];
    $details = $result["details"] ?? "";
}
else
{
    $invitation = $result["invitation"];
    if (registration_form_is_document_kind($invitation["kind"] ?? ""))
    {
        $document_meta = $invitation["schema"]["document"] ?? [];
        $document_reference = (string)($document_meta["reference"] ?? "");
        $document_resolved = dabsic_editor_resolve_file($document_reference, false);
        if ($document_resolved["ok"])
            $invitation["schema"]["document"]["label"] = document_title_from_file(
                $document_resolved["absolute"],
                trim((string)($document_meta["label"] ?? "")) != ""
                    ? trim((string)$document_meta["label"])
                    : document_title_fallback($document_reference)
            );
    }
    $student_result = registration_form_student($invitation["id_user"]);
    if (!$student_result["ok"])
    {
        $error = $Dictionnary[$student_result["error"]] ?? $student_result["error"];
        $details = $student_result["details"] ?? "";
    }
    else
        $student = $student_result["student"];
}
?>
<style><?php require (__DIR__."/style.css"); ?></style>
<div class="registration-form-page">
    <header class="registration-form-header">
        <h1><?php
            if ($invitation && registration_form_is_document_signature_kind($invitation["kind"] ?? ""))
                echo htmlspecialchars($Dictionnary["DocumentSignatureTitle"] ?? "Document à signer");
            else if ($invitation && registration_form_is_document_kind($invitation["kind"] ?? ""))
                echo htmlspecialchars(($invitation["schema"]["document"]["label"] ?? "") != ""
                    ? $invitation["schema"]["document"]["label"]
                    : ($Dictionnary["DocumentFormTitle"] ?? "Document à compléter"));
            else if ($invitation && registration_form_is_profile_kind($invitation["kind"] ?? ""))
                echo $Dictionnary["AdministrativeFormTitle"] ?? "Informations administratives";
            else
                echo $Dictionnary["RegistrationFormTitle"] ?? "Dossier d'inscription";
        ?></h1>
        <?php if ($invitation) { ?>
            <p><?=htmlspecialchars(trim(($invitation["first_name"] ?? "")." ".($invitation["family_name"] ?? "")), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></p>
        <?php } ?>
    </header>
    <?php if ($error != "") { ?>
        <div class="registration-form-message is-error"><strong><?=htmlspecialchars($error); ?></strong><?php if ($details != "") { ?><pre><?=htmlspecialchars($details); ?></pre><?php } ?></div>
    <?php } else if ($invitation["completed_at"] !== NULL) { ?>
        <div class="registration-form-message is-success"><?=$Dictionnary["RegistrationFormAlreadyCompleted"] ?? "Ce dossier a déjà été validé."; ?></div>
    <?php } else {
        $schema = $invitation["schema"];
        $answers = $invitation["answers_data"];
        $is_profile_form = registration_form_is_profile_kind($invitation["kind"] ?? "");
        $is_document_form = registration_form_is_document_kind($invitation["kind"] ?? "");
        $is_document_signature = registration_form_is_document_signature_kind($invitation["kind"] ?? "");
    ?>
        <p class="registration-form-intro"><?php
            if ($is_document_signature)
                echo $Dictionnary["DocumentSignatureIntroduction"] ?? "Lisez le document ci-dessous. La signature porte sur cette version précise et toute modification du PDF invaliderait son empreinte.";
            else if ($is_document_form)
                echo $Dictionnary["DocumentFormIntroduction"] ?? "Complétez les informations demandées pour ce document. Vos réponses sont enregistrées directement dans le dossier documentaire de l'établissement. Vous pouvez sauvegarder un brouillon avant la validation définitive.";
            else if ($is_profile_form)
                echo $Dictionnary["AdministrativeFormIntroduction"] ?? "Complétez ou vérifiez vos informations administratives. Vous pouvez sauvegarder un brouillon avant la validation définitive.";
            else
                echo $Dictionnary["RegistrationFormIntroduction"] ?? "Complétez les informations demandées. Vous pouvez sauvegarder un brouillon avant la validation définitive.";
        ?></p>
        <?php if ($is_document_signature) { ?>
            <div class="registration-document-preview">
                <iframe src="/?p=DocumentSignaturePdf&silent=1&token=<?=rawurlencode($token); ?>" title="Document à signer"></iframe>
            </div>
        <?php } ?>
        <form id="registration-form" data-token="<?=htmlspecialchars($token, ENT_QUOTES); ?>"
            data-save-url="/api/dabsic/0/registration" data-final-url="/api/dabsic/0/registration_finalize"
            data-confirm-final="<?=htmlspecialchars($Dictionnary["RegistrationFormConfirmFinal"] ?? "Valider définitivement ce dossier ?", ENT_QUOTES); ?>"
            data-network-error="<?=htmlspecialchars($Dictionnary["DabsicEditorNetworkError"] ?? "Erreur réseau.", ENT_QUOTES); ?>">
            <?php if ($is_document_form) {
                $document_active_groups = [];
                $document_readonly_groups = [];
                foreach (($schema["groups"] ?? []) as $group)
                {
                    $access = $schema["group_access"][$group] ?? [];
                    if (!empty($access["edit"]) || !empty($access["validate"]))
                        $document_active_groups[] = $group;
                    else
                        $document_readonly_groups[] = $group;
                }
            ?>
                <?php foreach ($document_active_groups as $group) { ?>
                    <fieldset class="registration-form-group">
                        <legend><?=htmlspecialchars($schema["group_labels"][$group] ?? $group); ?></legend>
                        <?php foreach (($schema["fields"] ?? []) as $field => $definition) if (($definition["group"] ?? "") == $group) {
                            $value = array_key_exists($field, $answers) ? $answers[$field] : "";
                            $type = $definition["type"] ?? "text";
                            $editable = !empty($definition["editable"]);
                        ?>
                            <label class="registration-form-field<?=$editable ? "" : " is-readonly"; ?>">
                                <span><?=htmlspecialchars($definition["label"] ?? $field); ?><?php if (!empty($definition["required"])) { ?><strong class="registration-form-required"> *</strong><?php } ?></span>
                                <?php if (!$editable) { ?>
                                    <div class="registration-form-readonly-value"><?=trim((string)$value) !== "" ? htmlspecialchars((string)$value) : "—"; ?></div>
                                <?php } else if ($type == "boolean") { ?>
                                    <select data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>">
                                        <option value=""></option><option value="1" <?=$value === "1" || $value === 1 || $value === true ? "selected" : ""; ?>><?=$Dictionnary["Yes"] ?? "Oui"; ?></option>
                                        <option value="0" <?=$value === "0" || $value === 0 || $value === false ? "selected" : ""; ?>><?=$Dictionnary["No"] ?? "Non"; ?></option>
                                    </select>
                                <?php } else if ($type == "gender") {
                                    $gender_options = [
                                        "" => "",
                                        "female" => ($Dictionnary["Female"] ?? "Femme"),
                                        "male" => ($Dictionnary["Male"] ?? "Homme"),
                                        "other" => ($Dictionnary["Other"] ?? "Autre"),
                                    ];
                                ?>
                                    <select data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>">
                                        <?php if ($value !== "" && !array_key_exists((string)$value, $gender_options)) { ?>
                                            <option value="<?=htmlspecialchars((string)$value, ENT_QUOTES); ?>" selected><?=htmlspecialchars((string)$value); ?></option>
                                        <?php } ?>
                                        <?php foreach ($gender_options as $gender_value => $gender_label) { ?>
                                            <option value="<?=htmlspecialchars($gender_value, ENT_QUOTES); ?>" <?=(string)$value === (string)$gender_value ? "selected" : ""; ?>><?=htmlspecialchars($gender_label); ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else if ($type == "textarea") { ?>
                                    <textarea data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>"><?=htmlspecialchars((string)$value); ?></textarea>
                                <?php } else { ?>
                                    <input type="<?=htmlspecialchars($type); ?>" data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>" value="<?=htmlspecialchars((string)$value, ENT_QUOTES); ?>" />
                                <?php } ?>
                            </label>
                        <?php } ?>
                    </fieldset>
                <?php } ?>

                <?php if (count($document_readonly_groups)) { ?>
                    <details class="registration-form-other-information">
                        <summary><?=$Dictionnary["DocumentOtherInformation"] ?? "Autres informations du document"; ?></summary>
                        <?php foreach ($document_readonly_groups as $group) { ?>
                            <fieldset class="registration-form-group is-readonly">
                                <legend><?=htmlspecialchars($schema["group_labels"][$group] ?? $group); ?></legend>
                                <?php foreach (($schema["fields"] ?? []) as $field => $definition) if (($definition["group"] ?? "") == $group) {
                                    $value = array_key_exists($field, $answers) ? $answers[$field] : "";
                                ?>
                                    <div class="registration-form-field is-readonly">
                                        <span><?=htmlspecialchars($definition["label"] ?? $field); ?></span>
                                        <div class="registration-form-readonly-value"><?=trim((string)$value) !== "" ? htmlspecialchars((string)$value) : "—"; ?></div>
                                    </div>
                                <?php } ?>
                            </fieldset>
                        <?php } ?>
                    </details>
                <?php } ?>
            <?php } else { ?>
                <?php foreach (($schema["groups"] ?? []) as $group) { ?>
                    <fieldset class="registration-form-group" data-registration-group="<?=htmlspecialchars($group, ENT_QUOTES); ?>">
                        <legend><?=htmlspecialchars($is_profile_form
                            ? ($Dictionnary["AdministrativeFormPersonalData"] ?? "Informations personnelles")
                            : $group); ?></legend>
                        <?php foreach (($schema["fields"] ?? []) as $field => $definition) if (($definition["group"] ?? "") == $group) {
                            $value = array_key_exists($field, $answers) ? $answers[$field] : "";
                            $type = $definition["type"] ?? "text";
                        ?>
                            <label class="registration-form-field">
                                <span><?=htmlspecialchars($definition["label"] ?? $field); ?><?php if (!$is_profile_form && empty($definition["custom_label"])) { ?><small><?=htmlspecialchars($field); ?></small><?php } ?></span>
                                <?php if ($type == "boolean") { ?>
                                    <select data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>">
                                        <option value=""></option><option value="1" <?=$value === "1" || $value === 1 || $value === true ? "selected" : ""; ?>><?=$Dictionnary["Yes"] ?? "Oui"; ?></option>
                                        <option value="0" <?=$value === "0" || $value === 0 || $value === false ? "selected" : ""; ?>><?=$Dictionnary["No"] ?? "Non"; ?></option>
                                    </select>
                                <?php } else if ($type == "gender") {
                                    $gender_options = [
                                        "" => "",
                                        "female" => ($Dictionnary["Female"] ?? "Femme"),
                                        "male" => ($Dictionnary["Male"] ?? "Homme"),
                                        "other" => ($Dictionnary["Other"] ?? "Autre"),
                                    ];
                                ?>
                                    <select data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>">
                                        <?php if ($value !== "" && !array_key_exists((string)$value, $gender_options)) { ?>
                                            <option value="<?=htmlspecialchars((string)$value, ENT_QUOTES); ?>" selected><?=htmlspecialchars((string)$value); ?></option>
                                        <?php } ?>
                                        <?php foreach ($gender_options as $gender_value => $gender_label) { ?>
                                            <option value="<?=htmlspecialchars($gender_value, ENT_QUOTES); ?>" <?=(string)$value === (string)$gender_value ? "selected" : ""; ?>><?=htmlspecialchars($gender_label); ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else if ($type == "textarea") { ?>
                                    <textarea data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>"><?=htmlspecialchars((string)$value); ?></textarea>
                                <?php } else { ?>
                                    <input type="<?=htmlspecialchars($type); ?>" data-registration-field="<?=htmlspecialchars($field, ENT_QUOTES); ?>" value="<?=htmlspecialchars((string)$value, ENT_QUOTES); ?>" />
                                <?php } ?>
                            </label>
                        <?php } ?>
                        <?php if (in_array($group, $schema["signature_groups"] ?? [], true)) {
                            if ($is_document_signature)
                            {
                                $signature_file = "";
                                $signature_bound = false;
                                $has_profile_signature = false;
                                $has_signature = false;
                            }
                            else
                            {
                                $signature_file = registration_form_signature_path($student, $group, $answers);
                                $signature_bound = isset($answers[$group.".Signature"]) && is_file($signature_file);
                                $has_profile_signature = is_file($signature_file);
                                $has_signature = $is_profile_form ? $signature_bound : $has_profile_signature;
                            }
                        ?>
                        <section class="registration-signature<?=$has_signature ? " is-existing" : ""; ?>" data-signature-group="<?=htmlspecialchars($group, ENT_QUOTES); ?>" data-existing="<?=$has_signature ? "1" : "0"; ?>">
                            <h3><?=$Dictionnary["Signature"] ?? "Signature"; ?></h3>
                            <p class="registration-signature-status"><?php
                                if ($is_profile_form && $has_profile_signature && !$signature_bound)
                                    echo htmlspecialchars($Dictionnary["RegistrationFormSignatureMustBeConfirmed"] ?? "Une signature existe déjà sur votre profil. Tracez-la de nouveau pour la lier explicitement à cette validation.");
                                else
                                    echo htmlspecialchars($has_signature
                                        ? ($Dictionnary["RegistrationFormSignatureRecorded"] ?? "Une signature est déjà enregistrée pour ce formulaire. Dessiner la remplacera.")
                                        : ($Dictionnary["RegistrationFormSignatureOptional"] ?? "Signez ici lorsque cette signature est nécessaire."));
                            ?></p>
                            <canvas width="900" height="260" aria-label="Signature"></canvas>
                            <div><button type="button" data-clear-signature><?=$Dictionnary["Clear"] ?? "Effacer"; ?></button></div>
                        </section>
                        <?php } ?>
                    </fieldset>
                <?php } ?>
            <?php } ?>
            <?php if (($is_profile_form && registration_form_required_profile_signature($schema)) || $is_document_signature) { ?>
                <label class="registration-signature-consent">
                    <input type="checkbox" id="registration-signature-consent" />
                    <span><?=htmlspecialchars($is_document_signature
                        ? ($Dictionnary["DocumentSignatureConsent"] ?? registration_form_document_signature_consent_text())
                        : ($Dictionnary["RegistrationFormSignatureConsent"] ?? registration_form_signature_consent_text())); ?></span>
                </label>
            <?php } ?>
            <div id="registration-form-message" class="registration-form-message" aria-live="polite"></div>
            <div class="registration-form-actions">
                <?php if (!$is_document_signature) { ?><button type="button" id="registration-save"><?=$Dictionnary["RegistrationFormSaveDraft"] ?? "Sauvegarder le brouillon"; ?></button><?php } ?>
                <button type="button" id="registration-finalize"><?=$is_document_signature
                    ? ($Dictionnary["DocumentSignatureAction"] ?? "Signer le document")
                    : ($Dictionnary["RegistrationFormFinalize"] ?? "Valider définitivement"); ?></button>
            </div>
        </form>
    <?php } ?>
</div>
<script><?php require (__DIR__."/script.js"); ?></script>
