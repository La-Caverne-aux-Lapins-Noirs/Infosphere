<?php

/*
 * Local electronic-invoicing simulator.
 *
 * Nothing leaves Infosphère.  State is intentionally ephemeral and kept out
 * of dres/database so the mock cannot become an accidental production data
 * source.  It is only a deterministic stand-in for a PA while exercising the
 * real billing workflow.
 */

function billing_einvoice_mock_state_root()
{
    $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.
        "infosphere-einvoice-mock-".substr(sha1((string)realpath(__DIR__."/../..")), 0, 16);
    if (!is_dir($root))
        @mkdir($root, 0775, true);
    return ($root);
}

function billing_einvoice_mock_state_file($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (NULL);
    $school = db_select_one("codename FROM school WHERE id = $id_school AND deleted IS NULL");
    if ($school == NULL)
        return (NULL);
    $codename = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$school["codename"]);
    return (billing_einvoice_mock_state_root().DIRECTORY_SEPARATOR.$codename."-".$id_school.".json");
}

function billing_einvoice_mock_empty_state()
{
    return ([
        "version" => 1,
        "next_flow" => 1,
        "next_sequence" => 1,
        "next_send_mode" => "normal",
        "flows" => [],
        "incoming" => [],
    ]);
}

function billing_einvoice_mock_state($id_school)
{
    $file = billing_einvoice_mock_state_file($id_school);
    if ($file === NULL || !is_file($file))
        return (billing_einvoice_mock_empty_state());
    $json = @file_get_contents($file);
    $state = $json === false ? NULL : json_decode($json, true);
    if (!is_array($state))
        return (billing_einvoice_mock_empty_state());
    foreach (billing_einvoice_mock_empty_state() as $key => $value)
        if (!array_key_exists($key, $state))
            $state[$key] = $value;
    if (!is_array($state["flows"])) $state["flows"] = [];
    if (!is_array($state["incoming"])) $state["incoming"] = [];
    return ($state);
}

function billing_einvoice_mock_save_state($id_school, $state)
{
    $file = billing_einvoice_mock_state_file($id_school);
    if ($file === NULL || !is_array($state))
        return (false);
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true))
        return (false);
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false)
        return (false);
    $tmp = $file.".".getmypid().".".bin2hex(random_bytes(4)).".tmp";
    if (@file_put_contents($tmp, $json, LOCK_EX) === false)
        return (false);
    @chmod($tmp, 0660);
    if (!@rename($tmp, $file))
    {
        @unlink($tmp);
        return (false);
    }
    return (true);
}

function billing_einvoice_mock_flow_id($id_school, &$state, $prefix = "flow")
{
    $counter = max(1, (int)($state["next_flow"] ?? 1));
    $state["next_flow"] = $counter + 1;
    return ("mock-".$prefix."-".(int)$id_school."-".$counter);
}

function billing_einvoice_mock_sequence(&$state)
{
    $sequence = max(1, (int)($state["next_sequence"] ?? 1));
    $state["next_sequence"] = $sequence + 1;
    return ($sequence);
}

function billing_einvoice_mock_status_code($status)
{
    $map = [
        "submitted" => "200", "issued" => "201", "received" => "202",
        "available" => "203", "taken_over" => "204", "accepted" => "205",
        "partially_accepted" => "206", "disputed" => "207", "suspended" => "208",
        "completed" => "209", "refused" => "210", "payment_sent" => "211",
        "paid" => "212", "rejected" => "213",
    ];
    return ($map[(string)$status] ?? "200");
}

function billing_einvoice_mock_result_from_flow($flow, $event = "mock_flow")
{
    return ([
        "ok" => true,
        "provider_document_id" => (string)$flow["flow_id"],
        "transmission_id" => (string)$flow["flow_id"],
        "status" => (string)($flow["status"] ?? "submitted"),
        "external_status_code" => (string)($flow["status_code"] ?? billing_einvoice_mock_status_code($flow["status"] ?? "submitted")),
        "event_code" => $event,
        "message" => "Réponse simulée par le connecteur Mock",
    ]);
}

function billing_einvoice_mock_set_next_send_mode($id_school, $mode)
{
    $mode = (string)$mode;
    if (!in_array($mode, ["normal", "technical_error", "ambiguous_timeout"], true))
        return (false);
    $state = billing_einvoice_mock_state($id_school);
    $state["next_send_mode"] = $mode;
    return (billing_einvoice_mock_save_state($id_school, $state));
}

function billing_einvoice_mock_set_flow_status($id_school, $flow_id, $status_code)
{
    $status_code = trim((string)$status_code);
    if (!preg_match('/^(20[0-9]|21[0-3])$/', $status_code))
        return (false);
    $state = billing_einvoice_mock_state($id_school);
    if (!isset($state["flows"][$flow_id]) || !is_array($state["flows"][$flow_id]))
        return (false);
    $state["flows"][$flow_id]["status_code"] = $status_code;
    $state["flows"][$flow_id]["status"] = billing_einvoice_status_from_reform_code($status_code, "processing");
    $state["flows"][$flow_id]["updated_at"] = gmdate("Y-m-d\\TH:i:s\\Z");
    return (billing_einvoice_mock_save_state($id_school, $state));
}

function billing_einvoice_mock_lifecycle_xml($reference, $status_code)
{
    $reference = billing_einvoice_xml_escape((string)$reference);
    $status_code = billing_einvoice_xml_escape((string)$status_code);
    return ('<?xml version="1.0" encoding="UTF-8"?>'.
        '<MockLifecycleDocument>'.
        '<ProcessConditionCode>'.$status_code.'</ProcessConditionCode>'.
        '<ReferenceReferencedDocument>'.
        '<IssuerAssignedID>'.$reference.'</IssuerAssignedID>'.
        '<ProcessConditionCode>'.$status_code.'</ProcessConditionCode>'.
        '</ReferenceReferencedDocument>'.
        '</MockLifecycleDocument>');
}

function billing_einvoice_mock_queue_lifecycle($id_school, $id_document, $status_code)
{
    $id_school = (int)$id_school;
    $id_document = (int)$id_document;
    $status_code = trim((string)$status_code);
    if (!preg_match('/^(20[0-9]|21[0-3])$/', $status_code))
        return (false);
    $document = billing_einvoice_document($id_document);
    if ($document == NULL || (int)$document["id_school"] !== $id_school || ($document["direction"] ?? "outgoing") != "outgoing")
        return (false);
    $reference = trim((string)($document["local_reference"] ?? ""));
    if ($reference == "")
        return (false);
    $state = billing_einvoice_mock_state($id_school);
    $flow_id = billing_einvoice_mock_flow_id($id_school, $state, "lc");
    $sequence = billing_einvoice_mock_sequence($state);
    $updated = gmdate("Y-m-d\\TH:i:s\\Z");
    $state["incoming"][] = [
        "sequence" => $sequence,
        "kind" => "lifecycle",
        "remote" => [
            "flow_id" => $flow_id,
            "updated_at" => $updated,
            "content" => billing_einvoice_mock_lifecycle_xml($reference, $status_code),
            "content_type" => "application/xml",
            "metadata" => [
                "flowId" => $flow_id,
                "flowType" => "CustomerInvoiceLC",
                "flowDirection" => "In",
                "flowSyntax" => "CDAR",
                "updatedAt" => $updated,
            ],
        ],
    ];
    return (billing_einvoice_mock_save_state($id_school, $state));
}


function billing_einvoice_mock_queue_lifecycle_for_flow($id_school, $flow_id)
{
    $id_school = (int)$id_school;
    $flow_id = trim((string)$flow_id);
    if ($id_school <= 0 || $flow_id == "")
        return (false);
    $state = billing_einvoice_mock_state($id_school);
    $flow = $state["flows"][$flow_id] ?? NULL;
    if (!is_array($flow))
        return (false);
    $id_document = (int)($flow["document_id"] ?? 0);
    $status_code = trim((string)($flow["status_code"] ?? ""));
    if ($id_document <= 0 || !preg_match('/^(20[0-9]|21[0-3])$/', $status_code))
        return (false);
    return (billing_einvoice_mock_queue_lifecycle($id_school, $id_document, $status_code));
}

function billing_einvoice_mock_supplier_party($name, $siret)
{
    $digits = preg_replace('/[^0-9]/', '', (string)$siret);
    $siren = strlen($digits) >= 9 ? substr($digits, 0, 9) : "";
    return ([
        "kind" => "organization",
        "codename" => "mock-supplier-".$siren,
        "name" => (string)$name,
        "legal_name" => (string)$name,
        "siren" => $siren,
        "siret" => $digits,
        "vat_number" => "",
        "electronic_address" => $siren,
        "electronic_address_scheme" => $siren != "" ? "0225" : "",
        "routing_code" => "",
        "routing_scheme" => "",
        "address" => [
            "line1" => "1 rue du Test",
            "line2" => "",
            "zipcode" => "75001",
            "city" => "Paris",
            "country" => "France",
            "legacy" => "",
        ],
        "contact" => ["mail" => "", "phone" => ""],
    ]);
}

function billing_einvoice_mock_queue_supplier_invoice($id_school, $data, $pdf_content = NULL)
{
    $id_school = (int)$id_school;
    $name = trim((string)($data["supplier_name"] ?? ""));
    $siret = preg_replace('/[^0-9]/', '', (string)($data["supplier_siret"] ?? ""));
    $reference = trim((string)($data["reference"] ?? ""));
    $issue_date = trim((string)($data["issue_date"] ?? ""));
    $gross = (int)($data["gross"] ?? 0);
    $rate = (float)($data["vat_rate"] ?? 20);
    if ($name == "" || strlen($siret) != 14 || $reference == "" || !billing_einvoice_valid_date($issue_date) || $gross <= 0 || $rate < 0 || $rate > 100)
        return (["ok" => false, "error" => "BadRequest"]);
    $school = billing_einvoice_school_seller($id_school);
    if ($school == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    $net = $rate > 0 ? (int)round($gross / (1 + $rate / 100)) : $gross;
    $tax = $gross - $net;
    $due_stamp = strtotime($issue_date." +30 days");
    $canonical = [
        "schema" => "infosphere.billing.canonical-invoice",
        "schema_version" => 2,
        "flow_type" => "einvoice",
        "direction" => "incoming",
        "billing_frame" => "S1",
        "treatment" => "B2B",
        "document_type" => "invoice",
        "reference" => $reference,
        "issue_date" => $issue_date,
        "due_date" => $due_stamp === false ? $issue_date : date("Y-m-d", $due_stamp),
        "currency" => "EUR",
        "seller" => billing_einvoice_mock_supplier_party($name, $siret),
        "buyer" => $school["snapshot"],
        "totals" => ["net" => $net, "tax" => $tax, "gross" => $gross],
        "taxes" => [["rate" => $rate, "base" => $net, "amount" => $tax, "exemption_mention" => ""]],
        "lines" => [[
            "position" => 1,
            "description" => "Facture fournisseur de test",
            "quantity" => 1,
            "unit" => "C62",
            "unit_price_net" => $net,
            "net_amount" => $net,
            "vat_rate" => $rate,
        ]],
        "local" => [],
    ];
    $ubl = billing_einvoice_ubl_from_canonical($canonical);
    if (empty($ubl["ok"]))
        return (["ok" => false, "error" => "BillingElectronicIncomplete", "details" => $ubl["missing"] ?? []]);
    $state = billing_einvoice_mock_state($id_school);
    $flow_id = billing_einvoice_mock_flow_id($id_school, $state, "in");
    $sequence = billing_einvoice_mock_sequence($state);
    $updated = gmdate("Y-m-d\\TH:i:s\\Z");
    $remote = [
        "metadata" => [
            "flowId" => $flow_id,
            "flowType" => "SupplierInvoice",
            "flowDirection" => "In",
            "flowSyntax" => "UBL",
            "updatedAt" => $updated,
            "name" => $reference.".xml",
        ],
        "flow_id" => $flow_id,
        "flow_syntax" => "UBL",
        "name" => $reference.".xml",
        "updated_at" => $updated,
        "content" => (string)$ubl["content"],
        "content_type" => "application/xml",
        "converted" => NULL,
        "readable" => NULL,
    ];
    if (is_string($pdf_content) && strlen($pdf_content) >= 5 && substr($pdf_content, 0, 5) === "%PDF-")
    {
        /* The Mock state is JSON.  A PDF is arbitrary binary data and cannot
         * safely be handed to json_encode() as an UTF-8 string.  Keep the
         * simulator state JSON-safe and restore the binary payload only when
         * the fake platform delivers the document. */
        $remote["readable"] = [
            "ok" => true,
            "content_base64" => base64_encode($pdf_content),
            "content_type" => "application/pdf",
            "filename" => $reference.".pdf",
        ];
    }
    $state["incoming"][] = ["sequence" => $sequence, "kind" => "supplier", "remote" => $remote];
    if (!billing_einvoice_mock_save_state($id_school, $state))
        return (["ok" => false, "error" => "CannotRegister"]);
    return (["ok" => true, "flow_id" => $flow_id]);
}

function billing_einvoice_mock_hydrate_remote($remote)
{
    if (!is_array($remote))
        return ($remote);
    if (is_array($remote["readable"] ?? NULL))
    {
        $readable = $remote["readable"];
        if (!isset($readable["content"]) && isset($readable["content_base64"]))
        {
            $content = base64_decode((string)$readable["content_base64"], true);
            if ($content !== false)
                $readable["content"] = $content;
            unset($readable["content_base64"]);
            $remote["readable"] = $readable;
        }
    }
    return ($remote);
}

function billing_einvoice_mock_reset($id_school)
{
    global $Database;

    $id_school = (int)$id_school;
    $file = billing_einvoice_mock_state_file($id_school);
    if ($file !== NULL && is_file($file) && !@unlink($file))
        return (false);
    if ($id_school > 0)
        $Database->query("UPDATE school SET electronic_invoice_receive_cursor = '', electronic_invoice_receive_at = NULL WHERE id = $id_school AND deleted IS NULL");
    return (true);
}

function billing_einvoice_mock_summary($id_school)
{
    $state = billing_einvoice_mock_state($id_school);
    $cursor_row = db_select_one("electronic_invoice_receive_cursor as receive_cursor FROM school WHERE id = ".(int)$id_school." AND deleted IS NULL");
    $cursor = max(0, (int)($cursor_row["receive_cursor"] ?? 0));
    $pending_supplier = 0;
    $pending_lifecycle = 0;
    foreach ($state["incoming"] as $item)
    {
        if ((int)($item["sequence"] ?? 0) <= $cursor)
            continue ;
        if (($item["kind"] ?? "") == "supplier") ++$pending_supplier;
        if (($item["kind"] ?? "") == "lifecycle") ++$pending_lifecycle;
    }
    $flows = array_values($state["flows"]);
    usort($flows, function ($a, $b) { return strcmp((string)($b["updated_at"] ?? ""), (string)($a["updated_at"] ?? "")); });
    return ([
        "next_send_mode" => (string)($state["next_send_mode"] ?? "normal"),
        "flows" => $flows,
        "pending_supplier" => $pending_supplier,
        "pending_lifecycle" => $pending_lifecycle,
    ]);
}

class BillingElectronicInvoiceMockConnector extends BillingElectronicInvoiceConnector
{
    public function key() { return ("mock"); }
    public function label() { return ("Simulateur local (Mock)"); }
    public function standard() { return ("Simulation locale"); }

    public function capabilities()
    {
        return ([
            "send_einvoice" => true,
            "receive_einvoice" => true,
            "send_transaction_reporting" => true,
            "send_payment_reporting" => true,
            "send_einvoice_lifecycle" => true,
            "refresh_status" => true,
        ]);
    }

    public function supported_formats() { return (["ubl"]); }
    public function configuration_schema() { return ([]); }
    public function validate_configuration($configuration) { return ([]); }

    public function healthcheck($configuration)
    {
        return (["ok" => true, "message" => "Simulateur local disponible"]);
    }

    private function submit($document, $kind)
    {
        $id_school = (int)($document["id_school"] ?? 0);
        $id_document = (int)($document["id"] ?? 0);
        if ($id_school <= 0 || $id_document <= 0)
            return (["ok" => false, "error" => "BadRequest"]);
        $state = billing_einvoice_mock_state($id_school);
        foreach ($state["flows"] as $flow)
            if ((int)($flow["document_id"] ?? 0) === $id_document)
                return (billing_einvoice_mock_result_from_flow($flow, "mock_idempotent_replay"));

        $mode = (string)($state["next_send_mode"] ?? "normal");
        $state["next_send_mode"] = "normal";
        if ($mode == "technical_error")
        {
            billing_einvoice_mock_save_state($id_school, $state);
            return (["ok" => false, "error" => "BillingElectronicMockTechnicalError"]);
        }

        $flow_id = billing_einvoice_mock_flow_id($id_school, $state, "out");
        $now = gmdate("Y-m-d\\TH:i:s\\Z");
        $flow = [
            "flow_id" => $flow_id,
            "document_id" => $id_document,
            "reference" => (string)($document["local_reference"] ?? ""),
            "kind" => (string)$kind,
            "status" => "submitted",
            "status_code" => "200",
            "submitted_at" => $now,
            "updated_at" => $now,
        ];
        $state["flows"][$flow_id] = $flow;
        if (!billing_einvoice_mock_save_state($id_school, $state))
            return (["ok" => false, "error" => "CannotRegister"]);
        if ($mode == "ambiguous_timeout")
            return (["ok" => false, "error" => "BillingElectronicMockAmbiguousTimeout"]);
        return (billing_einvoice_mock_result_from_flow($flow, "mock_submitted"));
    }

    public function send_document($document, $canonical, $payload, $configuration) { return ($this->submit($document, "einvoice")); }
    public function report_transaction($document, $canonical, $payload, $configuration) { return ($this->submit($document, "b2c")); }
    public function report_payment($document, $canonical, $payload, $configuration) { return ($this->submit($document, "payment")); }
    public function send_lifecycle($document, $canonical, $payload, $configuration) { return ($this->submit($document, "lifecycle")); }

    public function refresh_document($document, $configuration)
    {
        $id_school = (int)($document["id_school"] ?? 0);
        $flow_id = trim((string)($document["provider_document_id"] ?? ($document["transmission_id"] ?? "")));
        if ($flow_id == "")
            return (["ok" => false, "error" => "BillingElectronicFlowIdMissing"]);
        $state = billing_einvoice_mock_state($id_school);
        if (!isset($state["flows"][$flow_id]))
            return (["ok" => false, "error" => "BillingElectronicMockFlowMissing"]);
        return (billing_einvoice_mock_result_from_flow($state["flows"][$flow_id], "mock_refreshed"));
    }

    public function receive_documents($id_school, $cursor, $configuration)
    {
        $state = billing_einvoice_mock_state((int)$id_school);
        $after = max(0, (int)$cursor);
        $documents = [];
        $lifecycle = [];
        $max = $after;
        foreach ($state["incoming"] as $item)
        {
            $sequence = (int)($item["sequence"] ?? 0);
            if ($sequence <= $after)
                continue ;
            $max = max($max, $sequence);
            if (($item["kind"] ?? "") == "supplier")
                $documents[] = billing_einvoice_mock_hydrate_remote($item["remote"]);
            else if (($item["kind"] ?? "") == "lifecycle")
                $lifecycle[] = billing_einvoice_mock_hydrate_remote($item["remote"]);
        }
        return (["ok" => true, "documents" => $documents, "lifecycle" => $lifecycle, "cursor" => (string)$max]);
    }
}

billing_einvoice_register_connector(new BillingElectronicInvoiceMockConnector());
