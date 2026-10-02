<?php

/*
 * Provider-neutral implementation of the mandatory Flow API defined by
 * XP Z12-013.  It deliberately contains no platform name or vendor SDK.
 *
 * Credentials are not stored directly in the school row: the configuration
 * references either an environment variable or a server-side file containing
 * the OAuth2 client secret.
 */
class BillingElectronicInvoiceXpZ12013Connector extends BillingElectronicInvoiceConnector
{
    public function key()
    {
        return ("xpz12013");
    }

    public function label()
    {
        return ("API standard XP Z12-013");
    }

    public function capabilities()
    {
        return ([
            "send_einvoice" => true,
            "receive_einvoice" => true,
            // A B2C invoice is sent with processingRule=B2C. The PA is then
            // responsible for constituting/aggregating the regulatory FRR.
            "send_transaction_reporting" => true,
            "send_payment_reporting" => true,
            "send_einvoice_lifecycle" => true,
            "refresh_status" => true,
        ]);
    }

    public function supported_formats()
    {
        return (["ubl"]);
    }

    public function configuration_schema()
    {
        return ([
            ["name" => "token_url", "label" => "Token URL OAuth2", "type" => "url", "required" => true],
            ["name" => "client_id", "label" => "Client ID", "type" => "text", "required" => true],
            ["name" => "client_secret_env", "label" => "Variable d'environnement du Client Secret", "type" => "text", "required" => false],
            ["name" => "client_secret_file", "label" => "Fichier serveur du Client Secret", "type" => "text", "required" => false],
            ["name" => "organisation_id", "label" => "Organisation-Id", "type" => "text", "required" => false],
            ["name" => "oauth_scope", "label" => "Scope OAuth2", "type" => "text", "required" => false],
            ["name" => "receive_initial_days", "label" => "Historique initial (jours)", "type" => "number", "required" => false],
            ["name" => "receive_delay_minutes", "label" => "Marge de synchronisation (minutes)", "type" => "number", "required" => false],
            ["name" => "retry_base_minutes", "label" => "Délai initial de reprise (minutes)", "type" => "number", "required" => false],
            ["name" => "retry_max_attempts", "label" => "Nombre maximal de tentatives", "type" => "number", "required" => false],
        ]);
    }

    public function validate_configuration($configuration)
    {
        $errors = [];
        $endpoint = trim((string)($configuration["endpoint"] ?? ""));
        $token_url = trim((string)($configuration["token_url"] ?? ""));
        $environment = strtolower(trim((string)($configuration["environment"] ?? "test")));
        if (!$this->valid_url($endpoint, $environment == "production"))
            $errors[] = "endpoint";
        if (!$this->valid_url($token_url, $environment == "production"))
            $errors[] = "token_url";
        if (trim((string)($configuration["client_id"] ?? "")) == "")
            $errors[] = "client_id";
        if (trim((string)($configuration["client_secret_env"] ?? "")) == "" &&
            trim((string)($configuration["client_secret_file"] ?? "")) == "")
            $errors[] = "client_secret_source";
        $auth = strtolower(trim((string)($configuration["oauth_client_auth"] ?? "body")));
        if ($auth != "" && !in_array($auth, ["body", "basic"], true))
            $errors[] = "oauth_client_auth";
        return ($errors);
    }

    private function valid_url($url, $https_required)
    {
        if ($url == "" || filter_var($url, FILTER_VALIDATE_URL) === false)
            return (false);
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ["http", "https"], true))
            return (false);
        return (!$https_required || $scheme == "https");
    }

    private function secret($configuration)
    {
        $env = trim((string)($configuration["client_secret_env"] ?? ""));
        if ($env != "")
        {
            $value = getenv($env);
            if ($value !== false && trim((string)$value) != "")
                return (trim((string)$value));
        }
        $file = trim((string)($configuration["client_secret_file"] ?? ""));
        if ($file != "" && is_file($file) && is_readable($file))
        {
            $value = @file_get_contents($file);
            if ($value !== false && trim((string)$value) != "")
                return (trim((string)$value));
        }
        return (NULL);
    }

    private function endpoint($configuration, $path)
    {
        return (rtrim(trim((string)($configuration["endpoint"] ?? "")), "/")."/".ltrim($path, "/"));
    }

    private function request($method, $url, $headers = [], $body = NULL, $timeout = 30)
    {
        $method = strtoupper((string)$method);
        $lines = [];
        $has_accept = false;
        foreach ($headers as $name => $value)
        {
            if (strtolower((string)$name) == "accept")
                $has_accept = true;
            $lines[] = $name.": ".$value;
        }
        if (!$has_accept)
            array_unshift($lines, "Accept: application/json");
        $options = [
            "http" => [
                "method" => $method,
                "ignore_errors" => true,
                "timeout" => max(1, (int)$timeout),
                "header" => implode("\r\n", $lines)."\r\n",
            ],
        ];
        if ($body !== NULL)
            $options["http"]["content"] = $body;
        $context = stream_context_create($options);
        $content = @file_get_contents($url, false, $context);
        $response_headers = $http_response_header ?? [];
        $status = 0;
        if (isset($response_headers[0]) && preg_match('/\s([0-9]{3})(?:\s|$)/', $response_headers[0], $m))
            $status = (int)$m[1];
        if ($content === false && $status == 0)
            return (["ok" => false, "error" => "BillingElectronicHttpError", "http_status" => 0]);
        $json = json_decode((string)$content, true);
        return ([
            "ok" => ($status >= 200 && $status < 300),
            "http_status" => $status,
            "headers" => $response_headers,
            "content" => $content === false ? "" : (string)$content,
            "json" => is_array($json) ? $json : NULL,
        ]);
    }

    private function access_token($configuration)
    {
        $secret = $this->secret($configuration);
        if ($secret === NULL)
            return (["ok" => false, "error" => "BillingElectronicMissingClientSecret"]);
        $client_id = trim((string)($configuration["client_id"] ?? ""));
        $token_url = trim((string)($configuration["token_url"] ?? ""));
        $scope = trim((string)($configuration["oauth_scope"] ?? ""));
        $auth = strtolower(trim((string)($configuration["oauth_client_auth"] ?? "body")));
        if ($auth == "") $auth = "body";
        $params = ["grant_type" => "client_credentials"];
        $headers = ["Content-Type" => "application/x-www-form-urlencoded"];
        if ($scope != "") $params["scope"] = $scope;
        if ($auth == "basic")
            $headers["Authorization"] = "Basic ".base64_encode($client_id.":".$secret);
        else
        {
            $params["client_id"] = $client_id;
            $params["client_secret"] = $secret;
        }
        $response = $this->request("POST", $token_url, $headers, http_build_query($params, '', '&', PHP_QUERY_RFC3986), 20);
        if (empty($response["ok"]) || !is_array($response["json"]))
            return (["ok" => false, "error" => "BillingElectronicOAuthError", "http_status" => $response["http_status"] ?? 0]);
        $token = trim((string)($response["json"]["access_token"] ?? ""));
        if ($token == "")
            return (["ok" => false, "error" => "BillingElectronicOAuthTokenMissing"]);
        return (["ok" => true, "token" => $token]);
    }

    private function authenticated_headers($configuration)
    {
        $token = $this->access_token($configuration);
        if (empty($token["ok"]))
            return ($token);
        $headers = ["Authorization" => "Bearer ".$token["token"]];
        $organisation = trim((string)($configuration["organisation_id"] ?? ""));
        if ($organisation != "")
            $headers["Organisation-Id"] = $organisation;
        return (["ok" => true, "headers" => $headers]);
    }

    private function multipart($flow_info, $payload, $configuration)
    {
        $boundary = "--------------------------infosphere".bin2hex(random_bytes(12));
        $flow_field = trim((string)($configuration["flow_info_field"] ?? "flowInfo"));
        $file_field = trim((string)($configuration["file_field"] ?? "file"));
        if ($flow_field == "") $flow_field = "flowInfo";
        if ($file_field == "") $file_field = "file";
        $filename = str_replace(["\r", "\n", '"'], "_", (string)($payload["filename"] ?? "flow.bin"));
        $body = "--$boundary\r\n";
        $body .= 'Content-Disposition: form-data; name="'.$flow_field.'"'."\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= billing_einvoice_json($flow_info)."\r\n";
        $body .= "--$boundary\r\n";
        $body .= 'Content-Disposition: form-data; name="'.$file_field.'"; filename="'.$filename.'"'."\r\n";
        $body .= "Content-Type: ".($payload["content_type"] ?? "application/octet-stream")."\r\n\r\n";
        $body .= (string)($payload["content"] ?? "")."\r\n";
        $body .= "--$boundary--\r\n";
        return (["content_type" => "multipart/form-data; boundary=$boundary", "body" => $body]);
    }

    public function healthcheck($configuration)
    {
        $errors = $this->validate_configuration($configuration);
        if (count($errors))
            return (["ok" => false, "error" => "BillingElectronicConnectorConfiguration", "details" => $errors]);
        $auth = $this->authenticated_headers($configuration);
        if (empty($auth["ok"]))
            return ($auth);
        $response = $this->request("GET", $this->endpoint($configuration, "healthcheck"), $auth["headers"], NULL, 15);
        if (empty($response["ok"]))
            return (["ok" => false, "error" => "BillingElectronicHealthcheckFailed", "http_status" => $response["http_status"] ?? 0]);
        return (["ok" => true, "message" => "API XP Z12-013 accessible", "http_status" => $response["http_status"]]);
    }


    private function find_flow_by_tracking($tracking, $configuration, $headers = NULL)
    {
        if ($headers === NULL)
        {
            $auth = $this->authenticated_headers($configuration);
            if (empty($auth["ok"]))
                return ($auth);
            $headers = $auth["headers"];
        }
        $headers["Content-Type"] = "application/json; charset=UTF-8";
        $criteria = ["trackingId" => trim((string)$tracking)];
        $response = $this->request("POST", $this->endpoint($configuration, "search"), $headers, billing_einvoice_json($criteria), 30);
        if (empty($response["ok"]) || !is_array($response["json"]))
            return (["ok" => false, "error" => "BillingElectronicFlowSearchFailed", "http_status" => $response["http_status"] ?? 0]);
        foreach ($this->search_items($response["json"]) as $item)
            if (is_array($item) && trim((string)($item["trackingId"] ?? "")) === trim((string)$tracking) && trim((string)($item["flowId"] ?? "")) != "")
                return (["ok" => true, "found" => true, "flow" => $item]);
        return (["ok" => true, "found" => false]);
    }

    private function submit_flow($document, $payload, $configuration, $flow_info, $event_code, $message)
    {
        if (!is_array($payload) || empty($payload["content"]))
            return (["ok" => false, "error" => "BillingElectronicEmptyFlow"]);
        if (strlen((string)$payload["content"]) > 100 * 1024 * 1024)
            return (["ok" => false, "error" => "BillingElectronicFlowTooLarge"]);
        $auth = $this->authenticated_headers($configuration);
        if (empty($auth["ok"]))
            return ($auth);
        $sha256 = hash("sha256", (string)$payload["content"]);
        $kind = strtolower((string)($flow_info["processingRule"] ?? ($flow_info["flowSyntax"] ?? "flow")));
        $tracking = "infosphere-".(int)($document["id"] ?? 0)."-".$kind."-".substr($sha256, 0, 12);
        $flow_info["trackingId"] = $tracking;
        $flow_info["name"] = (string)($payload["filename"] ?? "flow.xml");
        $flow_info["sha256"] = $sha256;

        /* POST /flows is asynchronous.  A transport timeout can therefore
         * happen after the PA accepted the flow.  Search the stable
         * trackingId before every POST so a retry never creates a second
         * business flow merely because the first HTTP response was lost. */
        $existing = $this->find_flow_by_tracking($tracking, $configuration, $auth["headers"]);
        if (empty($existing["ok"]))
            return ($existing);
        if (!empty($existing["found"]))
        {
            $flow = $existing["flow"];
            $flow_id = trim((string)$flow["flowId"]);
            return ([
                "ok" => true,
                "provider_document_id" => $flow_id,
                "transmission_id" => $flow_id,
                "status" => "submitted",
                "event_code" => "flow_recovered_by_tracking",
                "message" => "Flux déjà présent sur la plateforme, retrouvé par trackingId",
                "tracking_id" => $tracking,
            ]);
        }

        $multipart = $this->multipart($flow_info, $payload, $configuration);
        $headers = $auth["headers"];
        $headers["Content-Type"] = $multipart["content_type"];
        $response = $this->request("POST", $this->endpoint($configuration, "flows"), $headers, $multipart["body"], 60);
        if (empty($response["ok"]) || !is_array($response["json"]))
        {
            $recovery = $this->find_flow_by_tracking($tracking, $configuration, $auth["headers"]);
            if (!empty($recovery["ok"]) && !empty($recovery["found"]))
            {
                $flow_id = trim((string)$recovery["flow"]["flowId"]);
                return ([
                    "ok" => true,
                    "provider_document_id" => $flow_id,
                    "transmission_id" => $flow_id,
                    "status" => "submitted",
                    "event_code" => "flow_recovered_after_transport_error",
                    "message" => "Réponse HTTP perdue, flux retrouvé par trackingId",
                    "tracking_id" => $tracking,
                ]);
            }
            return (["ok" => false, "error" => "BillingElectronicFlowRejected", "http_status" => $response["http_status"] ?? 0, "tracking_id" => $tracking]);
        }
        $remote = $response["json"];
        $flow_id = trim((string)($remote["flowId"] ?? ""));
        if ($flow_id == "")
            return (["ok" => false, "error" => "BillingElectronicFlowIdMissing", "tracking_id" => $tracking]);
        $remote_sha = strtolower(trim((string)($remote["sha256"] ?? "")));
        if ($remote_sha != "" && !hash_equals(strtolower($sha256), $remote_sha))
            return (["ok" => false, "error" => "BillingElectronicChecksumMismatch", "tracking_id" => $tracking]);
        return ([
            "ok" => true,
            "provider_document_id" => $flow_id,
            "transmission_id" => $flow_id,
            "status" => "submitted",
            "event_code" => $event_code,
            "message" => $message,
            "tracking_id" => $tracking,
        ]);
    }

    private function submit_invoice_flow($document, $canonical, $payload, $configuration, $processing_rule)
    {
        $customization = (string)($canonical["profile"]["customization_id"] ?? ($canonical["customization_id"] ?? ""));
        $profile = stripos($customization, "extended-ctc-fr") !== false ? "Extended-CTC-FR" : "CIUS";
        $flow_info = [
            "processingRule" => $processing_rule,
            "flowSyntax" => "UBL",
            "flowProfile" => $profile,
        ];
        $is_b2c = strtolower((string)$processing_rule) == "b2c";
        return ($this->submit_flow(
            $document, $payload, $configuration, $flow_info,
            $is_b2c ? "b2c_flow_submitted" : "flow_submitted",
            $is_b2c ? "Facture B2C déposée via XP Z12-013 pour e-reporting" : "Flux déposé via XP Z12-013"
        ));
    }

    public function send_document($document, $canonical, $payload, $configuration)
    {
        return ($this->submit_invoice_flow($document, $canonical, $payload, $configuration, "B2B"));
    }

    public function report_transaction($document, $canonical, $payload, $configuration)
    {
        return ($this->submit_invoice_flow($document, $canonical, $payload, $configuration, "B2C"));
    }

    public function report_payment($document, $canonical, $payload, $configuration)
    {
        return ($this->submit_flow(
            $document, $payload, $configuration,
            ["processingRule" => "B2C", "flowSyntax" => "CDAR"],
            "payment_cdar_212_submitted",
            "Encaissement transmis comme statut CDAR 212"
        ));
    }

    public function send_lifecycle($document, $canonical, $payload, $configuration)
    {
        return ($this->submit_flow(
            $document, $payload, $configuration,
            ["processingRule" => "B2B", "flowSyntax" => "CDAR"],
            "invoice_lifecycle_212_submitted",
            "Statut B2B Encaissée (212) transmis"
        ));
    }

    private function response_header($headers, $name)
    {
        $name = strtolower((string)$name);
        foreach ((array)$headers as $line)
        {
            $pos = strpos((string)$line, ":");
            if ($pos === false)
                continue ;
            if (strtolower(trim(substr($line, 0, $pos))) == $name)
                return (trim(substr($line, $pos + 1)));
        }
        return ("");
    }

    private function is_list_array($value)
    {
        if (!is_array($value))
            return (false);
        $expected = 0;
        foreach (array_keys($value) as $key)
            if ($key !== $expected++)
                return (false);
        return (true);
    }

    private function search_items($json)
    {
        if (!is_array($json))
            return ([]);
        if ($this->is_list_array($json))
            return ($json);
        foreach (["flows", "items", "results", "content", "data"] as $key)
            if (isset($json[$key]) && is_array($json[$key]))
            {
                if ($this->is_list_array($json[$key]))
                    return ($json[$key]);
                $nested = $this->search_items($json[$key]);
                if (count($nested))
                    return ($nested);
            }
        return ([]);
    }

    private function get_flow_component($flow_id, $doc_type, $configuration, $accept)
    {
        $auth = $this->authenticated_headers($configuration);
        if (empty($auth["ok"]))
            return ($auth);
        $headers = $auth["headers"];
        $headers["Accept"] = $accept;
        $url = $this->endpoint($configuration, "flows/".rawurlencode($flow_id))."?docType=".rawurlencode($doc_type);
        $response = $this->request("GET", $url, $headers, NULL, 60);
        if (empty($response["ok"]))
            return (["ok" => false, "error" => "BillingElectronicFlowDownloadFailed", "http_status" => $response["http_status"] ?? 0]);
        $content_type = $this->response_header($response["headers"] ?? [], "Content-Type");
        $disposition = $this->response_header($response["headers"] ?? [], "Content-Disposition");
        $filename = "";
        if ($disposition != "" && preg_match("/filename\\*?=(?:UTF-8'')?[\"']?([^\"';]+)/i", $disposition, $m))
            $filename = rawurldecode(trim($m[1]));
        return ([
            "ok" => true,
            "content" => (string)($response["content"] ?? ""),
            "content_type" => $content_type,
            "filename" => $filename,
        ]);
    }

    public function receive_documents($id_school, $cursor, $configuration)
    {
        $errors = $this->validate_configuration($configuration);
        if (count($errors))
            return (["ok" => false, "error" => "BillingElectronicConnectorConfiguration", "details" => $errors]);
        $auth = $this->authenticated_headers($configuration);
        if (empty($auth["ok"]))
            return ($auth);

        $delay = (int)($configuration["receive_delay_minutes"] ?? 15);
        if ($delay < 0) $delay = 0;
        if ($delay > 1440) $delay = 1440;
        $initial_days = (int)($configuration["receive_initial_days"] ?? 30);
        if ($initial_days < 1) $initial_days = 30;
        if ($initial_days > 730) $initial_days = 730;
        $until = gmdate("Y-m-d\\TH:i:s\\Z", time() - $delay * 60);
        $after = trim((string)$cursor);
        if ($after == "")
            $after = gmdate("Y-m-d\\TH:i:s\\Z", time() - $initial_days * 86400);

        $documents = [];
        $max_cursor = $after;
        for ($page = 0; $page < 20; ++$page)
        {
            $criteria = [
                "updatedAfter" => $max_cursor,
                "updatedBefore" => $until,
                "flowDirection" => ["In"],
                "flowType" => ["SupplierInvoice"],
            ];
            $headers = $auth["headers"];
            $headers["Content-Type"] = "application/json; charset=UTF-8";
            $response = $this->request("POST", $this->endpoint($configuration, "search"), $headers, billing_einvoice_json($criteria), 60);
            if (empty($response["ok"]) || !is_array($response["json"]))
                return (["ok" => false, "error" => "BillingElectronicFlowSearchFailed", "http_status" => $response["http_status"] ?? 0]);
            $items = $this->search_items($response["json"]);
            if (!count($items))
                break ;
            $advanced = false;
            foreach ($items as $item)
            {
                if (!is_array($item))
                    continue ;
                $flow_id = trim((string)($item["flowId"] ?? ""));
                if ($flow_id == "")
                    continue ;
                $updated_at = trim((string)($item["updatedAt"] ?? ($item["submittedAt"] ?? "")));
                if ($updated_at != "" && strcmp($updated_at, $max_cursor) > 0)
                {
                    $max_cursor = $updated_at;
                    $advanced = true;
                }
                $original = $this->get_flow_component($flow_id, "Original", $configuration, "application/xml, application/pdf, application/octet-stream;q=0.9, */*;q=0.1");
                if (empty($original["ok"]))
                {
                    $documents[] = ["metadata" => $item, "flow_id" => $flow_id, "error" => $original["error"] ?? "BillingElectronicFlowDownloadFailed"];
                    continue ;
                }
                $flow_syntax = strtoupper(trim((string)($item["flowSyntax"] ?? "")));
                $readable = $this->get_flow_component($flow_id, "ReadableView", $configuration, "application/pdf, text/html;q=0.8, */*;q=0.1");
                $converted = NULL;
                /* XP Z12-013 exposes a Converted component for invoices.  It
                 * lets Infosphère ingest CII/Factur-X too when the PA is
                 * configured to provide its preferred converted syntax. */
                if ($flow_syntax != "UBL")
                {
                    $converted_result = $this->get_flow_component($flow_id, "Converted", $configuration, "application/xml, text/xml;q=0.9, application/octet-stream;q=0.5, */*;q=0.1");
                    if (!empty($converted_result["ok"]))
                        $converted = $converted_result;
                }
                $documents[] = [
                    "metadata" => $item,
                    "flow_id" => $flow_id,
                    "flow_syntax" => $flow_syntax,
                    "name" => trim((string)($item["name"] ?? ($original["filename"] ?? ""))),
                    "updated_at" => $updated_at,
                    "content" => $original["content"],
                    "content_type" => $original["content_type"] ?? "",
                    "converted" => $converted,
                    "readable" => !empty($readable["ok"]) ? $readable : NULL,
                ];
                if (count($documents) >= 500)
                    break 2;
            }
            if (!$advanced || count($items) == 0)
                break ;
        }
        $lifecycle = [];
        $lifecycle_cursor = $after;
        for ($page = 0; $page < 20; ++$page)
        {
            $criteria = [
                "updatedAfter" => $lifecycle_cursor,
                "updatedBefore" => $until,
                "flowDirection" => ["In"],
                "flowType" => ["CustomerInvoiceLC"],
            ];
            $headers = $auth["headers"];
            $headers["Content-Type"] = "application/json; charset=UTF-8";
            $response = $this->request("POST", $this->endpoint($configuration, "search"), $headers, billing_einvoice_json($criteria), 60);
            if (empty($response["ok"]) || !is_array($response["json"]))
                return (["ok" => false, "error" => "BillingElectronicLifecycleSearchFailed", "http_status" => $response["http_status"] ?? 0]);
            $items = $this->search_items($response["json"]);
            if (!count($items))
                break ;
            $advanced = false;
            foreach ($items as $item)
            {
                if (!is_array($item)) continue ;
                $flow_id = trim((string)($item["flowId"] ?? ""));
                if ($flow_id == "") continue ;
                $updated_at = trim((string)($item["updatedAt"] ?? ($item["submittedAt"] ?? "")));
                if ($updated_at != "" && strcmp($updated_at, $lifecycle_cursor) > 0)
                {
                    $lifecycle_cursor = $updated_at;
                    if (strcmp($updated_at, $max_cursor) > 0) $max_cursor = $updated_at;
                    $advanced = true;
                }
                $original = $this->get_flow_component($flow_id, "Original", $configuration, "application/xml, text/xml;q=0.9, */*;q=0.1");
                $lifecycle[] = [
                    "metadata" => $item,
                    "flow_id" => $flow_id,
                    "updated_at" => $updated_at,
                    "content" => !empty($original["ok"]) ? $original["content"] : "",
                    "content_type" => !empty($original["ok"]) ? ($original["content_type"] ?? "") : "",
                    "error" => !empty($original["ok"]) ? NULL : ($original["error"] ?? "BillingElectronicFlowDownloadFailed"),
                ];
                if (count($lifecycle) >= 500)
                    break 2;
            }
            if (!$advanced)
                break ;
        }
        return (["ok" => true, "documents" => $documents, "lifecycle" => $lifecycle, "cursor" => $max_cursor, "until" => $until]);
    }

    private function find_reform_status_code($value)
    {
        if (!is_array($value))
            return (NULL);
        foreach ($value as $key => $item)
        {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$key));
            if (in_array($normalized, [
                "processconditioncode", "reformstatuscode", "businessstatuscode",
                "invoicestatuscode", "lifecyclecode", "statuscode"
            ], true))
            {
                $candidate = trim((string)$item);
                if (preg_match('/^(20[0-9]|21[0-3])$/', $candidate))
                    return ($candidate);
            }
            if (is_array($item))
            {
                $candidate = $this->find_reform_status_code($item);
                if ($candidate !== NULL)
                    return ($candidate);
            }
        }
        return (NULL);
    }

    private function reform_status($code, $default)
    {
        $map = [
            "200" => "submitted",
            "201" => "issued",
            "202" => "received",
            "203" => "available",
            "204" => "taken_over",
            "205" => "accepted",
            "206" => "partially_accepted",
            "207" => "disputed",
            "208" => "suspended",
            "209" => "completed",
            "210" => "refused",
            "211" => "payment_sent",
            "212" => "paid",
            "213" => "rejected",
        ];
        return ($map[(string)$code] ?? $default);
    }

    public function refresh_document($document, $configuration)
    {
        $flow_id = trim((string)($document["provider_document_id"] ?? ($document["transmission_id"] ?? "")));
        if ($flow_id == "")
            return (["ok" => false, "error" => "BillingElectronicFlowIdMissing"]);
        $auth = $this->authenticated_headers($configuration);
        if (empty($auth["ok"]))
            return ($auth);
        $url = $this->endpoint($configuration, "flows/".rawurlencode($flow_id))."?docType=Metadata";
        $response = $this->request("GET", $url, $auth["headers"], NULL, 30);
        if (empty($response["ok"]) || !is_array($response["json"]))
            return (["ok" => false, "error" => "BillingElectronicFlowRefreshFailed", "http_status" => $response["http_status"] ?? 0]);
        $remote = $response["json"];
        $ack = strtoupper(trim((string)($remote["ackStatus"] ?? ($remote["acknowledgement"]["status"] ?? ""))));
        $status = (string)($document["status"] ?? "submitted");
        if ($ack == "PENDING") $status = "processing";
        else if ($ack == "OK") $status = "submitted";
        else if ($ack == "ERROR") $status = "rejected";
        $reform_code = $this->find_reform_status_code($remote);
        if ($reform_code !== NULL)
            $status = $this->reform_status($reform_code, $status);
        return ([
            "ok" => true,
            "provider_document_id" => $flow_id,
            "transmission_id" => $flow_id,
            "status" => $status,
            "external_status_code" => $reform_code,
            "event_code" => $reform_code !== NULL ? "reform_status_".$reform_code : ($ack == "" ? "flow_refreshed" : "flow_ack_".strtolower($ack)),
            "message" => $reform_code !== NULL ? "Statut cycle de vie ".$reform_code : ($ack == "" ? "Métadonnées de flux actualisées" : "FlowAckStatus ".$ack),
        ]);
    }
}

billing_einvoice_register_connector(new BillingElectronicInvoiceXpZ12013Connector());
