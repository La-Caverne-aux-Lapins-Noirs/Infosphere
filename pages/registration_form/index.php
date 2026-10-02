<?php
require_once (__DIR__."/../../tools/registration_form.php");
$token = $_GET["token"] ?? "";
$result = registration_form_fetch_invitation($token, true);
$error = "";
$details = "";
$invitation = NULL;
$student = NULL;
$is_event_public = false;
$is_event_response = false;
$event_response_event = NULL;
if (!$result["ok"])
{
    $error = $Dictionnary[$result["error"]] ?? $result["error"];
    $details = $result["details"] ?? "";
}
else
{
    $invitation = $result["invitation"];
    $is_event_public = !empty($invitation["event_public"]);
    $is_event_response = communication_event_is_response_kind($invitation["kind"] ?? "");
    if ($is_event_response)
        $event_response_event = communication_event_fetch((int)($invitation["id_communication_event"] ?? 0), true);
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
    if (!$is_event_public)
    {
        $student_result = registration_form_student($invitation["id_user"]);
        if (!$student_result["ok"])
        {
            $error = $Dictionnary[$student_result["error"]] ?? $student_result["error"];
            $details = $student_result["details"] ?? "";
        }
        else
            $student = $student_result["student"];
    }
}
?>
<style><?php require (__DIR__."/style.css"); ?><?php require (__DIR__."/../../style/internship_calendar.css"); ?></style>
<div class="registration-form-page">
    <header class="registration-form-header">
        <h1><?php
            if ($is_event_public)
                echo htmlspecialchars($invitation["event"]["name"] ?? "Inscription à un évènement");
            else if ($is_event_response && is_array($event_response_event))
                echo htmlspecialchars($event_response_event["name"] ?? "Inscription à un évènement");
            else if ($invitation && registration_form_is_document_signature_kind($invitation["kind"] ?? ""))
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
        <?php if ($invitation && !$is_event_public) { ?>
            <p><?=htmlspecialchars(trim((string)($invitation["recipient_name"] ?? "")) != ""
                ? trim((string)$invitation["recipient_name"])
                : trim(($invitation["first_name"] ?? "")." ".($invitation["family_name"] ?? "")), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></p>
        <?php } ?>
    </header>
    <?php if ($error != "") { ?>
        <div class="registration-form-message is-error"><strong><?=htmlspecialchars($error); ?></strong><?php if ($details != "") { ?><pre><?=htmlspecialchars($details); ?></pre><?php } ?></div>
    <?php } else if ($invitation["completed_at"] !== NULL) { ?>
        <?php if ($is_event_response) {
            $event_response_data = communication_event_response_data($invitation);
            $event_session = $event_response_data["session"];
        ?>
            <div class="registration-form-message <?=$event_response_data["cancelled"] ? "is-error" : "is-success"; ?>">
                <?=$event_response_data["cancelled"] ? "Votre inscription à cet évènement a été annulée." : "Votre inscription à cet évènement est confirmée."; ?>
            </div>
            <?php if (is_array($event_session)) { ?>
                <div class="registration-event-summary">
                    <strong><?=htmlspecialchars($event_response_event["name"] ?? ($event_session["name"] ?? "Évènement")); ?></strong>
                    <p><?=datex("d/m/Y H:i", date_to_timestamp($event_session["begin_date"])); ?> – <?=datex("H:i", date_to_timestamp($event_session["end_date"])); ?></p>
                    <?php if ((int)$event_response_data["id_team"] > 0) { ?><p>Équipe : <?=htmlspecialchars(communication_event_team_label((int)$event_response_data["id_team"])); ?></p><?php } ?>
                </div>
            <?php } ?>
            <?php if (!$event_response_data["cancelled"]) { ?>
                <form id="registration-event-cancel-form" data-token="<?=htmlspecialchars($token, ENT_QUOTES); ?>" data-url="/api/dabsic/0/registration_event_cancel">
                    <button type="button" data-event-cancel>Annuler mon inscription</button>
                    <span class="registration-event-cancel-message" aria-live="polite"></span>
                </form>
            <?php } ?>
        <?php } else { ?>
            <div class="registration-form-message is-success"><?=$Dictionnary["RegistrationFormAlreadyCompleted"] ?? "Ce dossier a déjà été validé."; ?></div>
        <?php } ?>
    <?php } else {
        $schema = $invitation["schema"];
        $answers = $invitation["answers_data"];
        $is_profile_form = registration_form_is_profile_kind($invitation["kind"] ?? "");
        $is_document_form = registration_form_is_document_kind($invitation["kind"] ?? "");
        $is_document_signature = registration_form_is_document_signature_kind($invitation["kind"] ?? "");
        $show_registration_paraph = registration_form_requires_paraph($invitation["kind"] ?? "", $schema);
        $registration_paraph_file = "";
        $registration_paraph_exists = false;
        $registration_paraph_from_profile = false;
        $registration_paraph_data = "";
        if ($show_registration_paraph && is_array($student))
        {
            $registration_paraph_file = registration_form_paraph_path($invitation, $student);
            $registration_paraph_exists = $registration_paraph_file != "" && is_file($registration_paraph_file);
            if (!$registration_paraph_exists && $is_document_signature
                && function_exists("user_identity_document_initials_file"))
            {
                $profile_initials = user_identity_document_initials_file($student);
                if ($profile_initials != "" && is_file($profile_initials))
                {
                    $registration_paraph_file = $profile_initials;
                    $registration_paraph_exists = true;
                    $registration_paraph_from_profile = true;
                }
            }
            if ($registration_paraph_exists)
            {
                $raw_paraph = @file_get_contents($registration_paraph_file);
                if ($raw_paraph !== false)
                    $registration_paraph_data = "data:image/png;base64,".base64_encode($raw_paraph);
            }
        }
        $document_profile_signature = "";
        if ($is_document_signature && is_array($student) && function_exists("user_identity_document_signature_file"))
        {
            $candidate_signature = user_identity_document_signature_file($student);
            if ($candidate_signature != "" && is_file($candidate_signature))
                $document_profile_signature = $candidate_signature;
        }
    ?>
        <p class="registration-form-intro"><?php
            if ($is_event_public)
                echo nl2br(htmlspecialchars(trim((string)($invitation["event"]["description"] ?? "")) != ""
                    ? trim((string)$invitation["event"]["description"])
                    : "Choisissez votre créneau puis, si vous le souhaitez, rejoignez l’équipe de l’une de vos connaissances."));
            else if ($is_document_signature)
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
            data-confirm-final="<?=htmlspecialchars($is_event_public ? "Confirmer votre inscription à cet évènement ?" : ($Dictionnary["RegistrationFormConfirmFinal"] ?? "Valider définitivement ce dossier ?"), ENT_QUOTES); ?>"
            data-network-error="<?=htmlspecialchars($Dictionnary["DabsicEditorNetworkError"] ?? "Erreur réseau.", ENT_QUOTES); ?>">
            <?php if ($is_event_public) {
                $event_sessions = $schema["event"]["sessions"] ?? [];
                $class_choices = communication_event_class_choices();
            ?>
                <fieldset class="registration-form-group registration-event-fields">
                    <legend>Inscription</legend>
                    <div class="registration-form-field"><span>Prénom *</span><input type="text" required data-registration-field="Event.FirstName" /></div>
                    <div class="registration-form-field"><span>Nom *</span><input type="text" required data-registration-field="Event.FamilyName" /></div>
                    <div class="registration-form-field"><span>Adresse électronique *</span><input type="email" required data-registration-field="Event.Mail" /></div>
                    <div class="registration-form-field"><span>Téléphone *</span><input type="tel" required data-registration-field="Event.Phone" /></div>
                    <div class="registration-form-field"><span>Lycée / établissement actuel *</span><input type="text" required data-registration-field="Event.HighSchool" /></div>
                    <div class="registration-form-field"><span>Ville *</span><input type="text" required data-registration-field="Event.City" /></div>
                    <div class="registration-form-field"><span>Classe / niveau actuel *</span><select required data-registration-field="Event.CurrentClass"><option value=""></option><?php foreach ($class_choices as $value => $label) { ?><option value="<?=htmlspecialchars((string)$value, ENT_QUOTES); ?>"><?=htmlspecialchars($label); ?></option><?php } ?></select></div>
                    <div class="registration-form-field"><span>Spécialités / options</span><input type="text" placeholder="Ex. NSI, mathématiques, SI…" data-registration-field="Event.Specialties" /></div>
                    <div class="registration-form-field"><span>Qu’est-ce qui t’intéresse en informatique ?</span><textarea rows="4" placeholder="Facultatif" data-registration-field="Event.Interests"></textarea></div>
                    <div class="registration-form-field"><span>Créneau *</span><select required data-registration-field="Event.Session" data-event-session><option value=""></option><?php foreach ($event_sessions as $session) {
                        $rooms = array_map(function($room) { return ($room["name"] ?: $room["codename"]); }, $session["rooms"] ?? []);
                        $label = datex("d/m/Y H:i", date_to_timestamp($session["begin_date"]))." – ".datex("H:i", date_to_timestamp($session["end_date"]));
                        if (count($rooms)) $label .= " — ".implode(", ", $rooms);
                    ?><option value="<?=(int)$session["id"]; ?>"><?=htmlspecialchars($label); ?></option><?php } ?></select></div>
                    <div class="registration-form-field registration-event-team-field"><span>Rejoignez si vous le souhaitez l'équipe de l'une de vos connaissances :</span><select required data-registration-field="Event.Team" data-event-team><option value="new">Non merci, créer ma propre équipe</option><?php foreach ($event_sessions as $session) foreach (($session["teams"] ?? []) as $team) { ?><option value="<?=(int)$team["id"]; ?>" data-event-session="<?=(int)$session["id"]; ?>"><?=htmlspecialchars($team["label"]); ?></option><?php } ?></select></div>
                    <?php if (!empty($schema["event"]["parental_authorization_required"])) { ?>
                        <div class="registration-form-field registration-event-parental">
                            <span>Autorisation parentale *</span>
                            <label><input type="checkbox" value="1" required data-registration-field="Event.ParentAuthorization" /> Je confirme que mon représentant légal m’autorise à participer à cet évènement.</label>
                            <small>Cette case enregistre une attestation du participant ; elle ne constitue pas une signature électronique du représentant légal.</small>
                        </div>
                    <?php } ?>
                </fieldset>
            <?php } else if ($is_document_form) {
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
                            <div class="registration-form-field<?=$editable ? "" : " is-readonly"; ?>">
                                <span><?=htmlspecialchars($definition["label"] ?? $field); ?><?php if (!empty($definition["required"])) { ?><strong class="registration-form-required"> *</strong><?php } ?></span>
                                <?php if (!$editable) { ?>
                                    <?php $display = form_field_display_values($definition, $value); ?>
                                    <div class="registration-form-readonly-value"><?=count($display) ? htmlspecialchars(implode(", ", $display)) : "—"; ?></div>
                                <?php } else if (form_field_is_common_type($type)) { ?>
                                    <?=form_field_render($definition, $value, [
                                        "name" => "registration[".$field."]",
                                        "attributes" => ["data-registration-field" => $field],
                                    ]); ?>
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
                            </div>
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
                                        <?php $display = form_field_display_values($definition, $value); ?><div class="registration-form-readonly-value"><?=count($display) ? htmlspecialchars(implode(", ", $display)) : "—"; ?></div>
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
                            <div class="registration-form-field">
                                <span><?=htmlspecialchars($definition["label"] ?? $field); ?><?php if (!$is_profile_form && empty($definition["custom_label"])) { ?><small><?=htmlspecialchars($field); ?></small><?php } ?></span>
                                <?php if (form_field_is_common_type($type)) { ?>
                                    <?=form_field_render($definition, $value, [
                                        "name" => "registration[".$field."]",
                                        "attributes" => ["data-registration-field" => $field],
                                    ]); ?>
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
                            </div>
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
                            <?php if ($is_document_signature && $document_profile_signature != "") { ?>
                                <div class="registration-signature-profile-choice">
                                    <button type="button" data-use-profile-signature>
                                        <?=htmlspecialchars($Dictionnary["DocumentSignatureUseProfile"] ?? "Utiliser ma signature enregistrée"); ?>
                                    </button>
                                    <span data-profile-signature-status></span>
                                </div>
                            <?php } ?>
                            <canvas width="680" height="260" aria-label="Signature"></canvas>
                            <div><button type="button" data-clear-signature><?=$Dictionnary["Clear"] ?? "Effacer"; ?></button></div>
                        </section>
                        <?php } ?>
                    </fieldset>
                <?php } ?>
            <?php } ?>
            <?php if ($show_registration_paraph) { ?>
                <section class="registration-paraph<?=$registration_paraph_exists ? " is-existing" : ""; ?>"
                    data-paraph-existing="<?=$registration_paraph_exists ? "1" : "0"; ?>">
                    <h3><?=htmlspecialchars($Dictionnary["RegistrationFormParaphTitle"] ?? "Paraphe"); ?></h3>
                    <p><?=htmlspecialchars($registration_paraph_from_profile
                        ? "Votre paraphe enregistré est prêt à être utilisé. Vous pouvez le refaire pour le remplacer."
                        : ($Dictionnary["RegistrationFormParaphHelp"] ?? "En conclusion du formulaire, tracez une seule fois vos initiales ou votre paraphe. Il sera associé à cette validation définitive.")); ?></p>
                    <div data-paraph-existing-box <?=$registration_paraph_exists ? "" : "hidden"; ?>>
                        <img data-paraph-current class="registration-paraph-current"
                            src="<?=htmlspecialchars($registration_paraph_data, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>"
                            alt="<?=htmlspecialchars($Dictionnary["RegistrationFormParaphCurrent"] ?? "Paraphe enregistré", ENT_QUOTES); ?>"
                            <?=$registration_paraph_data != "" ? "" : "hidden"; ?> />
                        <div><button type="button" data-replace-paraph><?=htmlspecialchars($Dictionnary["RegistrationFormParaphReplace"] ?? "Refaire le paraphe"); ?></button></div>
                    </div>
                    <div data-paraph-editor <?=$registration_paraph_exists ? "hidden" : ""; ?>>
                        <canvas width="360" height="140" aria-label="<?=htmlspecialchars($Dictionnary["RegistrationFormParaphTitle"] ?? "Paraphe", ENT_QUOTES); ?>"></canvas>
                        <div class="registration-paraph-buttons">
                            <button type="button" data-clear-paraph><?=$Dictionnary["Clear"] ?? "Effacer"; ?></button>
                            <button type="button" data-cancel-paraph <?=$registration_paraph_exists ? "" : "hidden"; ?>><?=htmlspecialchars($Dictionnary["RegistrationFormParaphKeep"] ?? "Conserver le paraphe actuel"); ?></button>
                        </div>
                    </div>
                </section>
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
                <?php if (!$is_document_signature && !$is_event_public) { ?><button type="button" id="registration-save"><?=$Dictionnary["RegistrationFormSaveDraft"] ?? "Sauvegarder le brouillon"; ?></button><?php } ?>
                <button type="button" id="registration-finalize"><?=$is_event_public
                    ? "M’inscrire"
                    : ($is_document_signature
                        ? ($Dictionnary["DocumentSignatureAction"] ?? "Signer le document")
                        : ($Dictionnary["RegistrationFormFinalize"] ?? "Valider définitivement")); ?></button>
            </div>
        </form>
    <?php } ?>
</div>
<script><?php require (__DIR__."/../../script/internship_calendar.js"); ?><?php require (__DIR__."/script.js"); ?></script>
