<?php

/*
 * Provider-neutral electronic invoicing layer.
 *
 * This file deliberately contains no PA/PDP-specific implementation.  Local
 * billing remains authoritative; connectors only transport documents and
 * return lifecycle events.
 */

abstract class BillingElectronicInvoiceConnector
{
    abstract public function key();
    abstract public function label();

    /*
     * The core intentionally does not know a provider.  Implementations live
     * in tools/electronic_invoice_connectors/ and may be replaced without
     * changing the billing model.
     */
    public function standard()
    {
        return ("XP Z12-013");
    }

    public function capabilities()
    {
        return ([
            "send_einvoice" => false,
            "receive_einvoice" => false,
            "send_transaction_reporting" => false,
            "send_payment_reporting" => false,
            "send_einvoice_lifecycle" => false,
            "refresh_status" => false,
        ]);
    }

    public function supported_formats()
    {
        return (["ubl"]);
    }

    public function preferred_format($document)
    {
        $formats = $this->supported_formats();
        return (count($formats) ? $formats[0] : NULL);
    }

    public function healthcheck($configuration)
    {
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    }

    public function send_document($document, $canonical, $payload, $configuration)
    {
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    }

    public function receive_documents($id_school, $cursor, $configuration)
    {
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    }

    public function refresh_document($document, $configuration)
    {
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    }

    public function report_transaction($document, $canonical, $payload, $configuration)
    {
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    }

    public function report_payment($document, $canonical, $payload, $configuration)
    {
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    }

    public function send_lifecycle($document, $canonical, $payload, $configuration)
    {
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    }

    /*
     * A connector may expose neutral settings without leaking them into the
     * accounting schema.  Secrets should normally be referenced indirectly
     * (environment/file/secret store) rather than copied to the database.
     */
    public function configuration_schema()
    {
        return ([]);
    }

    public function validate_configuration($configuration)
    {
        return ([]);
    }
}

function billing_einvoice_connectors()
{
    if (!isset($GLOBALS["BillingElectronicInvoiceConnectors"]) ||
        !is_array($GLOBALS["BillingElectronicInvoiceConnectors"]))
        $GLOBALS["BillingElectronicInvoiceConnectors"] = [];
    return ($GLOBALS["BillingElectronicInvoiceConnectors"]);
}

function billing_einvoice_register_connector($connector)
{
    if (!($connector instanceof BillingElectronicInvoiceConnector))
        return (false);
    $key = trim((string)$connector->key());
    if ($key == "" || !preg_match('/^[a-z0-9_.-]+$/i', $key))
        return (false);
    if (!isset($GLOBALS["BillingElectronicInvoiceConnectors"]) ||
        !is_array($GLOBALS["BillingElectronicInvoiceConnectors"]))
        $GLOBALS["BillingElectronicInvoiceConnectors"] = [];
    $GLOBALS["BillingElectronicInvoiceConnectors"][$key] = $connector;
    return (true);
}


function billing_einvoice_load_connectors($directory = NULL)
{
    static $loaded = false;

    if ($loaded)
        return (billing_einvoice_connectors());
    $loaded = true;
    if ($directory === NULL)
        $directory = __DIR__."/electronic_invoice_connectors";
    if (!is_dir($directory))
        return (billing_einvoice_connectors());
    $files = glob(rtrim($directory, "/")."/*.php");
    if (!is_array($files))
        return (billing_einvoice_connectors());
    sort($files, SORT_STRING);
    foreach ($files as $file)
        require_once ($file);
    return (billing_einvoice_connectors());
}

function billing_einvoice_connector_configuration($config)
{
    if (!is_array($config))
        return ([]);
    $json = json_decode((string)($config["configuration_json"] ?? ""), true);
    if (!is_array($json))
        $json = [];
    $json["environment"] = (string)($config["environment"] ?? "test");
    $json["endpoint"] = trim((string)($config["endpoint"] ?? ""));
    return ($json);
}

function billing_einvoice_connector_config($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (NULL);
    return (db_select_one("
        id as id_school,
        electronic_invoice_connector_key as connector_key,
        electronic_invoice_connector_enabled as enabled,
        electronic_invoice_connector_environment as environment,
        electronic_invoice_connector_endpoint as endpoint,
        electronic_invoice_connector_configuration_json as configuration_json,
        electronic_invoice_connector_actor as id_actor,
        electronic_invoice_connector_updated_at as updated_at,
        electronic_invoice_receive_cursor as receive_cursor,
        electronic_invoice_receive_at as receive_at,
        electronic_invoice_payment_reporting as payment_reporting
        FROM school
        WHERE id = $id_school
        AND deleted IS NULL
    "));
}

function billing_einvoice_save_connector_config($id_school, $data)
{
    global $Database, $User;

    $id_school = (int)$id_school;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        return (false);
    $connectors = billing_einvoice_connectors();
    $previous_config = billing_einvoice_connector_config($id_school);
    $previous_key = trim((string)($previous_config["connector_key"] ?? ""));
    $key = trim((string)($data["connector_key"] ?? ""));
    if ($key != "" && !isset($connectors[$key]))
        return (false);
    $environment = strtolower(trim((string)($data["environment"] ?? "test")));
    if (!in_array($environment, ["test", "production"], true))
        $environment = "test";
    $endpoint = trim((string)($data["endpoint"] ?? ""));
    if (strlen($endpoint) > 512)
        return (false);
    $enabled = !empty($data["enabled"]) && $key != "" ? 1 : 0;
    $payment_reporting = !empty($data["payment_reporting"]) ? 1 : 0;
    $configuration = $data["configuration"] ?? [];
    if (!is_array($configuration))
        $configuration = [];
    foreach ([
        "token_url", "client_id", "client_secret_env", "client_secret_file",
        "oauth_scope", "oauth_client_auth", "organisation_id",
        "flow_info_field", "file_field", "receive_initial_days", "receive_delay_minutes",
        "retry_base_minutes", "retry_max_attempts"
    ] as $field)
        if (array_key_exists($field, $data))
            $configuration[$field] = trim((string)$data[$field]);
    if ($key != "")
    {
        $validation_configuration = $configuration;
        $validation_configuration["environment"] = $environment;
        $validation_configuration["endpoint"] = $endpoint;
        $errors = $connectors[$key]->validate_configuration($validation_configuration);
        if (!is_array($errors) || count($errors))
            return (false);
    }
    $json = billing_einvoice_json($configuration);
    $key_sql = $Database->real_escape_string($key);
    $environment_sql = $Database->real_escape_string($environment);
    $endpoint_sql = $Database->real_escape_string($endpoint);
    $json_sql = $Database->real_escape_string($json);
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $reset_receive = $previous_key !== $key
        ? ",
            electronic_invoice_receive_cursor = '',
            electronic_invoice_receive_at = NULL" : "";
    return ($Database->query("
        UPDATE school SET
            electronic_invoice_connector_key = '$key_sql',
            electronic_invoice_connector_enabled = $enabled,
            electronic_invoice_connector_environment = '$environment_sql',
            electronic_invoice_connector_endpoint = '$endpoint_sql',
            electronic_invoice_connector_configuration_json = '$json_sql',
            electronic_invoice_payment_reporting = $payment_reporting,
            electronic_invoice_connector_actor = $actor,
            electronic_invoice_connector_updated_at = NOW()$reset_receive
        WHERE id = $id_school
        AND deleted IS NULL
    ") !== NULL);
}

function billing_einvoice_connector_for_school($id_school)
{
    $config = billing_einvoice_connector_config($id_school);
    if ($config == NULL || empty($config["enabled"]) || empty($config["connector_key"]))
        return (NULL);
    $connectors = billing_einvoice_connectors();
    return ($connectors[$config["connector_key"]] ?? NULL);
}

function billing_einvoice_connector($key)
{
    $connectors = billing_einvoice_connectors();
    return ($connectors[$key] ?? NULL);
}

function billing_einvoice_json($value)
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    return ($json === false ? "{}" : $json);
}

function billing_einvoice_organization($id_organization)
{
    $id_organization = (int)$id_organization;
    if ($id_organization <= 0)
        return (NULL);
    return (db_select_one("
        id, codename, name, legal_name,
        head_office_address_line1, head_office_address_line2,
        head_office_zipcode, head_office_city, head_office_country,
        address, mail, phone, siret, vat_number,
        electronic_invoice_address, electronic_invoice_address_scheme,
        electronic_invoice_routing_code, electronic_invoice_routing_scheme,
        registration_registry, registration_number, share_capital
        FROM organization
        WHERE id = $id_organization AND deleted IS NULL
    "));
}

function billing_einvoice_siren_from_siret($siret)
{
    $digits = preg_replace('/[^0-9]/', '', (string)$siret);
    if (strlen($digits) < 9)
        return ("");
    return (substr($digits, 0, 9));
}

function billing_einvoice_organization_snapshot($organization)
{
    if (!is_array($organization))
        return (NULL);
    $name = trim((string)($organization["legal_name"] ?? ""));
    if ($name == "")
        $name = trim((string)($organization["name"] ?? ""));
    if ($name == "")
        $name = trim((string)($organization["codename"] ?? ""));
    $siret = preg_replace('/[^0-9]/', '', (string)($organization["siret"] ?? ""));
    return ([
        "kind" => "organization",
        "id" => (int)($organization["id"] ?? 0),
        "codename" => (string)($organization["codename"] ?? ""),
        "legal_name" => $name,
        "siren" => billing_einvoice_siren_from_siret($siret),
        "siret" => $siret,
        "vat_number" => strtoupper(trim((string)($organization["vat_number"] ?? ""))),
        "electronic_address" => trim((string)($organization["electronic_invoice_address"] ?? "")),
        "electronic_address_scheme" => trim((string)($organization["electronic_invoice_address_scheme"] ?? "")),
        "routing_code" => trim((string)($organization["electronic_invoice_routing_code"] ?? "")),
        "routing_scheme" => trim((string)($organization["electronic_invoice_routing_scheme"] ?? "")),
        "address" => [
            "line1" => trim((string)($organization["head_office_address_line1"] ?? "")),
            "line2" => trim((string)($organization["head_office_address_line2"] ?? "")),
            "zipcode" => trim((string)($organization["head_office_zipcode"] ?? "")),
            "city" => trim((string)($organization["head_office_city"] ?? "")),
            "country" => trim((string)($organization["head_office_country"] ?? "France")),
            "legacy" => trim((string)($organization["address"] ?? "")),
        ],
        "contact" => [
            "mail" => trim((string)($organization["mail"] ?? "")),
            "phone" => trim((string)($organization["phone"] ?? "")),
        ],
    ]);
}

function billing_einvoice_school_seller($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (NULL);
    $school = db_select_one("
        school.id, school.codename, school.id_organization,
        school.vat_exemption_mention
        FROM school
        WHERE school.id = $id_school AND school.deleted IS NULL
    ");
    if ($school == NULL || (int)$school["id_organization"] <= 0)
        return (NULL);
    $organization = billing_einvoice_organization((int)$school["id_organization"]);
    if ($organization == NULL)
        return (NULL);
    return ([
        "school" => $school,
        "organization" => $organization,
        "snapshot" => billing_einvoice_organization_snapshot($organization),
    ]);
}

function billing_einvoice_person_snapshot($user, $student_id = 0)
{
    if (!is_array($user))
        return ([]);
    return ([
        "kind" => "person",
        "id_user" => (int)($user["id"] ?? 0),
        "beneficiary_user_id" => (int)$student_id,
        "codename" => (string)($user["codename"] ?? ""),
        "first_name" => (string)($user["first_name"] ?? ""),
        "family_name" => (string)($user["family_name"] ?? ""),
        "mail" => (string)($user["mail"] ?? ""),
        "address" => [
            "line1" => (string)($user["street_name"] ?? ""),
            "line2" => "",
            "zipcode" => (string)($user["postal_code"] ?? ""),
            "city" => (string)($user["city"] ?? ""),
            "country" => (string)($user["country"] ?? ""),
        ],
    ]);
}

function billing_einvoice_student_snapshot($entry)
{
    $id_student = (int)($entry["id_user"] ?? 0);
    if ($id_student > 0)
    {
        $relations = db_select_all("
            user.*, parent_child.relation
            FROM parent_child
            LEFT JOIN user ON user.id = parent_child.id_parent
            WHERE parent_child.id_child = $id_student
              AND user.id IS NOT NULL
              AND user.authority != -1
            ORDER BY parent_child.id ASC
        ");
        foreach (["financial", "legal"] as $wanted)
            foreach ($relations as $relation)
                if (user_relation_has($relation["relation"] ?? "", $wanted))
                    return (billing_einvoice_person_snapshot($relation, $id_student));
        $student = db_select_one("* FROM user WHERE id = $id_student AND authority != -1");
        if ($student != NULL)
            return (billing_einvoice_person_snapshot($student, $id_student));
    }
    return ([
        "kind" => "person",
        "id_user" => $id_student,
        "beneficiary_user_id" => $id_student,
        "codename" => (string)($entry["codename"] ?? ""),
        "first_name" => (string)($entry["first_name"] ?? ""),
        "family_name" => (string)($entry["family_name"] ?? ""),
        "mail" => (string)($entry["mail"] ?? ""),
        "address" => ["line1" => "", "line2" => "", "zipcode" => "", "city" => "", "country" => ""],
    ]);
}

function billing_einvoice_country_code($country)
{
    $country = trim((string)$country);
    if ($country == "")
        return ("");
    $upper = strtoupper($country);
    if (in_array($upper, ["FR", "FRA", "FRANCE"], true))
        return ("FR");
    if (strlen($upper) == 2 && preg_match('/^[A-Z]{2}$/', $upper))
        return ($upper);
    return ("");
}

function billing_einvoice_organization_is_french($snapshot)
{
    if (!is_array($snapshot))
        return (false);
    return (billing_einvoice_country_code($snapshot["address"]["country"] ?? "") == "FR" &&
        strlen((string)($snapshot["siren"] ?? "")) == 9);
}

function billing_einvoice_buyer_snapshot($entry)
{
    $id_organization = (int)($entry["id_organization"] ?? 0);
    if ($id_organization <= 0)
        return (billing_einvoice_student_snapshot($entry));
    $organization = billing_einvoice_organization($id_organization);
    if ($organization == NULL)
        return (billing_einvoice_student_snapshot($entry));
    $snapshot = billing_einvoice_organization_snapshot($organization);
    $invoice_routing = trim((string)($entry["buyer_routing_code"] ?? ""));
    if ($invoice_routing != "")
        $snapshot["routing_code"] = $invoice_routing;
    return ($snapshot);
}

function billing_einvoice_exemption_outside_reform($mention)
{
    $mention = trim((string)$mention);
    if ($mention == "")
        return (false);
    // Articles 261 à 261 E du CGI: opérations exonérées hors du champ de la
    // facturation électronique/e-reporting.  Do not infer this from a 0 %
    // rate alone: the explicit exemption wording is the source of truth.
    return (preg_match('/\b(?:article\s*)?261(?:\s*[A-E])?(?:\b|[-–—])/iu', $mention) === 1);
}

function billing_einvoice_canonical_outside_reform($canonical)
{
    if (!is_array($canonical))
        return (false);
    $taxes = $canonical["taxes"] ?? [];
    if (!is_array($taxes) || !count($taxes))
        return (false);
    $found = false;
    foreach ($taxes as $tax)
    {
        if (!is_array($tax))
            return (false);
        if (abs((float)($tax["rate"] ?? 0)) > 0.0001 || (int)($tax["amount"] ?? 0) != 0)
            return (false);
        $mention = trim((string)($tax["exemption_mention"] ?? ""));
        if (!billing_einvoice_exemption_outside_reform($mention))
            return (false);
        $found = true;
    }
    return ($found);
}

function billing_einvoice_canonical_from_billing_entry($entry)
{
    if (!is_array($entry) || empty($entry["sent_date"]))
        return (NULL);
    $seller = billing_einvoice_school_seller((int)$entry["id_school"]);
    if ($seller == NULL)
        return (NULL);

    $buyer = billing_einvoice_buyer_snapshot($entry);
    $is_credit = billing_is_credit_note($entry);
    $amount_ttc = abs((int)$entry["amount"]);
    $amount_ht = abs((int)billing_entry_amount_ht($entry));
    $tax = max(0, $amount_ttc - $amount_ht);
    $vat_rate = billing_normalize_vat_rate($entry["vat_rate"] ?? 0);
    // Use the same fiscal wording as the human-readable invoice.  The current
    // billing model derives the exemption mention from the VAT rate; it is not
    // stored as a school property.
    $exemption_mention = billing_invoice_vat_exemption($vat_rate, $entry["id_school"] ?? 0);
    $outside_reform = billing_einvoice_exemption_outside_reform($exemption_mention);
    $flow_type = $outside_reform
        ? "out_of_scope_exempt"
        : ((($buyer["kind"] ?? "") == "organization" && billing_einvoice_organization_is_french($buyer))
            ? "einvoice" : "ereporting_transaction");
    $related_reference = "";
    if ($is_credit && (int)($entry["related_entry_id"] ?? 0) > 0)
    {
        $related = billing_entry_with_user((int)$entry["related_entry_id"], true);
        if ($related != NULL)
            $related_reference = billing_invoice_document_reference($related);
    }

    return ([
        "schema" => "infosphere.billing.canonical-invoice",
        "schema_version" => 2,
        "flow_type" => $flow_type,
        "direction" => "outgoing",
        // EFRITS/OF/CFA billing entries are tuition/training services.  S1 is
        // the ordinary French invoicing framework for a service invoice.
        "billing_frame" => "S1",
        "treatment" => $outside_reform ? "OUT_OF_SCOPE" : ($flow_type == "einvoice" ? "B2B" : "B2C"),
        "document_type" => $is_credit ? "credit_note" : "invoice",
        "reference" => billing_invoice_document_reference($entry),
        "issue_date" => (string)$entry["sent_date"],
        "due_date" => $is_credit ? NULL : (string)($entry["due_date"] ?? ""),
        "currency" => "EUR",
        "seller" => $seller["snapshot"],
        "buyer" => $buyer,
        "totals" => [
            "net" => $amount_ht,
            "tax" => $tax,
            "gross" => $amount_ttc,
        ],
        "taxes" => [[
            "rate" => $vat_rate,
            "base" => $amount_ht,
            "amount" => $tax,
            "exemption_mention" => $exemption_mention,
        ]],
        "lines" => [[
            "position" => 1,
            "description" => (string)($entry["label"] ?? ""),
            "quantity" => 1,
            "unit" => "C62",
            "unit_price_net" => $amount_ht,
            "net_amount" => $amount_ht,
            "vat_rate" => $vat_rate,
        ]],
        "local" => [
            "billing_entry_id" => (int)$entry["id"],
            "student_id" => (int)$entry["id_user"],
            "school_id" => (int)$entry["id_school"],
            "id_organization" => (int)($entry["id_organization"] ?? 0),
            "invoice_type" => billing_normalize_invoice_type($entry["invoice_type"] ?? "school"),
            "regulatory_scope" => $outside_reform ? "outside_reform_261" : "in_scope",
            "related_invoice_reference" => $related_reference,
            "pdf_relative_path" => billing_invoice_relative_path($entry),
        ],
    ]);
}


function billing_einvoice_payment_reporting_enabled($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (false);
    $row = db_select_one("electronic_invoice_payment_reporting FROM school WHERE id = $id_school AND deleted IS NULL");
    return ($row != NULL && !empty($row["electronic_invoice_payment_reporting"]));
}

function billing_einvoice_payment_allocations($payment)
{
    if (!is_array($payment) || (int)($payment["id"] ?? 0) <= 0 || (int)($payment["amount"] ?? 0) <= 0)
        return ([]);
    $id_payment = (int)$payment["id"];
    $id_user = (int)($payment["id_user"] ?? 0);
    $id_organization = (int)($payment["id_organization"] ?? 0);
    $id_school = (int)$payment["id_school"];
    if (($id_user > 0) === ($id_organization > 0))
        return ([]);

    $payment_date = db_escape((string)$payment["payment_date"]);
    if ($id_organization > 0)
    {
        $payer_filter = "id_organization = $id_organization";
        $entries = billing_positive_invoices_for_organization($id_organization, $id_school, true);
    }
    else
    {
        $payer_filter = "id_user = $id_user AND id_organization IS NULL";
        $entries = billing_positive_invoices_for_user($id_user, true);
    }

    $prior = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE $payer_filter
          AND id_school = $id_school
          AND deleted IS NULL
          AND (payment_date < '$payment_date' OR (payment_date = '$payment_date' AND id < $id_payment))
    ");
    $before_available = max(0, (int)($prior["amount"] ?? 0));
    $after_available = max(0, $before_available + (int)$payment["amount"]);
    $allocations = [];

    foreach ($entries as $entry)
    {
        if ((int)$entry["id_school"] !== $id_school)
            continue ;
        $amount = max(0, (int)$entry["amount"]);
        $credit = min($amount, billing_credit_note_total_for_entry((int)$entry["id"], true));
        $effective = max(0, $amount - $credit);
        if ($effective <= 0)
            continue ;

        $covered_before = min($effective, $before_available);
        $before_available -= $covered_before;
        $covered_after = min($effective, $after_available);
        $after_available -= $covered_after;
        $allocated = max(0, $covered_after - $covered_before);
        if ($allocated <= 0)
            continue ;

        $electronic = billing_einvoice_document_for_source("billing_entry", (int)$entry["id"]);
        if ($electronic == NULL)
        {
            $prepared = billing_einvoice_upsert_from_entry((int)$entry["id"]);
            if (!empty($prepared["ok"]))
                $electronic = billing_einvoice_document((int)$prepared["id"]);
        }
        if ($electronic == NULL)
            continue ;
        $invoice_canonical = json_decode((string)$electronic["canonical_json"], true);
        if (!is_array($invoice_canonical))
            continue ;
        // The payment still consumes this invoice in FIFO allocation, but an
        // operation explicitly exempt under article 261 is outside the reform:
        // it must not produce a PAY/CDAR electronic reporting document.
        if (billing_einvoice_canonical_outside_reform($invoice_canonical))
            continue ;

        $gross = max(1, (int)($invoice_canonical["totals"]["gross"] ?? $effective));
        $tax_total = max(0, (int)($invoice_canonical["totals"]["tax"] ?? 0));
        $tax = (int)round($allocated * $tax_total / $gross);
        $net = max(0, $allocated - $tax);
        $rate = (float)($invoice_canonical["taxes"][0]["rate"] ?? 0);
        $allocations[] = [
            "billing_entry_id" => (int)$entry["id"],
            "electronic_document_id" => (int)$electronic["id"],
            "invoice_reference" => (string)$electronic["local_reference"],
            "invoice_issue_date" => (string)$electronic["issue_date"],
            "source_flow_type" => (string)$electronic["flow_type"],
            "amount" => $allocated,
            "net" => $net,
            "tax" => $tax,
            "vat_rate" => $rate,
            "buyer" => $invoice_canonical["buyer"] ?? [],
        ];
    }
    return ($allocations);
}

function billing_einvoice_canonical_from_payment($payment, $target = "b2c")
{
    if (!is_array($payment) || (int)($payment["amount"] ?? 0) <= 0)
        return (NULL);
    $id_school = (int)$payment["id_school"];
    if (!billing_einvoice_payment_reporting_enabled($id_school))
        return (NULL);
    $seller = billing_einvoice_school_seller($id_school);
    if ($seller == NULL)
        return (NULL);

    $target = strtolower(trim((string)$target));
    if (!in_array($target, ["b2c", "b2b"], true))
        return (NULL);
    $wanted_flow = $target == "b2b" ? "einvoice" : "ereporting_transaction";
    $flow_type = $target == "b2b" ? "einvoice_lifecycle" : "ereporting_payment";
    $allocations = [];
    $ignored = 0;
    foreach (billing_einvoice_payment_allocations($payment) as $allocation)
    {
        if (($allocation["source_flow_type"] ?? "") == $wanted_flow)
            $allocations[] = $allocation;
        else
            $ignored += (int)$allocation["amount"];
    }
    if (!count($allocations))
        return (NULL);

    $gross = 0;
    $net = 0;
    $tax = 0;
    $taxes = [];
    foreach ($allocations as $allocation)
    {
        $gross += (int)$allocation["amount"];
        $net += (int)$allocation["net"];
        $tax += (int)$allocation["tax"];
        $key = number_format((float)$allocation["vat_rate"], 4, '.', '');
        if (!isset($taxes[$key]))
            $taxes[$key] = ["rate" => (float)$allocation["vat_rate"], "base" => 0, "amount" => 0];
        $taxes[$key]["base"] += (int)$allocation["net"];
        $taxes[$key]["amount"] += (int)$allocation["tax"];
    }
    // local_reference is a short, stable Infosphère identifier.  The bank
    // wording/reference can be arbitrarily long and is kept separately in
    // payment.transfer_reference inside the canonical payload.
    $reference = "PAY-".str_pad((string)((int)$payment["id"]), 6, "0", STR_PAD_LEFT);
    $reference .= $target == "b2b" ? "-B2B" : "-B2C";

    return ([
        "schema" => "infosphere.billing.canonical-payment",
        "schema_version" => 1,
        "flow_type" => $flow_type,
        "treatment" => strtoupper($target),
        "direction" => "outgoing",
        "document_type" => "payment",
        "reference" => $reference,
        "issue_date" => (string)$payment["payment_date"],
        "currency" => "EUR",
        "seller" => $seller["snapshot"],
        "buyer" => $allocations[0]["buyer"] ?? [],
        "totals" => ["net" => $net, "tax" => $tax, "gross" => $gross],
        "taxes" => array_values($taxes),
        "payment" => [
            "billing_payment_id" => (int)$payment["id"],
            "amount" => (int)$payment["amount"],
            "reported_amount" => $gross,
            "date" => (string)$payment["payment_date"],
            "transfer_reference" => (string)($payment["transfer_reference"] ?? ""),
            "comment" => (string)($payment["comment"] ?? ""),
        ],
        "allocations" => $allocations,
        "local" => [
            "billing_payment_id" => (int)$payment["id"],
            "student_id" => (int)($payment["id_user"] ?? 0),
            "organization_id" => (int)($payment["id_organization"] ?? 0),
            "school_id" => $id_school,
            "payment_target" => $target,
            "ignored_other_amount" => $ignored,
        ],
    ]);
}

function billing_einvoice_upsert_payment_flow($payment, $target)
{
    global $Database;
    if (!is_array($payment))
        return (["ok" => true, "ignored" => true]);
    $id_payment = (int)$payment["id"];
    $target = strtolower(trim((string)$target));
    $canonical = billing_einvoice_canonical_from_payment($payment, $target);
    $source_type = $target == "b2b" ? "billing_payment_b2b" : "billing_payment_b2c";
    $flow_type = $target == "b2b" ? "einvoice_lifecycle" : "ereporting_payment";
    $existing = billing_einvoice_document_for_source($source_type, $id_payment);
    if ($canonical == NULL)
    {
        if ($existing != NULL && in_array((string)$existing["status"], ["prepared", "draft", "transport_error"], true) && empty($existing["provider_document_id"]))
            $Database->query("UPDATE billing_electronic_document SET deleted = NOW(), updated_at = NOW() WHERE id = ".(int)$existing["id"]);
        return (["ok" => true, "ignored" => true]);
    }
    $json = billing_einvoice_json($canonical);
    $sha = hash("sha256", $json);
    if ($existing != NULL && !in_array((string)$existing["status"], ["prepared", "draft", "transport_error"], true))
        return (["ok" => true, "id" => (int)$existing["id"], "unchanged" => true]);
    if ($existing != NULL && hash_equals((string)$existing["canonical_sha256"], $sha))
        return (["ok" => true, "id" => (int)$existing["id"], "unchanged" => true]);

    $seller = billing_einvoice_school_seller((int)$payment["id_school"]);
    $seller_id = (int)($seller["organization"]["id"] ?? 0);
    $reference = $Database->real_escape_string((string)$canonical["reference"]);
    $issue = $Database->real_escape_string((string)$canonical["issue_date"]);
    $json_sql = $Database->real_escape_string($json);
    $sha_sql = $Database->real_escape_string($sha);
    $actor = isset($GLOBALS["User"]["id"]) ? (int)$GLOBALS["User"]["id"] : "NULL";
    $buyer_organization_id = (int)($payment["id_organization"] ?? 0);
    $buyer_user_id = (int)($payment["id_user"] ?? 0);
    $buyer_organization_sql = $buyer_organization_id > 0 ? (string)$buyer_organization_id : "NULL";
    $buyer_user_sql = $buyer_user_id > 0 ? (string)$buyer_user_id : "NULL";
    if ($existing == NULL)
    {
        $source_sql = $Database->real_escape_string($source_type);
        $flow_sql = $Database->real_escape_string($flow_type);
        if ($Database->query("
            INSERT INTO billing_electronic_document
            (id_school, direction, flow_type, document_type, source_type, source_id,
             local_reference, issue_date, due_date, currency, seller_organization_id,
             buyer_organization_id, buyer_user_id, status, canonical_json, canonical_sha256, id_actor)
            VALUES
            (".(int)$payment["id_school"].", 'outgoing', '$flow_sql', 'payment', '$source_sql', $id_payment,
             '$reference', '$issue', NULL, 'EUR', $seller_id, $buyer_organization_sql, $buyer_user_sql,
             'prepared', '$json_sql', '$sha_sql', $actor)
        ") === NULL)
            return (["ok" => false, "error" => "CannotRegister"]);
        $id_document = (int)$Database->insert_id;
        billing_einvoice_event($id_document, "payment_".$target."_prepared", "local", ["canonical_sha256" => $sha]);
        return (["ok" => true, "id" => $id_document, "created" => true]);
    }

    $id_document = (int)$existing["id"];
    if ($Database->query("
        UPDATE billing_electronic_document SET
            local_reference = '$reference', issue_date = '$issue', canonical_json = '$json_sql',
            canonical_sha256 = '$sha_sql', seller_organization_id = $seller_id,
            buyer_organization_id = $buyer_organization_sql, buyer_user_id = $buyer_user_sql,
            updated_at = NOW()
        WHERE id = $id_document
    ") === NULL)
        return (["ok" => false, "error" => "CannotRegister"]);
    billing_einvoice_event($id_document, "payment_".$target."_snapshot_refreshed", "local", ["canonical_sha256" => $sha]);
    return (["ok" => true, "id" => $id_document, "updated" => true]);
}

function billing_einvoice_upsert_from_payment($id_payment)
{
    $id_payment = (int)$id_payment;
    $payment = db_select_one("* FROM billing_payment WHERE id = $id_payment AND deleted IS NULL");
    if ($payment == NULL || (int)$payment["amount"] <= 0)
        return (["ok" => true, "ignored" => true]);
    $results = [
        billing_einvoice_upsert_payment_flow($payment, "b2c"),
        billing_einvoice_upsert_payment_flow($payment, "b2b"),
    ];
    $ret = ["ok" => true, "created" => false, "updated" => false, "unchanged" => false, "ignored" => true, "ids" => []];
    foreach ($results as $result)
    {
        if (empty($result["ok"]))
            return ($result);
        if (!empty($result["id"])) $ret["ids"][] = (int)$result["id"];
        if (!empty($result["created"])) { $ret["created"] = true; $ret["ignored"] = false; }
        else if (!empty($result["updated"])) { $ret["updated"] = true; $ret["ignored"] = false; }
        else if (!empty($result["unchanged"])) { $ret["unchanged"] = true; $ret["ignored"] = false; }
    }
    return ($ret);
}

function billing_einvoice_track_payment($id_payment)
{
    $ret = billing_einvoice_upsert_from_payment((int)$id_payment);
    if (empty($ret["ok"]))
    {
        if (function_exists("add_log"))
            add_log(WARNING, "Cannot prepare electronic payment document for payment #".((int)$id_payment).": ".($ret["error"] ?? "unknown"));
        return (false);
    }
    return (true);
}

function billing_einvoice_payment_removed($id_payment)
{
    global $Database;
    $ok = true;
    foreach (["billing_payment_b2c", "billing_payment_b2b"] as $source_type)
    {
        $document = billing_einvoice_document_for_source($source_type, (int)$id_payment);
        if ($document == NULL)
            continue ;
        $id_document = (int)$document["id"];
        if (in_array((string)$document["status"], ["draft", "prepared", "transport_error"], true) && empty($document["provider_document_id"]))
        {
            if ($Database->query("UPDATE billing_electronic_document SET deleted = NOW(), updated_at = NOW() WHERE id = $id_document") === NULL)
                $ok = false;
            else
                billing_einvoice_event($id_document, "source_payment_deleted", "local", ["id_payment" => (int)$id_payment]);
            continue ;
        }
        $note = "Le paiement source #".(int)$id_payment." a été supprimé après transmission : contrôle requis.";
        $note_sql = $Database->real_escape_string($note);
        if ($Database->query("UPDATE billing_electronic_document SET review_note = '$note_sql', review_closed_at = NULL, updated_at = NOW() WHERE id = $id_document") === NULL)
            $ok = false;
        else
            billing_einvoice_event($id_document, "source_payment_deleted_after_submission", "local", ["id_payment" => (int)$id_payment]);
    }
    return ($ok);
}

function billing_einvoice_event($id_document, $event_code, $event_source = "local", $payload = NULL, $external_id = "")
{
    global $Database;

    $id_document = (int)$id_document;
    if ($id_document <= 0 || trim((string)$event_code) == "")
        return (false);
    $code = $Database->real_escape_string(trim((string)$event_code));
    $source = $Database->real_escape_string(trim((string)$event_source));
    $external = $Database->real_escape_string(trim((string)$external_id));
    $json = $payload === NULL ? "" : billing_einvoice_json($payload);
    $json = $Database->real_escape_string($json);
    return ($Database->query("
        INSERT INTO billing_electronic_event
        (id_document, event_code, event_source, event_date, external_id, payload)
        VALUES ($id_document, '$code', '$source', NOW(), '$external', '$json')
    ") !== NULL);
}

function billing_einvoice_document_for_source($source_type, $source_id)
{
    global $Database;

    $source_type = $Database->real_escape_string(trim((string)$source_type));
    $source_id = (int)$source_id;
    if ($source_type == "" || $source_id <= 0)
        return (NULL);
    return (db_select_one("
        *
        FROM billing_electronic_document
        WHERE source_type = '$source_type'
          AND source_id = $source_id
          AND deleted IS NULL
    "));
}

function billing_einvoice_upsert_from_entry($id_entry)
{
    global $Database;

    $id_entry = (int)$id_entry;
    $entry = billing_entry_with_user($id_entry, true);
    if ($entry == NULL || empty($entry["sent_date"]) || !empty($entry["deleted"]))
        return (["ok" => false, "error" => "NotFound"]);
    $canonical = billing_einvoice_canonical_from_billing_entry($entry);
    if ($canonical == NULL)
        return (["ok" => false, "error" => "BillingElectronicSellerMissing"]);

    $json = billing_einvoice_json($canonical);
    $sha256 = hash("sha256", $json);
    $existing = billing_einvoice_document_for_source("billing_entry", $id_entry);
    if ($existing != NULL && !in_array((string)$existing["status"], ["prepared", "draft"], true))
        return (["ok" => true, "id" => (int)$existing["id"], "unchanged" => true]);
    if ($existing != NULL && hash_equals((string)$existing["canonical_sha256"], $sha256))
        return (["ok" => true, "id" => (int)$existing["id"], "unchanged" => true]);

    $id_school = (int)$entry["id_school"];
    $seller = billing_einvoice_school_seller($id_school);
    $seller_id = (int)($seller["organization"]["id"] ?? 0);
    $buyer_organization_id = (int)($entry["id_organization"] ?? 0);
    $buyer_organization_sql = $buyer_organization_id > 0 ? (string)$buyer_organization_id : "NULL";
    $buyer_user_sql = $buyer_organization_id > 0 ? "NULL" : (string)((int)$entry["id_user"]);
    $flow_sql = $Database->real_escape_string((string)$canonical["flow_type"]);
    $document_type = billing_is_credit_note($entry) ? "credit_note" : "invoice";
    $reference = $Database->real_escape_string(billing_invoice_document_reference($entry));
    $issue_date = $Database->real_escape_string((string)$entry["sent_date"]);
    $due_date = billing_is_credit_note($entry) ? NULL : (string)($entry["due_date"] ?? "");
    $due_sql = $due_date == "" ? "NULL" : "'".$Database->real_escape_string($due_date)."'";
    $json_sql = $Database->real_escape_string($json);
    $sha_sql = $Database->real_escape_string($sha256);
    $type_sql = $Database->real_escape_string($document_type);
    $actor = isset($GLOBALS["User"]["id"]) ? (int)$GLOBALS["User"]["id"] : "NULL";

    if ($existing == NULL)
    {
        if ($Database->query("
            INSERT INTO billing_electronic_document
            (id_school, direction, flow_type, document_type, source_type, source_id,
             local_reference, issue_date, due_date, currency, seller_organization_id,
             buyer_organization_id, buyer_user_id, status, canonical_json, canonical_sha256, id_actor)
            VALUES
            ($id_school, 'outgoing', '$flow_sql', '$type_sql', 'billing_entry', $id_entry,
             '$reference', '$issue_date', $due_sql, 'EUR', $seller_id,
             $buyer_organization_sql, $buyer_user_sql, 'prepared', '$json_sql', '$sha_sql', $actor)
        ") === NULL)
            return (["ok" => false, "error" => "CannotRegister"]);
        $id_document = (int)$Database->insert_id;
        billing_einvoice_event($id_document, "prepared", "local", ["canonical_sha256" => $sha256]);
        return (["ok" => true, "id" => $id_document, "created" => true]);
    }

    $id_document = (int)$existing["id"];
    if ($Database->query("
        UPDATE billing_electronic_document
        SET local_reference = '$reference', issue_date = '$issue_date', due_date = $due_sql,
            document_type = '$type_sql', flow_type = '$flow_sql', canonical_json = '$json_sql', canonical_sha256 = '$sha_sql',
            seller_organization_id = $seller_id, buyer_organization_id = $buyer_organization_sql,
            buyer_user_id = $buyer_user_sql, updated_at = NOW()
        WHERE id = $id_document
    ") === NULL)
        return (["ok" => false, "error" => "CannotRegister"]);
    billing_einvoice_event($id_document, "snapshot_refreshed", "local", ["canonical_sha256" => $sha256]);
    return (["ok" => true, "id" => $id_document, "updated" => true]);
}

function billing_einvoice_track_issued_entry($id_entry)
{
    $ret = billing_einvoice_upsert_from_entry($id_entry);
    if (empty($ret["ok"]))
    {
        if (function_exists("add_log"))
            add_log(WARNING, "Cannot prepare electronic billing document for entry #".((int)$id_entry).": ".($ret["error"] ?? "unknown"));
        return (false);
    }
    return (true);
}

function billing_einvoice_sync_managed()
{
    $entries = db_select_all("
        billing_entry.id
        FROM billing_entry
        WHERE billing_entry.sent_date IS NOT NULL
          AND billing_entry.deleted IS NULL
        ".billing_school_filter("billing_entry")."
        ORDER BY billing_entry.id ASC
    ");
    $ret = ["created" => 0, "updated" => 0, "unchanged" => 0, "errors" => 0];
    foreach ($entries as $entry)
    {
        $sync = billing_einvoice_upsert_from_entry((int)$entry["id"]);
        if (empty($sync["ok"]))
            ++$ret["errors"];
        else if (!empty($sync["created"]))
            ++$ret["created"];
        else if (!empty($sync["updated"]))
            ++$ret["updated"];
        else
            ++$ret["unchanged"];
    }

    $ret["payments_created"] = 0;
    $ret["payments_updated"] = 0;
    $ret["payments_unchanged"] = 0;
    $ret["payments_ignored"] = 0;
    $payments = db_select_all("
        billing_payment.id
        FROM billing_payment
        WHERE billing_payment.deleted IS NULL
          AND billing_payment.amount > 0
        ".billing_school_filter("billing_payment")."
        ORDER BY billing_payment.id ASC
    ");
    foreach ($payments as $payment)
    {
        $sync = billing_einvoice_upsert_from_payment((int)$payment["id"]);
        if (empty($sync["ok"]))
            ++$ret["errors"];
        else if (!empty($sync["created"]))
            ++$ret["payments_created"];
        else if (!empty($sync["updated"]))
            ++$ret["payments_updated"];
        else if (!empty($sync["ignored"]))
            ++$ret["payments_ignored"];
        else
            ++$ret["payments_unchanged"];
    }
    return ($ret);
}

function billing_einvoice_documents()
{
    return (db_select_all("
        billing_electronic_document.*,
        school.codename as school_codename,
        seller.codename as seller_codename,
        seller.name as seller_name,
        seller.legal_name as seller_legal_name,
        buyer.codename as buyer_codename,
        buyer.first_name as buyer_first_name,
        buyer.family_name as buyer_family_name,
        buyer_org.codename as buyer_organization_codename,
        buyer_org.name as buyer_organization_name,
        buyer_org.legal_name as buyer_organization_legal_name
        FROM billing_electronic_document
        LEFT JOIN school ON school.id = billing_electronic_document.id_school
        LEFT JOIN organization seller ON seller.id = billing_electronic_document.seller_organization_id
        LEFT JOIN user buyer ON buyer.id = billing_electronic_document.buyer_user_id
        LEFT JOIN organization buyer_org ON buyer_org.id = billing_electronic_document.buyer_organization_id
        WHERE billing_electronic_document.deleted IS NULL
        ".billing_school_filter("billing_electronic_document")."
        ORDER BY COALESCE(billing_electronic_document.issue_date, billing_electronic_document.created_at) DESC,
                 billing_electronic_document.id DESC
    "));
}

function billing_einvoice_events($id_document)
{
    $id_document = (int)$id_document;
    if ($id_document <= 0)
        return ([]);
    return (db_select_all("
        * FROM billing_electronic_event
        WHERE id_document = $id_document
        ORDER BY event_date DESC, id DESC
    "));
}

function billing_einvoice_school_readiness()
{
    $ids = billing_managed_school_ids();
    if (!count($ids))
        return ([]);
    $id_sql = implode(",", array_map("intval", $ids));
    $schools = db_select_all("
        school.id, school.codename, school.id_organization,
        organization.codename as organization_codename,
        organization.name as organization_name,
        organization.legal_name, organization.siret, organization.vat_number,
        organization.electronic_invoice_address, organization.electronic_invoice_address_scheme,
        organization.head_office_address_line1,
        organization.head_office_zipcode,
        organization.head_office_city,
        organization.head_office_country,
        organization.address
        FROM school
        LEFT JOIN organization ON organization.id = school.id_organization AND organization.deleted IS NULL
        WHERE school.deleted IS NULL AND school.id IN ($id_sql)
        ORDER BY school.codename ASC
    ");
    foreach ($schools as &$school)
    {
        $missing = [];
        if ((int)($school["id_organization"] ?? 0) <= 0)
            $missing[] = "organisation juridique";
        $legal_name = trim((string)($school["legal_name"] ?? ""));
        if ($legal_name == "")
            $legal_name = trim((string)($school["organization_name"] ?? ""));
        if ($legal_name == "")
            $missing[] = "raison sociale";
        $siret = preg_replace('/[^0-9]/', '', (string)($school["siret"] ?? ""));
        if (strlen($siret) != 14)
            $missing[] = "SIRET";
        if (trim((string)($school["head_office_address_line1"] ?? "")) == "" &&
            trim((string)($school["address"] ?? "")) == "")
            $missing[] = "adresse légale";
        if (trim((string)($school["head_office_city"] ?? "")) == "")
            $missing[] = "ville";
        if (trim((string)($school["head_office_country"] ?? "")) == "")
            $missing[] = "pays";
        $school["legal_name_display"] = $legal_name;
        $school["siren"] = billing_einvoice_siren_from_siret($siret);
        $school["missing"] = $missing;
        $school["ready"] = !count($missing);
    }
    unset($school);
    return ($schools);
}

function billing_einvoice_document($id_document)
{
    $id_document = (int)$id_document;
    if ($id_document <= 0)
        return (NULL);
    return (db_select_one("
        * FROM billing_electronic_document
        WHERE id = $id_document AND deleted IS NULL
        ".billing_school_filter("billing_electronic_document")."
    "));
}

function billing_einvoice_xml_escape($value)
{
    return (htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, "UTF-8"));
}

function billing_einvoice_money($cents)
{
    return (number_format(((int)$cents) / 100, 2, '.', ''));
}

function billing_einvoice_date($value)
{
    $stamp = date_to_timestamp($value);
    return ($stamp === NULL ? "" : date("Y-m-d", $stamp));
}

function billing_einvoice_party_name($party)
{
    if (!is_array($party))
        return ("");
    if (($party["kind"] ?? "") == "person")
    {
        $name = trim((string)($party["first_name"] ?? "")." ".(string)($party["family_name"] ?? ""));
        return ($name != "" ? $name : trim((string)($party["codename"] ?? "")));
    }
    return (trim((string)($party["legal_name"] ?? "")));
}

function billing_einvoice_ubl_party($party)
{
    if (!is_array($party))
        return ("");
    $x = "";
    $name = billing_einvoice_party_name($party);
    $siren = preg_replace('/[^0-9]/', '', (string)($party["siren"] ?? ""));
    $siret = preg_replace('/[^0-9]/', '', (string)($party["siret"] ?? ""));
    $endpoint = trim((string)($party["electronic_address"] ?? ""));
    $endpoint_scheme = trim((string)($party["electronic_address_scheme"] ?? ""));
    if ($endpoint == "" && strlen($siren) == 9)
    {
        $endpoint = $siren;
        $endpoint_scheme = "0225";
    }
    if ($endpoint != "")
        $x .= '<cbc:EndpointID'.($endpoint_scheme != "" ? ' schemeID="'.billing_einvoice_xml_escape($endpoint_scheme).'"' : '').'>'.billing_einvoice_xml_escape($endpoint).'</cbc:EndpointID>';
    if (strlen($siren) == 9)
        $x .= '<cac:PartyIdentification><cbc:ID schemeID="0002">'.$siren.'</cbc:ID></cac:PartyIdentification>';
    if (strlen($siret) == 14)
        $x .= '<cac:PartyIdentification><cbc:ID schemeID="0009">'.$siret.'</cbc:ID></cac:PartyIdentification>';
    $routing = trim((string)($party["routing_code"] ?? ""));
    $routing_scheme = trim((string)($party["routing_scheme"] ?? ""));
    if ($routing != "")
        $x .= '<cac:PartyIdentification><cbc:ID'.($routing_scheme != "" ? ' schemeID="'.billing_einvoice_xml_escape($routing_scheme).'"' : '').'>'.billing_einvoice_xml_escape($routing).'</cbc:ID></cac:PartyIdentification>';
    if ($name != "")
        $x .= '<cac:PartyName><cbc:Name>'.billing_einvoice_xml_escape($name).'</cbc:Name></cac:PartyName>';
    $address = $party["address"] ?? [];
    $line1 = trim((string)($address["line1"] ?? ""));
    $line2 = trim((string)($address["line2"] ?? ""));
    $zip = trim((string)($address["zipcode"] ?? ""));
    $city = trim((string)($address["city"] ?? ""));
    $country = billing_einvoice_country_code($address["country"] ?? "");
    if ($line1 != "" || $line2 != "" || $zip != "" || $city != "" || $country != "")
    {
        $x .= '<cac:PostalAddress>';
        if ($line1 != "") $x .= '<cbc:StreetName>'.billing_einvoice_xml_escape($line1).'</cbc:StreetName>';
        if ($line2 != "") $x .= '<cbc:AdditionalStreetName>'.billing_einvoice_xml_escape($line2).'</cbc:AdditionalStreetName>';
        if ($city != "") $x .= '<cbc:CityName>'.billing_einvoice_xml_escape($city).'</cbc:CityName>';
        if ($zip != "") $x .= '<cbc:PostalZone>'.billing_einvoice_xml_escape($zip).'</cbc:PostalZone>';
        if ($country != "") $x .= '<cac:Country><cbc:IdentificationCode>'.$country.'</cbc:IdentificationCode></cac:Country>';
        $x .= '</cac:PostalAddress>';
    }
    $vat = strtoupper(trim((string)($party["vat_number"] ?? "")));
    if ($vat != "")
        $x .= '<cac:PartyTaxScheme><cbc:CompanyID>'.billing_einvoice_xml_escape($vat).'</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>';
    if ($name != "" || strlen($siren) == 9)
    {
        $x .= '<cac:PartyLegalEntity>';
        if ($name != "") $x .= '<cbc:RegistrationName>'.billing_einvoice_xml_escape($name).'</cbc:RegistrationName>';
        if (strlen($siren) == 9) $x .= '<cbc:CompanyID schemeID="0002">'.$siren.'</cbc:CompanyID>';
        $x .= '</cac:PartyLegalEntity>';
    }
    return ($x);
}

function billing_einvoice_luhn_valid($value)
{
    $digits = preg_replace('/[^0-9]/', '', (string)$value);
    if ($digits == "")
        return (false);
    $sum = 0;
    $alternate = false;
    for ($i = strlen($digits) - 1; $i >= 0; --$i)
    {
        $n = (int)$digits[$i];
        if ($alternate)
        {
            $n *= 2;
            if ($n > 9)
                $n -= 9;
        }
        $sum += $n;
        $alternate = !$alternate;
    }
    return (($sum % 10) == 0);
}

function billing_einvoice_valid_date($value)
{
    if ($value === NULL || trim((string)$value) == "")
        return (false);
    return (date_to_timestamp($value) !== NULL);
}

function billing_einvoice_validate_party_identifiers($party, $prefix, &$errors, &$warnings)
{
    if (!is_array($party))
    {
        $errors[] = $prefix;
        return ;
    }
    $siren = preg_replace('/[^0-9]/', '', (string)($party["siren"] ?? ""));
    $siret = preg_replace('/[^0-9]/', '', (string)($party["siret"] ?? ""));
    $vat = strtoupper(preg_replace('/\s+/', '', (string)($party["vat_number"] ?? "")));

    if ($siren != "" && strlen($siren) != 9)
        $errors[] = $prefix.".siren_format";
    else if (strlen($siren) == 9 && $siren != "356000000" && !billing_einvoice_luhn_valid($siren))
        $warnings[] = $prefix.".siren_checksum";

    if ($siret != "")
    {
        if (strlen($siret) != 14)
            $errors[] = $prefix.".siret_format";
        else
        {
            if ($siren != "" && substr($siret, 0, 9) !== $siren)
                $errors[] = $prefix.".siret_siren_mismatch";
            if ($siren != "356000000" && !billing_einvoice_luhn_valid($siret))
                $warnings[] = $prefix.".siret_checksum";
        }
    }

    if ($vat != "" && !preg_match('/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vat))
        $warnings[] = $prefix.".vat_format";
}

function billing_einvoice_validate_canonical_detailed($canonical)
{
    $errors = [];
    $warnings = [];
    $checks = [
        "canonical" => false,
        "identifiers" => false,
        "dates" => false,
        "amounts" => false,
        "lines" => false,
        "taxes" => false,
    ];

    if (!is_array($canonical))
        return (["ok" => false, "errors" => ["canonical"], "warnings" => [], "checks" => $checks]);

    foreach (["reference", "issue_date", "currency", "seller", "buyer", "totals", "lines"] as $field)
        if (!isset($canonical[$field]) || $canonical[$field] === "" || $canonical[$field] === [])
            $errors[] = $field;

    if (($canonical["schema"] ?? "") != "infosphere.billing.canonical-invoice")
        $warnings[] = "schema";
    if ((int)($canonical["schema_version"] ?? 0) < 2)
        $warnings[] = "schema_version";

    $reference = trim((string)($canonical["reference"] ?? ""));
    if ($reference == "")
        $errors[] = "reference";
    else if (strlen($reference) > 128)
        $errors[] = "reference_length";

    $currency = strtoupper(trim((string)($canonical["currency"] ?? "")));
    if (!preg_match('/^[A-Z]{3}$/', $currency))
        $errors[] = "currency";

    $document_type = (string)($canonical["document_type"] ?? "invoice");
    if (!in_array($document_type, ["invoice", "credit_note"], true))
        $errors[] = "document_type";
    $flow = (string)($canonical["flow_type"] ?? "");
    if (!in_array($flow, ["einvoice", "ereporting_transaction", "ereporting_payment", "out_of_scope_exempt"], true))
        $errors[] = "flow_type";
    $billing_frame = strtoupper(trim((string)($canonical["billing_frame"] ?? "")));
    $allowed_frames = ["B1", "S1", "M1", "B2", "S2", "M2", "S3", "B4", "S4", "M4", "S5", "S6", "B7", "S7", "B8", "S8", "M8", "B9", "S9", "M9"];
    if ($flow == "einvoice" && !in_array($billing_frame, $allowed_frames, true))
        $errors[] = "billing_frame";
    $treatment = strtoupper(trim((string)($canonical["treatment"] ?? "")));
    if (($flow == "einvoice" && $treatment != "B2B")
        || ($flow == "out_of_scope_exempt" && $treatment != "OUT_OF_SCOPE"))
        $errors[] = "treatment";

    $issue_valid = billing_einvoice_valid_date($canonical["issue_date"] ?? NULL);
    if (!$issue_valid)
        $errors[] = "issue_date";
    $due = $canonical["due_date"] ?? NULL;
    $due_valid = ($due === NULL || trim((string)$due) == "") ? true : billing_einvoice_valid_date($due);
    if (!$due_valid)
        $errors[] = "due_date";
    if ($issue_valid && $due_valid && $document_type == "invoice" && $due !== NULL && trim((string)$due) != "")
    {
        $issue_stamp = date_to_timestamp($canonical["issue_date"]);
        $due_stamp = date_to_timestamp($due);
        if ($issue_stamp !== NULL && $due_stamp !== NULL)
        {
            // Invoice due dates are calendar dates.  A due date on the same
            // civil day as issuance is valid even when sent_date carries a
            // later time-of-day while due_date is stored at midnight.
            $issue_day = date("Y-m-d", $issue_stamp);
            $due_day = date("Y-m-d", $due_stamp);
            if ($due_day < $issue_day)
                $errors[] = "due_before_issue";
        }
    }
    $checks["dates"] = !in_array("issue_date", $errors, true) && !in_array("due_date", $errors, true) && !in_array("due_before_issue", $errors, true);

    $seller = $canonical["seller"] ?? [];
    if (billing_einvoice_party_name($seller) == "")
        $errors[] = "seller.legal_name";
    if (strlen((string)($seller["siren"] ?? "")) != 9)
        $errors[] = "seller.siren";
    if (trim((string)($seller["address"]["line1"] ?? "")) == "")
        $errors[] = "seller.address";
    if (trim((string)($seller["address"]["city"] ?? "")) == "")
        $errors[] = "seller.city";
    if (billing_einvoice_country_code($seller["address"]["country"] ?? "") == "")
        $errors[] = "seller.country";
    billing_einvoice_validate_party_identifiers($seller, "seller", $errors, $warnings);

    $buyer = $canonical["buyer"] ?? [];
    if ($flow == "einvoice")
    {
        if (($buyer["kind"] ?? "") != "organization")
            $errors[] = "buyer.organization";
        if (billing_einvoice_party_name($buyer) == "")
            $errors[] = "buyer.legal_name";
        if (strlen((string)($buyer["siren"] ?? "")) != 9)
            $errors[] = "buyer.siren";
        if (trim((string)($buyer["address"]["line1"] ?? "")) == "")
            $errors[] = "buyer.address";
        if (trim((string)($buyer["address"]["city"] ?? "")) == "")
            $errors[] = "buyer.city";
        if (billing_einvoice_country_code($buyer["address"]["country"] ?? "") == "")
            $errors[] = "buyer.country";
        billing_einvoice_validate_party_identifiers($buyer, "buyer", $errors, $warnings);
    }
    else if (billing_einvoice_party_name($buyer) == "")
        $errors[] = "buyer.name";

    $identifier_errors = array_filter($errors, function ($error) {
        return (strpos($error, "siren") !== false || strpos($error, "siret") !== false || strpos($error, "vat") !== false);
    });
    $checks["identifiers"] = !count($identifier_errors);

    $totals = $canonical["totals"] ?? [];
    foreach (["net", "tax", "gross"] as $name)
        if (!isset($totals[$name]) || !is_numeric($totals[$name]))
            $errors[] = "totals.".$name;
    $net = (int)($totals["net"] ?? 0);
    $tax_total = (int)($totals["tax"] ?? 0);
    $gross = (int)($totals["gross"] ?? 0);
    if ($net < 0 || $tax_total < 0 || $gross < 0)
        $errors[] = "totals.negative";
    if (abs(($net + $tax_total) - $gross) > 1)
        $errors[] = "totals.balance";
    $checks["amounts"] = !in_array("totals.balance", $errors, true) && !in_array("totals.negative", $errors, true);

    $lines = $canonical["lines"] ?? [];
    if (!is_array($lines) || !count($lines))
        $errors[] = "lines";
    $line_total = 0;
    $positions = [];
    foreach ((array)$lines as $i => $line)
    {
        $position = (int)($line["position"] ?? ($i + 1));
        if ($position <= 0 || isset($positions[$position]))
            $errors[] = "lines.position";
        $positions[$position] = true;
        if (trim((string)($line["description"] ?? "")) == "")
            $errors[] = "lines.description";
        $quantity = (float)($line["quantity"] ?? 0);
        if ($quantity <= 0)
            $errors[] = "lines.quantity";
        $unit_price = (int)($line["unit_price_net"] ?? 0);
        $line_net = (int)($line["net_amount"] ?? 0);
        if ($unit_price < 0 || $line_net < 0)
            $errors[] = "lines.amount";
        if ($quantity > 0 && abs((int)round($unit_price * $quantity) - $line_net) > 1)
            $warnings[] = "lines.rounding";
        $line_total += $line_net;
        $rate = (float)($line["vat_rate"] ?? 0);
        if ($rate < 0 || $rate > 100)
            $errors[] = "lines.vat_rate";
    }
    if (abs($line_total - $net) > 1)
        $errors[] = "lines.total";
    $checks["lines"] = !count(array_filter($errors, function ($error) { return (strpos($error, "lines") === 0); }));

    $taxes = $canonical["taxes"] ?? [];
    if (!is_array($taxes) || !count($taxes))
        $errors[] = "taxes";
    $tax_base_total = 0;
    $tax_amount_total = 0;
    foreach ((array)$taxes as $tax)
    {
        $rate = (float)($tax["rate"] ?? 0);
        $base = (int)($tax["base"] ?? 0);
        $amount = (int)($tax["amount"] ?? 0);
        if ($rate < 0 || $rate > 100 || $base < 0 || $amount < 0)
            $errors[] = "taxes.values";
        $tax_base_total += $base;
        $tax_amount_total += $amount;
        if ($rate == 0.0 && trim((string)($tax["exemption_mention"] ?? "")) == "")
            $warnings[] = "taxes.zero_without_reason";
        if ($rate > 0 && abs((int)round($base * $rate / 100) - $amount) > 1)
            $warnings[] = "taxes.rounding";
    }
    if (abs($tax_base_total - $net) > 1)
        $errors[] = "taxes.base_total";
    if (abs($tax_amount_total - $tax_total) > 1)
        $errors[] = "taxes.amount_total";
    $checks["taxes"] = !count(array_filter($errors, function ($error) { return (strpos($error, "taxes") === 0); }));

    if ($document_type == "credit_note" && trim((string)($canonical["local"]["related_invoice_reference"] ?? "")) == "")
        $warnings[] = "credit_note.related_invoice";

    $errors = array_values(array_unique($errors));
    $warnings = array_values(array_unique($warnings));
    $checks["canonical"] = !count($errors);
    return ([
        "ok" => !count($errors),
        "errors" => $errors,
        "warnings" => $warnings,
        "checks" => $checks,
    ]);
}

function billing_einvoice_validate_canonical($canonical)
{
    $validation = billing_einvoice_validate_canonical_detailed($canonical);
    return ($validation["errors"]);
}

function billing_einvoice_ubl_from_canonical($canonical)
{
    $errors = billing_einvoice_validate_canonical($canonical);
    if (count($errors))
        return (["ok" => false, "error" => "BillingElectronicIncomplete", "missing" => $errors]);
    $credit = ($canonical["document_type"] ?? "") == "credit_note";
    $root = $credit ? "CreditNote" : "Invoice";
    $line_tag = $credit ? "CreditNoteLine" : "InvoiceLine";
    $quantity_tag = $credit ? "CreditedQuantity" : "InvoicedQuantity";
    $currency = billing_einvoice_xml_escape($canonical["currency"] ?? "EUR");
    $reference = billing_einvoice_xml_escape($canonical["reference"] ?? "");
    $issue = billing_einvoice_date($canonical["issue_date"] ?? "");
    $due = billing_einvoice_date($canonical["due_date"] ?? "");
    $type_code = $credit ? "381" : "380";
    $type_element = $credit ? "CreditNoteTypeCode" : "InvoiceTypeCode";
    $totals = $canonical["totals"] ?? [];
    $tax = $canonical["taxes"][0] ?? ["rate" => 0, "base" => 0, "amount" => 0, "exemption_mention" => ""];
    $rate = (float)($tax["rate"] ?? 0);
    $exemption = trim((string)($tax["exemption_mention"] ?? ""));
    $tax_category = $rate > 0 ? "S" : ($exemption != "" ? "E" : "Z");
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<'.$root.' xmlns="urn:oasis:names:specification:ubl:schema:xsd:'.$root.'-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">';
    $xml .= '<cbc:CustomizationID>urn:cen.eu:en16931:2017</cbc:CustomizationID>';
    $billing_frame = strtoupper(trim((string)($canonical["billing_frame"] ?? "")));
    if ($billing_frame != "")
        $xml .= '<cbc:ProfileID>'.billing_einvoice_xml_escape($billing_frame).'</cbc:ProfileID>';
    $xml .= '<cbc:ID>'.$reference.'</cbc:ID><cbc:IssueDate>'.$issue.'</cbc:IssueDate>';
    if (!$credit && $due != "") $xml .= '<cbc:DueDate>'.$due.'</cbc:DueDate>';
    $xml .= '<cbc:'.$type_element.'>'.$type_code.'</cbc:'.$type_element.'>';
    $treatment = strtoupper(trim((string)($canonical["treatment"] ?? "")));
    if (in_array($treatment, ["B2B", "B2C"], true))
        $xml .= '<cbc:Note>#BAR#'.billing_einvoice_xml_escape($treatment).'</cbc:Note>';
    $xml .= '<cbc:DocumentCurrencyCode>'.$currency.'</cbc:DocumentCurrencyCode>';
    $related_reference = trim((string)($canonical["local"]["related_invoice_reference"] ?? ""));
    if ($credit && $related_reference != "")
        $xml .= '<cac:BillingReference><cac:InvoiceDocumentReference><cbc:ID>'.billing_einvoice_xml_escape($related_reference).'</cbc:ID></cac:InvoiceDocumentReference></cac:BillingReference>';
    $xml .= '<cac:AccountingSupplierParty><cac:Party>'.billing_einvoice_ubl_party($canonical["seller"]).'</cac:Party></cac:AccountingSupplierParty>';
    $xml .= '<cac:AccountingCustomerParty><cac:Party>'.billing_einvoice_ubl_party($canonical["buyer"]).'</cac:Party></cac:AccountingCustomerParty>';
    $xml .= '<cac:TaxTotal><cbc:TaxAmount currencyID="'.$currency.'">'.billing_einvoice_money($totals["tax"] ?? 0).'</cbc:TaxAmount><cac:TaxSubtotal>';
    $xml .= '<cbc:TaxableAmount currencyID="'.$currency.'">'.billing_einvoice_money($tax["base"] ?? 0).'</cbc:TaxableAmount>';
    $xml .= '<cbc:TaxAmount currencyID="'.$currency.'">'.billing_einvoice_money($tax["amount"] ?? 0).'</cbc:TaxAmount>';
    $xml .= '<cac:TaxCategory><cbc:ID>'.$tax_category.'</cbc:ID><cbc:Percent>'.number_format($rate, 2, '.', '').'</cbc:Percent>';
    if ($tax_category == "E" && $exemption != "")
        $xml .= '<cbc:TaxExemptionReason>'.billing_einvoice_xml_escape($exemption).'</cbc:TaxExemptionReason>';
    $xml .= '<cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory></cac:TaxSubtotal></cac:TaxTotal>';
    $xml .= '<cac:LegalMonetaryTotal><cbc:LineExtensionAmount currencyID="'.$currency.'">'.billing_einvoice_money($totals["net"] ?? 0).'</cbc:LineExtensionAmount>';
    $xml .= '<cbc:TaxExclusiveAmount currencyID="'.$currency.'">'.billing_einvoice_money($totals["net"] ?? 0).'</cbc:TaxExclusiveAmount>';
    $xml .= '<cbc:TaxInclusiveAmount currencyID="'.$currency.'">'.billing_einvoice_money($totals["gross"] ?? 0).'</cbc:TaxInclusiveAmount>';
    $xml .= '<cbc:PayableAmount currencyID="'.$currency.'">'.billing_einvoice_money($totals["gross"] ?? 0).'</cbc:PayableAmount></cac:LegalMonetaryTotal>';
    foreach (($canonical["lines"] ?? []) as $i => $line)
    {
        $line_id = (int)($line["position"] ?? ($i + 1));
        $qty = (float)($line["quantity"] ?? 1);
        $unit = trim((string)($line["unit"] ?? "C62"));
        $line_rate = (float)($line["vat_rate"] ?? $rate);
        $line_category = $line_rate > 0 ? "S" : ($exemption != "" ? "E" : "Z");
        $xml .= '<cac:'.$line_tag.'><cbc:ID>'.$line_id.'</cbc:ID><cbc:'.$quantity_tag.' unitCode="'.billing_einvoice_xml_escape($unit).'">'.number_format($qty, 2, '.', '').'</cbc:'.$quantity_tag.'>';
        $xml .= '<cbc:LineExtensionAmount currencyID="'.$currency.'">'.billing_einvoice_money($line["net_amount"] ?? 0).'</cbc:LineExtensionAmount>';
        $xml .= '<cac:Item><cbc:Name>'.billing_einvoice_xml_escape($line["description"] ?? "").'</cbc:Name><cac:ClassifiedTaxCategory><cbc:ID>'.$line_category.'</cbc:ID><cbc:Percent>'.number_format($line_rate, 2, '.', '').'</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory></cac:Item>';
        $xml .= '<cac:Price><cbc:PriceAmount currencyID="'.$currency.'">'.billing_einvoice_money($line["unit_price_net"] ?? 0).'</cbc:PriceAmount></cac:Price></cac:'.$line_tag.'>';
    }
    $xml .= '</'.$root.'>';
    return (["ok" => true, "content" => $xml]);
}

function billing_einvoice_validate_local($canonical)
{
    $validation = billing_einvoice_validate_canonical_detailed($canonical);
    $errors = $validation["errors"];
    $warnings = $validation["warnings"];
    $checks = $validation["checks"];
    $checks["ubl_generated"] = false;
    $checks["ubl_xml"] = false;

    if (count($errors))
        return (["ok" => false, "errors" => $errors, "warnings" => $warnings, "checks" => $checks]);

    $ubl = billing_einvoice_ubl_from_canonical($canonical);
    if (empty($ubl["ok"]))
    {
        $errors = array_merge($errors, $ubl["missing"] ?? [$ubl["error"] ?? "ubl"]);
        return ([
            "ok" => false,
            "errors" => array_values(array_unique($errors)),
            "warnings" => $warnings,
            "checks" => $checks,
        ]);
    }
    $checks["ubl_generated"] = true;

    if (class_exists("DOMDocument"))
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $loaded = $dom->loadXML($ubl["content"], LIBXML_NONET | LIBXML_NOBLANKS);
        if (!$loaded)
        {
            foreach (libxml_get_errors() as $error)
                $errors[] = "xml:".trim((string)$error->message);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $checks["ubl_xml"] = $loaded;
    }
    else
    {
        $checks["ubl_xml"] = NULL;
        $warnings[] = "xml.dom_unavailable";
    }

    return ([
        "ok" => !count($errors),
        "errors" => array_values(array_unique($errors)),
        "warnings" => array_values(array_unique($warnings)),
        "checks" => $checks,
        "warning" => "xsd_rules_not_checked",
    ]);
}


function billing_einvoice_validate_payment_canonical($canonical)
{
    $errors = [];
    $warnings = [];
    $checks = ["canonical" => false, "dates" => false, "amounts" => false, "allocations" => false];
    if (!is_array($canonical))
        return (["ok" => false, "errors" => ["canonical"], "warnings" => [], "checks" => $checks]);
    if (($canonical["schema"] ?? "") != "infosphere.billing.canonical-payment")
        $errors[] = "payment.schema";
    $flow_type = (string)($canonical["flow_type"] ?? "");
    if (!in_array($flow_type, ["ereporting_payment", "einvoice_lifecycle"], true))
        $errors[] = "payment.flow_type";
    $treatment = strtoupper(trim((string)($canonical["treatment"] ?? "")));
    if (($flow_type == "ereporting_payment" && $treatment != "B2C")
        || ($flow_type == "einvoice_lifecycle" && $treatment != "B2B"))
        $errors[] = "payment.treatment";
    if (($canonical["document_type"] ?? "") != "payment")
        $errors[] = "payment.document_type";
    if (!billing_einvoice_valid_date($canonical["issue_date"] ?? NULL))
        $errors[] = "payment.date";
    else
        $checks["dates"] = true;
    if (billing_einvoice_party_name($canonical["seller"] ?? []) == "")
        $errors[] = "payment.seller";
    $totals = $canonical["totals"] ?? [];
    $gross = (int)($totals["gross"] ?? 0);
    if ($gross <= 0)
        $errors[] = "payment.amount";
    $reported = (int)($canonical["payment"]["reported_amount"] ?? 0);
    if ($reported <= 0 || abs($reported - $gross) > 1)
        $errors[] = "payment.reported_amount";
    $allocations = $canonical["allocations"] ?? [];
    $sum = 0;
    if (!is_array($allocations) || !count($allocations))
        $errors[] = "payment.allocations";
    else
    {
        foreach ($allocations as $allocation)
        {
            $amount = (int)($allocation["amount"] ?? 0);
            if ($amount <= 0 || trim((string)($allocation["invoice_reference"] ?? "")) == "")
                $errors[] = "payment.allocation";
            $sum += max(0, $amount);
        }
        if (abs($sum - $gross) > 1)
            $errors[] = "payment.allocations_total";
    }
    $checks["amounts"] = !count(array_filter($errors, function ($error) { return (strpos($error, "payment.amount") === 0 || strpos($error, "payment.reported_amount") === 0); }));
    $checks["allocations"] = !count(array_filter($errors, function ($error) { return (strpos($error, "payment.alloc") === 0); }));
    if ((int)($canonical["local"]["ignored_other_amount"] ?? 0) > 0)
        $warnings[] = "payment.other_flow_not_in_document";
    $errors = array_values(array_unique($errors));
    $warnings = array_values(array_unique($warnings));
    $checks["canonical"] = !count($errors);
    return (["ok" => !count($errors), "errors" => $errors, "warnings" => $warnings, "checks" => $checks]);
}

function billing_einvoice_cdar_party($party)
{
    $id = trim((string)($party["siren"] ?? ""));
    if ($id == "") $id = trim((string)($party["siret"] ?? ""));
    $name = billing_einvoice_party_name($party);
    $xml = "";
    if ($id != "")
        $xml .= '<ram:ID schemeID="0002">'.billing_einvoice_xml_escape($id).'</ram:ID>';
    if ($name != "")
        $xml .= '<ram:Name>'.billing_einvoice_xml_escape($name).'</ram:Name>';
    return ($xml);
}

function billing_einvoice_cdar_from_payment($canonical)
{
    $validation = billing_einvoice_validate_payment_canonical($canonical);
    if (empty($validation["ok"]))
        return (["ok" => false, "error" => "BillingElectronicIncomplete", "missing" => $validation["errors"]]);
    $reference = preg_replace('/[^A-Za-z0-9_.-]+/', '-', (string)($canonical["reference"] ?? "payment"));
    $message_id = "IS-CDV-".$reference."-".substr(hash("sha256", billing_einvoice_json($canonical)), 0, 12);
    $date = billing_einvoice_date($canonical["issue_date"] ?? "");
    $timestamp = $date != "" ? str_replace("-", "", $date) : gmdate("Ymd");
    $seller = $canonical["seller"] ?? [];
    $buyer = $canonical["buyer"] ?? [];
    $currency = billing_einvoice_xml_escape($canonical["currency"] ?? "EUR");

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<rsm:CrossDomainApplicationResponse xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossDomainApplicationResponse:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">';
    $xml .= '<rsm:ExchangedDocumentContext><ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>urn:cpro.gouv.fr:1p0:CDV:invoice</ram:ID></ram:GuidelineSpecifiedDocumentContextParameter></rsm:ExchangedDocumentContext>';
    $xml .= '<rsm:ExchangedDocument><ram:ID>'.billing_einvoice_xml_escape($message_id).'</ram:ID><ram:Name>Cycle de vie facture</ram:Name><ram:IssueDateTime><udt:DateTimeString format="102">'.$timestamp.'</udt:DateTimeString></ram:IssueDateTime><ram:LanguageID>fr</ram:LanguageID>';
    $xml .= '<ram:SenderTradeParty>'.billing_einvoice_cdar_party($seller).'</ram:SenderTradeParty>';
    $xml .= '<ram:IssuerTradeParty>'.billing_einvoice_cdar_party($seller).'</ram:IssuerTradeParty>';
    if (billing_einvoice_party_name($buyer) != "")
        $xml .= '<ram:RecipientTradeParty>'.billing_einvoice_cdar_party($buyer).'</ram:RecipientTradeParty>';
    $xml .= '</rsm:ExchangedDocument>';
    $xml .= '<rsm:AcknowledgementDocument><ram:ID>'.billing_einvoice_xml_escape($message_id).'</ram:ID><ram:TypeCode>23</ram:TypeCode><ram:Name>Encaissée</ram:Name><ram:IssueDateTime><udt:DateTimeString format="102">'.$timestamp.'</udt:DateTimeString></ram:IssueDateTime>';
    foreach (($canonical["allocations"] ?? []) as $allocation)
    {
        $invoice_reference = billing_einvoice_xml_escape($allocation["invoice_reference"] ?? "");
        $invoice_date = billing_einvoice_date($allocation["invoice_issue_date"] ?? "");
        $invoice_stamp = $invoice_date != "" ? str_replace("-", "", $invoice_date) : $timestamp;
        $amount = (int)($allocation["amount"] ?? 0);
        $rate = (float)($allocation["vat_rate"] ?? 0);
        $xml .= '<ram:ReferenceReferencedDocument>';
        $xml .= '<ram:IssuerAssignedID>'.$invoice_reference.'</ram:IssuerAssignedID><ram:StatusCode>47</ram:StatusCode><ram:TypeCode>380</ram:TypeCode>';
        $xml .= '<ram:FormattedIssueDateTime><qdt:DateTimeString xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100" format="102">'.$invoice_stamp.'</qdt:DateTimeString></ram:FormattedIssueDateTime>';
        $xml .= '<ram:Status>Paid</ram:Status><ram:ProcessConditionCode>212</ram:ProcessConditionCode><ram:ProcessCondition>Encaissée</ram:ProcessCondition>';
        $xml .= '<ram:IssuerTradeParty>'.billing_einvoice_cdar_party($seller).'</ram:IssuerTradeParty>';
        $xml .= '<ram:SpecifiedDocumentStatus><ram:SpecifiedDocumentCharacteristic><ram:TypeCode>MEN</ram:TypeCode><ram:ValueChangedIndicator><udt:IndicatorString>false</udt:IndicatorString></ram:ValueChangedIndicator>';
        $xml .= '<ram:ValueAmount currencyID="'.$currency.'">'.billing_einvoice_money($amount).'</ram:ValueAmount><ram:ValuePercent>'.number_format($rate, 2, '.', '').'</ram:ValuePercent>';
        $xml .= '</ram:SpecifiedDocumentCharacteristic></ram:SpecifiedDocumentStatus></ram:ReferenceReferencedDocument>';
    }
    $xml .= '</rsm:AcknowledgementDocument></rsm:CrossDomainApplicationResponse>';
    return (["ok" => true, "content" => $xml]);
}

function billing_einvoice_export_cdar($id_document)
{
    $document = billing_einvoice_document((int)$id_document);
    if ($document == NULL || !in_array(($document["flow_type"] ?? ""), ["ereporting_payment", "einvoice_lifecycle"], true))
        return (["ok" => false, "error" => "NotFound"]);
    $canonical = json_decode((string)$document["canonical_json"], true);
    if (!is_array($canonical))
        return (["ok" => false, "error" => "BillingElectronicInvalidCanonical"]);
    $cdar = billing_einvoice_cdar_from_payment($canonical);
    if (empty($cdar["ok"]))
        return ($cdar);
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)$document["local_reference"]);
    if ($safe == "") $safe = "payment";
    return (["ok" => true, "filename" => $safe."-CDV-212.xml", "content_type" => "application/xml; charset=UTF-8", "content" => $cdar["content"]]);
}

function billing_einvoice_document_local_validation($document)
{
    if (!is_array($document))
        return (["ok" => false, "errors" => ["document"], "warnings" => [], "checks" => []]);
    $json = (string)($document["canonical_json"] ?? "");
    $canonical = json_decode($json, true);
    if (!is_array($canonical))
        return (["ok" => false, "errors" => ["canonical_json"], "warnings" => [], "checks" => []]);
    if (($canonical["schema"] ?? "") == "infosphere.billing.canonical-payment")
    {
        $validation = billing_einvoice_validate_payment_canonical($canonical);
        $cdar = empty($validation["ok"]) ? ["ok" => false] : billing_einvoice_cdar_from_payment($canonical);
        $validation["checks"]["cdar_generated"] = !empty($cdar["ok"]);
        if (empty($cdar["ok"]) && !empty($validation["ok"]))
        {
            $validation["errors"][] = "payment.cdar";
            $validation["ok"] = false;
        }
    }
    else
        $validation = billing_einvoice_validate_local($canonical);
    $expected = trim((string)($document["canonical_sha256"] ?? ""));
    $actual = hash("sha256", $json);
    $validation["checks"]["snapshot_hash"] = ($expected != "" && hash_equals($expected, $actual));
    if (!$validation["checks"]["snapshot_hash"])
    {
        $validation["errors"][] = "canonical_sha256";
        $validation["errors"] = array_values(array_unique($validation["errors"]));
        $validation["ok"] = false;
    }
    return ($validation);
}

function billing_einvoice_validation_label($code)
{
    $labels = [
        "canonical" => "Document canonique invalide",
        "canonical_json" => "Snapshot JSON illisible",
        "canonical_sha256" => "Empreinte SHA-256 du snapshot incohérente",
        "schema" => "Identifiant de schéma canonique inattendu",
        "schema_version" => "Version du schéma canonique ancienne",
        "reference" => "Référence de facture manquante",
        "reference_length" => "Référence de facture trop longue",
        "currency" => "Code devise invalide",
        "document_type" => "Type de document invalide",
        "flow_type" => "Type de flux électronique invalide",
        "billing_frame" => "Cadre de facturation français manquant ou invalide (BT-23)",
        "treatment" => "Qualification du traitement électronique invalide",
        "issue_date" => "Date d'émission invalide",
        "due_date" => "Date d'échéance invalide",
        "due_before_issue" => "Échéance antérieure à la date d'émission",
        "seller.legal_name" => "Raison sociale vendeur manquante",
        "seller.siren" => "SIREN vendeur manquant",
        "seller.siren_format" => "Format du SIREN vendeur invalide",
        "seller.siren_checksum" => "Clé de contrôle du SIREN vendeur inhabituelle",
        "seller.siret_format" => "Format du SIRET vendeur invalide",
        "seller.siret_checksum" => "Clé de contrôle du SIRET vendeur inhabituelle",
        "seller.siret_siren_mismatch" => "SIRET vendeur incohérent avec le SIREN",
        "seller.vat_format" => "Format du numéro de TVA vendeur inhabituel",
        "seller.address" => "Adresse vendeur manquante",
        "seller.city" => "Ville vendeur manquante",
        "seller.country" => "Pays vendeur manquant ou inconnu",
        "buyer.organization" => "Destinataire B2B non rattaché à une organisation",
        "buyer.legal_name" => "Raison sociale acheteur manquante",
        "buyer.name" => "Nom du destinataire manquant",
        "buyer.siren" => "SIREN acheteur manquant",
        "buyer.siren_format" => "Format du SIREN acheteur invalide",
        "buyer.siren_checksum" => "Clé de contrôle du SIREN acheteur inhabituelle",
        "buyer.siret_format" => "Format du SIRET acheteur invalide",
        "buyer.siret_checksum" => "Clé de contrôle du SIRET acheteur inhabituelle",
        "buyer.siret_siren_mismatch" => "SIRET acheteur incohérent avec le SIREN",
        "buyer.vat_format" => "Format du numéro de TVA acheteur inhabituel",
        "buyer.address" => "Adresse acheteur manquante",
        "buyer.city" => "Ville acheteur manquante",
        "buyer.country" => "Pays acheteur manquant ou inconnu",
        "totals.net" => "Total HT invalide",
        "totals.tax" => "Total TVA invalide",
        "totals.gross" => "Total TTC invalide",
        "totals.negative" => "Montant total négatif dans le snapshot",
        "totals.balance" => "HT + TVA ne correspond pas au TTC",
        "lines" => "Aucune ligne de facture",
        "lines.position" => "Numéro de ligne invalide ou dupliqué",
        "lines.description" => "Libellé de ligne manquant",
        "lines.quantity" => "Quantité de ligne invalide",
        "lines.amount" => "Montant de ligne invalide",
        "lines.rounding" => "Arrondi prix × quantité à contrôler",
        "lines.vat_rate" => "Taux de TVA de ligne invalide",
        "lines.total" => "Somme des lignes différente du total HT",
        "taxes" => "Ventilation de TVA manquante",
        "taxes.values" => "Ventilation de TVA invalide",
        "taxes.base_total" => "Bases de TVA différentes du total HT",
        "taxes.amount_total" => "Somme des TVA différente du total TVA",
        "taxes.zero_without_reason" => "TVA à 0 % sans motif d'exonération",
        "taxes.rounding" => "Arrondi de TVA à contrôler",
        "credit_note.related_invoice" => "Avoir sans référence de facture d'origine",
        "xml.dom_unavailable" => "Extension DOM absente : XML non reparsé localement",
        "payment.schema" => "Schéma canonique d’encaissement invalide",
        "payment.flow_type" => "Type de flux d’encaissement invalide",
        "payment.treatment" => "Traitement B2B/B2C incohérent avec le flux d’encaissement",
        "payment.document_type" => "Type de document d’encaissement invalide",
        "payment.date" => "Date d’encaissement invalide",
        "payment.seller" => "Vendeur manquant pour l’encaissement",
        "payment.amount" => "Montant d’encaissement invalide",
        "payment.reported_amount" => "Montant à déclarer incohérent",
        "payment.allocations" => "Aucune facture B2C couverte par cet encaissement",
        "payment.allocation" => "Rattachement de facture invalide",
        "payment.allocations_total" => "Somme des factures rattachées différente du montant déclaré",
        "payment.other_flow_not_in_document" => "Une partie du règlement relève d’un autre circuit B2B/B2C et figure dans un document électronique séparé",
        "payment.cdar" => "Message CDAR 212 impossible à générer",
    ];
    if (isset($labels[$code]))
        return ($labels[$code]);
    if (strpos((string)$code, "xml:") === 0)
        return (substr((string)$code, 4));
    return ((string)$code);
}

function billing_einvoice_export_ubl($id_document)
{
    $document = billing_einvoice_document($id_document);
    if ($document == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    $canonical = json_decode((string)$document["canonical_json"], true);
    if (!is_array($canonical))
        return (["ok" => false, "error" => "BillingElectronicInvalidCanonical"]);
    $ubl = billing_einvoice_ubl_from_canonical($canonical);
    if (empty($ubl["ok"]))
        return ($ubl);
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)$document["local_reference"]);
    if ($safe == "") $safe = "invoice";
    return (["ok" => true, "filename" => $safe.".xml", "content_type" => "application/xml; charset=UTF-8", "content" => $ubl["content"]]);
}

function billing_einvoice_xml_text($xpath, $query, $context = NULL)
{
    if (!($xpath instanceof DOMXPath))
        return ("");
    $nodes = $context === NULL ? @$xpath->query($query) : @$xpath->query($query, $context);
    if ($nodes === false || $nodes->length == 0)
        return ("");
    return (trim((string)$nodes->item(0)->textContent));
}

function billing_einvoice_xml_cents($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return (0);
    $value = str_replace([" ", "\xc2\xa0", ","], ["", "", "."], $value);
    if (!is_numeric($value))
        return (0);
    return ((int)round((float)$value * 100));
}

function billing_einvoice_ubl_parse_party($xpath, $party_node)
{
    if (!($xpath instanceof DOMXPath) || !($party_node instanceof DOMNode))
        return ([]);
    $name = billing_einvoice_xml_text($xpath, ".//*[local-name()='PartyLegalEntity']/*[local-name()='RegistrationName']", $party_node);
    if ($name == "")
        $name = billing_einvoice_xml_text($xpath, ".//*[local-name()='PartyName']/*[local-name()='Name']", $party_node);
    $siren = "";
    $siret = "";
    $routing = "";
    $routing_scheme = "";
    $ids = @$xpath->query(".//*[local-name()='PartyIdentification']/*[local-name()='ID']", $party_node);
    if ($ids !== false)
        foreach ($ids as $id)
        {
            $value = preg_replace('/\s+/', '', trim((string)$id->textContent));
            $scheme = $id instanceof DOMElement ? trim((string)$id->getAttribute("schemeID")) : "";
            if ($scheme == "0002" && preg_match('/^[0-9]{9}$/', $value))
                $siren = $value;
            else if ($scheme == "0009" && preg_match('/^[0-9]{14}$/', $value))
                $siret = $value;
            else if ($routing == "" && $value != "")
            {
                $routing = $value;
                $routing_scheme = $scheme;
            }
        }
    if ($siren == "" && strlen($siret) == 14)
        $siren = substr($siret, 0, 9);
    $endpoint = billing_einvoice_xml_text($xpath, "./*[local-name()='EndpointID']", $party_node);
    $endpoint_scheme = "";
    $endpoint_nodes = @$xpath->query("./*[local-name()='EndpointID']", $party_node);
    if ($endpoint_nodes !== false && $endpoint_nodes->length && $endpoint_nodes->item(0) instanceof DOMElement)
        $endpoint_scheme = trim((string)$endpoint_nodes->item(0)->getAttribute("schemeID"));
    if ($siren == "" && $endpoint_scheme == "0225" && preg_match('/^[0-9]{9}$/', preg_replace('/\D/', '', $endpoint)))
        $siren = preg_replace('/\D/', '', $endpoint);
    $vat = billing_einvoice_xml_text($xpath, ".//*[local-name()='PartyTaxScheme']/*[local-name()='CompanyID']", $party_node);
    return ([
        "kind" => "organization",
        "legal_name" => $name,
        "siren" => $siren,
        "siret" => $siret,
        "vat_number" => strtoupper(trim($vat)),
        "electronic_address" => $endpoint,
        "electronic_address_scheme" => $endpoint_scheme,
        "routing_code" => $routing,
        "routing_scheme" => $routing_scheme,
        "address" => [
            "line1" => billing_einvoice_xml_text($xpath, ".//*[local-name()='PostalAddress']/*[local-name()='StreetName']", $party_node),
            "line2" => billing_einvoice_xml_text($xpath, ".//*[local-name()='PostalAddress']/*[local-name()='AdditionalStreetName']", $party_node),
            "zipcode" => billing_einvoice_xml_text($xpath, ".//*[local-name()='PostalAddress']/*[local-name()='PostalZone']", $party_node),
            "city" => billing_einvoice_xml_text($xpath, ".//*[local-name()='PostalAddress']/*[local-name()='CityName']", $party_node),
            "country" => billing_einvoice_xml_text($xpath, ".//*[local-name()='PostalAddress']//*[local-name()='Country']/*[local-name()='IdentificationCode']", $party_node),
        ],
    ]);
}

function billing_einvoice_xml_plain_blocks($xml, $local_name)
{
    if (!is_string($xml) || $xml == "")
        return ([]);
    $name = preg_quote((string)$local_name, '/');
    $pattern = '/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?'.$name.'\b[^>]*>(.*?)<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?'.$name.'\s*>/si';
    if (!preg_match_all($pattern, $xml, $matches))
        return ([]);
    return ($matches[0]);
}

function billing_einvoice_xml_plain_value($xml, $local_name)
{
    $blocks = billing_einvoice_xml_plain_blocks($xml, $local_name);
    if (!count($blocks))
        return ("");
    $block = $blocks[0];
    $start = strpos($block, ">");
    $end = strrpos($block, "</");
    if ($start === false || $end === false || $end <= $start)
        return ("");
    $text = substr($block, $start + 1, $end - $start - 1);
    $text = preg_replace('/<!\[CDATA\[(.*?)\]\]>/s', '$1', $text);
    $text = strip_tags($text);
    return (trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1, "UTF-8")));
}

function billing_einvoice_xml_plain_attribute($xml, $local_name, $attribute)
{
    $name = preg_quote((string)$local_name, '/');
    $attribute = preg_quote((string)$attribute, '/');
    if (!preg_match('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?'.$name.'\b([^>]*)>/si', (string)$xml, $m))
        return ("");
    if (!preg_match('/\b'.$attribute.'\s*=\s*(["\'])(.*?)\1/si', $m[1], $a))
        return ("");
    return (html_entity_decode(trim($a[2]), ENT_QUOTES | ENT_XML1, "UTF-8"));
}

function billing_einvoice_ubl_parse_party_plain($party)
{
    $legal = billing_einvoice_xml_plain_blocks($party, "PartyLegalEntity");
    $name = count($legal) ? billing_einvoice_xml_plain_value($legal[0], "RegistrationName") : "";
    if ($name == "")
    {
        $party_name = billing_einvoice_xml_plain_blocks($party, "PartyName");
        $name = count($party_name) ? billing_einvoice_xml_plain_value($party_name[0], "Name") : "";
    }
    $siren = "";
    $siret = "";
    $routing = "";
    $routing_scheme = "";
    foreach (billing_einvoice_xml_plain_blocks($party, "PartyIdentification") as $identification)
    {
        $value = preg_replace('/\s+/', '', billing_einvoice_xml_plain_value($identification, "ID"));
        $scheme = billing_einvoice_xml_plain_attribute($identification, "ID", "schemeID");
        if ($scheme == "0002" && preg_match('/^[0-9]{9}$/', $value))
            $siren = $value;
        else if ($scheme == "0009" && preg_match('/^[0-9]{14}$/', $value))
            $siret = $value;
        else if ($routing == "" && $value != "")
        {
            $routing = $value;
            $routing_scheme = $scheme;
        }
    }
    if ($siren == "" && strlen($siret) == 14)
        $siren = substr($siret, 0, 9);
    $endpoint = billing_einvoice_xml_plain_value($party, "EndpointID");
    $endpoint_scheme = billing_einvoice_xml_plain_attribute($party, "EndpointID", "schemeID");
    if ($siren == "" && $endpoint_scheme == "0225")
    {
        $digits = preg_replace('/\D/', '', $endpoint);
        if (strlen($digits) == 9)
            $siren = $digits;
    }
    $tax_scheme = billing_einvoice_xml_plain_blocks($party, "PartyTaxScheme");
    $vat = count($tax_scheme) ? billing_einvoice_xml_plain_value($tax_scheme[0], "CompanyID") : "";
    $address = billing_einvoice_xml_plain_blocks($party, "PostalAddress");
    $address = count($address) ? $address[0] : "";
    $country = billing_einvoice_xml_plain_blocks($address, "Country");
    return ([
        "kind" => "organization",
        "legal_name" => $name,
        "siren" => $siren,
        "siret" => $siret,
        "vat_number" => strtoupper(trim($vat)),
        "electronic_address" => $endpoint,
        "electronic_address_scheme" => $endpoint_scheme,
        "routing_code" => $routing,
        "routing_scheme" => $routing_scheme,
        "address" => [
            "line1" => billing_einvoice_xml_plain_value($address, "StreetName"),
            "line2" => billing_einvoice_xml_plain_value($address, "AdditionalStreetName"),
            "zipcode" => billing_einvoice_xml_plain_value($address, "PostalZone"),
            "city" => billing_einvoice_xml_plain_value($address, "CityName"),
            "country" => count($country) ? billing_einvoice_xml_plain_value($country[0], "IdentificationCode") : "",
        ],
    ]);
}

function billing_einvoice_parse_ubl_plain($content, $remote = [])
{
    if (!is_string($content) || trim($content) == "")
        return (["ok" => false, "error" => "BillingElectronicInvalidXml"]);
    if (!preg_match('/<\s*(?:[A-Za-z_][A-Za-z0-9_.-]*:)?(Invoice|CreditNote)\b/i', $content, $root_match))
        return (["ok" => false, "error" => "BillingElectronicUnsupportedInvoiceXml"]);
    $root = strcasecmp($root_match[1], "CreditNote") == 0 ? "CreditNote" : "Invoice";
    $suppliers = billing_einvoice_xml_plain_blocks($content, "AccountingSupplierParty");
    $customers = billing_einvoice_xml_plain_blocks($content, "AccountingCustomerParty");
    if (!count($suppliers) || !count($customers))
        return (["ok" => false, "error" => "BillingElectronicInvoicePartyMissing"]);
    $supplier_party = billing_einvoice_xml_plain_blocks($suppliers[0], "Party");
    $customer_party = billing_einvoice_xml_plain_blocks($customers[0], "Party");
    if (!count($supplier_party) || !count($customer_party))
        return (["ok" => false, "error" => "BillingElectronicInvoicePartyMissing"]);
    $seller = billing_einvoice_ubl_parse_party_plain($supplier_party[0]);
    $buyer = billing_einvoice_ubl_parse_party_plain($customer_party[0]);

    $reference = billing_einvoice_xml_plain_value($content, "ID");
    $issue = billing_einvoice_xml_plain_value($content, "IssueDate");
    $due = $root == "Invoice" ? billing_einvoice_xml_plain_value($content, "DueDate") : "";
    $currency = strtoupper(billing_einvoice_xml_plain_value($content, "DocumentCurrencyCode"));
    if ($currency == "") $currency = "EUR";
    $monetary = billing_einvoice_xml_plain_blocks($content, "LegalMonetaryTotal");
    $monetary = count($monetary) ? $monetary[0] : "";
    $net = billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($monetary, "TaxExclusiveAmount"));
    if ($net == 0)
        $net = billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($monetary, "LineExtensionAmount"));
    $gross = billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($monetary, "TaxInclusiveAmount"));
    if ($gross == 0)
        $gross = billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($monetary, "PayableAmount"));
    $tax_total = 0;
    $taxes = [];
    $tax_totals = billing_einvoice_xml_plain_blocks($content, "TaxTotal");
    if (count($tax_totals))
    {
        $tax_total = billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($tax_totals[0], "TaxAmount"));
        foreach (billing_einvoice_xml_plain_blocks($tax_totals[0], "TaxSubtotal") as $tax)
        {
            $category = billing_einvoice_xml_plain_blocks($tax, "TaxCategory");
            $category = count($category) ? $category[0] : "";
            $taxes[] = [
                "rate" => (float)billing_einvoice_xml_plain_value($category, "Percent"),
                "base" => billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($tax, "TaxableAmount")),
                "amount" => billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($tax, "TaxAmount")),
                "exemption_mention" => billing_einvoice_xml_plain_value($category, "TaxExemptionReason"),
            ];
        }
    }
    if ($gross == 0) $gross = $net + $tax_total;
    if ($tax_total == 0 && $gross >= $net) $tax_total = $gross - $net;
    if (!count($taxes))
        $taxes[] = ["rate" => 0, "base" => $net, "amount" => $tax_total, "exemption_mention" => ""];

    $lines = [];
    $line_blocks = billing_einvoice_xml_plain_blocks($content, $root == "CreditNote" ? "CreditNoteLine" : "InvoiceLine");
    foreach ($line_blocks as $i => $line)
    {
        $quantity_tag = $root == "CreditNote" ? "CreditedQuantity" : "InvoicedQuantity";
        $quantity = (float)billing_einvoice_xml_plain_value($line, $quantity_tag);
        if ($quantity <= 0) $quantity = 1.0;
        $unit = billing_einvoice_xml_plain_attribute($line, $quantity_tag, "unitCode");
        if ($unit == "") $unit = "C62";
        $item = billing_einvoice_xml_plain_blocks($line, "Item");
        $item = count($item) ? $item[0] : "";
        $description = billing_einvoice_xml_plain_value($item, "Name");
        if ($description == "") $description = billing_einvoice_xml_plain_value($item, "Description");
        if ($description == "") $description = "Ligne ".($i + 1);
        $line_net = billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($line, "LineExtensionAmount"));
        $price_block = billing_einvoice_xml_plain_blocks($line, "Price");
        $price = count($price_block) ? billing_einvoice_xml_cents(billing_einvoice_xml_plain_value($price_block[0], "PriceAmount")) : 0;
        if ($price == 0 && $quantity > 0)
            $price = (int)round($line_net / $quantity);
        $tax_category = billing_einvoice_xml_plain_blocks($item, "ClassifiedTaxCategory");
        $vat_rate = count($tax_category) ? (float)billing_einvoice_xml_plain_value($tax_category[0], "Percent") : 0.0;
        $lines[] = [
            "position" => (int)(billing_einvoice_xml_plain_value($line, "ID") ?: ($i + 1)),
            "description" => $description,
            "quantity" => $quantity,
            "unit" => $unit,
            "unit_price_net" => $price,
            "net_amount" => $line_net,
            "vat_rate" => $vat_rate,
        ];
    }
    if (!count($lines))
        $lines[] = ["position" => 1, "description" => $reference != "" ? $reference : "Facture reçue", "quantity" => 1, "unit" => "C62", "unit_price_net" => $net, "net_amount" => $net, "vat_rate" => (float)($taxes[0]["rate"] ?? 0)];

    $related = "";
    $billing_reference = billing_einvoice_xml_plain_blocks($content, "BillingReference");
    if (count($billing_reference))
    {
        $invoice_reference = billing_einvoice_xml_plain_blocks($billing_reference[0], "InvoiceDocumentReference");
        if (count($invoice_reference))
            $related = billing_einvoice_xml_plain_value($invoice_reference[0], "ID");
    }
    $profile = billing_einvoice_xml_plain_value($content, "ProfileID");
    return (["ok" => true, "canonical" => [
        "schema" => "infosphere.billing.canonical-invoice",
        "schema_version" => 2,
        "flow_type" => "einvoice",
        "direction" => "incoming",
        "billing_frame" => $profile != "" ? $profile : "S1",
        "treatment" => "B2B",
        "document_type" => $root == "CreditNote" ? "credit_note" : "invoice",
        "reference" => $reference,
        "issue_date" => $issue,
        "due_date" => $due == "" ? NULL : $due,
        "currency" => $currency,
        "seller" => $seller,
        "buyer" => $buyer,
        "totals" => ["net" => $net, "tax" => $tax_total, "gross" => $gross],
        "taxes" => $taxes,
        "lines" => $lines,
        "local" => [
            "remote_flow_id" => (string)($remote["flow_id"] ?? ""),
            "related_invoice_reference" => $related,
        ],
    ]]);
}

function billing_einvoice_parse_ubl($content, $remote = [])
{
    if (!is_string($content) || trim($content) == "")
        return (["ok" => false, "error" => "BillingElectronicInvalidXml"]);
    if (!class_exists("DOMDocument"))
        return (billing_einvoice_parse_ubl_plain($content, $remote));
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $loaded = @$dom->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || $dom->documentElement == NULL)
        return (["ok" => false, "error" => "BillingElectronicInvalidXml"]);
    $root = $dom->documentElement->localName;
    if (!in_array($root, ["Invoice", "CreditNote"], true))
        return (["ok" => false, "error" => "BillingElectronicUnsupportedInvoiceXml"]);
    $xpath = new DOMXPath($dom);
    $supplier_nodes = @$xpath->query("//*[local-name()='AccountingSupplierParty']/*[local-name()='Party']");
    $customer_nodes = @$xpath->query("//*[local-name()='AccountingCustomerParty']/*[local-name()='Party']");
    if ($supplier_nodes === false || !$supplier_nodes->length || $customer_nodes === false || !$customer_nodes->length)
        return (["ok" => false, "error" => "BillingElectronicInvoicePartyMissing"]);
    $seller = billing_einvoice_ubl_parse_party($xpath, $supplier_nodes->item(0));
    $buyer = billing_einvoice_ubl_parse_party($xpath, $customer_nodes->item(0));
    $reference = billing_einvoice_xml_text($xpath, "/*[local-name()='Invoice' or local-name()='CreditNote']/*[local-name()='ID']");
    $issue = billing_einvoice_xml_text($xpath, "/*[local-name()='Invoice' or local-name()='CreditNote']/*[local-name()='IssueDate']");
    $due = billing_einvoice_xml_text($xpath, "/*[local-name()='Invoice']/*[local-name()='DueDate']");
    $currency = strtoupper(billing_einvoice_xml_text($xpath, "/*[local-name()='Invoice' or local-name()='CreditNote']/*[local-name()='DocumentCurrencyCode']"));
    if ($currency == "") $currency = "EUR";
    $net = billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "//*[local-name()='LegalMonetaryTotal']/*[local-name()='TaxExclusiveAmount']"));
    if ($net == 0)
        $net = billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "//*[local-name()='LegalMonetaryTotal']/*[local-name()='LineExtensionAmount']"));
    $gross = billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "//*[local-name()='LegalMonetaryTotal']/*[local-name()='TaxInclusiveAmount']"));
    if ($gross == 0)
        $gross = billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "//*[local-name()='LegalMonetaryTotal']/*[local-name()='PayableAmount']"));
    $tax_total = billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "//*[local-name()='TaxTotal']/*[local-name()='TaxAmount']"));
    if ($gross == 0) $gross = $net + $tax_total;
    if ($tax_total == 0 && $gross >= $net) $tax_total = $gross - $net;

    $taxes = [];
    $subtotals = @$xpath->query("//*[local-name()='TaxTotal']/*[local-name()='TaxSubtotal']");
    if ($subtotals !== false)
        foreach ($subtotals as $tax)
        {
            $taxes[] = [
                "rate" => (float)billing_einvoice_xml_text($xpath, ".//*[local-name()='TaxCategory']/*[local-name()='Percent']", $tax),
                "base" => billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "./*[local-name()='TaxableAmount']", $tax)),
                "amount" => billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "./*[local-name()='TaxAmount']", $tax)),
                "exemption_mention" => billing_einvoice_xml_text($xpath, ".//*[local-name()='TaxCategory']/*[local-name()='TaxExemptionReason']", $tax),
            ];
        }
    if (!count($taxes))
        $taxes[] = ["rate" => 0, "base" => $net, "amount" => $tax_total, "exemption_mention" => ""];

    $lines = [];
    $line_nodes = @$xpath->query("//*[local-name()='InvoiceLine' or local-name()='CreditNoteLine']");
    if ($line_nodes !== false)
        foreach ($line_nodes as $i => $line)
        {
            $quantity_nodes = @$xpath->query("./*[local-name()='InvoicedQuantity' or local-name()='CreditedQuantity']", $line);
            $quantity = 1.0;
            $unit = "C62";
            if ($quantity_nodes !== false && $quantity_nodes->length)
            {
                $quantity = (float)trim((string)$quantity_nodes->item(0)->textContent);
                if ($quantity <= 0) $quantity = 1.0;
                if ($quantity_nodes->item(0) instanceof DOMElement && trim((string)$quantity_nodes->item(0)->getAttribute("unitCode")) != "")
                    $unit = trim((string)$quantity_nodes->item(0)->getAttribute("unitCode"));
            }
            $description = billing_einvoice_xml_text($xpath, ".//*[local-name()='Item']/*[local-name()='Name']", $line);
            if ($description == "")
                $description = billing_einvoice_xml_text($xpath, ".//*[local-name()='Item']/*[local-name()='Description']", $line);
            if ($description == "") $description = "Ligne ".($i + 1);
            $line_net = billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, "./*[local-name()='LineExtensionAmount']", $line));
            $price = billing_einvoice_xml_cents(billing_einvoice_xml_text($xpath, ".//*[local-name()='Price']/*[local-name()='PriceAmount']", $line));
            if ($price == 0 && $quantity > 0)
                $price = (int)round($line_net / $quantity);
            $lines[] = [
                "position" => (int)(billing_einvoice_xml_text($xpath, "./*[local-name()='ID']", $line) ?: ($i + 1)),
                "description" => $description,
                "quantity" => $quantity,
                "unit" => $unit,
                "unit_price_net" => $price,
                "net_amount" => $line_net,
                "vat_rate" => (float)billing_einvoice_xml_text($xpath, ".//*[local-name()='ClassifiedTaxCategory']/*[local-name()='Percent']", $line),
            ];
        }
    if (!count($lines))
        $lines[] = ["position" => 1, "description" => $reference != "" ? $reference : "Facture reçue", "quantity" => 1, "unit" => "C62", "unit_price_net" => $net, "net_amount" => $net, "vat_rate" => (float)($taxes[0]["rate"] ?? 0)];

    $related = billing_einvoice_xml_text($xpath, "//*[local-name()='BillingReference']//*[local-name()='InvoiceDocumentReference']/*[local-name()='ID']");
    $canonical = [
        "schema" => "infosphere.billing.canonical-invoice",
        "schema_version" => 2,
        "flow_type" => "einvoice",
        "direction" => "incoming",
        "billing_frame" => billing_einvoice_xml_text($xpath, "/*[local-name()='Invoice' or local-name()='CreditNote']/*[local-name()='ProfileID']") ?: "S1",
        "treatment" => "B2B",
        "document_type" => $root == "CreditNote" ? "credit_note" : "invoice",
        "reference" => $reference,
        "issue_date" => $issue,
        "due_date" => $due == "" ? NULL : $due,
        "currency" => $currency,
        "seller" => $seller,
        "buyer" => $buyer,
        "totals" => ["net" => $net, "tax" => $tax_total, "gross" => $gross],
        "taxes" => $taxes,
        "lines" => $lines,
        "local" => [
            "remote_flow_id" => (string)($remote["flow_id"] ?? ""),
            "related_invoice_reference" => $related,
        ],
    ];
    return (["ok" => true, "canonical" => $canonical]);
}

function billing_einvoice_find_organization_for_party($party)
{
    if (!is_array($party))
        return (NULL);
    $siret = preg_replace('/\D/', '', (string)($party["siret"] ?? ""));
    $siren = preg_replace('/\D/', '', (string)($party["siren"] ?? ""));
    $vat = strtoupper(trim((string)($party["vat_number"] ?? "")));
    if (strlen($siret) == 14)
    {
        $match = db_select_one("id FROM organization WHERE siret = '".db_escape($siret)."' AND deleted IS NULL");
        if ($match != NULL) return ((int)$match["id"]);
    }
    if ($vat != "")
    {
        $matches = db_select_all("id FROM organization WHERE UPPER(vat_number) = '".db_escape($vat)."' AND deleted IS NULL");
        if (count($matches) == 1) return ((int)$matches[0]["id"]);
    }
    if (strlen($siren) == 9)
    {
        $matches = db_select_all("id FROM organization WHERE (siret LIKE '".db_escape($siren)."%' OR registration_number = '".db_escape($siren)."') AND deleted IS NULL");
        if (count($matches) == 1) return ((int)$matches[0]["id"]);
    }
    $name = trim((string)($party["legal_name"] ?? ""));
    if ($name != "")
    {
        $matches = db_select_all("id FROM organization WHERE (LOWER(legal_name) = LOWER('".db_escape($name)."') OR LOWER(name) = LOWER('".db_escape($name)."')) AND deleted IS NULL");
        if (count($matches) == 1) return ((int)$matches[0]["id"]);
    }
    return (NULL);
}

function billing_einvoice_create_organization_for_party($party)
{
    if (!is_array($party))
        return (NULL);
    $name = trim((string)($party["legal_name"] ?? ""));
    if ($name == "") $name = "Entreprise reçue";
    $base = convert_to_codename($name);
    if ($base == "") $base = "entreprise";
    $codename = $base;
    for ($n = 2; ; ++$n)
    {
        $resolved = resolve_codename("organization", $codename);
        if ($resolved->is_error()) break ;
        $codename = $base."-".$n;
    }
    $address = $party["address"] ?? [];
    $ret = add_enterprise([
        "codename" => $codename,
        "name" => $name,
        "legal_name" => $name,
        "head_office_address_line1" => $address["line1"] ?? "",
        "head_office_address_line2" => $address["line2"] ?? "",
        "head_office_zipcode" => $address["zipcode"] ?? "",
        "head_office_city" => $address["city"] ?? "",
        "head_office_country" => $address["country"] ?? "",
        "siret" => $party["siret"] ?? "",
        "vat_number" => $party["vat_number"] ?? "",
        "registration_number" => $party["siren"] ?? "",
        "electronic_invoice_address" => $party["electronic_address"] ?? "",
        "electronic_invoice_address_scheme" => $party["electronic_address_scheme"] ?? "",
        "electronic_invoice_routing_code" => $party["routing_code"] ?? "",
        "electronic_invoice_routing_scheme" => $party["routing_scheme"] ?? "",
    ]);
    if (!is_object($ret) || $ret->is_error())
        return (NULL);
    return ((int)$ret->value["id"]);
}

function billing_einvoice_document_for_remote($id_school, $provider, $flow_id)
{
    $id_school = (int)$id_school;
    $provider = trim((string)$provider);
    $flow_id = trim((string)$flow_id);
    if ($id_school <= 0 || $provider == "" || $flow_id == "")
        return (NULL);
    return (db_select_one("*
        FROM billing_electronic_document
        WHERE id_school = $id_school
          AND direction = 'incoming'
          AND provider_key = '".db_escape($provider)."'
          AND provider_document_id = '".db_escape($flow_id)."'
          AND deleted IS NULL
    "));
}

function billing_einvoice_incoming_raw_extension($syntax, $content_type, $filename)
{
    $syntax = strtoupper(trim((string)$syntax));
    $content_type = strtolower((string)$content_type);
    $extension = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    if ($syntax == "FACTUR-X" || strpos($content_type, "pdf") !== false || $extension == "pdf")
        return ("pdf");
    return ("xml");
}

function billing_einvoice_store_incoming_raw($id_document, $id_school, $school_organization, $content, $extension)
{
    global $Database;
    $id_document = (int)$id_document;
    $id_school = (int)$id_school;
    if ($id_document <= 0 || $id_school <= 0 || !is_array($school_organization) || empty($school_organization["codename"]) || !is_string($content) || $content == "")
        return (false);
    if (!in_array($extension, ["xml", "pdf"], true))
        return (false);
    $path = organization_dir($school_organization["codename"])."accounting/".$id_school."/electronic-".$id_document.".".$extension;
    if ((new_directory($path))->is_error())
        return (false);
    $tmp = $path.".tmp.".getmypid();
    if (@file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $path))
    {
        @unlink($tmp);
        return (false);
    }
    @chmod($path, 0640);
    return ($Database->query("UPDATE billing_electronic_document SET raw_path = '".db_escape($path)."', updated_at = NOW() WHERE id = $id_document AND deleted IS NULL") !== NULL);
}

function billing_einvoice_raw_url($document)
{
    $path = trim((string)($document["raw_path"] ?? ""));
    return ($path == "" ? "" : "/".ltrim($path, "/"));
}

function billing_einvoice_insert_incoming($id_school, $connector, $remote, $canonical = NULL, $id_organization_entry = 0, $id_seller_organization = 0, $status = "received")
{
    global $Database, $User;
    $id_school = (int)$id_school;
    $id_organization_entry = (int)$id_organization_entry;
    $id_seller_organization = (int)$id_seller_organization;
    $flow_id = trim((string)($remote["flow_id"] ?? ""));
    if ($flow_id == "" || !($connector instanceof BillingElectronicInvoiceConnector))
        return (0);
    $school_seller = billing_einvoice_school_seller($id_school);
    if ($school_seller == NULL)
        return (0);
    if (!is_array($canonical))
    {
        $canonical = [
            "schema" => "infosphere.billing.remote-flow",
            "schema_version" => 1,
            "direction" => "incoming",
            "flow_type" => "einvoice",
            "reference" => trim((string)($remote["name"] ?? $flow_id)),
            "remote" => $remote["metadata"] ?? [],
        ];
    }
    $json = billing_einvoice_json($canonical);
    $sha = hash("sha256", $json);
    $reference = trim((string)($canonical["reference"] ?? ($remote["name"] ?? $flow_id)));
    $issue = trim((string)($canonical["issue_date"] ?? ""));
    $due = trim((string)($canonical["due_date"] ?? ""));
    $currency = strtoupper(trim((string)($canonical["currency"] ?? "EUR")));
    $doc_type = (string)($canonical["document_type"] ?? "invoice");
    $provider = (string)$connector->key();
    $syntax = strtolower(trim((string)($remote["flow_syntax"] ?? "")));
    $filename = trim((string)($remote["name"] ?? ""));
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $sql = "INSERT INTO billing_electronic_document
        (id_school, direction, flow_type, document_type, source_type, source_id, local_reference, issue_date, due_date, currency,
         seller_organization_id, buyer_organization_id, buyer_user_id, status, provider_key, provider_document_id, transmission_id,
         canonical_json, canonical_sha256, raw_format, raw_filename, id_actor)
        VALUES
        ($id_school, 'incoming', 'einvoice', '".db_escape($doc_type)."', ".($id_organization_entry > 0 ? "'organization_account_entry'" : "NULL").", ".($id_organization_entry > 0 ? $id_organization_entry : "NULL").",
         '".db_escape($reference)."', ".($issue != "" ? "'".db_escape(db_form_date($issue))."'" : "NULL").", ".($due != "" ? "'".db_escape(db_form_date($due))."'" : "NULL").", '".db_escape($currency)."',
         ".($id_seller_organization > 0 ? $id_seller_organization : "NULL").", ".(int)$school_seller["organization"]["id"].", NULL, '".db_escape($status)."', '".db_escape($provider)."', '".db_escape($flow_id)."', '".db_escape($flow_id)."',
         '".db_escape($json)."', '$sha', '".db_escape($syntax)."', '".db_escape($filename)."', $actor)";
    if ($Database->query($sql) === NULL)
        return (0);
    return ((int)$Database->insert_id);
}

function billing_einvoice_readable_pdf_document($remote)
{
    $readable = $remote["readable"] ?? NULL;
    if (!is_array($readable) || empty($readable["content"]))
        return (NULL);
    $content = (string)$readable["content"];
    if (strlen($content) < 5 || substr($content, 0, 5) !== "%PDF-")
        return (NULL);
    return ([
        "content" => $content,
        "extension" => "pdf",
        "name" => basename((string)($readable["filename"] ?? "facture.pdf")),
    ]);
}

function billing_einvoice_status_from_reform_code($code, $default = "processing")
{
    $map = [
        "200" => "submitted", "201" => "issued", "202" => "received",
        "203" => "available", "204" => "taken_over", "205" => "accepted",
        "206" => "partially_accepted", "207" => "disputed", "208" => "suspended",
        "209" => "completed", "210" => "refused", "211" => "payment_sent",
        "212" => "paid", "213" => "rejected",
    ];
    return ($map[(string)$code] ?? $default);
}

function billing_einvoice_xml_first_local_value($xml, $tag)
{
    $tag = preg_quote((string)$tag, '/');
    if (preg_match('/<(?:[A-Za-z0-9_.-]+:)?'.$tag.'(?:\\s[^>]*)?>(.*?)<\\/(?:[A-Za-z0-9_.-]+:)?'.$tag.'>/si', (string)$xml, $m))
        return (trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_XML1, "UTF-8")));
    return ("");
}

function billing_einvoice_lifecycle_code_from_xml($xml)
{
    foreach (["ProcessConditionCode", "StatusCode"] as $tag)
    {
        $tag_q = preg_quote($tag, '/');
        if (preg_match_all('/<(?:[A-Za-z0-9_.-]+:)?'.$tag_q.'(?:\\s[^>]*)?>([^<]+)<\\//i', (string)$xml, $codes))
            foreach ($codes[1] as $candidate)
                if (preg_match('/^(20[0-9]|21[0-3])$/', trim((string)$candidate)))
                    return (trim((string)$candidate));
    }
    return ("");
}

function billing_einvoice_parse_lifecycle_cdar($xml)
{
    $xml = (string)$xml;
    if (trim($xml) == "")
        return (["ok" => false, "error" => "BillingElectronicEmptyFlow"]);
    $global_code = billing_einvoice_lifecycle_code_from_xml($xml);
    $items = [];
    if (preg_match_all('/<(?:[A-Za-z0-9_.-]+:)?ReferenceReferencedDocument\\b[^>]*>(.*?)<\\/(?:[A-Za-z0-9_.-]+:)?ReferenceReferencedDocument>/si', $xml, $blocks))
    {
        foreach ($blocks[1] as $block)
        {
            $reference = billing_einvoice_xml_first_local_value($block, "IssuerAssignedID");
            if ($reference == "") $reference = billing_einvoice_xml_first_local_value($block, "ID");
            $code = billing_einvoice_lifecycle_code_from_xml($block);
            if ($code == "") $code = $global_code;
            if ($reference != "" && $code != "")
                $items[] = ["reference" => $reference, "status_code" => $code, "status" => billing_einvoice_status_from_reform_code($code)];
        }
    }
    if (!count($items))
    {
        $reference = billing_einvoice_xml_first_local_value($xml, "IssuerAssignedID");
        if ($global_code == "")
            return (["ok" => false, "error" => "BillingElectronicLifecycleStatusMissing"]);
        if ($reference == "")
            return (["ok" => false, "error" => "BillingElectronicLifecycleReferenceMissing", "status_code" => $global_code]);
        $items[] = ["reference" => $reference, "status_code" => $global_code, "status" => billing_einvoice_status_from_reform_code($global_code)];
    }
    return (["ok" => true, "items" => $items]);
}

function billing_einvoice_apply_received_lifecycle($id_school, $connector, $remote)
{
    global $Database;
    $id_school = (int)$id_school;
    if (!($connector instanceof BillingElectronicInvoiceConnector) || !is_array($remote))
        return (["ok" => false, "error" => "BadRequest"]);
    $flow_id = trim((string)($remote["flow_id"] ?? ""));
    if ($flow_id == "" || empty($remote["content"]))
        return (["ok" => false, "error" => "BillingElectronicFlowIdMissing"]);
    $flow_sql = $Database->real_escape_string($flow_id);
    $known = db_select_one("
        billing_electronic_event.id
        FROM billing_electronic_event
        INNER JOIN billing_electronic_document ON billing_electronic_document.id = billing_electronic_event.id_document
        WHERE billing_electronic_document.id_school = $id_school
        AND billing_electronic_event.event_source = 'connector'
        AND billing_electronic_event.external_id = '$flow_sql'
    ");
    if ($known != NULL)
        return (["ok" => true, "known" => true, "updated_count" => 0, "unmatched_count" => 0]);
    $parsed = billing_einvoice_parse_lifecycle_cdar((string)$remote["content"]);
    if (empty($parsed["ok"]))
        return ($parsed);
    $updated_count = 0;
    $unmatched_count = 0;
    foreach ((array)$parsed["items"] as $item)
    {
        $reference_sql = $Database->real_escape_string((string)$item["reference"]);
        $document = db_select_one("
            * FROM billing_electronic_document
            WHERE id_school = $id_school
            AND direction = 'outgoing'
            AND local_reference = '$reference_sql'
            AND document_type IN ('invoice', 'credit_note')
            AND deleted IS NULL
            ORDER BY id DESC
        ");
        if ($document == NULL)
        {
            ++$unmatched_count;
            continue ;
        }
        $status = billing_einvoice_normalize_remote_status($item["status"], (string)$document["status"]);
        $status_sql = $Database->real_escape_string($status);
        $review_reset = in_array($status, ["rejected", "refused"], true) ? ", review_closed_at = NULL" : "";
        if ($Database->query("UPDATE billing_electronic_document SET status = '$status_sql'$review_reset, updated_at = NOW() WHERE id = ".(int)$document["id"]." AND deleted IS NULL") === NULL)
            return (["ok" => false, "error" => "CannotRegister"]);
        billing_einvoice_event((int)$document["id"], "reform_status_".(string)$item["status_code"], "connector", [
            "status" => $status,
            "status_code" => (string)$item["status_code"],
            "reference" => (string)$item["reference"],
            "metadata" => $remote["metadata"] ?? [],
        ], $flow_id);
        ++$updated_count;
    }
    return (["ok" => true, "updated" => $updated_count > 0, "updated_count" => $updated_count, "unmatched" => $unmatched_count > 0, "unmatched_count" => $unmatched_count]);
}

function billing_einvoice_receive_for_school($id_school)
{
    global $Database;
    $id_school = (int)$id_school;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
        return (["ok" => false, "error" => "Forbidden"]);
    $config_row = billing_einvoice_connector_config($id_school);
    if ($config_row == NULL || empty($config_row["enabled"]) || empty($config_row["connector_key"]))
        return (["ok" => false, "error" => "BillingElectronicNoConnector"]);
    $connector = billing_einvoice_connector((string)$config_row["connector_key"]);
    if ($connector == NULL || empty($connector->capabilities()["receive_einvoice"]))
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    $school = billing_einvoice_school_seller($id_school);
    if ($school == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    $cursor_row = db_select_one("electronic_invoice_receive_cursor as receive_cursor FROM school WHERE id = $id_school AND deleted IS NULL");
    $cursor = trim((string)($cursor_row["receive_cursor"] ?? ""));
    $result = $connector->receive_documents($id_school, $cursor, billing_einvoice_connector_configuration($config_row));
    if (!is_array($result) || empty($result["ok"]))
        return (is_array($result) ? $result : ["ok" => false, "error" => "BillingElectronicConnectorError"]);

    $stats = ["received" => 0, "imported" => 0, "review" => 0, "unparsed" => 0, "known" => 0, "errors" => 0,
              "lifecycle" => 0, "lifecycle_known" => 0, "lifecycle_unmatched" => 0];
    foreach ((array)($result["documents"] ?? []) as $remote)
    {
        $flow_id = trim((string)($remote["flow_id"] ?? ""));
        if ($flow_id == "")
        {
            ++$stats["errors"];
            continue ;
        }
        if (billing_einvoice_document_for_remote($id_school, $connector->key(), $flow_id) != NULL)
        {
            ++$stats["known"];
            continue ;
        }
        if (!empty($remote["error"]) || empty($remote["content"]))
        {
            ++$stats["errors"];
            continue ;
        }
        ++$stats["received"];
        $syntax = strtoupper(trim((string)($remote["flow_syntax"] ?? "")));
        $parse_content = (string)$remote["content"];
        if (is_array($remote["converted"] ?? NULL) && !empty($remote["converted"]["content"]))
            $parse_content = (string)$remote["converted"]["content"];
        $parsed = strpos(ltrim($parse_content), "<") === 0
            ? billing_einvoice_parse_ubl($parse_content, $remote)
            : ["ok" => false, "error" => "BillingElectronicUnsupportedIncomingSyntax"];

        $id_document = 0;
        $id_entry = 0;
        if (!empty($parsed["ok"]))
        {
            $canonical = $parsed["canonical"];
            $id_org = billing_einvoice_find_organization_for_party($canonical["seller"] ?? []);
            if ($id_org === NULL)
                $id_org = billing_einvoice_create_organization_for_party($canonical["seller"] ?? []);
            if (!$id_org)
            {
                ++$stats["errors"];
                continue ;
            }
            $gross = abs((int)($canonical["totals"]["gross"] ?? 0));
            if ($gross <= 0)
            {
                /* Preserve unusual/zero-value invoices without blocking the
                 * receive cursor.  They remain visible for manual review. */
                $id_document = billing_einvoice_insert_incoming($id_school, $connector, $remote, $canonical, 0, $id_org, "received_review");
                if (!$id_document)
                {
                    ++$stats["errors"];
                    continue ;
                }
                ++$stats["review"];
            }
            else
            {
            $movement = ($canonical["document_type"] ?? "invoice") == "credit_note" ? "credit" : "debit";
            $org = billing_einvoice_organization($id_org);
            $org_name = $org == NULL ? "Entreprise" : ((string)($org["legal_name"] ?: ($org["name"] ?: $org["codename"])));
            $label = ($movement == "credit" ? "Avoir électronique " : "Facture électronique ").$org_name;
            $id_entry = billing_add_organization_entry($id_school, $id_org, $movement, $gross, $canonical["issue_date"], $label, $canonical["reference"], "Reçue via facturation électronique · flux ".$flow_id);
            if (!$id_entry)
            {
                ++$stats["errors"];
                continue ;
            }
            $pdf = billing_einvoice_readable_pdf_document($remote);
            if ($pdf !== NULL && !billing_store_organization_entry_document($id_entry, $pdf))
            {
                billing_delete_organization_entry($id_entry);
                ++$stats["errors"];
                continue ;
            }
            $id_document = billing_einvoice_insert_incoming($id_school, $connector, $remote, $canonical, $id_entry, $id_org, "received");
            if (!$id_document)
            {
                billing_delete_organization_entry($id_entry);
                ++$stats["errors"];
                continue ;
            }
            ++$stats["imported"];
            }
        }
        else
        {
            $id_document = billing_einvoice_insert_incoming($id_school, $connector, $remote, NULL, 0, 0, "received_unparsed");
            if (!$id_document)
            {
                ++$stats["errors"];
                continue ;
            }
            ++$stats["unparsed"];
        }
        $extension = billing_einvoice_incoming_raw_extension($syntax, $remote["content_type"] ?? "", $remote["name"] ?? "");
        if (!billing_einvoice_store_incoming_raw($id_document, $id_school, $school["organization"], (string)$remote["content"], $extension))
        {
            /* Keep reception atomic: a failed raw archive must not leave a
             * half-imported invoice which would then be considered known. */
            $Database->query("UPDATE billing_electronic_document SET deleted = NOW(), updated_at = NOW() WHERE id = ".(int)$id_document." AND deleted IS NULL");
            if ($id_entry > 0)
                billing_delete_organization_entry($id_entry);
            ++$stats["errors"];
            continue ;
        }
        billing_einvoice_event($id_document, "received", "connector", $remote["metadata"] ?? [], $flow_id);
    }

    foreach ((array)($result["lifecycle"] ?? []) as $remote)
    {
        $lifecycle = billing_einvoice_apply_received_lifecycle($id_school, $connector, $remote);
        if (empty($lifecycle["ok"])) ++$stats["errors"];
        else if (!empty($lifecycle["known"])) ++$stats["lifecycle_known"];
        else
        {
            $stats["lifecycle"] += (int)($lifecycle["updated_count"] ?? 0);
            $stats["lifecycle_unmatched"] += (int)($lifecycle["unmatched_count"] ?? 0);
        }
    }

    if ($stats["errors"] == 0)
    {
        $new_cursor = trim((string)($result["cursor"] ?? $cursor));
        $Database->query("UPDATE school SET electronic_invoice_receive_cursor = '".db_escape($new_cursor)."', electronic_invoice_receive_at = NOW() WHERE id = $id_school AND deleted IS NULL");
    }
    $stats["ok"] = true;
    $stats["cursor_advanced"] = $stats["errors"] == 0;
    return ($stats);
}


function billing_einvoice_capability_for_flow($flow)
{
    if ($flow == "einvoice")
        return ("send_einvoice");
    if ($flow == "ereporting_transaction")
        return ("send_transaction_reporting");
    if ($flow == "ereporting_payment")
        return ("send_payment_reporting");
    if ($flow == "einvoice_lifecycle")
        return ("send_einvoice_lifecycle");
    return (NULL);
}

function billing_einvoice_transport_state($document)
{
    if (!is_array($document))
        return (["ready" => false, "reason" => "document"]);
    if (($document["direction"] ?? "outgoing") != "outgoing")
        return (["ready" => false, "reason" => "incoming"]);
    if (($document["flow_type"] ?? "") == "out_of_scope_exempt")
        return (["ready" => false, "reason" => "out_of_scope_exempt"]);
    $id_school = (int)($document["id_school"] ?? 0);
    $config = billing_einvoice_connector_config($id_school);
    if ($config == NULL || trim((string)($config["connector_key"] ?? "")) == "")
        return (["ready" => false, "reason" => "no_connector", "config" => $config]);
    if (empty($config["enabled"]))
        return (["ready" => false, "reason" => "connector_disabled", "config" => $config]);
    $connector = billing_einvoice_connector((string)$config["connector_key"]);
    if ($connector == NULL)
        return (["ready" => false, "reason" => "connector_missing", "config" => $config]);
    $capability = billing_einvoice_capability_for_flow((string)($document["flow_type"] ?? ""));
    $capabilities = $connector->capabilities();
    if ($capability == NULL || empty($capabilities[$capability]))
        return ([
            "ready" => false,
            "reason" => "capability",
            "capability" => $capability,
            "connector" => $connector,
            "config" => $config,
        ]);
    $validation = billing_einvoice_document_local_validation($document);
    if (empty($validation["ok"]))
        return ([
            "ready" => false,
            "reason" => "validation",
            "validation" => $validation,
            "connector" => $connector,
            "config" => $config,
        ]);
    return ([
        "ready" => true,
        "reason" => "ready",
        "capability" => $capability,
        "connector" => $connector,
        "config" => $config,
        "validation" => $validation,
    ]);
}

function billing_einvoice_prepare_transport_payload($document, $connector)
{
    if (!is_array($document) || !($connector instanceof BillingElectronicInvoiceConnector))
        return (["ok" => false, "error" => "BillingElectronicInvalidTransport"]);
    $canonical = json_decode((string)($document["canonical_json"] ?? ""), true);
    if (!is_array($canonical))
        return (["ok" => false, "error" => "BillingElectronicInvalidCanonical"]);

    /*
     * XP Z12-013 allows a B2C invoice to be handed to the PA as an invoice
     * flow with processingRule=B2C.  The PA then performs the regulatory
     * e-reporting/FRR aggregation.  We therefore keep the same structured UBL
     * invoice as the transport payload for B2B and B2C transactions.
     */
    $flow_type = (string)($document["flow_type"] ?? "");
    if (in_array($flow_type, ["ereporting_payment", "einvoice_lifecycle"], true))
    {
        $cdar = billing_einvoice_cdar_from_payment($canonical);
        if (empty($cdar["ok"]))
            return ($cdar);
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)$document["local_reference"]);
        if ($safe == "") $safe = "payment";
        return ([
            "ok" => true,
            "format" => "cdar",
            "content_type" => "application/xml; charset=UTF-8",
            "filename" => $safe."-CDV-212.xml",
            "content" => $cdar["content"],
            "canonical" => $canonical,
        ]);
    }
    if (!in_array($flow_type, ["einvoice", "ereporting_transaction"], true))
        return (["ok" => false, "error" => "BillingElectronicUnsupportedFlow"]);

    $format = strtolower(trim((string)$connector->preferred_format($document)));
    if ($format == "" || $format == "ubl")
    {
        $ubl = billing_einvoice_ubl_from_canonical($canonical);
        if (empty($ubl["ok"]))
            return ($ubl);
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)$document["local_reference"]);
        if ($safe == "") $safe = "invoice";
        return ([
            "ok" => true,
            "format" => "ubl",
            "content_type" => "application/xml; charset=UTF-8",
            "filename" => $safe.".xml",
            "content" => $ubl["content"],
            "canonical" => $canonical,
        ]);
    }
    return (["ok" => false, "error" => "BillingElectronicUnsupportedFormat", "format" => $format]);
}

function billing_einvoice_normalize_remote_status($status, $default = "submitted")
{
    $status = strtolower(trim((string)$status));
    $allowed = [
        "prepared", "submitted", "issued", "received", "delivered", "available",
        "taken_over", "accepted", "partially_accepted", "disputed", "suspended",
        "completed", "refused", "payment_sent", "paid", "rejected", "cancelled",
        "processing", "transport_error", "received_unparsed", "received_review",
        "received_manual",
    ];
    return (in_array($status, $allowed, true) ? $status : $default);
}

function billing_einvoice_transport_retry_configuration($configuration)
{
    $base = (int)($configuration["retry_base_minutes"] ?? 5);
    $max = (int)($configuration["retry_max_attempts"] ?? 6);
    if ($base < 1) $base = 1;
    if ($base > 1440) $base = 1440;
    if ($max < 1) $max = 1;
    if ($max > 20) $max = 20;
    return (["base_minutes" => $base, "max_attempts" => $max]);
}

function billing_einvoice_transport_begin($id_document)
{
    global $Database;
    $id_document = (int)$id_document;
    if ($id_document <= 0)
        return (false);
    return ($Database->query("
        UPDATE billing_electronic_document SET
            transport_attempts = transport_attempts + 1,
            last_transport_attempt_at = NOW(),
            updated_at = NOW()
        WHERE id = $id_document AND deleted IS NULL
    ") !== NULL);
}

function billing_einvoice_transport_failure($document, $error, $configuration)
{
    global $Database;
    if (!is_array($document))
        return (false);
    $id_document = (int)($document["id"] ?? 0);
    if ($id_document <= 0)
        return (false);
    $fresh = db_select_one("* FROM billing_electronic_document WHERE id = $id_document AND deleted IS NULL");
    $attempts = max(1, (int)($fresh["transport_attempts"] ?? ((int)($document["transport_attempts"] ?? 0) + 1)));
    $retry = billing_einvoice_transport_retry_configuration($configuration);
    $minutes = $retry["base_minutes"] * (2 ** max(0, min(8, $attempts - 1)));
    $minutes = min(1440, $minutes);
    $next = $attempts < $retry["max_attempts"]
        ? "DATE_ADD(NOW(), INTERVAL ".(int)$minutes." MINUTE)" : "NULL";
    $error_raw = substr(trim((string)$error), 0, 255);
    $error_sql = $Database->real_escape_string($error_raw);
    $ok = $Database->query("
        UPDATE billing_electronic_document SET
            status = 'transport_error',
            last_transport_error = '$error_sql',
            next_transport_retry_at = $next,
            updated_at = NOW()
        WHERE id = $id_document AND deleted IS NULL
    ") !== NULL;
    if ($ok)
        billing_einvoice_event($id_document, "transport_error", "connector", [
            "error" => $error_raw,
            "attempt" => $attempts,
            "next_retry_minutes" => $attempts < $retry["max_attempts"] ? $minutes : NULL,
            "retry_exhausted" => $attempts >= $retry["max_attempts"],
        ]);
    return ($ok);
}

function billing_einvoice_retry_for_school($id_school, $force = false)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (["ok" => false, "error" => "BadRequest"]);
    $config = billing_einvoice_connector_config($id_school);
    if ($config == NULL)
        return (["ok" => false, "error" => "BillingElectronicNoConnector"]);
    $configuration = billing_einvoice_connector_configuration($config);
    $retry = billing_einvoice_transport_retry_configuration($configuration);
    $due = $force ? "1" : "(next_transport_retry_at IS NULL OR next_transport_retry_at <= NOW())";
    $rows = db_select_all("
        *
        FROM billing_electronic_document
        WHERE id_school = $id_school
        AND direction = 'outgoing'
        AND status = 'transport_error'
        AND transport_attempts < ".(int)$retry["max_attempts"]."
        AND $due
        AND deleted IS NULL
        ORDER BY COALESCE(next_transport_retry_at, updated_at) ASC, id ASC
    ");
    $ret = ["ok" => true, "retried" => 0, "sent" => 0, "errors" => 0];
    foreach ($rows as $row)
    {
        $ret["retried"]++;
        $result = billing_einvoice_send_document((int)$row["id"]);
        if (!empty($result["ok"])) $ret["sent"]++;
        else $ret["errors"]++;
    }
    return ($ret);
}

function billing_einvoice_apply_connector_result($document, $connector, $result, $default_status = "submitted")
{
    global $Database;

    if (!is_array($document) || !($connector instanceof BillingElectronicInvoiceConnector) || !is_array($result) || empty($result["ok"]))
        return (false);
    $id_document = (int)$document["id"];
    $provider_raw = (string)$connector->key();
    $provider_document_id_raw = trim((string)($result["provider_document_id"] ?? ($document["provider_document_id"] ?? "")));
    $transmission_id_raw = trim((string)($result["transmission_id"] ?? ($document["transmission_id"] ?? "")));
    $provider = $Database->real_escape_string($provider_raw);
    $provider_document_id = $Database->real_escape_string($provider_document_id_raw);
    $transmission_id = $Database->real_escape_string($transmission_id_raw);
    $status = billing_einvoice_normalize_remote_status($result["status"] ?? "", $default_status);
    $status_sql = $Database->real_escape_string($status);
    $review_reset_sql = in_array($status, ["rejected", "refused"], true) ? "\n            review_closed_at = NULL," : "";
    if ($Database->query("\n        UPDATE billing_electronic_document SET\n            provider_key = '$provider',\n            provider_document_id = ".($provider_document_id == "" ? "NULL" : "'$provider_document_id'").",\n            transmission_id = ".($transmission_id == "" ? "NULL" : "'$transmission_id'").",\n            status = '$status_sql',$review_reset_sql\n            last_transport_error = NULL,\n            next_transport_retry_at = NULL,\n            updated_at = NOW()\n        WHERE id = $id_document AND deleted IS NULL\n    ") === NULL)
        return (false);
    $event_code = trim((string)($result["event_code"] ?? $status));
    if ($event_code == "") $event_code = $status;
    $payload = [
        "connector" => $provider_raw,
        "provider_document_id" => $provider_document_id_raw,
        "transmission_id" => $transmission_id_raw,
        "status" => $status,
    ];
    if (isset($result["message"]))
        $payload["message"] = (string)$result["message"];
    if (isset($result["external_status_code"]) && $result["external_status_code"] !== NULL)
        $payload["external_status_code"] = (string)$result["external_status_code"];
    billing_einvoice_event($id_document, $event_code, "connector", $payload, $provider_document_id_raw);
    return (true);
}

function billing_einvoice_send_document($id_document)
{
    $document = billing_einvoice_document((int)$id_document);
    if ($document == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    if (!in_array((string)$document["status"], ["prepared", "draft", "transport_error"], true))
        return (["ok" => false, "error" => "BillingElectronicAlreadySubmitted"]);
    $state = billing_einvoice_transport_state($document);
    if (empty($state["ready"]))
        return (["ok" => false, "error" => "BillingElectronicTransportNotReady", "reason" => $state["reason"] ?? "unknown"]);
    $connector = $state["connector"];
    $config = billing_einvoice_connector_configuration($state["config"]);
    $payload = billing_einvoice_prepare_transport_payload($document, $connector);
    if (empty($payload["ok"]))
        return ($payload);
    $canonical = $payload["canonical"];
    if (!billing_einvoice_transport_begin((int)$document["id"]))
        return (["ok" => false, "error" => "CannotRegister"]);

    if (($document["flow_type"] ?? "") == "einvoice")
        $result = $connector->send_document($document, $canonical, $payload, $config);
    else if (($document["flow_type"] ?? "") == "ereporting_transaction")
        $result = $connector->report_transaction($document, $canonical, $payload, $config);
    else if (($document["flow_type"] ?? "") == "ereporting_payment")
        $result = $connector->report_payment($document, $canonical, $payload, $config);
    else if (($document["flow_type"] ?? "") == "einvoice_lifecycle")
        $result = $connector->send_lifecycle($document, $canonical, $payload, $config);
    else
        return (["ok" => false, "error" => "BillingElectronicUnsupportedFlow"]);

    if (!is_array($result) || empty($result["ok"]))
    {
        $error = is_array($result) ? ($result["error"] ?? "BillingElectronicConnectorError") : "BillingElectronicConnectorError";
        billing_einvoice_transport_failure($document, (string)$error, $config);
        return (["ok" => false, "error" => $error]);
    }
    if (!billing_einvoice_apply_connector_result($document, $connector, $result, "submitted"))
        return (["ok" => false, "error" => "CannotRegister"]);
    return (["ok" => true, "status" => billing_einvoice_normalize_remote_status($result["status"] ?? "", "submitted")]);
}

function billing_einvoice_refresh_document($id_document)
{
    $document = billing_einvoice_document((int)$id_document);
    if ($document == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    $config = billing_einvoice_connector_config((int)$document["id_school"]);
    if ($config == NULL || empty($config["enabled"]) || trim((string)($config["connector_key"] ?? "")) == "")
        return (["ok" => false, "error" => "BillingElectronicNoConnector"]);
    $connector = billing_einvoice_connector((string)$config["connector_key"]);
    if ($connector == NULL)
        return (["ok" => false, "error" => "BillingElectronicConnectorMissing"]);
    $capabilities = $connector->capabilities();
    if (empty($capabilities["refresh_status"]))
        return (["ok" => false, "error" => "BillingElectronicConnectorUnsupported"]);
    $result = $connector->refresh_document($document, billing_einvoice_connector_configuration($config));
    if (!is_array($result) || empty($result["ok"]))
        return (["ok" => false, "error" => is_array($result) ? ($result["error"] ?? "BillingElectronicConnectorError") : "BillingElectronicConnectorError"]);
    if (!billing_einvoice_apply_connector_result($document, $connector, $result, (string)$document["status"]))
        return (["ok" => false, "error" => "CannotRegister"]);
    return (["ok" => true, "status" => billing_einvoice_normalize_remote_status($result["status"] ?? "", (string)$document["status"])]);
}

function billing_einvoice_review_documents()
{
    return (db_select_all("
        billing_electronic_document.*,
        school.codename as school_codename,
        seller.codename as seller_codename,
        seller.name as seller_name,
        seller.legal_name as seller_legal_name
        FROM billing_electronic_document
        LEFT JOIN school ON school.id = billing_electronic_document.id_school
        LEFT JOIN organization seller ON seller.id = billing_electronic_document.seller_organization_id
        WHERE billing_electronic_document.deleted IS NULL
        AND billing_electronic_document.review_closed_at IS NULL
        AND (
            billing_electronic_document.status IN ('received_review', 'received_unparsed', 'transport_error', 'rejected', 'refused')
            OR billing_electronic_document.review_note IS NOT NULL
            OR billing_electronic_document.regulatory_validation_status = 'invalid'
        )
        ".billing_school_filter("billing_electronic_document")."
        ORDER BY COALESCE(billing_electronic_document.updated_at, billing_electronic_document.created_at) DESC,
                 billing_electronic_document.id DESC
    "));
}

function billing_einvoice_mark_reviewed($id_document, $note = "")
{
    global $Database;
    $document = billing_einvoice_document((int)$id_document);
    if ($document == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    if (!is_billing_manager_for_school((int)$document["id_school"]))
        return (["ok" => false, "error" => "Forbidden"]);
    $note = trim((string)$note);
    if (strlen($note) > 4000)
        $note = substr($note, 0, 4000);
    $note_sql = $Database->real_escape_string($note);
    $status = (string)($document["status"] ?? "");
    $status_sql = in_array($status, ["received_review", "received_unparsed"], true)
        ? "status = 'received_manual'," : "";
    if ($Database->query("
        UPDATE billing_electronic_document SET
            $status_sql
            review_note = ".($note == "" ? "review_note" : "'$note_sql'").",
            review_closed_at = NOW(),
            updated_at = NOW()
        WHERE id = ".(int)$document["id"]." AND deleted IS NULL
    ") === NULL)
        return (["ok" => false, "error" => "CannotRegister"]);
    billing_einvoice_event((int)$document["id"], "review_closed", "local", ["note" => $note]);
    return (["ok" => true]);
}

function billing_einvoice_regulatory_payload($document)
{
    if (!is_array($document))
        return (["ok" => false, "error" => "NotFound"]);
    $canonical = json_decode((string)($document["canonical_json"] ?? ""), true);
    if (!is_array($canonical))
        return (["ok" => false, "error" => "BillingElectronicInvalidCanonical"]);
    if (in_array(($document["flow_type"] ?? ""), ["ereporting_payment", "einvoice_lifecycle"], true))
    {
        $ret = billing_einvoice_cdar_from_payment($canonical);
        if (empty($ret["ok"])) return ($ret);
        $ret["format"] = "cdar";
        return ($ret);
    }
    $ret = billing_einvoice_ubl_from_canonical($canonical);
    if (empty($ret["ok"])) return ($ret);
    $ret["format"] = "ubl";
    return ($ret);
}

function billing_einvoice_run_regulatory_validation($id_document)
{
    global $Database;
    $document = billing_einvoice_document((int)$id_document);
    if ($document == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    $payload = billing_einvoice_regulatory_payload($document);
    if (empty($payload["ok"]))
        return ($payload);

    $command = trim((string)getenv("INFOSPHERE_EINVOICE_VALIDATOR"));
    if ($command == "")
    {
        $status = "unconfigured";
        $details = "Aucun validateur réglementaire externe n'est configuré (INFOSPHERE_EINVOICE_VALIDATOR).";
        $exit_code = NULL;
    }
    else if (strpos($command, "{file}") === false || !function_exists("proc_open"))
    {
        $status = "unavailable";
        $details = strpos($command, "{file}") === false
            ? "La commande de validation doit contenir le marqueur {file}."
            : "proc_open() n'est pas disponible sur ce serveur.";
        $exit_code = NULL;
    }
    else
    {
        $tmp = tempnam(sys_get_temp_dir(), "infosphere-einvoice-");
        if ($tmp === false || file_put_contents($tmp, (string)$payload["content"]) === false)
            return (["ok" => false, "error" => "CannotWriteFile"]);
        $resolved = str_replace(
            ["{file}", "{format}"],
            [escapeshellarg($tmp), escapeshellarg((string)($payload["format"] ?? "xml"))],
            $command
        );
        $pipes = [];
        $process = @proc_open($resolved, [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ], $pipes);
        $stdout = "";
        $stderr = "";
        if (is_resource($process))
        {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
            $exit_code = proc_close($process);
            $status = $exit_code === 0 ? "valid" : "invalid";
            $details = trim((string)$stdout."\n".(string)$stderr);
            if ($details == "")
                $details = $status == "valid" ? "Validation externe réussie." : "Le validateur externe a refusé le document.";
        }
        else
        {
            $exit_code = NULL;
            $status = "unavailable";
            $details = "Impossible de lancer le validateur externe.";
        }
        @unlink($tmp);
    }

    if (strlen($details) > 50000)
        $details = substr($details, 0, 50000);
    $status_sql = $Database->real_escape_string($status);
    $details_sql = $Database->real_escape_string($details);
    if ($Database->query("
        UPDATE billing_electronic_document SET
            regulatory_validation_status = '$status_sql',
            regulatory_validation_at = NOW(),
            regulatory_validation_details = '$details_sql',
            review_closed_at = ".($status == "invalid" ? "NULL" : "review_closed_at").",
            updated_at = NOW()
        WHERE id = ".(int)$document["id"]." AND deleted IS NULL
    ") === NULL)
        return (["ok" => false, "error" => "CannotRegister"]);
    billing_einvoice_event((int)$document["id"], "regulatory_validation_".$status, "validator", [
        "status" => $status,
        "exit_code" => $exit_code,
        "details" => $details,
    ]);
    return (["ok" => true, "status" => $status, "details" => $details]);
}

function billing_einvoice_test_connector($id_school)
{
    $id_school = (int)$id_school;
    $config = billing_einvoice_connector_config($id_school);
    if ($config == NULL || empty($config["enabled"]) || trim((string)($config["connector_key"] ?? "")) == "")
        return (["ok" => false, "error" => "BillingElectronicNoConnector"]);
    $connector = billing_einvoice_connector((string)$config["connector_key"]);
    if ($connector == NULL)
        return (["ok" => false, "error" => "BillingElectronicConnectorMissing"]);
    $errors = $connector->validate_configuration(billing_einvoice_connector_configuration($config));
    if (is_array($errors) && count($errors))
        return (["ok" => false, "error" => "BillingElectronicConnectorConfiguration", "details" => $errors]);
    $result = $connector->healthcheck(billing_einvoice_connector_configuration($config));
    if (!is_array($result))
        return (["ok" => false, "error" => "BillingElectronicConnectorError"]);
    return ($result);
}

function billing_einvoice_transport_reason_label($reason)
{
    $labels = [
        "no_connector" => "Aucun connecteur",
        "connector_disabled" => "Connecteur désactivé",
        "connector_missing" => "Module de connecteur absent",
        "capability" => "Flux non pris en charge",
        "validation" => "Document incomplet",
        "ready" => "Prêt à transmettre",
        "incoming" => "Document reçu",
        "out_of_scope_exempt" => "Hors champ · exonération art. 261",
    ];
    return ($labels[$reason] ?? (string)$reason);
}

function billing_einvoice_flow_label($flow)
{
    if ($flow == "einvoice")
        return ("e-invoicing B2B");
    if ($flow == "ereporting_transaction")
        return ("e-reporting transaction");
    if ($flow == "ereporting_payment")
        return ("e-reporting encaissement B2C");
    if ($flow == "einvoice_lifecycle")
        return ("cycle de vie B2B · encaissée");
    if ($flow == "out_of_scope_exempt")
        return ("hors champ · exonération art. 261");
    return ((string)$flow);
}

function billing_einvoice_status_label($status)
{
    $labels = [
        "draft" => "Brouillon",
        "prepared" => "Préparée localement",
        "submitted" => "Déposée",
        "issued" => "Émise",
        "received" => "Reçue",
        "delivered" => "Mise à disposition",
        "available" => "Mise à disposition",
        "taken_over" => "Prise en charge",
        "accepted" => "Acceptée",
        "partially_accepted" => "Partiellement acceptée",
        "disputed" => "Litige",
        "completed" => "Traitement terminé",
        "refused" => "Refusée",
        "payment_sent" => "Paiement transmis",
        "rejected" => "Rejetée",
        "paid" => "Encaissée",
        "processing" => "En traitement",
        "transport_error" => "Erreur de transport",
        "suspended" => "Suspendue",
        "cancelled" => "Annulée",
        "received_unparsed" => "Reçue · non interprétée",
        "received_review" => "Reçue · à contrôler",
        "received_manual" => "Reçue · contrôlée",
    ];
    return ($labels[$status] ?? (string)$status);
}

/* Load optional provider adapters only after the provider-neutral API exists. */
billing_einvoice_load_connectors();
