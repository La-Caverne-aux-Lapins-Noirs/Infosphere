<?php
$einvoice_schools = billing_einvoice_school_readiness();
$einvoice_documents = billing_einvoice_documents();
$einvoice_connectors = billing_einvoice_connectors();
$einvoice_review_documents = billing_einvoice_review_documents();
$einvoice_connector_configs = [];
foreach ($einvoice_schools as $school)
    $einvoice_connector_configs[(int)$school["id"]] = billing_einvoice_connector_config((int)$school["id"]);
?>
<div class="billing_einvoice_tab">
    <div class="billing_einvoice_header">
        <div>
            <h3><?=$Dictionnary["BillingElectronicInvoicing"] ?? "Facturation électronique"; ?></h3>
            <p><?=$Dictionnary["BillingElectronicInvoicingHint"] ?? "Infosphère conserve le document canonique localement. Une plateforme agréée ne sera qu'un connecteur de transport interchangeable."; ?></p>
        </div>
        <form method="post" action="/api/billing/-1/electronic_sync" onsubmit="return billing_einvoice_sync(this);">
            <button type="submit"><?=$Dictionnary["BillingElectronicSync"] ?? "Synchroniser les factures locales"; ?></button>
        </form>
    </div>

    <div class="billing_einvoice_architecture">
        <div><strong>Stockage</strong><span>Infosphère reste la source de vérité</span></div>
        <div><strong>Formats cibles</strong><span>Factur-X · UBL · CII / EN16931</span></div>
        <div><strong>Cible API</strong><span>XP Z12-013</span></div>
        <div><strong>Plateforme</strong><span><?=count($einvoice_connectors) ? billing_e(count($einvoice_connectors)." connecteur(s) chargé(s)") : ($Dictionnary["BillingElectronicNoConnector"] ?? "Aucun connecteur configuré"); ?></span></div>
    </div>

    <h4><?=$Dictionnary["BillingElectronicSchoolReadiness"] ?? "Préparation des écoles"; ?></h4>
    <div class="billing_einvoice_school_grid">
        <?php foreach ($einvoice_schools as $school) { ?>
            <article class="billing_einvoice_school <?=!empty($school["ready"]) ? "ready" : "incomplete"; ?>">
                <div class="billing_einvoice_school_title">
                    <strong><?=billing_e(strtoupper((string)$school["codename"])); ?></strong>
                    <span><?=!empty($school["ready"]) ? "Profil local prêt" : "Profil à compléter"; ?></span>
                </div>
                <div><?=billing_e($school["legal_name_display"] ?: "—"); ?></div>
                <small>SIREN : <?=billing_e($school["siren"] ?: "—"); ?> · SIRET : <?=billing_e($school["siret"] ?: "—"); ?></small>
                <small>TVA : <?=billing_e($school["vat_number"] ?: "—"); ?> · Adresse électronique : <?=billing_e($school["electronic_invoice_address"] ?: "SIREN par défaut"); ?></small>
                <?php if (!empty($school["missing"])) { ?>
                    <small class="billing_einvoice_missing">Manque : <?=billing_e(implode(", ", $school["missing"])); ?></small>
                <?php } ?>
                <?php
                    $connector_config = $einvoice_connector_configs[(int)$school["id"]] ?? NULL;
                    $connector_values = billing_einvoice_connector_configuration($connector_config);
                ?>
                <form
                    class="billing_einvoice_connector_form"
                    method="post"
                    action="/api/billing/<?=(int)$school["id"]; ?>/electronic_connector"
                    onsubmit="return billing_einvoice_save_connector(this);"
                >
                    <label>
                        <span>Connecteur</span>
                        <select name="connector_key" onchange="billing_einvoice_connector_changed(this.form);">
                            <option value="">Aucun connecteur</option>
                            <?php foreach ($einvoice_connectors as $connector_key => $connector) { ?>
                                <option value="<?=billing_e($connector_key); ?>" <?=($connector_config["connector_key"] ?? "") === $connector_key ? "selected" : ""; ?>>
                                    <?=billing_e($connector->label()); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                    <label>
                        <span>Environnement</span>
                        <select name="environment">
                            <option value="test" <?=($connector_config["environment"] ?? "test") === "test" ? "selected" : ""; ?>>Test</option>
                            <option value="production" <?=($connector_config["environment"] ?? "") === "production" ? "selected" : ""; ?>>Production</option>
                        </select>
                    </label>
                    <label class="billing_einvoice_connector_endpoint">
                        <span>Point d’accès API</span>
                        <input type="text" name="endpoint" value="<?=billing_e($connector_config["endpoint"] ?? ""); ?>" placeholder="https://…/flow-service/v1" />
                    </label>
                    <div class="billing_einvoice_xpz12013_config">
                        <label>
                            <span>Token URL OAuth2</span>
                            <input type="text" name="token_url" value="<?=billing_e($connector_values["token_url"] ?? ""); ?>" placeholder="https://…/oauth/token" />
                        </label>
                        <label>
                            <span>Client ID</span>
                            <input type="text" name="client_id" value="<?=billing_e($connector_values["client_id"] ?? ""); ?>" />
                        </label>
                        <label>
                            <span>Client Secret via variable d’environnement</span>
                            <input type="text" name="client_secret_env" value="<?=billing_e($connector_values["client_secret_env"] ?? ""); ?>" placeholder="EFRITS_EINVOICE_CLIENT_SECRET" />
                        </label>
                        <label>
                            <span>ou fichier serveur contenant le Client Secret</span>
                            <input type="text" name="client_secret_file" value="<?=billing_e($connector_values["client_secret_file"] ?? ""); ?>" placeholder="/etc/infosphere/einvoice.secret" />
                        </label>
                        <label>
                            <span>Authentification Client OAuth2</span>
                            <select name="oauth_client_auth">
                                <option value="body" <?=($connector_values["oauth_client_auth"] ?? "body") === "body" ? "selected" : ""; ?>>Client ID/Secret dans le body</option>
                                <option value="basic" <?=($connector_values["oauth_client_auth"] ?? "") === "basic" ? "selected" : ""; ?>>HTTP Basic vers Token URL</option>
                            </select>
                        </label>
                        <label>
                            <span>Scope OAuth2 (si requis)</span>
                            <input type="text" name="oauth_scope" value="<?=billing_e($connector_values["oauth_scope"] ?? ""); ?>" />
                        </label>
                        <label>
                            <span>Organisation-Id (si requis)</span>
                            <input type="text" name="organisation_id" value="<?=billing_e($connector_values["organisation_id"] ?? ""); ?>" />
                        </label>
                        <label>
                            <span>Historique initial à récupérer (jours)</span>
                            <input type="number" min="1" max="730" name="receive_initial_days" value="<?=billing_e($connector_values["receive_initial_days"] ?? "30"); ?>" />
                        </label>
                        <label>
                            <span>Marge de synchronisation (minutes)</span>
                            <input type="number" min="0" max="1440" name="receive_delay_minutes" value="<?=billing_e($connector_values["receive_delay_minutes"] ?? "15"); ?>" />
                        </label>
                        <label>
                            <span>Reprise après erreur (minutes)</span>
                            <input type="number" min="1" max="1440" name="retry_base_minutes" value="<?=billing_e($connector_values["retry_base_minutes"] ?? "5"); ?>" />
                        </label>
                        <label>
                            <span>Nombre maximal de tentatives</span>
                            <input type="number" min="1" max="20" name="retry_max_attempts" value="<?=billing_e($connector_values["retry_max_attempts"] ?? "6"); ?>" />
                        </label>
                        <small>Le Client Secret lui-même n’est pas stocké dans Infosphère : indique une variable d’environnement ou un fichier serveur. Le point d’accès doit viser la racine versionnée du Flow Service (par exemple …/flow-service/v1).</small>
                    </div>
                    <label class="billing_einvoice_connector_enabled billing_einvoice_payment_reporting">
                        <input type="checkbox" name="payment_reporting" value="1" <?=!empty($connector_config["payment_reporting"]) ? "checked" : ""; ?> />
                        <span>TVA exigible à l’encaissement : préparer/transmettre les encaissements</span>
                    </label>
                    <small class="billing_einvoice_payment_reporting_hint">À activer seulement si la situation fiscale de cette école impose la transmission des encaissements. Infosphère ne la déduit pas automatiquement.</small>
                    <label class="billing_einvoice_connector_enabled">
                        <input type="checkbox" name="enabled" value="1" <?=!empty($connector_config["enabled"]) ? "checked" : ""; ?> />
                        <span>Activer pour cette école</span>
                    </label>
                    <?php
                        $school_connector = !empty($connector_config["connector_key"])
                            ? ($einvoice_connectors[$connector_config["connector_key"]] ?? NULL) : NULL;
                        $can_receive = $school_connector != NULL && !empty($school_connector->capabilities()["receive_einvoice"]);
                    ?>
                    <div class="billing_einvoice_connector_actions">
                        <button type="submit">Enregistrer</button>
                        <button type="button" class="secondary" onclick="billing_einvoice_test_connector(<?=(int)$school["id"]; ?>, this);" <?=empty($connector_config["enabled"]) || empty($connector_config["connector_key"]) ? "disabled" : ""; ?>>Tester</button>
                        <?php if ($can_receive) { ?>
                            <button type="button" onclick="billing_einvoice_receive(<?=(int)$school["id"]; ?>, this);" <?=empty($connector_config["enabled"]) ? "disabled" : ""; ?>>Récupérer les factures reçues</button>
                        <?php } ?>
                        <button type="button" class="secondary" onclick="billing_einvoice_retry_school(<?=(int)$school["id"]; ?>, this);" <?=empty($connector_config["enabled"]) ? "disabled" : ""; ?>>Retenter les erreurs</button>
                    </div>
                    <?php if (!empty($connector_config["receive_at"])) { ?>
                        <small>Dernière récupération : <?=billing_e(billing_document_date_label($connector_config["receive_at"])); ?></small>
                    <?php } ?>
                </form>
                <?php if (($connector_config["connector_key"] ?? "") === "mock" && function_exists("billing_einvoice_mock_summary")) {
                    $mock = billing_einvoice_mock_summary((int)$school["id"]);
                ?>
                <section class="billing_einvoice_mock_panel">
                    <div class="billing_einvoice_mock_header">
                        <div>
                            <strong>Simulateur local</strong>
                            <small>Aucune donnée ne quitte Infosphère. État éphémère conservé dans le répertoire temporaire du serveur.</small>
                        </div>
                        <span><?=count($mock["flows"]); ?> flux émis · <?=(int)$mock["pending_supplier"]; ?> facture(s) reçue(s) · <?=(int)$mock["pending_lifecycle"]; ?> retour(s) CDAR</span>
                    </div>

                    <div class="billing_einvoice_mock_grid">
                        <form method="post" action="/api/billing/<?=(int)$school["id"]; ?>/electronic_mock" onsubmit="return billing_einvoice_mock_submit(this);" class="billing_einvoice_mock_card">
                            <input type="hidden" name="mock_action" value="next_send" />
                            <strong>Prochaine transmission</strong>
                            <label><span>Comportement</span><select name="mode">
                                <option value="normal" <?=$mock["next_send_mode"] === "normal" ? "selected" : ""; ?>>Normale</option>
                                <option value="technical_error" <?=$mock["next_send_mode"] === "technical_error" ? "selected" : ""; ?>>Erreur technique avant dépôt</option>
                                <option value="ambiguous_timeout" <?=$mock["next_send_mode"] === "ambiguous_timeout" ? "selected" : ""; ?>>Timeout ambigu après dépôt</option>
                            </select></label>
                            <button type="submit">Armer le scénario</button>
                            <small>Le timeout ambigu crée réellement le flux côté Mock puis renvoie une erreur à Infosphère : le retry doit le retrouver sans doublon.</small>
                        </form>

                        <form method="post" action="/api/billing/<?=(int)$school["id"]; ?>/electronic_mock" enctype="multipart/form-data" data-direct-file-upload="1" onsubmit="return billing_einvoice_mock_submit(this);" class="billing_einvoice_mock_card billing_einvoice_mock_supplier">
                            <input type="hidden" name="mock_action" value="supplier" />
                            <strong>Injecter une facture fournisseur</strong>
                            <div class="billing_einvoice_mock_supplier_fields">
                                <label><span>Fournisseur</span><input type="text" name="supplier_name" value="Fournisseur test" required /></label>
                                <label><span>SIRET</span><input type="text" name="supplier_siret" value="73282932000074" pattern="[0-9 ]{14,20}" required /></label>
                                <label><span>Référence</span><input type="text" name="reference" value="MOCK-<?=date('Ymd-His'); ?>" required /></label>
                                <label><span>Date</span><input type="date" name="issue_date" value="<?=date('Y-m-d'); ?>" required /></label>
                                <label><span>TTC</span><input type="text" name="amount" value="120.00" required /></label>
                                <label><span>TVA %</span><input type="number" name="vat_rate" value="20" min="0" max="100" step="0.01" required /></label>
                                <label class="billing_einvoice_mock_pdf"><span>PDF lisible (facultatif)</span><input type="file" name="invoice_pdf" accept="application/pdf,.pdf" /></label>
                            </div>
                            <button type="submit">Placer dans la boîte de réception</button>
                        </form>
                    </div>

                    <?php if (count($mock["flows"])) { ?>
                    <div class="billing_einvoice_mock_flows">
                        <strong>Flux émis vers le Mock</strong>
                        <?php foreach ($mock["flows"] as $mock_flow) { ?>
                        <form method="post" action="/api/billing/<?=(int)$school["id"]; ?>/electronic_mock" onsubmit="return billing_einvoice_mock_submit(this);" class="billing_einvoice_mock_flow">
                            <input type="hidden" name="mock_action" value="status" />
                            <input type="hidden" name="flow_id" value="<?=billing_e($mock_flow["flow_id"]); ?>" />
                            <input type="hidden" name="id_document" value="<?=(int)$mock_flow["document_id"]; ?>" />
                            <div>
                                <strong><?=billing_e($mock_flow["reference"] ?: ("Document #".(int)$mock_flow["document_id"])); ?></strong>
                                <small><?=billing_e($mock_flow["flow_id"]); ?> · <?=billing_e($mock_flow["kind"]); ?> · <?=billing_e(billing_einvoice_status_label($mock_flow["status"])); ?></small>
                            </div>
                            <select name="status_code">
                                <?php foreach (["200" => "Déposée", "203" => "Mise à disposition", "205" => "Acceptée", "207" => "En litige", "210" => "Refusée", "212" => "Encaissée", "213" => "Rejetée"] as $mock_code => $mock_label) { ?>
                                    <option value="<?=$mock_code; ?>" <?=(string)($mock_flow["status_code"] ?? "") === (string)$mock_code ? "selected" : ""; ?>><?=$mock_code; ?> — <?=$mock_label; ?></option>
                                <?php } ?>
                            </select>
                            <button type="submit">Changer le statut distant</button>
                            <button type="button" class="secondary" onclick="billing_einvoice_mock_lifecycle(this.form);">Mettre ce CDAR en réception</button>
                        </form>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <form method="post" action="/api/billing/<?=(int)$school["id"]; ?>/electronic_mock" onsubmit="return billing_einvoice_mock_submit(this, 'Vider tout l’état de la plateforme Mock ? Les documents Infosphère ne seront pas supprimés.');" class="billing_einvoice_mock_reset">
                        <input type="hidden" name="mock_action" value="reset" />
                        <button type="submit" class="secondary">Vider la plateforme Mock</button>
                    </form>
                </section>
                <?php } ?>
            </article>
        <?php } ?>
        <?php if (!count($einvoice_schools)) { ?>
            <div class="billing_empty_tab">Aucune école accessible en comptabilité.</div>
        <?php } ?>
    </div>

    <div class="billing_einvoice_note">
        <strong>Classification :</strong>
        une facture sans entreprise destinataire reste en <em>e-reporting transaction</em>.
        Une organisation française avec SIREN explicitement choisie sur la facture la fait passer en <em>e-invoicing B2B</em>.
        L’export UBL est généré localement à partir du snapshot canonique ; il reste indiqué « à valider » tant qu’il n’a pas été passé dans les contrôles AFNOR/XSD officiels.
    </div>

    <?php if (count($einvoice_review_documents)) { ?>
    <section class="billing_einvoice_review_queue">
        <div class="billing_einvoice_table_header">
            <h4>À contrôler</h4>
            <span><?=count($einvoice_review_documents); ?> élément(s)</span>
        </div>
        <?php foreach ($einvoice_review_documents as $review_document) {
            $review_party = trim((string)($review_document["seller_legal_name"] ?? ""));
            if ($review_party == "") $review_party = trim((string)($review_document["seller_name"] ?? ""));
            if ($review_party == "") $review_party = (string)($review_document["seller_codename"] ?? "");
        ?>
            <article class="billing_einvoice_review_item">
                <div>
                    <strong><?=billing_e($review_document["local_reference"] ?? "—"); ?></strong>
                    <span><?=billing_e($review_document["school_codename"] ?? ""); ?> · <?=billing_e(billing_einvoice_status_label($review_document["status"] ?? "")); ?><?php if ($review_party != "") { ?> · <?=billing_e($review_party); ?><?php } ?></span>
                    <?php if (!empty($review_document["last_transport_error"])) { ?><small><?=billing_e($review_document["last_transport_error"]); ?></small><?php } ?>
                    <?php if (($review_document["regulatory_validation_status"] ?? "") == "invalid") { ?><small>Validation réglementaire externe invalide<?php if (!empty($review_document["regulatory_validation_details"])) { ?> : <?=billing_e(substr((string)$review_document["regulatory_validation_details"], 0, 280)); ?><?php } ?></small><?php } ?>
                    <?php if (!empty($review_document["review_note"])) { ?><small><?=billing_e($review_document["review_note"]); ?></small><?php } ?>
                </div>
                <div class="billing_einvoice_review_actions">
                    <?php if (($review_document["status"] ?? "") == "transport_error") { ?>
                        <button type="button" onclick="billing_einvoice_send(<?=(int)$review_document["id"]; ?>, this);">Réessayer</button>
                    <?php } ?>
                    <button type="button" class="secondary" onclick="billing_einvoice_review(<?=(int)$review_document["id"]; ?>, this);">Marquer contrôlé</button>
                </div>
            </article>
        <?php } ?>
    </section>
    <?php } ?>

    <div class="billing_einvoice_table_header">
        <h4><?=$Dictionnary["BillingElectronicDocuments"] ?? "Documents électroniques locaux"; ?></h4>
        <span><?=count($einvoice_documents); ?> document(s)</span>
    </div>
    <div class="billing_einvoice_table_scroll">
        <table class="billing_einvoice_table">
            <thead><tr>
                <th>Date</th>
                <th>École</th>
                <th>Référence</th>
                <th>Nature</th>
                <th>Tiers</th>
                <th>Statut</th>
                <th>Structuré</th>
                <th>Contrôle local</th>
                <th>Transport</th>
                <th>Journal</th>
            </tr></thead>
            <tbody>
            <?php if (!count($einvoice_documents)) { ?>
                <tr><td colspan="10" class="billing_empty_tab">Aucun document préparé. Utilisez « Synchroniser les factures locales ».</td></tr>
            <?php } ?>
            <?php foreach ($einvoice_documents as $document) {
                if (($document["direction"] ?? "outgoing") == "incoming")
                {
                    $buyer = trim((string)($document["seller_legal_name"] ?? ""));
                    if ($buyer == "") $buyer = trim((string)($document["seller_name"] ?? ""));
                    if ($buyer == "") $buyer = (string)($document["seller_codename"] ?? "—");
                }
                else
                {
                    $buyer = trim((string)($document["buyer_organization_legal_name"] ?? ""));
                    if ($buyer == "") $buyer = trim((string)($document["buyer_organization_name"] ?? ""));
                    if ($buyer == "") $buyer = trim((string)($document["buyer_first_name"] ?? "")." ".(string)($document["buyer_family_name"] ?? ""));
                    if ($buyer == "") $buyer = (string)($document["buyer_codename"] ?? ($document["buyer_organization_codename"] ?? "—"));
                }
                $events = billing_einvoice_events((int)$document["id"]);
                $local_validation = billing_einvoice_document_local_validation($document);
                $transport = billing_einvoice_transport_state($document);
            ?>
                <tr>
                    <td><?=billing_e(billing_document_date_label($document["issue_date"])); ?></td>
                    <td><?=billing_e($document["school_codename"] ?? ""); ?></td>
                    <td>
                        <?php if (($document["source_type"] ?? "") == "billing_entry" && (int)$document["source_id"] > 0) { ?>
                            <a target="_blank" href="/api/billing/<?=(int)$document["source_id"]; ?>/invoice"><?=billing_e($document["local_reference"]); ?></a>
                        <?php } else { ?>
                            <?=billing_e($document["local_reference"]); ?>
                        <?php } ?>
                    </td>
                    <td>
                        <?=billing_e($document["document_type"] == "payment" ? "Encaissement" : ($document["document_type"] == "credit_note" ? "Avoir" : "Facture")); ?><br />
                        <small><?=($document["direction"] ?? "outgoing") == "incoming" ? "Reçue" : "Émise"; ?> · <?=billing_e(billing_einvoice_flow_label($document["flow_type"])); ?></small>
                    </td>
                    <td><?=billing_e($buyer); ?></td>
                    <td><span class="billing_einvoice_status status_<?=billing_e($document["status"]); ?>"><?=billing_e(billing_einvoice_status_label($document["status"])); ?></span></td>
                    <td>
                        <?php if (($document["direction"] ?? "outgoing") == "incoming") { ?>
                            <?php $raw_url = billing_einvoice_raw_url($document); ?>
                            <?php if ($raw_url != "") { ?><a class="billing_einvoice_ubl" target="_blank" href="<?=billing_e($raw_url); ?>">Original <?=billing_e(strtoupper((string)($document["raw_format"] ?? ""))); ?></a><?php } else { ?>—<?php } ?><br />
                            <small><?php if (($document["status"] ?? "") == "received_unparsed") { ?>Format conservé, interprétation à ajouter<?php } else if (($document["status"] ?? "") == "received_review") { ?>Import structuré · rapprochement manuel requis<?php } else { ?>Import structuré<?php } ?></small>
                        <?php } else if (in_array(($document["flow_type"] ?? ""), ["ereporting_payment", "einvoice_lifecycle"], true)) { ?>
                            <a class="billing_einvoice_ubl" target="_blank" href="/api/billing/<?=(int)$document["id"]; ?>/electronic_cdar">CDAR 212</a><br />
                            <small>Encaissement · à valider</small>
                        <?php } else if (($document["flow_type"] ?? "") == "out_of_scope_exempt") { ?>
                            <a class="billing_einvoice_ubl" target="_blank" href="/api/billing/<?=(int)$document["id"]; ?>/electronic_ubl">UBL 2.1</a><br />
                            <small>Représentation locale · hors champ</small>
                        <?php } else { ?>
                            <a class="billing_einvoice_ubl" target="_blank" href="/api/billing/<?=(int)$document["id"]; ?>/electronic_ubl">UBL 2.1</a><br />
                            <small>EN16931 · à valider</small>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if (($document["status"] ?? "") == "received_unparsed") { ?>
                            <span class="billing_einvoice_validation incomplete">Non interprété</span>
                            <small class="billing_einvoice_validation_limit">Le flux est conservé sans écriture comptable automatique.</small>
                        <?php } else { ?>
                        <span class="billing_einvoice_validation <?=!empty($local_validation["ok"]) ? "ready" : "incomplete"; ?>">
                            <?=!empty($local_validation["ok"]) ? "Prêt localement" : "Incomplet"; ?>
                        </span>
                        <?php if (!empty($local_validation["errors"])) { ?>
                            <details class="billing_einvoice_validation_details errors">
                                <summary><?=count($local_validation["errors"]); ?> anomalie(s)</summary>
                                <?php foreach ($local_validation["errors"] as $error) { ?>
                                    <div><?=billing_e(billing_einvoice_validation_label($error)); ?></div>
                                <?php } ?>
                            </details>
                        <?php } ?>
                        <?php if (!empty($local_validation["warnings"])) { ?>
                            <details class="billing_einvoice_validation_details warnings">
                                <summary><?=count($local_validation["warnings"]); ?> avertissement(s)</summary>
                                <?php foreach ($local_validation["warnings"] as $warning) { ?>
                                    <div><?=billing_e(billing_einvoice_validation_label($warning)); ?></div>
                                <?php } ?>
                            </details>
                        <?php } ?>
                        <?php if (($document["flow_type"] ?? "") == "out_of_scope_exempt") { ?>
                            <small class="billing_einvoice_validation_limit">Validation réglementaire : non requise · opération hors champ</small>
                        <?php } else { ?>
                            <?php $regulatory_status = trim((string)($document["regulatory_validation_status"] ?? "")); ?>
                            <small class="billing_einvoice_validation_limit">
                                Validation réglementaire : <?=billing_e($regulatory_status != "" ? $regulatory_status : "non exécutée"); ?>
                                <?php if (!empty($document["regulatory_validation_at"])) { ?> · <?=billing_e(billing_document_date_label($document["regulatory_validation_at"])); ?><?php } ?>
                            </small>
                            <button type="button" class="billing_einvoice_transport_action secondary" onclick="billing_einvoice_validate(<?=(int)$document["id"]; ?>, this);">Valider externe</button>
                            <?php if (!empty($document["regulatory_validation_details"])) { ?>
                                <details class="billing_einvoice_validation_details"><summary>Détails validation</summary><div><?=nl2br(billing_e($document["regulatory_validation_details"])); ?></div></details>
                            <?php } ?>
                        <?php } ?>
                        <?php } ?>
                    </td>
                    <td class="billing_einvoice_transport_cell">
                        <?php if (($document["direction"] ?? "outgoing") == "incoming") { ?>
                            <span class="billing_einvoice_transport_ready">Réceptionnée</span>
                            <?php if (!empty($document["provider_key"])) { ?><small><?=billing_e($document["provider_key"]); ?> · <?=billing_e($document["provider_document_id"] ?? ""); ?></small><?php } ?>
                        <?php } else if (($document["status"] ?? "") == "transport_error") { ?>
                            <span class="billing_einvoice_transport_error">Erreur de transport</span>
                            <?php if (!empty($document["last_transport_error"])) { ?><small><?=billing_e($document["last_transport_error"]); ?></small><?php } ?>
                            <?php if (!empty($document["next_transport_retry_at"])) { ?><small>Nouvelle tentative : <?=billing_e(billing_document_date_label($document["next_transport_retry_at"])); ?></small><?php } ?>
                            <button type="button" class="billing_einvoice_transport_action" onclick="billing_einvoice_send(<?=(int)$document["id"]; ?>, this);">Réessayer</button>
                        <?php } else if (!empty($document["provider_key"])) { ?>
                            <strong><?=billing_e($document["provider_key"]); ?></strong>
                            <?php if (!empty($document["provider_document_id"])) { ?><small><?=billing_e($document["provider_document_id"]); ?></small><?php } ?>
                            <?php if (!empty($transport["connector"]) && !empty($transport["connector"]->capabilities()["refresh_status"])) { ?>
                                <button type="button" class="billing_einvoice_transport_action secondary" onclick="billing_einvoice_refresh(<?=(int)$document["id"]; ?>, this);">Actualiser</button>
                            <?php } ?>
                        <?php } else if (!empty($transport["ready"])) { ?>
                            <span class="billing_einvoice_transport_ready">Prêt à transmettre</span>
                            <button type="button" class="billing_einvoice_transport_action" onclick="billing_einvoice_send(<?=(int)$document["id"]; ?>, this);">Transmettre</button>
                        <?php } else { ?>
                            <span class="billing_einvoice_local_only"><?=billing_e(billing_einvoice_transport_reason_label($transport["reason"] ?? "")); ?></span>
                        <?php } ?>
                    </td>
                    <td class="billing_einvoice_journal_cell">
                        <details class="billing_einvoice_events">
                            <summary><?=count($events); ?> événement(s)</summary>
                            <?php foreach ($events as $event) { ?>
                                <div><strong><?=billing_e($event["event_code"]); ?></strong> · <?=billing_e(billing_document_date_label($event["event_date"])); ?> <small><?=billing_e($event["event_source"]); ?></small></div>
                            <?php } ?>
                            <div class="billing_einvoice_hash" title="SHA-256 du document canonique">SHA-256 <?=billing_e(substr((string)$document["canonical_sha256"], 0, 16)); ?>…</div>
                        </details>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function billing_einvoice_connector_changed(form)
{
    if (!form)
        return ;
    let has_connector = !!form.elements.connector_key.value;
    if (form.elements.enabled)
    {
        form.elements.enabled.disabled = !has_connector;
        if (!has_connector)
            form.elements.enabled.checked = false;
    }
    let key = form.elements.connector_key.value;
    let xp = form.querySelector(".billing_einvoice_xpz12013_config");
    if (xp)
    {
        let active = key == "xpz12013";
        xp.style.display = active ? "grid" : "none";
        xp.querySelectorAll("input, select, textarea").forEach(function (field) { field.disabled = !active; });
    }
    let endpoint = form.querySelector(".billing_einvoice_connector_endpoint");
    if (endpoint)
    {
        let active = key != "mock" && key != "";
        endpoint.style.display = active ? "flex" : "none";
        endpoint.querySelectorAll("input, select, textarea").forEach(function (field) { field.disabled = !active; });
    }
}

function billing_einvoice_save_connector(form)
{
    billing_einvoice_connector_changed(form);
    return silent_submitf(form, {
        after_success: function () { billing_einvoice_refresh_view(); }
    });
}

function billing_einvoice_post(action, button, confirm_message)
{
    if (confirm_message && !window.confirm(confirm_message))
        return (false);
    if (button)
        button.disabled = true;
    let form = document.createElement("form");
    form.method = "post";
    form.action = action;
    return silent_submitf(form, {
        after_success: function () { billing_einvoice_refresh_view(); },
        after_complete: function (success) { if (!success && button) button.disabled = false; }
    });
}

function billing_einvoice_mock_submit(form, confirmation)
{
    if (confirmation && !window.confirm(confirmation))
        return false;
    return silent_submitf(form, {
        after_success: function () { billing_einvoice_refresh_view(); }
    });
}

function billing_einvoice_mock_lifecycle(form)
{
    if (!form)
        return false;
    let action = form.elements.mock_action;
    let previous = action.value;
    action.value = "lifecycle";
    return silent_submitf(form, {
        after_success: function () { billing_einvoice_refresh_view(); },
        after_complete: function (success) { action.value = previous; }
    });
}

function billing_einvoice_test_connector(id_school, button)
{
    return billing_einvoice_post("/api/billing/" + id_school + "/electronic_connector_test", button, "");
}

function billing_einvoice_send(id_document, button)
{
    return billing_einvoice_post(
        "/api/billing/" + id_document + "/electronic_send",
        button,
        "Transmettre ce document avec le connecteur configuré pour cette école ?"
    );
}

function billing_einvoice_refresh(id_document, button)
{
    return billing_einvoice_post("/api/billing/" + id_document + "/electronic_refresh", button, "");
}

function billing_einvoice_receive(id_school, button)
{
    return billing_einvoice_post(
        "/api/billing/" + id_school + "/electronic_receive",
        button,
        "Récupérer les nouvelles factures électroniques de cette école ?"
    );
}

function billing_einvoice_retry_school(id_school, button)
{
    return billing_einvoice_post(
        "/api/billing/" + id_school + "/electronic_retry",
        button,
        "Retenter maintenant les transmissions en erreur pour cette école ?"
    );
}

function billing_einvoice_review(id_document, button)
{
    let note = window.prompt("Note de contrôle (facultative) :", "");
    if (note === null)
        return false;
    if (button) button.disabled = true;
    let form = document.createElement("form");
    form.method = "post";
    form.action = "/api/billing/" + id_document + "/electronic_review";
    let field = document.createElement("input");
    field.type = "hidden"; field.name = "note"; field.value = note; form.appendChild(field);
    return silent_submitf(form, {
        after_success: function () { billing_einvoice_refresh_view(); },
        after_complete: function (success) { if (!success && button) button.disabled = false; }
    });
}

function billing_einvoice_validate(id_document, button)
{
    return billing_einvoice_post("/api/billing/" + id_document + "/electronic_validate", button, "");
}

window.setTimeout(function () {
    document.querySelectorAll(".billing_einvoice_connector_form").forEach(billing_einvoice_connector_changed);
}, 0);
</script>
