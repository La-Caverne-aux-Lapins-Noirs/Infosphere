<?php

require_once (__DIR__."/school_activity.php");

function is_billing_manager($id_user = -1)
{
    global $User;

    if ($id_user == -1)
    {
        if (!$User)
            return (false);
        $id_user = (int)$User["id"];
    }
    $id_user = (int)$id_user;
    return (
        is_director($id_user) ||
        is_secretariat($id_user) ||
        is_accountant($id_user)
    );
}

function am_i_billing_manager()
{
    global $User;

    if (!$User)
        return (false);
    return (is_admin() || is_billing_manager((int)$User["id"]));
}

function is_billing_manager_for_school($id_school)
{
    if (is_admin())
        return (true);
    return (
        is_director_for_school($id_school) ||
        is_secretariat_for_school($id_school) ||
        is_accountant_for_school($id_school)
    );
}


function is_billing_manager_for_billing_entry($id_entry)
{
    if (is_admin())
        return (true);
    $id_entry = (int)$id_entry;
    if ($id_entry <= 0)
        return (am_i_billing_manager());
    $entry = db_select_one("id_user, id_organization, id_school FROM billing_entry WHERE id = $id_entry");
    return (billing_entry_is_managed($entry));
}

function is_billing_manager_for_billing_payment($id_payment)
{
    if (is_admin())
        return (true);
    $id_payment = (int)$id_payment;
    if ($id_payment <= 0)
        return (am_i_billing_manager());
    $payment = db_select_one("id_user, id_organization, id_school FROM billing_payment WHERE id = $id_payment AND deleted IS NULL");
    if ($payment == NULL)
        return (false);
    return (is_billing_manager_for_school((int)$payment["id_school"]));
}

function is_billing_manager_for_user($id_user)
{
    return (billing_user_is_managed($id_user));
}


function is_billing_manager_for_organization_account_entry($id_entry)
{
    if (is_admin())
        return (true);
    $id_entry = (int)$id_entry;
    if ($id_entry <= 0)
        return (am_i_billing_manager());
    $entry = db_select_one("id_school FROM organization_account_entry WHERE id = $id_entry AND deleted IS NULL");
    if ($entry == NULL)
        return (false);
    return (is_billing_manager_for_school((int)$entry["id_school"]));
}

function billing_amount_to_cents($amount)
{
    if ($amount === NULL)
        return (NULL);
    $amount = trim((string)$amount);
    if ($amount == "")
        return (NULL);
    $amount = str_replace(["€", " "], "", $amount);
    $amount = str_replace(",", ".", $amount);
    if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $amount))
        return (NULL);
    return ((int)round(((float)$amount) * 100));
}

function billing_euros($amount)
{
    return (number_format(((int)$amount) / 100, 2, ",", " ")." €");
}

function billing_document_date_label($date)
{
    $stamp = date_to_timestamp($date);

    if ($stamp == NULL)
        return ((string)$date);
    return (datex("d/m/Y", $stamp));
}

function billing_managed_school_ids()
{
    global $User;

    if (is_admin())
        return (array_keys(db_select_all("id FROM school WHERE deleted IS NULL", "id")));
    if (!$User)
        return ([]);
    $out = [];
    foreach (user_school_authorities((int)$User["id"]) as $id_school => $roles)
        if (isset($roles["DIRECTOR"]) || isset($roles["SECRETARIAT"]) || isset($roles["ACCOUNTANT"]))
            $out[] = (int)$id_school;
    return ($out);
}

function billing_school_filter($alias = "user_school")
{
    $schools = billing_managed_school_ids();
    if (is_admin())
        return ("");
    if (!count($schools))
        return (" AND 0 ");
    $ids = implode(",", array_map("intval", $schools));
    return (" AND $alias.id_school IN ($ids) ");
}

function billing_user_is_managed($id_user)
{
    $id_user = (int)$id_user;
    $student_authority = user_school_student_authority_sql();
    if ($id_user <= 0)
        return (false);
    if (is_admin())
        return (true);
    return (db_select_one("
        user_school.id
        FROM user_school
        WHERE user_school.id_user = $id_user
          AND user_school.authority = $student_authority
        ".billing_school_filter("user_school")) != NULL);
}

function billing_entry_is_managed($entry)
{
    if (!is_array($entry))
        return (false);
    $id_school = (int)($entry["id_school"] ?? 0);
    if ($id_school <= 0)
        return (false);
    return (is_billing_manager_for_school($id_school));
}

function billing_school_context($id_school)
{
    $id_school = (int)$id_school;
    if ($id_school <= 0)
        return (NULL);
    return (db_select_one("
        school.id as id_school,
        school.codename as school_codename,
        school.is_school,
        school.is_of,
        school.is_cfa
        FROM school
        WHERE school.id = $id_school
          AND school.deleted IS NULL
    "));
}

function billing_user_main_school($id_user)
{
    $id_user = (int)$id_user;
    $student_authority = user_school_student_authority_sql();
    return (db_select_one("
        school.id as id_school,
        school.codename as school_codename,
        school.is_school,
        school.is_of,
        school.is_cfa
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user
          AND user_school.authority = $student_authority
          AND school.deleted IS NULL
        ".billing_school_filter("user_school")."
        ORDER BY user_school.id DESC
    "));
}

function billing_tariff_year_for_user($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([
            "index" => NULL,
            "year" => NULL,
            "trimester" => NULL,
            "billing_basis" => NULL,
        ]);

    // Billing must determine its own tariff year. Pedagogical cycles are a bad
    // proxy here: a new year can already be invoiced before its first cycle has
    // started, and direct admissions do not necessarily start in year 1.
    //
    // Use the billing frontier instead. Payments are already allocated FIFO by
    // billing_payment_coverage_for_user(). Starting just after the most recent
    // invoice that is 100% covered, the first draft identifies the active
    // tariff block. Choosing the *first* draft rather than the furthest one is
    // important when two or three future years have already been prepared.
    // Older issued-but-unpaid invoices may stay before that draft: they are
    // carried separately as prior balance by billing_payment_schedule_for_user().
    $coverage = billing_payment_coverage_for_user($id_user);
    $entries = db_select_all("
        billing_entry.*,
        COALESCE(billing_template.tariff_year, 0) as template_tariff_year
        FROM billing_entry
        LEFT JOIN billing_template ON billing_template.id = billing_entry.id_template
        WHERE billing_entry.id_user = $id_user
          AND billing_entry.deleted IS NULL
          AND billing_entry.amount > 0
          AND billing_entry.entry_type != 'credit_note'
        ORDER BY billing_entry.due_date ASC, billing_entry.id ASC
    ");

    if (!count($entries))
        return ([
            "index" => NULL,
            "year" => NULL,
            "trimester" => NULL,
            "billing_basis" => NULL,
        ]);

    $last_paid_index = -1;
    $last_paid_year = 0;
    foreach ($entries as $index => $entry)
    {
        if (empty($entry["sent_date"]))
            continue ;
        $amount = max(0, (int)$entry["amount"]);
        $covered = max(0, (int)($coverage[(int)$entry["id"]] ?? 0));
        if ($amount <= 0 || $covered < $amount)
            continue ;
        $last_paid_index = $index;
        $year = max(0, (int)($entry["template_tariff_year"] ?? 0));
        if ($year > 0)
            $last_paid_year = $year;
    }

    $active_year = 0;
    $basis = NULL;

    // Prefer the first draft after the paid frontier. This is the practical
    // boundary between the closed billing history and the schedule currently
    // being prepared, without jumping to a later preloaded tariff year.
    for ($index = $last_paid_index + 1; $index < count($entries); ++$index)
    {
        $entry = $entries[$index];
        $year = max(0, (int)($entry["template_tariff_year"] ?? 0));
        if ($year <= 0 || !empty($entry["sent_date"]))
            continue ;
        $active_year = $year;
        $basis = "first_draft_after_paid";
        break ;
    }

    // A one-shot schedule can have no draft left at all. In that case the
    // first still-open issued invoice after the frontier is the active year.
    if ($active_year == 0)
        for ($index = $last_paid_index + 1; $index < count($entries); ++$index)
        {
            $entry = $entries[$index];
            $year = max(0, (int)($entry["template_tariff_year"] ?? 0));
            if ($year <= 0)
                continue ;
            $active_year = $year;
            $basis = "first_open_after_paid";
            break ;
        }

    // Everything is settled and no newer draft exists: keep the last billed
    // tariff year rather than falling back to pedagogy.
    if ($active_year == 0 && $last_paid_year > 0)
    {
        $active_year = $last_paid_year;
        $basis = "last_paid";
    }

    // No paid anchor yet (typical direct admission): start with the first
    // tariffed billing entry, preferring a draft when available.
    if ($active_year == 0)
    {
        foreach ($entries as $entry)
        {
            $year = max(0, (int)($entry["template_tariff_year"] ?? 0));
            if ($year <= 0 || !empty($entry["sent_date"]))
                continue ;
            $active_year = $year;
            $basis = "first_draft";
            break ;
        }
    }
    if ($active_year == 0)
        foreach ($entries as $entry)
        {
            $year = max(0, (int)($entry["template_tariff_year"] ?? 0));
            if ($year <= 0)
                continue ;
            $active_year = $year;
            $basis = "first_billing_entry";
            break ;
        }

    if ($active_year <= 0)
        return ([
            "index" => NULL,
            "year" => NULL,
            "trimester" => NULL,
            "billing_basis" => NULL,
        ]);

    return ([
        "index" => $active_year,
        "year" => $active_year,
        "trimester" => NULL,
        "billing_basis" => $basis,
    ]);
}

function billing_account_for_user($id_user)
{
    $id_user = (int)$id_user;
    $today = dbnow();
    // This account follows the student's tuition, not the legal identity of
    // the payer. Entries financed by an organization therefore stay in every
    // student-side total; only their cash allocation belongs to the
    // organization's payer account.
    $planned = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_entry
        WHERE id_user = $id_user
          AND deleted IS NULL
          AND (entry_type != 'credit_note' OR sent_date IS NOT NULL)
    ");
    $issued = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_entry
        WHERE id_user = $id_user
          AND deleted IS NULL
          AND sent_date IS NOT NULL
    ");
    $due = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_entry
        WHERE id_user = $id_user
          AND deleted IS NULL
          AND sent_date IS NOT NULL
          AND due_date <= '$today'
    ");
    $to_invoice = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_entry
        WHERE id_user = $id_user
          AND deleted IS NULL
          AND sent_date IS NULL
          AND entry_type != 'credit_note'
          AND amount > 0
    ");
    $direct_paid = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_user = $id_user
          AND id_organization IS NULL
          AND deleted IS NULL
    ");
    $planned = (int)$planned["amount"];
    $issued = (int)$issued["amount"];
    $due = (int)$due["amount"];
    $to_invoice = (int)$to_invoice["amount"];
    $coverage = billing_payment_coverage_for_student($id_user);
    $paid = (int)$direct_paid["amount"] + billing_organization_cash_paid_for_student($id_user, $coverage);
    $balance = $issued - $paid;
    $projected_balance = $planned - $paid;
    $late = 0;
    $today_stamp = now();
    foreach (billing_positive_invoices_for_student($id_user, true) as $entry)
    {
        $due_stamp = date_to_timestamp($entry["due_date"] ?? "");
        if ($due_stamp == NULL || $due_stamp > $today_stamp)
            continue ;
        $late += max(0, (int)$entry["amount"] - (int)($coverage[(int)$entry["id"]] ?? 0));
    }

    return ([
        "planned" => $planned,
        "billed" => $issued,
        "due" => $due,
        "paid" => $paid,
        "balance" => $balance,
        "projected_balance" => $projected_balance,
        "to_invoice" => max(0, $to_invoice),
        "billed_unpaid" => max(0, $balance),
        "late" => max(0, $late),
        "advance" => max(0, -$balance),
    ]);
}


function billing_account_statement($entries, $payments, $id_user = 0)
{
    $statement = [];

    foreach ($entries as $entry)
    {
        if (empty($entry["sent_date"]))
            continue ;
        $amount = (int)$entry["amount"];
        $credit_note = billing_is_credit_note($entry);
        $statement[] = [
            "type" => $credit_note ? "credit_note" : "invoice",
            "id" => (int)$entry["id"],
            "date" => $entry["sent_date"],
            "label" => $entry["label"],
            "reference" => trim((string)($entry["invoice_reference"] ?? "")),
            "due_date" => $credit_note ? NULL : $entry["due_date"],
            "debit" => $credit_note ? 0 : max(0, $amount),
            "credit" => $credit_note ? abs(min(0, $amount)) : 0,
            "comment" => "",
            "payer" => (int)($entry["id_organization"] ?? 0) > 0 ? billing_entry_organization_name($entry) : "",
            "external" => billing_is_external_invoice($entry),
            "has_pdf" => !empty($entry["invoice_filename"]),
            "related_entry_id" => (int)($entry["related_entry_id"] ?? 0),
        ];
    }

    foreach ($payments as $payment)
    {
        $amount = (int)$payment["amount"];
        $refund = $amount < 0;
        $statement[] = [
            "type" => $refund ? "refund" : "payment",
            "id" => (int)$payment["id"],
            "date" => $payment["payment_date"],
            "label" => trim((string)($payment["comment"] ?? "")),
            "reference" => trim((string)($payment["transfer_reference"] ?? "")),
            "due_date" => NULL,
            "debit" => $refund ? abs($amount) : 0,
            "credit" => $refund ? 0 : max(0, $amount),
            "comment" => trim((string)($payment["comment"] ?? "")),
        ];
    }

    // Organization payments are stored on the payer account, but the share
    // actually allocated to this student's invoices belongs in the student's
    // tuition statement as well. Never copy the whole organization payment:
    // one payment may finance several students.
    $id_user = (int)$id_user;
    if ($id_user > 0)
    {
        $coverage = billing_payment_coverage_for_student($id_user);
        $payment_dates = [];
        foreach ($entries as $entry)
        {
            $id_organization = (int)($entry["id_organization"] ?? 0);
            $id_school = (int)($entry["id_school"] ?? 0);
            if ($id_organization <= 0 || $id_school <= 0 || empty($entry["sent_date"]) ||
                billing_is_credit_note($entry) || (int)$entry["amount"] <= 0)
                continue ;
            $amount = max(0, (int)$entry["amount"]);
            $credit = min($amount, billing_credit_note_total_for_entry((int)$entry["id"], true));
            $covered = min($amount, (int)($coverage[(int)$entry["id"]] ?? 0));
            $cash = max(0, min($amount - $credit, $covered - $credit));
            if ($cash <= 0)
                continue ;

            $key = $id_school.":".$id_organization;
            if (!isset($payment_dates[$key]))
            {
                $last = db_select_one("
                    MAX(payment_date) as payment_date
                    FROM billing_payment
                    WHERE id_organization = $id_organization
                      AND id_school = $id_school
                      AND deleted IS NULL
                      AND amount > 0
                ");
                $payment_dates[$key] = trim((string)($last["payment_date"] ?? ""));
            }
            $date = $payment_dates[$key] != "" ? $payment_dates[$key] : $entry["sent_date"];
            $statement[] = [
                "type" => "organization_payment",
                "id" => (int)$entry["id"],
                "date" => $date,
                "label" => billing_entry_organization_name($entry),
                "reference" => billing_invoice_document_reference($entry),
                "due_date" => NULL,
                "debit" => 0,
                "credit" => $cash,
                "comment" => billing_entry_organization_name($entry),
                "payer" => billing_entry_organization_name($entry),
            ];
        }
    }

    $order = ["invoice" => 0, "credit_note" => 1, "payment" => 2, "organization_payment" => 2, "refund" => 3];
    usort($statement, function($a, $b) use ($order)
    {
        $ta = date_to_timestamp($a["date"]);
        $tb = date_to_timestamp($b["date"]);

        if ($ta == $tb)
        {
            $oa = $order[$a["type"]] ?? 9;
            $ob = $order[$b["type"]] ?? 9;
            if ($oa != $ob)
                return ($oa <=> $ob);
            return ($a["id"] <=> $b["id"]);
        }
        return ($ta <=> $tb);
    });

    $balance = 0;
    foreach ($statement as &$movement)
    {
        $balance += (int)$movement["debit"] - (int)$movement["credit"];
        $movement["balance"] = $balance;
    }
    unset($movement);

    return ($statement);
}



function billing_fetch_organizations()
{
    global $Language;

    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    return (db_select_all("
        organization.id,
        organization.codename,
        organization.name,
        organization.legal_name,
        organization.billing_payer_kind,
        organization.billing_reminder_enabled,
        organization.$field as localized_name
        FROM organization
        WHERE organization.deleted IS NULL
          AND organization.type = 'enterprise'
        ORDER BY COALESCE(NULLIF(organization.$field, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), organization.codename), organization.codename
    "));
}

function billing_organization_payer_kind($organization)
{
    if (is_array($organization))
        $kind = $organization["billing_payer_kind"] ?? "direct";
    else
    {
        $row = db_select_one("billing_payer_kind FROM organization WHERE id = ".(int)$organization." AND deleted IS NULL");
        $kind = $row["billing_payer_kind"] ?? "direct";
    }
    $kind = strtolower(trim((string)$kind));
    return (in_array($kind, ["direct", "opco", "institutional", "other"], true) ? $kind : "direct");
}

function billing_organization_payer_kind_label($organization)
{
    global $Dictionnary;

    $labels = [
        "direct" => $Dictionnary["BillingPayerKindDirect"] ?? "Entreprise / financeur direct",
        "opco" => $Dictionnary["BillingPayerKindOPCO"] ?? "OPCO",
        "institutional" => $Dictionnary["BillingPayerKindInstitutional"] ?? "Financeur institutionnel",
        "other" => $Dictionnary["BillingPayerKindOther"] ?? "Autre financeur",
    ];
    $kind = billing_organization_payer_kind($organization);
    return ($labels[$kind] ?? $labels["direct"]);
}

function billing_organization_can_receive_reminder($organization)
{
    if (!is_array($organization))
        $organization = db_select_one("
            id, billing_payer_kind, billing_reminder_enabled
            FROM organization
            WHERE id = ".(int)$organization."
              AND type = 'enterprise'
              AND deleted IS NULL
        ");
    if (!is_array($organization))
        return (false);
    if (billing_organization_payer_kind($organization) === "opco")
        return (false);
    return ((int)($organization["billing_reminder_enabled"] ?? 1) > 0);
}

function billing_organization_recipient_mails($id_organization)
{
    $id_organization = (int)$id_organization;
    if ($id_organization <= 0)
        return ([]);
    $usable = static function ($mail) {
        $mail = trim((string)$mail);
        return ($mail != "" && strcasecmp($mail, "nomail") != 0 && filter_var($mail, FILTER_VALIDATE_EMAIL));
    };
    $mails = [];

    // A dedicated billing contact takes precedence over generic company
    // contacts. Multiple billing contacts are legitimate and all receive the
    // document.
    foreach (db_select_all("
        user.mail
        FROM organization_user
        LEFT JOIN user ON user.id = organization_user.id_user
        WHERE organization_user.id_organization = $id_organization
          AND FIND_IN_SET('billing', REPLACE(organization_user.document_role, ' ', '')) > 0
          AND user.authority != -1
        ORDER BY organization_user.id ASC
    ") as $row)
        if ($usable($row["mail"] ?? ""))
            $mails[] = trim((string)$row["mail"]);

    $organization = db_select_one("mail FROM organization WHERE id = $id_organization AND deleted IS NULL");
    if (is_array($organization) && $usable($organization["mail"] ?? ""))
        $mails[] = trim((string)$organization["mail"]);

    // Compatibility for organizations created before the dedicated billing
    // role existed: if no usable billing destination is configured, fall back
    // to ordinary contacts rather than silently making invoice sending fail.
    if (!count($mails))
        foreach (db_select_all("
            user.mail
            FROM organization_user
            LEFT JOIN user ON user.id = organization_user.id_user
            WHERE organization_user.id_organization = $id_organization
              AND user.authority != -1
            ORDER BY
                CASE
                    WHEN FIND_IN_SET('representative', REPLACE(organization_user.document_role, ' ', '')) > 0 THEN 0
                    WHEN FIND_IN_SET('contact', REPLACE(organization_user.document_role, ' ', '')) > 0 THEN 1
                    ELSE 2
                END,
                organization_user.id ASC
        ") as $row)
            if ($usable($row["mail"] ?? ""))
                $mails[] = trim((string)$row["mail"]);

    return (array_values(array_unique($mails)));
}

function billing_fetch_managed_schools()
{
    global $Language;

    $ids = billing_managed_school_ids();
    if (!count($ids))
        return ([]);
    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    $id_list = implode(",", array_map("intval", $ids));
    return (db_select_all("
        school.id,
        school.codename,
        school.is_school,
        school.is_of,
        school.is_cfa,
        COALESCE(NULLIF(organization.$field, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), school.codename) as name
        FROM school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE school.id IN ($id_list)
          AND school.deleted IS NULL
        ORDER BY name, school.codename
    "));
}

function billing_organization_exists($id_organization)
{
    $id_organization = (int)$id_organization;
    if ($id_organization <= 0)
        return (false);
    return (db_select_one("id FROM organization WHERE id = $id_organization AND type = 'enterprise' AND deleted IS NULL") != NULL);
}

function billing_fetch_organization_account_entry($id_entry)
{
    global $Language;

    $id_entry = (int)$id_entry;
    if ($id_entry <= 0)
        return (NULL);
    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    return (db_select_one("
        organization_account_entry.*,
        organization.codename as organization_codename,
        COALESCE(NULLIF(organization.$field, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), organization.codename) as organization_name,
        school.codename as school_codename,
        actor.codename as actor_codename
        FROM organization_account_entry
        LEFT JOIN organization ON organization.id = organization_account_entry.id_organization
        LEFT JOIN school ON school.id = organization_account_entry.id_school
        LEFT JOIN user as actor ON actor.id = organization_account_entry.id_actor
        WHERE organization_account_entry.id = $id_entry
          AND organization_account_entry.deleted IS NULL
          AND organization.deleted IS NULL
          AND school.deleted IS NULL
    "));
}

function billing_organization_entry_document_path($entry)
{
    if (!is_array($entry))
        return ("");
    $path = trim((string)($entry["document_path"] ?? ""));
    if ($path == "")
        return ("");
    return ($path);
}

function billing_organization_entry_document_url($entry)
{
    $path = billing_organization_entry_document_path($entry);
    if ($path == "")
        return ("");
    return ("/".ltrim($path, "/"));
}

function billing_organization_entry_document_target($entry, $extension)
{
    $extension = strtolower(trim((string)$extension));
    if (!in_array($extension, ["pdf", "png", "jpg"], true))
        return ("");
    if (!is_array($entry) || empty($entry["organization_codename"]))
        return ("");
    return (
        organization_dir($entry["organization_codename"]).
        "accounting/".(int)$entry["id_school"]."/".(int)$entry["id"].".".$extension
    );
}

function billing_remove_organization_entry_document($entry)
{
    global $Database;

    if (!is_array($entry) || empty($entry["id"]))
        return (false);
    $path = billing_organization_entry_document_path($entry);
    if ($path != "" && is_file($path) && !@unlink($path))
        return (false);
    $id_entry = (int)$entry["id"];
    return ($Database->query("
        UPDATE organization_account_entry
        SET document_name = NULL, document_path = NULL, updated_at = NOW()
        WHERE id = $id_entry AND deleted IS NULL
    ") !== false);
}

function billing_store_organization_entry_document($id_entry, $document)
{
    global $Database;

    $id_entry = (int)$id_entry;
    if ($id_entry <= 0 || !is_array($document) || !isset($document["content"], $document["extension"]))
        return (false);
    $entry = billing_fetch_organization_account_entry($id_entry);
    if ($entry == NULL)
        return (false);
    $target = billing_organization_entry_document_target($entry, $document["extension"]);
    if ($target == "")
        return (false);
    if (($ret = new_directory($target))->is_error())
        return (false);

    $tmp = $target.".tmp.".getmypid();
    if (@file_put_contents($tmp, $document["content"], LOCK_EX) === false)
        return (false);
    @chmod($tmp, 0640);
    if (!@rename($tmp, $target))
    {
        @unlink($tmp);
        return (false);
    }
    @chmod($target, 0640);

    $old = billing_organization_entry_document_path($entry);
    if ($old != "" && $old != $target && is_file($old))
        @unlink($old);

    $ename = $Database->real_escape_string(substr((string)($document["name"] ?? basename($target)), 0, 255));
    $epath = $Database->real_escape_string($target);
    return ($Database->query("
        UPDATE organization_account_entry
        SET document_name = '$ename', document_path = '$epath', updated_at = NOW()
        WHERE id = $id_entry AND deleted IS NULL
    ") !== false);
}

function billing_move_organization_entry_document($before, $after)
{
    global $Database;

    $old = billing_organization_entry_document_path($before);
    if ($old == "")
        return (true);
    $extension = strtolower(pathinfo($old, PATHINFO_EXTENSION));
    $target = billing_organization_entry_document_target($after, $extension);
    if ($target == "" || $target == $old)
        return (true);
    if (($ret = new_directory($target))->is_error())
        return (false);
    if (!is_file($old))
    {
        $id_entry = (int)$after["id"];
        return ($Database->query("
            UPDATE organization_account_entry
            SET document_path = NULL, document_name = NULL, updated_at = NOW()
            WHERE id = $id_entry
        ") !== false);
    }
    if (!@rename($old, $target))
        return (false);
    @chmod($target, 0640);
    $id_entry = (int)$after["id"];
    $epath = $Database->real_escape_string($target);
    return ($Database->query("
        UPDATE organization_account_entry
        SET document_path = '$epath', updated_at = NOW()
        WHERE id = $id_entry
    ") !== false);
}

function billing_fetch_organization_entries($movement_type = NULL)
{
    global $Language;
    global $Database;

    $where_type = "";
    if ($movement_type !== NULL)
    {
        if (!in_array($movement_type, ["debit", "credit"], true))
            return ([]);
        $where_type = " AND organization_account_entry.movement_type = '".$Database->real_escape_string($movement_type)."' ";
    }
    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    return (db_select_all("
        organization_account_entry.*,
        organization.codename as organization_codename,
        COALESCE(NULLIF(organization.$field, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), organization.codename) as organization_name,
        school.codename as school_codename,
        actor.codename as actor_codename
        FROM organization_account_entry
        LEFT JOIN organization ON organization.id = organization_account_entry.id_organization
        LEFT JOIN school ON school.id = organization_account_entry.id_school
        LEFT JOIN user as actor ON actor.id = organization_account_entry.id_actor
        WHERE organization_account_entry.deleted IS NULL
          AND organization.deleted IS NULL
          AND school.deleted IS NULL
          $where_type
        ".billing_school_filter("organization_account_entry")."
        ORDER BY organization_account_entry.movement_date DESC, organization_account_entry.id DESC
    "));
}


function billing_fetch_organization_statement($id_organization)
{
    global $Language;

    $id_organization = (int)$id_organization;
    if ($id_organization <= 0 || !billing_organization_exists($id_organization))
        return ([]);
    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    $entries = db_select_all("
        organization_account_entry.*,
        organization.codename as organization_codename,
        COALESCE(NULLIF(organization.$field, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), organization.codename) as organization_name,
        school.codename as school_codename,
        actor.codename as actor_codename
        FROM organization_account_entry
        LEFT JOIN organization ON organization.id = organization_account_entry.id_organization
        LEFT JOIN school ON school.id = organization_account_entry.id_school
        LEFT JOIN user as actor ON actor.id = organization_account_entry.id_actor
        WHERE organization_account_entry.deleted IS NULL
          AND organization_account_entry.id_organization = $id_organization
          AND organization.deleted IS NULL
          AND school.deleted IS NULL
        ".billing_school_filter("organization_account_entry")."
        ORDER BY organization_account_entry.movement_date ASC, organization_account_entry.id ASC
    ");

    $balance = 0;
    foreach ($entries as &$entry)
    {
        $amount = (int)$entry["amount"];
        $entry["debit"] = $entry["movement_type"] == "debit" ? $amount : 0;
        $entry["credit"] = $entry["movement_type"] == "credit" ? $amount : 0;
        $balance += $entry["debit"] - $entry["credit"];
        $entry["balance"] = $balance;
    }
    unset($entry);
    return ($entries);
}


function billing_fetch_organization_statements()
{
    global $Language;

    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    $entries = db_select_all("
        organization_account_entry.*,
        organization.codename as organization_codename,
        COALESCE(NULLIF(organization.$field, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), organization.codename) as organization_name,
        school.codename as school_codename,
        actor.codename as actor_codename
        FROM organization_account_entry
        LEFT JOIN organization ON organization.id = organization_account_entry.id_organization
        LEFT JOIN school ON school.id = organization_account_entry.id_school
        LEFT JOIN user as actor ON actor.id = organization_account_entry.id_actor
        WHERE organization_account_entry.deleted IS NULL
          AND organization.deleted IS NULL
          AND school.deleted IS NULL
        ".billing_school_filter("organization_account_entry")."
        ORDER BY organization_account_entry.id_organization ASC, organization_account_entry.movement_date ASC, organization_account_entry.id ASC
    ");
    $out = [];
    $balances = [];
    foreach ($entries as $entry)
    {
        $id = (int)$entry["id_organization"];
        if (!isset($balances[$id]))
            $balances[$id] = 0;
        $amount = (int)$entry["amount"];
        $entry["debit"] = $entry["movement_type"] == "debit" ? $amount : 0;
        $entry["credit"] = $entry["movement_type"] == "credit" ? $amount : 0;
        $balances[$id] += $entry["debit"] - $entry["credit"];
        $entry["balance"] = $balances[$id];
        if (!isset($out[$id]))
            $out[$id] = [];
        $out[$id][] = $entry;
    }
    return ($out);
}

function billing_organization_statement_summary($entries)
{
    $debit = 0;
    $credit = 0;
    foreach ((array)$entries as $entry)
    {
        if (($entry["movement_type"] ?? "") == "debit")
            $debit += (int)($entry["amount"] ?? 0);
        else if (($entry["movement_type"] ?? "") == "credit")
            $credit += (int)($entry["amount"] ?? 0);
    }
    return (["debit" => $debit, "credit" => $credit, "balance" => $debit - $credit]);
}

function billing_accounting_export_safe_segment($value, $fallback = "item")
{
    $value = trim((string)$value);
    if ($value == "")
        $value = $fallback;
    if (function_exists("iconv"))
    {
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $value);
        if ($ascii !== false && trim($ascii) != "")
            $value = $ascii;
    }
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
    $value = trim((string)$value, '-_.');
    if ($value == "")
        $value = $fallback;
    return (substr($value, 0, 96));
}

function billing_accounting_export_csv($headers, $rows)
{
    $stream = fopen("php://temp", "w+");
    if ($stream === false)
        return (false);
    // UTF-8 BOM: convenient when the accountant opens the file directly in Excel.
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headers, ";", '"', "\\");
    foreach ($rows as $row)
        fputcsv($stream, $row, ";", '"', "\\");
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    return ($content);
}

function billing_accounting_export_date($value)
{
    $stamp = date_to_timestamp($value);
    return ($stamp === NULL ? "" : date("Y-m-d", $stamp));
}

function billing_accounting_export_period($start, $end)
{
    $start = billing_accounting_export_date($start);
    $end = billing_accounting_export_date($end);
    if ($start == "" || $end == "" || $start > $end)
        return (NULL);
    return (["start" => $start, "end" => $end]);
}

function billing_fetch_student_accounting_export_entries($start, $end)
{
    global $Language;
    global $Database;

    $start = $Database->real_escape_string($start);
    $end = $Database->real_escape_string($end);
    $language = in_array($Language, ["fr", "en"], true) ? $Language : "fr";
    return (db_select_all("
        billing_entry.*,
        user.codename,
        user.first_name,
        user.family_name,
        school.codename as school_codename,
        COALESCE(NULLIF(organization.{$language}_name, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), school.codename) as school_name
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE billing_entry.sent_date IS NOT NULL
          AND billing_entry.id_user IS NOT NULL
          AND billing_entry.id_organization IS NULL
          AND DATE(billing_entry.sent_date) >= '$start'
          AND DATE(billing_entry.sent_date) <= '$end'
        ".billing_school_filter("billing_entry")."
        ORDER BY billing_entry.invoice_type ASC, billing_entry.sent_date ASC, billing_entry.id ASC
    "));
}

function billing_fetch_organization_customer_invoice_export_entries($start, $end)
{
    global $Language;
    global $Database;

    $start = $Database->real_escape_string($start);
    $end = $Database->real_escape_string($end);
    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    return (db_select_all("
        billing_entry.*,
        client_organization.codename as organization_codename,
        COALESCE(NULLIF(client_organization.$field, ''), NULLIF(client_organization.name, ''), NULLIF(client_organization.legal_name, ''), client_organization.codename) as organization_name,
        user.codename,
        user.first_name,
        user.family_name,
        school.codename as school_codename
        FROM billing_entry
        LEFT JOIN organization client_organization ON client_organization.id = billing_entry.id_organization
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
        WHERE billing_entry.sent_date IS NOT NULL
          AND billing_entry.id_organization IS NOT NULL
          AND DATE(billing_entry.sent_date) >= '$start'
          AND DATE(billing_entry.sent_date) <= '$end'
          AND client_organization.deleted IS NULL
          AND school.deleted IS NULL
        ".billing_school_filter("billing_entry")."
        ORDER BY organization_name ASC, billing_entry.sent_date ASC, billing_entry.id ASC
    "));
}

function billing_fetch_organization_accounting_export_entries($start, $end)
{
    global $Language;
    global $Database;

    $start = $Database->real_escape_string($start);
    $end = $Database->real_escape_string($end);
    $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
    return (db_select_all("
        organization_account_entry.*,
        organization.codename as organization_codename,
        COALESCE(NULLIF(organization.$field, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), organization.codename) as organization_name,
        school.codename as school_codename,
        actor.codename as actor_codename
        FROM organization_account_entry
        LEFT JOIN organization ON organization.id = organization_account_entry.id_organization
        LEFT JOIN school ON school.id = organization_account_entry.id_school
        LEFT JOIN user as actor ON actor.id = organization_account_entry.id_actor
        WHERE organization_account_entry.deleted IS NULL
          AND DATE(organization_account_entry.movement_date) >= '$start'
          AND DATE(organization_account_entry.movement_date) <= '$end'
          AND organization.deleted IS NULL
          AND school.deleted IS NULL
        ".billing_school_filter("organization_account_entry")."
        ORDER BY organization_name ASC, organization_account_entry.movement_date ASC, organization_account_entry.id ASC
    "));
}

function billing_accounting_export_student_document_path($entry)
{
    if (!is_array($entry) || empty($entry["invoice_filename"]))
        return ("");
    $state = false;
    if (!empty($entry["deleted"]))
        $state = "deleted";
    else if (!billing_is_credit_note($entry) && !empty($entry["paid_date"]))
        $state = "paid";
    $directory = billing_entry_document_directory($entry, $state);
    if ($directory == "")
        return ("");
    $path = $directory.$entry["invoice_filename"];
    return (is_file($path) ? $path : "");
}

function billing_accounting_export_add_file($zip, $source, $target, &$used)
{
    if (!is_file($source))
        return (false);
    $target = str_replace("\\", "/", $target);
    $candidate = $target;
    $counter = 2;
    while (isset($used[$candidate]))
    {
        $info = pathinfo($target);
        $dir = isset($info["dirname"]) && $info["dirname"] != "." ? $info["dirname"]."/" : "";
        $name = $info["filename"] ?? "document";
        $extension = isset($info["extension"]) ? ".".$info["extension"] : "";
        $candidate = $dir.$name."-".$counter.$extension;
        ++$counter;
    }
    if (!$zip->addFile($source, $candidate))
        return (false);
    $used[$candidate] = true;
    return ($candidate);
}

function billing_build_accounting_export($start, $end)
{
    if (!class_exists("ZipArchive"))
        return (["ok" => false, "error" => "ZipUnavailable"]);
    $period = billing_accounting_export_period($start, $end);
    if ($period == NULL)
        return (["ok" => false, "error" => "BillingInvalidExportPeriod"]);

    $students = billing_fetch_student_accounting_export_entries($period["start"], $period["end"]);
    $organization_invoices = billing_fetch_organization_customer_invoice_export_entries($period["start"], $period["end"]);
    $organizations = billing_fetch_organization_accounting_export_entries($period["start"], $period["end"]);
    $tmp = tempnam(sys_get_temp_dir(), "billing-export-");
    $zip = new ZipArchive;
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true)
        return (["ok" => false, "error" => "CannotCreateArchive"]);

    $student_headers = ["date", "ecole", "type_contrat", "type_piece", "reference", "eleve", "codename", "libelle", "montant_ht", "montant_ttc", "tva", "statut", "document"];
    $enterprise_headers = ["date", "ecole", "type_mouvement", "entreprise", "codename", "reference", "libelle", "commentaire", "debit", "credit", "document", "saisi_par"];
    $enterprise_invoice_headers = ["date", "ecole", "type_piece", "entreprise", "codename", "beneficiaire", "reference", "libelle", "montant_ht", "montant_ttc", "tva", "statut", "document"];
    $all_headers = ["date", "ecole", "categorie", "type", "tiers", "codename", "reference", "libelle", "debit", "credit", "type_contrat", "statut", "document"];
    $student_rows = [];
    $enterprise_rows = ["debit" => [], "credit" => []];
    $enterprise_invoice_rows = ["all" => [], "invoice" => [], "credit_note" => []];
    $enterprise_rows_by_organization = [];
    $all_rows = [];
    foreach (billing_invoice_types() as $type => $label)
        $student_rows[$type] = ["all" => [], "invoice" => [], "credit_note" => []];
    $used = [];

    foreach ($students as $entry)
    {
        $type = billing_normalize_invoice_type($entry["invoice_type"] ?? "school");
        if (!isset($student_rows[$type]))
            $student_rows[$type] = ["all" => [], "invoice" => [], "credit_note" => []];
        $credit_note = billing_is_credit_note($entry);
        $piece = $credit_note ? "avoir" : "facture";
        $reference = billing_invoice_document_reference($entry);
        $name = trim(($entry["first_name"] ?? "")." ".($entry["family_name"] ?? ""));
        if ($name == "")
            $name = $entry["codename"] ?? "";
        $status = !empty($entry["deleted"]) ? "supprimee" : (!empty($entry["paid_date"]) ? "payee" : "emise");
        if ($credit_note)
            $status = !empty($entry["deleted"]) ? "supprime" : "emis";
        $document = "";
        $source = billing_accounting_export_student_document_path($entry);
        if ($source != "")
        {
            $type_dir = billing_accounting_export_safe_segment(billing_invoice_type_label($type), $type);
            $student_dir = billing_accounting_export_safe_segment($entry["codename"] ?? $name, "eleve-".(int)$entry["id_user"]);
            $piece_dir = $credit_note ? "Avoirs" : "Factures";
            $doc_name = billing_accounting_export_safe_segment($reference, $piece."-".(int)$entry["id"]).".pdf";
            $document = billing_accounting_export_add_file($zip, $source, "Eleves/$type_dir/$piece_dir/$student_dir/$doc_name", $used) ?: "";
        }
        $row = [
            billing_accounting_export_date($entry["sent_date"]),
            $entry["school_codename"] ?? "",
            billing_invoice_type_label($type),
            $piece,
            $reference,
            $name,
            $entry["codename"] ?? "",
            $entry["label"] ?? "",
            number_format(abs((int)billing_entry_amount_ht($entry)) / 100, 2, ".", ""),
            number_format(abs((int)$entry["amount"]) / 100, 2, ".", ""),
            billing_normalize_vat_rate($entry["vat_rate"] ?? 0),
            $status,
            $document,
        ];
        $student_rows[$type]["all"][] = $row;
        $student_rows[$type][$credit_note ? "credit_note" : "invoice"][] = $row;
        $amount = abs((int)$entry["amount"]);
        $all_rows[] = [
            billing_accounting_export_date($entry["sent_date"]),
            $entry["school_codename"] ?? "",
            "eleve",
            $piece,
            $name,
            $entry["codename"] ?? "",
            $reference,
            $entry["label"] ?? "",
            $credit_note ? "0.00" : number_format($amount / 100, 2, ".", ""),
            $credit_note ? number_format($amount / 100, 2, ".", "") : "0.00",
            billing_invoice_type_label($type),
            $status,
            $document,
        ];
    }

    foreach ($student_rows as $type => $sets)
    {
        $type_dir = billing_accounting_export_safe_segment(billing_invoice_type_label($type), $type);
        $files = [
            "factures_et_avoirs.csv" => $sets["all"],
            "factures.csv" => $sets["invoice"],
            "avoirs.csv" => $sets["credit_note"],
        ];
        foreach ($files as $filename => $rows)
        {
            $csv = billing_accounting_export_csv($student_headers, $rows);
            if ($csv !== false)
                $zip->addFromString("Eleves/$type_dir/$filename", $csv);
        }
    }

    foreach ($organization_invoices as $entry)
    {
        $credit_note = billing_is_credit_note($entry);
        $piece = $credit_note ? "avoir_emis" : "facture_emise";
        $reference = billing_invoice_document_reference($entry);
        $beneficiary = trim(($entry["first_name"] ?? "")." ".($entry["family_name"] ?? ""));
        if ($beneficiary == "")
            $beneficiary = $entry["codename"] ?? "";
        $status = !empty($entry["deleted"]) ? "supprimee" : (!empty($entry["paid_date"]) ? "payee" : "emise");
        if ($credit_note)
            $status = !empty($entry["deleted"]) ? "supprime" : "emis";
        $document = "";
        $source = billing_accounting_export_student_document_path($entry);
        $organization_id = (int)$entry["id_organization"];
        $org_dir = billing_accounting_export_safe_segment($entry["organization_name"] ?? $entry["organization_codename"], "entreprise-".$organization_id);
        if ($source != "")
        {
            $piece_dir = $credit_note ? "Avoirs clients" : "Factures clients";
            $doc_name = billing_accounting_export_safe_segment($reference, $piece."-".(int)$entry["id"]).".pdf";
            $document = billing_accounting_export_add_file($zip, $source, "Entreprises/$org_dir/$piece_dir/$doc_name", $used) ?: "";
        }
        $row = [
            billing_accounting_export_date($entry["sent_date"]),
            $entry["school_codename"] ?? "",
            $credit_note ? "avoir" : "facture",
            $entry["organization_name"] ?? "",
            $entry["organization_codename"] ?? "",
            $beneficiary,
            $reference,
            $entry["label"] ?? "",
            number_format(abs((int)billing_entry_amount_ht($entry)) / 100, 2, ".", ""),
            number_format(abs((int)$entry["amount"]) / 100, 2, ".", ""),
            billing_normalize_vat_rate($entry["vat_rate"] ?? 0),
            $status,
            $document,
        ];
        $enterprise_invoice_rows["all"][] = $row;
        $enterprise_invoice_rows[$credit_note ? "credit_note" : "invoice"][] = $row;
        if (!isset($enterprise_rows_by_organization[$organization_id]))
        {
            $enterprise_rows_by_organization[$organization_id] = [
                "directory" => $org_dir,
                "debit" => [],
                "credit" => [],
                "customer_all" => [],
                "customer_invoice" => [],
                "customer_credit_note" => [],
            ];
        }
        $enterprise_rows_by_organization[$organization_id]["customer_all"][] = $row;
        $enterprise_rows_by_organization[$organization_id][$credit_note ? "customer_credit_note" : "customer_invoice"][] = $row;
        $amount = abs((int)$entry["amount"]);
        $all_rows[] = [
            billing_accounting_export_date($entry["sent_date"]),
            $entry["school_codename"] ?? "",
            "entreprise",
            $piece,
            $entry["organization_name"] ?? "",
            $entry["organization_codename"] ?? "",
            $reference,
            $entry["label"] ?? "",
            $credit_note ? "0.00" : number_format($amount / 100, 2, ".", ""),
            $credit_note ? number_format($amount / 100, 2, ".", "") : "0.00",
            billing_invoice_type_label(billing_normalize_invoice_type($entry["invoice_type"] ?? "school")),
            $status,
            $document,
        ];
    }

    foreach ($organizations as $entry)
    {
        $movement = $entry["movement_type"] == "credit" ? "credit" : "debit";
        $debit = $movement == "debit" ? (int)$entry["amount"] : 0;
        $credit = $movement == "credit" ? (int)$entry["amount"] : 0;
        $document = "";
        $source = billing_organization_entry_document_path($entry);
        if ($source != "" && is_file($source))
        {
            $org_dir = billing_accounting_export_safe_segment($entry["organization_name"] ?? $entry["organization_codename"], "entreprise-".(int)$entry["id_organization"]);
            $movement_dir = $movement == "debit" ? "Debits" : "Credits";
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $base = billing_accounting_export_safe_segment(($entry["movement_date"] ?? "")."-".($entry["reference"] ?? "")."-".($entry["label"] ?? ""), "mouvement-".(int)$entry["id"]);
            $document = billing_accounting_export_add_file($zip, $source, "Entreprises/$org_dir/$movement_dir/$base".($extension ? ".".$extension : ""), $used) ?: "";
        }
        $row = [
            billing_accounting_export_date($entry["movement_date"]),
            $entry["school_codename"] ?? "",
            $movement,
            $entry["organization_name"] ?? "",
            $entry["organization_codename"] ?? "",
            $entry["reference"] ?? "",
            $entry["label"] ?? "",
            $entry["comment"] ?? "",
            number_format($debit / 100, 2, ".", ""),
            number_format($credit / 100, 2, ".", ""),
            $document,
            $entry["actor_codename"] ?? "",
        ];
        $enterprise_rows[$movement][] = $row;
        $organization_id = (int)$entry["id_organization"];
        if (!isset($enterprise_rows_by_organization[$organization_id]))
        {
            $enterprise_rows_by_organization[$organization_id] = [
                "directory" => billing_accounting_export_safe_segment($entry["organization_name"] ?? $entry["organization_codename"], "entreprise-".$organization_id),
                "debit" => [],
                "credit" => [],
                "customer_all" => [],
                "customer_invoice" => [],
                "customer_credit_note" => [],
            ];
        }
        $enterprise_rows_by_organization[$organization_id][$movement][] = $row;
        $all_rows[] = [
            billing_accounting_export_date($entry["movement_date"]),
            $entry["school_codename"] ?? "",
            "entreprise",
            $movement,
            $entry["organization_name"] ?? "",
            $entry["organization_codename"] ?? "",
            $entry["reference"] ?? "",
            $entry["label"] ?? "",
            number_format($debit / 100, 2, ".", ""),
            number_format($credit / 100, 2, ".", ""),
            "",
            "",
            $document,
        ];
    }

    foreach ($enterprise_rows_by_organization as $organization_rows)
    {
        $directory = "Entreprises/".$organization_rows["directory"]."/";
        $combined = array_merge($organization_rows["debit"], $organization_rows["credit"]);
        usort($combined, function($a, $b) { return (strcmp($a[0], $b[0])); });
        foreach ([
            "debits.csv" => $organization_rows["debit"],
            "credits.csv" => $organization_rows["credit"],
            "debits_et_credits.csv" => $combined,
        ] as $filename => $rows)
        {
            $csv = billing_accounting_export_csv($enterprise_headers, $rows);
            if ($csv !== false)
                $zip->addFromString($directory.$filename, $csv);
        }
        foreach ([
            "factures_clients.csv" => $organization_rows["customer_invoice"],
            "avoirs_clients.csv" => $organization_rows["customer_credit_note"],
            "factures_et_avoirs_clients.csv" => $organization_rows["customer_all"],
        ] as $filename => $rows)
        {
            $csv = billing_accounting_export_csv($enterprise_invoice_headers, $rows);
            if ($csv !== false)
                $zip->addFromString($directory.$filename, $csv);
        }
    }

    $enterprise_all = array_merge($enterprise_rows["debit"], $enterprise_rows["credit"]);
    usort($enterprise_all, function($a, $b) { return (strcmp($a[0], $b[0])); });
    foreach ([
        "Entreprises/debits.csv" => $enterprise_rows["debit"],
        "Entreprises/credits.csv" => $enterprise_rows["credit"],
        "Entreprises/debits_et_credits.csv" => $enterprise_all,
    ] as $filename => $rows)
    {
        $csv = billing_accounting_export_csv($enterprise_headers, $rows);
        if ($csv !== false)
            $zip->addFromString($filename, $csv);
    }
    foreach ([
        "Entreprises/factures_clients.csv" => $enterprise_invoice_rows["invoice"],
        "Entreprises/avoirs_clients.csv" => $enterprise_invoice_rows["credit_note"],
        "Entreprises/factures_et_avoirs_clients.csv" => $enterprise_invoice_rows["all"],
    ] as $filename => $rows)
    {
        $csv = billing_accounting_export_csv($enterprise_invoice_headers, $rows);
        if ($csv !== false)
            $zip->addFromString($filename, $csv);
    }
    usort($all_rows, function($a, $b) { return (strcmp($a[0], $b[0])); });
    $csv = billing_accounting_export_csv($all_headers, $all_rows);
    if ($csv !== false)
        $zip->addFromString("tout.csv", $csv);

    $readme = "Export comptable Infosphère\nPériode : ".$period["start"]." au ".$period["end"]."\n\n".
        "Eleves/ : factures et avoirs classés par type de contrat, avec trois CSV par type.\n".
        "Entreprises/ : factures/avoirs clients et justificatifs de débits/crédits, classés par entreprise, plus les CSV consolidés.\n".
        "tout.csv : vue commune de l'ensemble des pièces exportées.\n";
    $zip->addFromString("LISEZ-MOI.txt", $readme);
    $zip->close();

    $content = @file_get_contents($tmp);
    @unlink($tmp);
    if ($content === false)
        return (["ok" => false, "error" => "CannotCreateArchive"]);
    return ([
        "ok" => true,
        "filename" => "comptabilite_".$period["start"]."_".$period["end"].".zip",
        "content" => $content,
    ]);
}

function billing_add_organization_entry($id_school, $id_organization, $movement_type, $amount, $movement_date, $label, $reference = "", $comment = "")
{
    global $Database;
    global $User;

    $id_school = (int)$id_school;
    $id_organization = (int)$id_organization;
    $amount = (int)$amount;
    if ($id_school <= 0 || $id_organization <= 0 || $amount <= 0 ||
        !in_array($movement_type, ["debit", "credit"], true))
        return (false);
    if (!is_billing_manager_for_school($id_school) || !billing_organization_exists($id_organization))
        return (false);

    $movement_date = db_form_date($movement_date);
    if (date_to_timestamp($movement_date) === NULL)
        return (false);
    $label = trim((string)$label);
    if ($label == "")
        return (false);

    $etype = $Database->real_escape_string($movement_type);
    $edate = $Database->real_escape_string($movement_date);
    $elabel = $Database->real_escape_string($label);
    $ereference = $Database->real_escape_string(trim((string)$reference));
    $ecomment = $Database->real_escape_string(trim((string)$comment));
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";

    if ($Database->query("
        INSERT INTO organization_account_entry
        (id_school, id_organization, movement_type, amount, movement_date, label, reference, comment, id_actor)
        VALUES
        ($id_school, $id_organization, '$etype', $amount, '$edate', '$elabel', '$ereference', '$ecomment', $actor)
    ") === false)
        return (false);
    return ((int)$Database->insert_id);
}

function billing_update_organization_entry($id_entry, $id_school, $id_organization, $movement_type, $amount, $movement_date, $label, $reference = "", $comment = "")
{
    global $Database;

    $id_entry = (int)$id_entry;
    $id_school = (int)$id_school;
    $id_organization = (int)$id_organization;
    $amount = (int)$amount;
    if ($id_entry <= 0 || $id_school <= 0 || $id_organization <= 0 || $amount <= 0 ||
        !in_array($movement_type, ["debit", "credit"], true))
        return (false);
    if (!is_billing_manager_for_organization_account_entry($id_entry) ||
        !is_billing_manager_for_school($id_school) ||
        !billing_organization_exists($id_organization))
        return (false);

    $movement_date = db_form_date($movement_date);
    if (date_to_timestamp($movement_date) === NULL)
        return (false);
    $label = trim((string)$label);
    if ($label == "")
        return (false);

    $etype = $Database->real_escape_string($movement_type);
    $edate = $Database->real_escape_string($movement_date);
    $elabel = $Database->real_escape_string($label);
    $ereference = $Database->real_escape_string(trim((string)$reference));
    $ecomment = $Database->real_escape_string(trim((string)$comment));
    return ($Database->query("
        UPDATE organization_account_entry SET
            id_school = $id_school,
            id_organization = $id_organization,
            movement_type = '$etype',
            amount = $amount,
            movement_date = '$edate',
            label = '$elabel',
            reference = '$ereference',
            comment = '$ecomment',
            updated_at = NOW()
        WHERE id = $id_entry AND deleted IS NULL
    ") !== false);
}


function billing_delete_organization_entry($id_entry)
{
    global $Database;

    $id_entry = (int)$id_entry;
    if ($id_entry <= 0 || !is_billing_manager_for_organization_account_entry($id_entry))
        return (false);
    $entry = billing_fetch_organization_account_entry($id_entry);
    if ($entry == NULL)
        return (false);
    if ($Database->query("UPDATE organization_account_entry SET deleted = NOW(), updated_at = NOW() WHERE id = $id_entry AND deleted IS NULL") === false)
        return (false);
    $path = billing_organization_entry_document_path($entry);
    if ($path != "" && is_file($path))
        @unlink($path);
    return (true);
}

function billing_fetch_students($include_hidden = false)
{
    global $Language;

    $student_authority = user_school_student_authority_sql();
    $hidden_filter = $include_hidden ? "" : " AND COALESCE(user.billing_hidden, 0) = 0 ";
    $students = db_select_all("
        DISTINCT user.id,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        NULL as deleted,
        COALESCE(user.billing_hidden, 0) as billing_hidden,
        school.codename as school_codename,
        COALESCE(NULLIF(organization.{$Language}_name, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), school.codename) as school_name
        FROM user_school
        LEFT JOIN user ON user.id = user_school.id_user
        LEFT JOIN school ON school.id = user_school.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE user_school.authority = $student_authority
          AND user.deleted IS NULL
          AND school.deleted IS NULL
          $hidden_filter
        ".billing_school_filter("user_school")."
        ORDER BY school.codename, user.family_name, user.first_name, user.codename
    ");
    foreach ($students as &$student)
    {
        $student["tariff_year"] = billing_tariff_year_for_user($student["id"]);
        $student["account"] = billing_account_for_user($student["id"]);
        $student["entries"] = billing_fetch_entries($student["id"]);
        $student["payments"] = billing_fetch_payments($student["id"]);
        $student["account_statement"] = billing_account_statement($student["entries"], $student["payments"], $student["id"]);
    }
    return ($students);
}

function billing_fetch_templates()
{
    global $Language;

    return (db_select_all("
        billing_template.*,
        school.codename as school_codename,
        COALESCE(NULLIF(organization.{$Language}_name, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), school.codename) as school_name
        FROM billing_template
        LEFT JOIN school ON school.id = billing_template.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE billing_template.deleted IS NULL
        ".billing_school_filter("billing_template")."
        ORDER BY school.codename, billing_template.tariff_year ASC, billing_template.name
    "));
}

function billing_fetch_templates_for_school($id_school, $invoice_type = "school")
{
    global $Language;

    $id_school = (int)$id_school;
    $invoice_type = db_escape((string)$invoice_type);
    if ($id_school <= 0)
        return ([]);
    return (db_select_all("
        billing_template.*,
        school.codename as school_codename,
        COALESCE(NULLIF(organization.{$Language}_name, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), school.codename) as school_name
        FROM billing_template
        LEFT JOIN school ON school.id = billing_template.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE billing_template.deleted IS NULL
          AND billing_template.id_school = $id_school
          AND billing_template.invoice_type = '$invoice_type'
        ORDER BY billing_template.tariff_year ASC, billing_template.name, billing_template.id ASC
    "));
}

function billing_admission_amounts($template, $is_foreign)
{
    if (!is_array($template))
        return (NULL);

    // amount_once is the tuition itself. registration_fee is kept separate in
    // the billing grid, so the total price is the sum of both. For foreign
    // students, one sixth of tuition is paid in advance together with the
    // registration fee; it is an advance, not an additional charge.
    $registration_fee = max(0, (int)($template["registration_fee"] ?? 0));
    $tuition = max(0, (int)($template["amount_once"] ?? 0));
    $foreign_advance = $is_foreign ? (int)round($tuition / 6) : 0;
    $due_at_registration = $registration_fee + $foreign_advance;
    $tuition_balance = max(0, $tuition - $foreign_advance);
    $total_price = $registration_fee + $tuition;

    return ([
        "registration_fee_cents" => $registration_fee,
        "tuition_cents" => $tuition,
        "foreign_advance_cents" => $foreign_advance,
        "due_at_registration_cents" => $due_at_registration,
        "tuition_balance_cents" => $tuition_balance,
        "total_price_cents" => $total_price,
        "registration_fee" => billing_euros($registration_fee),
        "tuition" => billing_euros($tuition),
        "foreign_advance" => billing_euros($foreign_advance),
        "due_at_registration" => billing_euros($due_at_registration),
        "tuition_balance" => billing_euros($tuition_balance),
        "total_price" => billing_euros($total_price),
    ]);
}

function billing_fetch_entries($id_user)
{
    $id_user = (int)$id_user;
    return (db_select_all("
        *
        FROM billing_entry
        WHERE id_user = $id_user
          AND deleted IS NULL
        ORDER BY due_date ASC, id ASC
    "));
}

function billing_fetch_payments($id_user)
{
    $id_user = (int)$id_user;
    return (db_select_all("
        *
        FROM billing_payment
        WHERE id_user = $id_user
          AND id_organization IS NULL
          AND deleted IS NULL
        ORDER BY payment_date ASC, id ASC
    "));
}


function billing_fetch_student_credit_notes()
{
    global $Language;

    return (db_select_all("
        billing_entry.*,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        school.codename as school_codename,
        COALESCE(NULLIF(organization.{$Language}_name, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), school.codename) as school_name
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        WHERE billing_entry.sent_date IS NOT NULL
          AND billing_entry.id_user IS NOT NULL
          AND billing_entry.entry_type = 'credit_note'
          AND billing_entry.deleted IS NULL
        ".billing_school_filter("billing_entry")."
        ORDER BY billing_entry.sent_date DESC, billing_entry.id DESC
    "));
}

function billing_fetch_issued_invoices()
{
    global $Language;

    $invoices = db_select_all("
        billing_entry.*,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        user.billing_hidden,
        school.codename as school_codename,
        COALESCE(NULLIF(school_organization.{$Language}_name, ''), NULLIF(school_organization.name, ''), NULLIF(school_organization.legal_name, ''), school.codename) as school_name,
        client_organization.codename as organization_codename,
        client_organization.mail as organization_mail,
        COALESCE(NULLIF(client_organization.{$Language}_name, ''), NULLIF(client_organization.name, ''), NULLIF(client_organization.legal_name, ''), client_organization.codename) as organization_name,
        billing_template.name as template_name,
        billing_template.tariff_year as template_tariff_year
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
        LEFT JOIN organization school_organization ON school_organization.id = school.id_organization
        LEFT JOIN organization client_organization ON client_organization.id = billing_entry.id_organization
        LEFT JOIN billing_template ON billing_template.id = billing_entry.id_template
        WHERE (
            billing_entry.sent_date IS NOT NULL
            OR (
                billing_entry.sent_date IS NULL
                AND billing_entry.deleted IS NULL
                AND billing_entry.id_user IS NULL
                AND billing_entry.id_organization IS NOT NULL
            )
        )
        ".billing_school_filter("billing_entry")."
        ORDER BY COALESCE(billing_entry.sent_date, billing_entry.created_at) DESC, billing_entry.id DESC
    ");

    $user_coverages = [];
    $organization_coverages = [];
    foreach ($invoices as &$invoice)
    {
        $id_user = (int)($invoice["id_user"] ?? 0);
        $id_organization = (int)($invoice["id_organization"] ?? 0);
        $id_school = (int)($invoice["id_school"] ?? 0);
        $invoice["covered_amount"] = 0;
        if ($id_organization > 0)
        {
            $key = $id_school.":".$id_organization;
            if (!isset($organization_coverages[$key]))
                $organization_coverages[$key] = billing_payment_coverage_for_organization($id_organization, $id_school);
            $invoice["covered_amount"] = $organization_coverages[$key][(int)$invoice["id"]] ?? 0;
        }
        else if ($id_user > 0)
        {
            if (!isset($user_coverages[$id_user]))
                $user_coverages[$id_user] = billing_payment_coverage_for_user($id_user);
            $invoice["covered_amount"] = $user_coverages[$id_user][(int)$invoice["id"]] ?? 0;
        }
        $invoice["credit_capacity"] = 0;
        if (empty($invoice["deleted"]) && !billing_is_credit_note($invoice) && !empty($invoice["sent_date"]) && (int)$invoice["amount"] > 0)
            $invoice["credit_capacity"] = max(0, (int)$invoice["amount"] - billing_credit_note_total_for_entry((int)$invoice["id"], false));
        $invoice["status_key"] = empty($invoice["sent_date"]) ? "draft" : "issued";
        if (!empty($invoice["deleted"]))
            $invoice["status_key"] = "deleted";
        else if (billing_is_credit_note($invoice) && !empty($invoice["sent_date"]))
            $invoice["status_key"] = "credit_note";
        else if (!empty($invoice["paid_date"]))
            $invoice["status_key"] = "paid";
        else if (!empty($invoice["sent_date"]) && $invoice["covered_amount"] >= (int)$invoice["amount"])
            $invoice["status_key"] = "covered";
        else if (!empty($invoice["sent_date"]) && $invoice["covered_amount"] > 0)
            $invoice["status_key"] = "partial";
    }
    unset($invoice);
    return ($invoices);
}

function billing_schedule_month_offsets($schedule)
{
    switch ($schedule)
    {
    case "once":
        return ([0]);
    case "twice_two_months":
        return ([0, 2]);
    case "four_with_gap":
        return ([0, 2, 4, 6]);
    case "twelve_monthly":
        return ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
    default:
        return ([]);
    }
}

function billing_schedule_amount_field($schedule)
{
    switch ($schedule)
    {
    case "once":
        return ("amount_once");
    case "twice_two_months":
        return ("amount_twice");
    case "four_with_gap":
        return ("amount_four");
    case "twelve_monthly":
        return ("amount_twelve");
    default:
        return (NULL);
    }
}

function billing_template_installment_amount($template, $schedule)
{
    $field = billing_schedule_amount_field($schedule);

    if ($field == NULL || !isset($template[$field]))
        return (0);
    return ((int)$template[$field]);
}

function billing_registration_fee_label($template)
{
    global $Dictionnary;

    $name = trim((string)($template["name"] ?? ""));
    if ($name == "")
        return ($Dictionnary["RegistrationFees"]);
    return ($name." - ".$Dictionnary["RegistrationFees"]);
}

function billing_user_has_registration_fee($id_user, $id_school)
{
    $id_user = (int)$id_user;
    $id_school = (int)$id_school;

    if ($id_user <= 0 || $id_school <= 0)
        return (false);
    return (db_select_one("
        id
        FROM billing_entry
        WHERE id_user = $id_user
          AND id_school = $id_school
          AND entry_type = 'registration_fee'
          AND deleted IS NULL
    ") != NULL);
}

function billing_schedule_labels()
{
    global $Dictionnary;

    return ([
        "once" => $Dictionnary["BillingScheduleOnce"],
        "twice_two_months" => $Dictionnary["BillingScheduleTwiceTwoMonths"],
        "four_with_gap" => $Dictionnary["BillingScheduleFourWithGap"],
        "twelve_monthly" => $Dictionnary["BillingScheduleTwelveMonthly"],
    ]);
}

function billing_schedule_short_labels()
{
    global $Dictionnary;

    return ([
        "once" => $Dictionnary["BillingScheduleOnceShort"],
        "twice_two_months" => $Dictionnary["BillingScheduleTwiceShort"],
        "four_with_gap" => $Dictionnary["BillingScheduleFourShort"],
        "twelve_monthly" => $Dictionnary["BillingScheduleTwelveShort"],
    ]);
}


function billing_invoice_types()
{
    global $Dictionnary;

    return ([
        "school" => $Dictionnary["BillingInvoiceTypeSchool"] ?? "École",
        "of" => $Dictionnary["BillingInvoiceTypeOF"] ?? "OF",
        "cfa" => $Dictionnary["BillingInvoiceTypeCFA"] ?? "CFA",
        "other" => $Dictionnary["BillingInvoiceTypeOther"] ?? "Autre",
    ]);
}

function billing_invoice_type_key($type)
{
    $type = strtolower(trim((string)$type));
    if ($type == "ecole")
        $type = "school";
    return (array_key_exists($type, billing_invoice_types()) ? $type : NULL);
}

function billing_normalize_invoice_type($type)
{
    return (billing_invoice_type_key($type) ?? "school");
}

function billing_invoice_type_is_allowed_for_school($school, $type)
{
    $type = billing_invoice_type_key($type);
    if ($type === NULL)
        return (false);
    if ($type === "other")
        return (true);
    if (!is_array($school))
    {
        $school = fetch_school((int)$school);
        if ($school instanceof ErrorResponse || !is_array($school))
            return (false);
    }
    return (school_activity_mode_allowed($school, $type));
}

function billing_invoice_types_for_school($school)
{
    $out = [];
    foreach (billing_invoice_types() as $type => $label)
        if (billing_invoice_type_is_allowed_for_school($school, $type))
            $out[$type] = $label;
    return ($out);
}

function billing_invoice_type_is_allowed_for_user($id_user, $type)
{
    $school = billing_user_main_school($id_user);
    return ($school != NULL && billing_invoice_type_is_allowed_for_school($school, $type));
}

function billing_invoice_type_label($type)
{
    $types = billing_invoice_types();
    $type = billing_normalize_invoice_type($type);
    return ($types[$type] ?? $type);
}

function billing_vat_rates()
{
    return ([0 => "0 %", 20 => "20 %"]);
}

function billing_normalize_vat_rate($rate)
{
    $rate = (int)$rate;
    return (array_key_exists($rate, billing_vat_rates()) ? $rate : 0);
}

function billing_amount_ttc_from_ht($amount_ht, $vat_rate)
{
    $amount_ht = (int)$amount_ht;
    $vat_rate = billing_normalize_vat_rate($vat_rate);
    return ((int)round($amount_ht * (100 + $vat_rate) / 100));
}

function billing_amount_ht_from_ttc($amount_ttc, $vat_rate)
{
    $amount_ttc = (int)$amount_ttc;
    $vat_rate = billing_normalize_vat_rate($vat_rate);
    return ((int)round($amount_ttc * 100 / (100 + $vat_rate)));
}

function billing_entry_amount_ht($entry)
{
    if (isset($entry["amount_ht"]) && (int)$entry["amount_ht"] != 0)
        return ((int)$entry["amount_ht"]);
    return (billing_amount_ht_from_ttc((int)($entry["amount"] ?? 0), $entry["vat_rate"] ?? 0));
}

function billing_invoice_vat_exemption($rate, $id_school = 0)
{
    if (billing_normalize_vat_rate($rate) != 0)
        return ("");

    $id_school = (int)$id_school;
    if ($id_school > 0 && function_exists("fetch_school"))
    {
        $school = fetch_school($id_school);
        if (is_array($school))
        {
            $mention = trim((string)($school["vat_exemption_mention"] ?? ""));
            if ($mention != "")
                return ($mention);
        }
    }
    return ("TVA exonérée — article 261-4-4°-a du CGI.");
}

function billing_installment_label_parts($entry)
{
    if (!is_array($entry) || ($entry["entry_type"] ?? "") !== "tuition" || empty($entry["id_template"]))
        return (NULL);

    $label = trim((string)($entry["label"] ?? ""));
    if (!preg_match('/^(.*?)\s*-\s*(?:échéance|echeance)\s+(\d+)\s*\/\s*(\d+)\s*$/iu', $label, $match))
        return (NULL);

    $index = (int)$match[2];
    $count = (int)$match[3];
    if ($count <= 1 || $index < 1 || $index > $count)
        return (NULL);
    return ([
        "base" => trim((string)$match[1]),
        "index" => $index,
        "count" => $count,
    ]);
}

function billing_installment_invoice_summary($entry)
{
    $parts = billing_installment_label_parts($entry);
    if ($parts == NULL)
        return (NULL);

    $current_amount = max(0, (int)($entry["amount"] ?? 0));
    if ($current_amount <= 0)
        return (NULL);

    // The invoice must stay a snapshot of the amount being billed, not become a
    // payment statement. Payment history and outstanding balance belong to the
    // dedicated schedule/account statement document. Keep only the contractual
    // tuition total here; registration fees are separate billing_entry rows.
    $tuition_total = $current_amount * $parts["count"];
    return ([
        "label" => $parts["base"]." - ".$parts["index"]."/".$parts["count"],
        "tuition_total_cents" => $tuition_total,
        "summary" =>
            "Montant total de la scolarité (hors frais d'inscription) : ".billing_euros($tuition_total),
    ]);
}

function billing_rib_details($value)
{
    $lines = preg_split('/(?:\r\n|\r|\n)/', (string)$value);
    $out = [];
    $started = false;
    foreach ($lines as $line)
    {
        $trim = trim((string)$line);
        if (!$started && preg_match('/^(RIB|IBAN|BIC|SWIFT|MOTIF|REFERENCE|RÉFÉRENCE|REF|TITULAIRE)\s*[:\-]/iu', $trim))
            $started = true;
        if ($started)
            $out[] = $line;
    }
    return ($started ? trim(implode("\n", $out)) : trim((string)$value));
}

function billing_add_entry($id_user, $label, $amount_ht, $due_date, $id_template = NULL, $entry_type = "tuition", $invoice_type = "school", $vat_rate = 0, $id_organization = NULL, $id_school = NULL)
{
    global $Database;
    global $User;

    $id_user = (int)$id_user;
    $id_organization = (int)$id_organization;
    $id_school = (int)$id_school;
    $amount_ht = (int)$amount_ht;
    if (($id_user <= 0 && $id_organization <= 0) || $amount_ht <= 0 || trim($label) == "")
        return (false);

    if ($id_user > 0)
    {
        if (!billing_user_is_managed($id_user))
            return (false);
        $school = billing_user_main_school($id_user);
        if ($school == NULL || ($id_school > 0 && $id_school !== (int)$school["id_school"]))
            return (false);
        $id_school = (int)$school["id_school"];
    }
    else
    {
        if ($id_school <= 0 || !is_billing_manager_for_school($id_school))
            return (false);
        $school = billing_school_context($id_school);
        if ($school == NULL)
            return (false);
    }
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (false);

    $label = $Database->real_escape_string(trim($label));
    $due_date = $Database->real_escape_string(db_form_date($due_date));
    $entry_type = trim((string)$entry_type);
    if ($entry_type == "")
        $entry_type = $id_user > 0 ? "tuition" : "service";
    $entry_type = $Database->real_escape_string($entry_type);
    $invoice_type = billing_invoice_type_key($invoice_type);
    if ($invoice_type === NULL || !billing_invoice_type_is_allowed_for_school($school, $invoice_type))
        return (false);
    $invoice_type = $Database->real_escape_string($invoice_type);
    $vat_rate = billing_normalize_vat_rate($vat_rate);
    $amount = billing_amount_ttc_from_ht($amount_ht, $vat_rate);
    $id_template = $id_template === NULL ? "NULL" : (int)$id_template;
    $id_user_sql = $id_user > 0 ? (string)$id_user : "NULL";
    $id_organization_sql = $id_organization > 0 ? (string)$id_organization : "NULL";
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    return ($Database->query("
        INSERT INTO billing_entry
        (id_user, id_organization, id_school, id_template, label, amount, amount_ht, entry_type, invoice_type, vat_rate, due_date, id_actor)
        VALUES
        ($id_user_sql, $id_organization_sql, $id_school, $id_template, '$label', $amount, $amount_ht, '$entry_type', '$invoice_type', $vat_rate, '$due_date', $actor)
    ") != NULL);
}


function billing_add_credit_note($related_entry_id, $label, $amount, $date)
{
    global $Database;
    global $User;

    $related_entry_id = (int)$related_entry_id;
    $amount = (int)$amount;
    if ($amount <= 0 || $related_entry_id <= 0)
        return (false);
    $related = billing_entry_with_user($related_entry_id);
    if ($related == NULL || !billing_entry_is_managed($related) || empty($related["sent_date"]) ||
        billing_is_credit_note($related) || (int)$related["amount"] <= 0)
        return (false);
    $already = billing_credit_note_total_for_entry($related_entry_id, false);
    if ($already + $amount > (int)$related["amount"])
        return (false);

    $label = trim((string)$label);
    if ($label == "")
        $label = "Avoir sur facture ".billing_invoice_document_reference($related);
    $label = $Database->real_escape_string($label);
    $date = $Database->real_escape_string(db_form_date($date));
    $invoice_type = $Database->real_escape_string(billing_normalize_invoice_type($related["invoice_type"] ?? "school"));
    $vat_rate = billing_normalize_vat_rate($related["vat_rate"] ?? 0);
    $amount_ht = billing_amount_ht_from_ttc($amount, $vat_rate);
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $negative = -$amount;
    $id_user = (int)($related["id_user"] ?? 0);
    $id_organization = (int)($related["id_organization"] ?? 0);
    $id_user_sql = $id_user > 0 ? (string)$id_user : "NULL";
    $id_organization_sql = $id_organization > 0 ? (string)$id_organization : "NULL";
    $buyer_routing_code = $Database->real_escape_string((string)($related["buyer_routing_code"] ?? ""));
    return ($Database->query("
        INSERT INTO billing_entry
        (id_user, id_organization, id_school, buyer_routing_code, id_template, label, amount, amount_ht, entry_type, related_entry_id, invoice_type, vat_rate, due_date, id_actor)
        VALUES
        ($id_user_sql, $id_organization_sql, ".(int)$related["id_school"].", '$buyer_routing_code', NULL, '$label', $negative, -$amount_ht, 'credit_note', $related_entry_id, '$invoice_type', $vat_rate, '$date', $actor)
    ") != NULL);
}


function billing_apply_template($id_user, $id_template, $first_due_date, $schedule, $id_organization = 0)
{
    $id_user = (int)$id_user;
    $id_template = (int)$id_template;
    $id_organization = (int)$id_organization;
    if (!billing_user_is_managed($id_user))
        return (0);
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (0);
    $template = db_select_one("* FROM billing_template WHERE id = $id_template AND deleted IS NULL");
    if ($template == NULL)
        return (0);
    if (!is_admin() && !in_array((int)$template["id_school"], billing_managed_school_ids()))
        return (0);
    $school = billing_user_main_school($id_user);
    if ($school == NULL || (int)$template["id_school"] !== (int)$school["id_school"] ||
        !billing_invoice_type_is_allowed_for_school($school, $template["invoice_type"] ?? "school"))
        return (0);

    $count = 0;
    if ((int)$template["registration_fee"] > 0 &&
        !billing_user_has_registration_fee($id_user, $template["id_school"]))
        if (billing_add_entry(
            $id_user,
            billing_registration_fee_label($template),
            $template["registration_fee"],
            $first_due_date,
            $id_template,
            "registration_fee",
            $template["invoice_type"] ?? "school",
            $template["vat_rate"] ?? 0,
            $id_organization,
            (int)$school["id_school"]
        ))
            ++$count;

    $offsets = billing_schedule_month_offsets($schedule);
    $amount = billing_template_installment_amount($template, $schedule);
    if (!count($offsets) || $amount <= 0)
        return ($count);
    foreach ($offsets as $idx => $offset)
    {
        $date = new DateTime(db_form_date($first_due_date));
        if ($offset > 0)
            $date->modify("+$offset months");
        if (billing_add_entry(
            $id_user,
            $template["name"]." - échéance ".($idx + 1)."/".count($offsets),
            $amount,
            $date->format("Y-m-d H:i:s"),
            $id_template,
            "tuition",
            $template["invoice_type"] ?? "school",
            $template["vat_rate"] ?? 0,
            $id_organization,
            (int)$school["id_school"]
        ))
            ++$count;
    }
    return ($count);
}

function billing_invoice_recipients($id_user, $id_buyer_organization = 0)
{
    $id_user = (int)$id_user;
    $id_buyer_organization = (int)$id_buyer_organization;

    // An organization-funded invoice has the organization as legal debtor.
    // Do not also send the invoice to the student or their financial
    // responsible: those people are beneficiaries/contacts, not invoice
    // recipients for this piece.
    if ($id_buyer_organization > 0)
        return (billing_organization_recipient_mails($id_buyer_organization));

    $mails = [];
    $usable_mail = function($mail) {
        $mail = trim((string)$mail);
        return ($mail != "" && strcasecmp($mail, "nomail") != 0);
    };
    foreach (db_select_all("mail FROM user WHERE id = $id_user AND mail IS NOT NULL AND mail != ''") as $m)
        if ($usable_mail($m["mail"] ?? ""))
            $mails[] = trim((string)$m["mail"]);

    $relations = array_values(array_filter(db_select_all("
        user.mail, parent_child.relation
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_parent
        WHERE parent_child.id_child = $id_user
          AND user.mail IS NOT NULL
          AND user.mail != ''
        ORDER BY parent_child.id ASC
    "), function($row) use ($usable_mail) {
        return ($usable_mail($row["mail"] ?? ""));
    }));
    $financial = array_values(array_filter($relations, function($row) {
        return (user_relation_has($row["relation"] ?? "", "financial"));
    }));
    // Si aucun responsable financier n'a été désigné, un responsable légal
    // reste un repli plus raisonnable que d'envoyer la facture à tous les contacts.
    if (!count($financial))
        $financial = array_values(array_filter($relations, function($row) {
            return (user_relation_has($row["relation"] ?? "", "legal"));
        }));
    foreach ($financial as $relation)
        $mails[] = trim((string)$relation["mail"]);
    return (array_values(array_unique($mails)));
}

function billing_invoice_draft_reference()
{
    return ("BROUILLON");
}

function billing_invoice_missing_reference()
{
    return ("SANS-REFERENCE");
}

function billing_invoice_reference_prefix()
{
    global $Configuration;

    $prefix = trim((string)($Configuration->Properties["billing_invoice_prefix"] ?? "INF"));
    $prefix = preg_replace('/[^A-Za-z0-9._-]+/', '', $prefix);
    $prefix = rtrim((string)$prefix, "-");
    if ($prefix == "")
        $prefix = "INF";
    return ($prefix);
}

function billing_next_invoice_reference()
{
    $prefix = billing_invoice_reference_prefix();
    $used = [];
    foreach (db_select_all("
        invoice_reference
        FROM billing_entry
        WHERE deleted IS NULL
          AND invoice_reference IS NOT NULL
          AND invoice_reference != ''
    ") as $row)
    {
        $reference = trim((string)($row["invoice_reference"] ?? ""));
        if (!preg_match('/^'.preg_quote($prefix, '/').'-([0-9]+)$/i', $reference, $match))
            continue ;
        $number = (int)$match[1];
        if ($number > 0)
            $used[$number] = true;
    }

    for ($number = 1; isset($used[$number]); ++$number)
        ;
    return ($prefix."-".str_pad((string)$number, 4, "0", STR_PAD_LEFT));
}

function billing_invoice_document_reference($entry)
{
    if (empty($entry["sent_date"]))
        return (billing_invoice_draft_reference());

    $reference = trim((string)($entry["invoice_reference"] ?? ""));
    if ($reference == "" && !empty($entry["deleted"]))
        $reference = trim((string)($entry["deleted_invoice_reference"] ?? ""));
    if ($reference != "")
        return ($reference);
    return (billing_invoice_missing_reference());
}

function billing_apply_template_remaining($id_user, $id_template, $first_due_date, $schedule, $deduct_entry_ids, $id_organization = 0)
{
    global $Database;

    $id_user = (int)$id_user;
    $id_template = (int)$id_template;
    $id_organization = (int)$id_organization;
    $deduct_entry_ids = array_values(array_unique(array_filter(array_map("intval", (array)$deduct_entry_ids))));
    if ($id_user <= 0 || $id_template <= 0 || !count($deduct_entry_ids) || !billing_user_is_managed($id_user))
        return (["ok" => false, "error" => "BillingRemainingScheduleSelectInvoice"]);
    $school = billing_user_main_school($id_user);
    if ($school == NULL)
        return (["ok" => false, "error" => "NotFound"]);
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (["ok" => false, "error" => "NotFound"]);

    if ($Database->query("START TRANSACTION") === NULL)
        return (["ok" => false, "error" => "CannotRegister"]);

    $deducted = 0;
    foreach ($deduct_entry_ids as $id_entry)
    {
        $entry = billing_entry_with_user($id_entry);
        if ($entry == NULL || (int)$entry["id_user"] !== $id_user || !empty($entry["deleted"]) ||
            billing_is_credit_note($entry) || (int)$entry["amount"] <= 0)
        {
            $Database->query("ROLLBACK");
            return (["ok" => false, "error" => "BillingRemainingScheduleInvalidInvoice"]);
        }
        if (empty($entry["sent_date"]) &&
            !billing_invoice_type_is_allowed_for_school($school, $entry["invoice_type"] ?? "school"))
        {
            $Database->query("ROLLBACK");
            return (["ok" => false, "error" => "BillingInvoiceTypeUnavailable"]);
        }

        // The selected entry stays untouched and represents the part already
        // prepared before creating the remaining schedule. It may therefore be an
        // issued invoice or a draft. Issued credit notes reduce an issued amount.
        $effective = max(0, (int)$entry["amount"] - billing_credit_note_total_for_entry($id_entry, true));
        if ($effective <= 0)
        {
            $Database->query("ROLLBACK");
            return (["ok" => false, "error" => "BillingRemainingScheduleInvalidInvoice"]);
        }
        $deducted += $effective;
    }

    $before = db_select_one("COALESCE(MAX(id), 0) AS id FROM billing_entry WHERE id_user = $id_user");
    if ($before == NULL)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotRegister"]);
    }
    $before_id = (int)$before["id"];

    // Reuse the normal template engine so the amount and date rules for once,
    // 2x, 4x and 12x stay defined in one place.
    $created_count = billing_apply_template($id_user, $id_template, $first_due_date, $schedule, $id_organization);
    if ($created_count <= 0)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotRegister"]);
    }

    $created = db_select_all("\n        *\n        FROM billing_entry\n        WHERE id_user = $id_user\n          AND id_template = $id_template\n          AND id > $before_id\n          AND sent_date IS NULL\n          AND deleted IS NULL\n        ORDER BY due_date ASC, id ASC\n    ");
    if (!is_array($created) || count($created) !== (int)$created_count)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotRegister"]);
    }

    $target_total = 0;
    foreach ($created as $entry)
        $target_total += max(0, (int)$entry["amount"]);
    if ($deducted > $target_total)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "BillingRemainingScheduleExceedsTarget"]);
    }

    // Consume the already-invoiced amount from the beginning of the new
    // schedule. Entirely covered drafts disappear; the boundary draft is
    // reduced to the exact TTC remainder, then later drafts stay untouched.
    $to_consume = $deducted;
    $kept_count = (int)$created_count;
    foreach ($created as $entry)
    {
        if ($to_consume <= 0)
            break ;
        $amount = max(0, (int)$entry["amount"]);
        $id_entry = (int)$entry["id"];

        if ($to_consume >= $amount)
        {
            if ($Database->query("DELETE FROM billing_entry WHERE id = $id_entry AND sent_date IS NULL") === NULL)
            {
                $Database->query("ROLLBACK");
                return (["ok" => false, "error" => "CannotRegister"]);
            }
            $to_consume -= $amount;
            --$kept_count;
            continue ;
        }

        $remaining_ttc = $amount - $to_consume;
        $remaining_ht = billing_amount_ht_from_ttc($remaining_ttc, $entry["vat_rate"] ?? 0);
        if ($remaining_ttc <= 0 || $remaining_ht <= 0 ||
            db_update_one("billing_entry", $id_entry, ["amount" => $remaining_ttc, "amount_ht" => $remaining_ht]) === NULL)
        {
            $Database->query("ROLLBACK");
            return (["ok" => false, "error" => "CannotRegister"]);
        }
        $to_consume = 0;
    }

    if ($to_consume != 0 || $Database->query("COMMIT") === NULL)
    {
        $Database->query("ROLLBACK");
        return (["ok" => false, "error" => "CannotRegister"]);
    }

    return ([
        "ok" => true,
        "deducted" => $deducted,
        "target" => $target_total,
        "remaining" => max(0, $target_total - $deducted),
        "created" => max(0, $kept_count),
    ]);
}

function billing_is_external_invoice($entry)
{
    return (is_array($entry) && (($entry["entry_type"] ?? "") === "external_invoice"));
}


function billing_is_credit_note($entry)
{
    return (is_array($entry) && (($entry["entry_type"] ?? "") === "credit_note"));
}

function billing_entry_document_label($entry)
{
    global $Dictionnary;

    return (billing_is_credit_note($entry)
        ? ($Dictionnary["BillingCreditNote"] ?? "Avoir")
        : ($Dictionnary["BillingInvoice"] ?? "Facture"));
}

function billing_credit_note_total_for_entry($id_entry, $issued_only = false)
{
    $id_entry = (int)$id_entry;
    if ($id_entry <= 0)
        return (0);
    $issued_filter = $issued_only ? " AND sent_date IS NOT NULL " : "";
    $row = db_select_one("
        COALESCE(SUM(-amount), 0) as amount
        FROM billing_entry
        WHERE related_entry_id = $id_entry
          AND entry_type = 'credit_note'
          AND amount < 0
          AND deleted IS NULL
          $issued_filter
    ");
    return ((int)($row["amount"] ?? 0));
}

function billing_positive_invoices_for_user($id_user, $issued_only = false)
{
    $id_user = (int)$id_user;
    $issued_filter = $issued_only ? " AND sent_date IS NOT NULL " : "";
    return (db_select_all("
        *
        FROM billing_entry
        WHERE id_user = $id_user
          AND id_organization IS NULL
          AND deleted IS NULL
          AND amount > 0
          AND entry_type != 'credit_note'
          $issued_filter
        ORDER BY due_date ASC, id ASC
    "));
}

function billing_positive_invoices_for_student($id_user, $issued_only = false)
{
    $id_user = (int)$id_user;
    $issued_filter = $issued_only ? " AND sent_date IS NOT NULL " : "";
    return (db_select_all("
        *
        FROM billing_entry
        WHERE id_user = $id_user
          AND deleted IS NULL
          AND amount > 0
          AND entry_type != 'credit_note'
          $issued_filter
        ORDER BY due_date ASC, id ASC
    "));
}


function billing_positive_invoices_for_organization($id_organization, $id_school = 0, $issued_only = false)
{
    $id_organization = (int)$id_organization;
    $id_school = (int)$id_school;
    if ($id_organization <= 0)
        return ([]);
    $issued_filter = $issued_only ? " AND sent_date IS NOT NULL " : "";
    $school_filter = $id_school > 0 ? " AND id_school = $id_school " : "";
    return (db_select_all("
        *
        FROM billing_entry
        WHERE id_organization = $id_organization
          AND deleted IS NULL
          AND amount > 0
          AND entry_type != 'credit_note'
          $issued_filter
          $school_filter
        ORDER BY due_date ASC, id ASC
    "));
}

function billing_payment_schedule_for_user($id_user)
{
    $id_user = (int)$id_user;
    $tariff = billing_tariff_year_for_user($id_user);
    $current_year = max(0, (int)($tariff["year"] ?? 0));
    $direct_payments = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_user = $id_user
          AND id_organization IS NULL
          AND deleted IS NULL
    ");
    $direct_available = max(0, (int)($direct_payments["amount"] ?? 0));
    $organization_coverage = [];
    $rows = [];
    $prior_remaining = 0;
    $current_total = 0;
    $current_paid = 0;
    $current_remaining = 0;

    // This schedule follows the student's tuition. The payer can vary from one
    // instalment to another. Direct student/family payments keep the historical
    // FIFO behaviour (including advances on drafts); organization-funded lines
    // use the corresponding organization account, without ever charging that
    // cash to the student's own payer account.
    $entries = db_select_all("
        billing_entry.*,
        COALESCE(billing_template.tariff_year, 0) as template_tariff_year
        FROM billing_entry
        LEFT JOIN billing_template ON billing_template.id = billing_entry.id_template
        WHERE billing_entry.id_user = $id_user
          AND billing_entry.deleted IS NULL
          AND billing_entry.amount > 0
          AND billing_entry.entry_type != 'credit_note'
        ORDER BY billing_entry.due_date ASC, billing_entry.id ASC
    ");
    foreach ($entries as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        $credit = min($amount, billing_credit_note_total_for_entry((int)$entry["id"], true));
        $effective = max(0, $amount - $credit);
        $id_organization = (int)($entry["id_organization"] ?? 0);
        $id_school = (int)($entry["id_school"] ?? 0);

        if ($id_organization > 0 && $id_school > 0)
        {
            $key = $id_school.":".$id_organization;
            if (!isset($organization_coverage[$key]))
                $organization_coverage[$key] = billing_payment_coverage_for_organization($id_organization, $id_school);
            $covered = min($amount, (int)($organization_coverage[$key][(int)$entry["id"]] ?? 0));
            $cash = max(0, min($effective, $covered - $credit));
        }
        else
        {
            $cash = min($effective, $direct_available);
            $direct_available -= $cash;
        }
        $remaining = $effective - $cash;
        $entry_year = max(0, (int)($entry["template_tariff_year"] ?? 0));

        if ($current_year > 0 && $entry_year > 0 && $entry_year < $current_year)
        {
            $prior_remaining += $remaining;
            continue ;
        }
        if ($current_year > 0 && $entry_year > $current_year)
            continue ;

        $current_total += $effective;
        $current_paid += $cash;
        $current_remaining += $remaining;
        $rows[] = [
            "id" => (int)$entry["id"],
            "date" => $entry["due_date"],
            "label" => $entry["label"],
            "reference" => !empty($entry["sent_date"]) ? billing_invoice_document_reference($entry) : "",
            "issued" => !empty($entry["sent_date"]),
            "amount" => $effective,
            "paid" => $cash,
            "remaining" => $remaining,
            "id_organization" => $id_organization,
            "payer" => $id_organization > 0 ? billing_entry_organization_name($entry) : "",
        ];
    }

    return ([
        "year" => $current_year,
        "prior_remaining" => $prior_remaining,
        "current_total" => $current_total,
        "current_paid" => $current_paid,
        "current_remaining" => $current_remaining,
        "total_remaining" => $prior_remaining + $current_remaining,
        "rows" => $rows,
    ]);
}


function billing_payment_schedule_document_fields($id_user)
{
    global $Dictionnary;

    $id_user = (int)$id_user;
    $schedule = billing_payment_schedule_for_user($id_user);
    $rows = $schedule["rows"] ?? [];
    $fields = [
        "GeneratedDate" => datex("d/m/Y"),
        "CurrentYear" => (int)($schedule["year"] ?? 0) > 0 ? (string)(int)$schedule["year"] : "",
        "CurrentTotal" => billing_euros($schedule["current_total"] ?? 0),
        "CurrentPaid" => billing_euros($schedule["current_paid"] ?? 0),
        "CurrentRemaining" => billing_euros($schedule["current_remaining"] ?? 0),
        "PriorRemaining" => (int)($schedule["prior_remaining"] ?? 0) > 0
            ? billing_euros($schedule["prior_remaining"]) : "",
        "TotalRemaining" => billing_euros($schedule["total_remaining"] ?? 0),
    ];
    $cell = static function ($value) {
        $value = preg_replace('/\s+/u', ' ', trim((string)$value));
        return (str_replace('|', '\\|', $value));
    };
    $visible_count = count($rows) > 24 ? 23 : count($rows);
    foreach (array_slice($rows, 0, $visible_count) as $index => $row)
    {
        $slot = sprintf("%02d", $index + 1);
        $status = !empty($row["issued"])
            ? (($row["reference"] ?? "") != "" ? $row["reference"] : ($Dictionnary["BillingInvoice"] ?? "Facture"))
            : ($Dictionnary["BillingPlanned"] ?? "À facturer");
        $fields["Row".$slot] = "1";
        $fields["Date".$slot] = "**".$cell(billing_document_date_label($row["date"]))."**";
        $payer = trim((string)($row["payer"] ?? ""));
        if ($payer == "")
            $payer = $Dictionnary["BillingReminderFinancialResponsible"] ?? "Responsable financier";
        $fields["Label".$slot] = $cell($row["label"] ?? "")."\\newline ".
            ($Dictionnary["BillingPayer"] ?? "Payeur")." : ".$cell($payer);
        $fields["Invoice".$slot] = $cell($status);
        $fields["Amount".$slot] = billing_euros($row["amount"] ?? 0);
        $fields["Situation".$slot] = "Déjà versé : ".billing_euros($row["paid"] ?? 0).
            "\\newline Reste : ".billing_euros($row["remaining"] ?? 0);
    }
    if (count($rows) > 24)
    {
        $extra_remaining = array_sum(array_map(fn($row) => (int)$row["remaining"], array_slice($rows, 23)));
        $extra_paid = array_sum(array_map(fn($row) => (int)$row["paid"], array_slice($rows, 23)));
        $extra_total = array_sum(array_map(fn($row) => (int)$row["amount"], array_slice($rows, 23)));
        $fields["Row24"] = "1";
        $fields["Date24"] = "";
        $fields["Label24"] = "**+ ".(count($rows) - 23)." ".
            ($Dictionnary["BillingOtherInstallments"] ?? "autres échéances")."**";
        $fields["Invoice24"] = "—";
        $fields["Amount24"] = billing_euros($extra_total);
        $fields["Situation24"] = "Déjà versé : ".billing_euros($extra_paid).
            "\\newline Reste : ".billing_euros($extra_remaining);
    }
    return ($fields);
}

function billing_payment_schedule_recipient_mail($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ("");
    $row = db_select_one("mail FROM user WHERE id = $id_user AND authority != -1");
    if (!is_array($row))
        return ("");
    $mail = trim((string)($row["mail"] ?? ""));
    if ($mail == "" || strcasecmp($mail, "nomail") == 0 || !filter_var($mail, FILTER_VALIDATE_EMAIL))
        return ("");
    return ($mail);
}

function billing_send_payment_schedule_document($id_student, $id_finance, $filename, $content)
{
    global $Dictionnary;

    $id_student = (int)$id_student;
    $id_finance = (int)$id_finance;
    if ($id_student <= 0 || $id_finance <= 0 || !is_string($content) || substr($content, 0, 4) != "%PDF")
        return (["error" => "CannotSendMail"]);

    $student_mail = billing_payment_schedule_recipient_mail($id_student);
    $finance_mail = billing_payment_schedule_recipient_mail($id_finance);
    if ($student_mail == "" || $finance_mail == "")
        return (["error" => "CannotSendMail"]);

    $recipients = [$student_mail];
    if (strcasecmp($finance_mail, $student_mail) != 0)
        $recipients[] = $finance_mail;

    $student = db_select_one("codename, first_name, family_name FROM user WHERE id = $id_student AND authority != -1");
    if (!is_array($student))
        return (["error" => "CannotSendMail"]);
    $student_name = trim((string)($student["first_name"] ?? "")." ".(string)($student["family_name"] ?? ""));
    if ($student_name == "")
        $student_name = trim((string)($student["codename"] ?? ""));

    $subject = sprintf(
        $Dictionnary["BillingPaymentScheduleMailSubject"] ?? "Échéancier des paiements — %s",
        $student_name
    );
    $body = ($Dictionnary["BillingMailGreeting"] ?? "Bonjour,")."\n\n".
        sprintf(
            $Dictionnary["BillingPaymentScheduleMailIntro"] ?? "Veuillez trouver en pièce jointe l'échéancier des paiements restant à effectuer concernant %s.",
            $student_name
        )."\n\n".
        ($Dictionnary["BillingMailRegards"] ?? "Cordialement,")."\n";
    $school = billing_user_main_school($id_student);
    if (is_array($school) && trim((string)($school["school_codename"] ?? "")) != "")
        $body .= "\n".strtoupper(trim((string)$school["school_codename"]))."\n";

    $filename = basename(trim((string)$filename));
    if ($filename == "")
        $filename = "echeancier_paiements.pdf";
    $mail = send_mail(
        $recipients,
        $subject,
        $body,
        NULL,
        [$filename => $content],
        false
    );
    if ($mail->is_error())
        return (["error" => "CannotSendMail"]);
    return (["recipients" => count($recipients)]);
}

function billing_invoice_safe_filename($reference)
{
    $reference = trim((string)$reference);
    if ($reference == "")
        $reference = "invoice";
    $reference = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $reference);
    $reference = trim($reference, "_.-");
    if ($reference == "")
        $reference = "invoice";
    return ($reference.".pdf");
}

function billing_invoice_existing_pdf_filename($entry, $reference)
{
    $filename = trim((string)($entry["invoice_filename"] ?? ""));

    if ($filename != "" && preg_match('/\.pdf$/i', $filename))
        return ($filename);
    return (billing_invoice_safe_filename($reference));
}

function billing_entry_with_user($id_entry, $include_deleted = false)
{
    global $Language;

    $id_entry = (int)$id_entry;
    $deleted_filter = $include_deleted ? "" : "AND billing_entry.deleted IS NULL";
    return (db_select_one("
        billing_entry.*,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        school.codename as school_codename,
        client_organization.codename as organization_codename,
        client_organization.mail as organization_mail,
        COALESCE(NULLIF(client_organization.{$Language}_name, ''), NULLIF(client_organization.name, ''), NULLIF(client_organization.legal_name, ''), client_organization.codename) as organization_name
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
        LEFT JOIN organization client_organization ON client_organization.id = billing_entry.id_organization
        WHERE billing_entry.id = $id_entry
          $deleted_filter
    "));
}

function billing_payment_with_user($id_payment)
{
    global $Language;

    $id_payment = (int)$id_payment;
    return (db_select_one("
        billing_payment.*,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        client_organization.codename as organization_codename,
        client_organization.mail as organization_mail,
        COALESCE(NULLIF(client_organization.{$Language}_name, ''), NULLIF(client_organization.name, ''), NULLIF(client_organization.legal_name, ''), client_organization.codename) as organization_name
        FROM billing_payment
        LEFT JOIN user ON user.id = billing_payment.id_user
        LEFT JOIN organization client_organization ON client_organization.id = billing_payment.id_organization
        WHERE billing_payment.id = $id_payment
          AND billing_payment.deleted IS NULL
    "));
}

function billing_entry_student_name($entry)
{
    if (!is_array($entry) || (int)($entry["id_user"] ?? 0) <= 0)
        return ("");
    $name = trim((string)($entry["first_name"] ?? "")." ".(string)($entry["family_name"] ?? ""));
    if ($name == "")
        $name = trim((string)($entry["codename"] ?? ""));
    return ($name);
}

function billing_entry_organization_name($entry)
{
    if (!is_array($entry) || (int)($entry["id_organization"] ?? 0) <= 0)
        return ("");
    $name = trim((string)($entry["organization_name"] ?? ""));
    if ($name == "")
        $name = trim((string)($entry["organization_codename"] ?? ""));
    if ($name == "")
    {
        global $Language;
        $field = in_array($Language, ["fr", "en"], true) ? $Language."_name" : "name";
        $row = db_select_one("
            codename,
            COALESCE(NULLIF($field, ''), NULLIF(name, ''), NULLIF(legal_name, ''), codename) as organization_name
            FROM organization
            WHERE id = ".(int)$entry["id_organization"]."
              AND deleted IS NULL
        ");
        if ($row != NULL)
            $name = trim((string)($row["organization_name"] ?? $row["codename"] ?? ""));
    }
    return ($name);
}

function billing_entry_client_name($entry)
{
    $organization = billing_entry_organization_name($entry);
    if ($organization != "")
        return ($organization);
    return (billing_entry_student_name($entry));
}

function billing_invoice_text($entry, $reference)
{
    global $Dictionnary;

    $piece = billing_entry_document_label($entry);
    $amount = billing_is_credit_note($entry) ? abs((int)$entry["amount"]) : (int)$entry["amount"];
    $body = $piece." : ".$reference."\n";
    $student = billing_entry_student_name($entry);
    $organization = billing_entry_organization_name($entry);
    if ($student != "")
        $body .= $Dictionnary["Student"]." : ".$student."\n";
    if ($organization != "")
        $body .= ($Dictionnary["BillingCustomerOrganization"] ?? "Client / organisation")." : ".$organization."\n";
    $body .=
        $Dictionnary["BillingLabel"]." : ".$entry["label"]."\n".
        $Dictionnary["BillingInvoiceType"]." : ".billing_invoice_type_label($entry["invoice_type"] ?? "school")."\n".
        $Dictionnary["Amount"]." : ".billing_euros($amount)."\n";
    if (!billing_is_credit_note($entry))
        $body .= $Dictionnary["DueDate"]." : ".billing_document_date_label($entry["due_date"])."\n";
    else if (!empty($entry["related_entry_id"]))
    {
        $related = billing_entry_with_user((int)$entry["related_entry_id"], true);
        if ($related != NULL)
            $body .= ($Dictionnary["BillingRelatedInvoice"] ?? "Facture concernée")." : ".billing_invoice_document_reference($related)."\n";
    }
    return ($body.$Dictionnary["Reference"]." : ".$reference."\n");
}

function billing_invoice_mail_school_name($entry)
{
    $name = trim((string)($entry["school_codename"] ?? ""));
    return ($name == "" ? "" : strtoupper($name));
}

function billing_invoice_mail_subject($entry, $reference)
{
    $subject = billing_entry_document_label($entry)." ".$reference;
    $school = billing_invoice_mail_school_name($entry);
    if ($school != "")
        $subject .= " — ".$school;
    return ($subject);
}

function billing_invoice_mail_body($entry, $reference, $relative_path = "", $custom_message = "")
{
    global $Dictionnary;

    // $relative_path est volontairement ignoré : il désigne un chemin interne à
    // Infosphère auquel le destinataire du mail n'a pas accès.
    $student_name = billing_entry_student_name($entry);
    $organization_name = billing_entry_organization_name($entry);
    $amount = billing_is_credit_note($entry) ? abs((int)$entry["amount"]) : (int)$entry["amount"];

    $body = ($Dictionnary["BillingMailGreeting"] ?? "Bonjour,")."\n\n";
    $custom_message = trim(str_replace(["\r\n", "\r"], "\n", (string)$custom_message));
    if ($custom_message != "")
        $body .= $custom_message."\n\n";
    $body .= sprintf(
        billing_is_credit_note($entry)
            ? ($Dictionnary["BillingCreditNoteMailIntro"] ?? "Veuillez trouver en pièce jointe l'avoir %s concernant :")
            : ($Dictionnary["BillingInvoiceMailIntro"] ?? "Veuillez trouver en pièce jointe la facture %s concernant :"),
        $reference
    )."\n\n";
    $body .= $Dictionnary["Student"]." : ".$name."\n";
    $body .= $Dictionnary["BillingLabel"]." : ".$entry["label"]."\n";
    $body .= $Dictionnary["BillingInvoiceType"]." : ".billing_invoice_type_label($entry["invoice_type"] ?? "school")."\n";
    $body .= $Dictionnary["Amount"]." : ".billing_euros($amount)."\n";

    if (!billing_is_credit_note($entry))
    {
        $body .= $Dictionnary["DueDate"]." : ".billing_document_date_label($entry["due_date"])."\n";
        $body .= "\n".sprintf(
            $Dictionnary["BillingInvoiceMailPaymentReference"] ?? "Pour faciliter le rapprochement de votre règlement, merci d'indiquer %s comme référence lors de votre virement.",
            $reference
        )."\n";
    }
    else if (!empty($entry["related_entry_id"]))
    {
        $related = billing_entry_with_user((int)$entry["related_entry_id"], true);
        if ($related != NULL)
            $body .= ($Dictionnary["BillingRelatedInvoice"] ?? "Facture concernée")." : ".billing_invoice_document_reference($related)."\n";
    }

    $body .= "\n".($Dictionnary["BillingMailRegards"] ?? "Cordialement,")."\n";
    $school = billing_invoice_mail_school_name($entry);
    if ($school != "")
        $body .= "\n".$school."\n";
    return ($body);
}

function billing_invoice_directory($codename, $state = false)
{
    global $Configuration;

    $base = $Configuration->UsersDir($codename)."admin/";
    if ($state === true || $state === "paid")
        return ($base."paid_invoices/");
    if ($state === "deleted")
        return ($base."deleted_invoices/");
    return ($base."invoices_to_pay/");
}

function billing_invoice_relative_path_for_state($state, $filename)
{
    if ($filename == "")
        return ("");
    if ($state === true || $state === "paid")
        return ("admin/paid_invoices/".$filename);
    if ($state === "deleted")
        return ("admin/deleted_invoices/".$filename);
    return ("admin/invoices_to_pay/".$filename);
}


function billing_entry_document_directory($entry, $state = false)
{
    global $Configuration;

    // Student-linked invoices keep living in the student's administrative
    // storage. Pure B2B invoices live in the client organization's accounting
    // storage instead.
    $codename = trim((string)($entry["codename"] ?? ""));
    if ($codename == "" && !empty($entry["id_user"]))
    {
        $user = db_select_one("codename FROM user WHERE id = ".((int)$entry["id_user"]));
        if ($user != NULL)
            $codename = trim((string)($user["codename"] ?? ""));
    }
    if ($codename != "")
    {
        if (billing_is_credit_note($entry) && $state !== "deleted")
            return ($Configuration->UsersDir($codename)."admin/credit_notes/");
        return (billing_invoice_directory($codename, $state));
    }

    $organization_codename = trim((string)($entry["organization_codename"] ?? ""));
    if ($organization_codename == "" && !empty($entry["id_organization"]))
    {
        $organization = db_select_one("codename FROM organization WHERE id = ".((int)$entry["id_organization"])." AND deleted IS NULL");
        if ($organization != NULL)
            $organization_codename = trim((string)($organization["codename"] ?? ""));
    }
    if ($organization_codename == "")
        return ("");

    $base = organization_dir($organization_codename)."accounting/".(int)($entry["id_school"] ?? 0)."/";
    if (billing_is_credit_note($entry) && $state !== "deleted")
        return ($base."credit_notes/");
    if ($state === true || $state === "paid")
        return ($base."paid_invoices/");
    if ($state === "deleted")
        return ($base."deleted_invoices/");
    return ($base."invoices_to_pay/");
}

function billing_entry_document_relative_path($entry, $state = false)
{
    if (empty($entry["invoice_filename"]))
        return ("");
    if ((int)($entry["id_user"] ?? 0) > 0)
    {
        if (billing_is_credit_note($entry) && $state !== "deleted")
            return ("admin/credit_notes/".$entry["invoice_filename"]);
        return (billing_invoice_relative_path_for_state($state, $entry["invoice_filename"]));
    }

    $base = "accounting/".(int)($entry["id_school"] ?? 0)."/";
    if (billing_is_credit_note($entry) && $state !== "deleted")
        return ($base."credit_notes/".$entry["invoice_filename"]);
    if ($state === true || $state === "paid")
        return ($base."paid_invoices/".$entry["invoice_filename"]);
    if ($state === "deleted")
        return ($base."deleted_invoices/".$entry["invoice_filename"]);
    return ($base."invoices_to_pay/".$entry["invoice_filename"]);
}

function billing_entry_document_owner_codename($entry)
{
    if ((int)($entry["id_user"] ?? 0) > 0)
    {
        $codename = trim((string)($entry["codename"] ?? ""));
        if ($codename != "")
            return ($codename);
        $row = db_select_one("codename FROM user WHERE id = ".(int)$entry["id_user"]);
        return ($row == NULL ? "" : trim((string)($row["codename"] ?? "")));
    }
    $codename = trim((string)($entry["organization_codename"] ?? ""));
    if ($codename != "")
        return ($codename);
    if ((int)($entry["id_organization"] ?? 0) <= 0)
        return ("");
    $row = db_select_one("codename FROM organization WHERE id = ".(int)$entry["id_organization"]." AND deleted IS NULL");
    return ($row == NULL ? "" : trim((string)($row["codename"] ?? "")));
}

function billing_invoice_relative_path($entry)
{
    if (empty($entry["invoice_filename"]))
        return ("");
    if (!empty($entry["deleted"]))
        return (billing_entry_document_relative_path($entry, "deleted"));
    if (billing_is_credit_note($entry))
        return (billing_entry_document_relative_path($entry, false));
    if (!empty($entry["paid_date"]))
        return (billing_entry_document_relative_path($entry, "paid"));
    return (billing_entry_document_relative_path($entry, false));
}


function billing_invoice_model_file($entry = NULL)
{
    if (billing_is_credit_note($entry))
    {
        // Credit notes are generated only through Billing because generating the
        // PDF must also create the accounting movement. Keep the model out of
        // the generic Documents catalogue to avoid a disconnected credit note.
        foreach (["./res/docs/fr/.billing/avoir.dab", "./res/docs/.billing/avoir.dab"] as $candidate)
            if (file_exists($candidate) && !is_dir($candidate))
                return ($candidate);
        return (NULL);
    }
    if (function_exists("document_builder_find_model"))
    {
        $model = document_builder_find_model("facture");
        if ($model != NULL)
            return ($model);
    }
    foreach (["./res/docs/facture.dab", "./res/docs/fr/facture.dab", "./dres/doc/facture.dab", "./dres/doc/fr/facture.dab"] as $candidate)
        if (file_exists($candidate) && !is_dir($candidate))
            return ($candidate);
    return (NULL);
}


function billing_invoice_context_fields($entry, $reference)
{
    $fields = [];
    $amount_ht = billing_entry_amount_ht($entry);
    $invoice = [
        "id" => (int)$entry["id"],
        "reference" => $reference,
        "label" => $entry["label"] ?? "",
        "type" => billing_normalize_invoice_type($entry["invoice_type"] ?? "school"),
        "type_label" => billing_invoice_type_label($entry["invoice_type"] ?? "school"),
        "entry_type" => $entry["entry_type"] ?? "",
        "amount_cents" => $amount_ht,
        "amount" => billing_euros($amount_ht),
        "amount_euros" => billing_euros($amount_ht),
        "amount_ttc_cents" => (int)($entry["amount"] ?? 0),
        "amount_ttc" => billing_euros($entry["amount"] ?? 0),
        "VAT" => billing_normalize_vat_rate($entry["vat_rate"] ?? 0),
        "VATExemption" => billing_invoice_vat_exemption($entry["vat_rate"] ?? 0, $entry["id_school"] ?? 0),
        "due_date" => $entry["due_date"] ?? "",
        "due_date_label" => billing_document_date_label($entry["due_date"] ?? ""),
        // DocBuilder Invoice consumes IssueDate/IssueDateLabel. Keep sent_date
        // as the accounting source of truth and expose renderer aliases only.
        "issue_date" => $entry["sent_date"] ?? "",
        "issue_date_label" => empty($entry["sent_date"])
            ? billing_invoice_draft_reference() : billing_document_date_label($entry["sent_date"]),
        "sent_date" => $entry["sent_date"] ?? "",
        // A draft has deliberately no issue date yet. The DocBuilder invoice
        // renderer terminates this display line with "\\"; an empty value
        // therefore leaves a bare "\\" in the generated LaTeX and XeLaTeX
        // aborts with "There's no line here to end".
        //
        // Do not fake sent_date: it is assigned only when the invoice is issued.
        // Give the renderer a non-empty display value for draft previews only.
        "sent_date_label" => empty($entry["sent_date"])
            ? billing_invoice_draft_reference()
            : billing_document_date_label($entry["sent_date"]),
        "paid_date" => $entry["paid_date"] ?? "",
        "paid_date_label" => empty($entry["paid_date"]) ? "" : billing_document_date_label($entry["paid_date"]),
    ];
    $installment = billing_installment_invoice_summary($entry);
    if ($installment != NULL)
    {
        // On the invoice itself, identify the installment only as X/N. The
        // timeline may keep its more verbose internal label.
        $invoice["label"] = $installment["label"];
        $invoice["InstallmentTuitionTotalCents"] = $installment["tuition_total_cents"];
        $invoice["InstallmentSummary"] = $installment["summary"];
        $invoice["LineDetails"] = $installment["summary"];
    }
    document_context_flatten($fields, "Invoice", $invoice);
    document_context_flatten($fields, "Billing", $invoice);

    $id_user = (int)($entry["id_user"] ?? 0);
    $student = $id_user > 0 ? document_context_person($id_user) : NULL;
    if ($student != NULL)
    {
        document_context_flatten($fields, "Student", $student);
        document_context_flatten($fields, "Destination", $student);
        document_context_flatten($fields, "User", $student);
    }

    $buyer_organization = NULL;
    if ((int)($entry["id_organization"] ?? 0) > 0 && function_exists("document_context_organization"))
        $buyer_organization = document_context_organization((int)$entry["id_organization"]);
    if ($buyer_organization != NULL)
    {
        document_context_flatten($fields, "BuyerOrganization", $buyer_organization);
        document_context_flatten($fields, "Destination", $buyer_organization);
    }

    $parent = $id_user > 0 ? document_context_parent_for_user($id_user) : NULL;
    if ($parent != NULL)
    {
        document_context_flatten($fields, "Parent", $parent);
        document_context_flatten($fields, "FinancialResponsible", $parent);
    }


    $finance = ($id_user > 0 && function_exists("document_context_relation_user"))
        ? document_context_relation_user($id_user, "financial", 0) : NULL;
    if ($finance == NULL)
        $finance = $student;
    if ($finance != NULL)
    {
        document_context_flatten($fields, "Finance", $finance);
        document_context_flatten($fields, "FinancialResponsible", $finance);
    }
    // An explicitly selected company is the legal invoice recipient. Keep the
    // student as beneficiary, but use the organization as payer/destination.
    if ($buyer_organization != NULL)
    {
        document_context_flatten($fields, "Finance", $buyer_organization);
        document_context_flatten($fields, "FinancialResponsible", $buyer_organization);
        document_context_flatten($fields, "Destination", $buyer_organization);
    }

    if (billing_is_credit_note($entry))
    {
        $related = !empty($entry["related_entry_id"])
            ? billing_entry_with_user((int)$entry["related_entry_id"], true) : NULL;
        $credit = [
            "reference" => $reference,
            "label" => $entry["label"] ?? "",
            "amount_cents" => abs((int)($entry["amount"] ?? 0)),
            "amount" => billing_euros(abs((int)($entry["amount"] ?? 0))),
            "issue_date" => !empty($entry["sent_date"]) ? $entry["sent_date"] : ($entry["due_date"] ?? dbnow()),
            "issue_date_label" => billing_document_date_label(!empty($entry["sent_date"]) ? $entry["sent_date"] : ($entry["due_date"] ?? dbnow())),
            "related_invoice_reference" => $related == NULL ? "" : billing_invoice_document_reference($related),
            "related_invoice_label" => $related["label"] ?? "",
        ];
        document_context_flatten($fields, "CreditNote", $credit);
        if ($related != NULL)
        {
            $related_data = [
                "id" => (int)$related["id"],
                "reference" => billing_invoice_document_reference($related),
                "label" => $related["label"] ?? "",
                "amount" => billing_euros((int)$related["amount"]),
                "sent_date_label" => billing_document_date_label($related["sent_date"] ?? ""),
            ];
            document_context_flatten($fields, "RelatedInvoice", $related_data);
        }
    }

    $school = document_context_school((int)$entry["id_school"]);
    if ($school != NULL)
    {
        // Le moteur Invoice historique utilise LegalAddress dans le bloc
        // émetteur. Pour une facture d'école, ce bloc doit montrer le lieu de
        // formation ; les coordonnées juridiques restent sous Organization et
        // sont imprimées dans le pied de page.
        $training_address = trim((string)($school["training_address"] ?? ($school["address"] ?? "")));
        if ($training_address != "")
        {
            $school["address"] = $training_address;
            $school["street"] = $training_address;
            $school["legal_address"] = $training_address;
        }
	$school["RIBDetails"] = billing_rib_details($school["RIB"] ?? "");
        document_context_flatten($fields, "Company", $school);
        document_context_flatten($fields, "School", $school);
    }

    $director = document_context_director_for_school((int)$entry["id_school"]);
    if ($director != NULL)
    {
        document_context_flatten($fields, "Director", $director);
    }
    return ($fields);
}

function billing_build_invoice_document($entry, $reference, $output)
{
    $model = billing_invoice_model_file($entry);
    if ($model == NULL)
    {
        add_log(TRACE, "Cannot build invoice #".((int)$entry["id"]).": missing billing document model", $entry["id_user"] ?? -1);
        return (NULL);
    }

    $parts = array_merge([["file" => $model]], billing_invoice_context_fields($entry, $reference));
    $include_paths = function_exists("document_builder_model_dirs") ? document_builder_model_dirs() : ["./res/docs/", "./dres/doc/"];
    $ret = build_document_from_parts($output, $parts, $include_paths);
    if ($ret->is_error())
    {
        add_log(TRACE, "Cannot build invoice #".((int)$entry["id"]).": ".strval($ret), $entry["id_user"] ?? -1);
        return (NULL);
    }
    if (!file_exists($output) || substr((string)@file_get_contents($output, false, NULL, 0, 4), 0, 4) !== "%PDF")
    {
        add_log(TRACE, "Cannot build invoice #".((int)$entry["id"]).": DocBuilder did not produce a PDF", $entry["id_user"] ?? -1);
        return (NULL);
    }
    @chmod($output, 0664);
    return (file_get_contents($output));
}

function billing_write_invoice_placeholder(&$entry, $reference, $state = false)
{
    $dir = billing_entry_document_directory($entry, $state);
    if ($dir == "")
        return (NULL);
    new_directory($dir."index.php");

    $filename = billing_invoice_existing_pdf_filename($entry, $reference);
    $entry["invoice_filename"] = $filename;
    $path = $dir.$filename;
    $content = billing_build_invoice_document($entry, $reference, $path);
    if ($content === NULL)
        return (NULL);
    return ([
        "filename" => $filename,
        "path" => $path,
        "relative_path" => billing_entry_document_relative_path(array_merge($entry, ["invoice_filename" => $filename]), $state),
        "content" => $content,
    ]);
}

function billing_reconcile_entry_account($entry)
{
    $id_organization = (int)($entry["id_organization"] ?? 0);
    $id_school = (int)($entry["id_school"] ?? 0);
    if ($id_organization > 0)
    {
        billing_reconcile_paid_invoices_for_organization($id_organization, $id_school);
        billing_archive_paid_invoices_for_organization($id_organization, $id_school);
        return ;
    }

    $id_user = (int)($entry["id_user"] ?? 0);
    if ($id_user <= 0)
        return ;
    billing_reconcile_paid_invoices($id_user);
    billing_archive_paid_invoices($id_user);
}

function billing_reconcile_payment_account($payment)
{
    if (!is_array($payment))
        return ;
    $id_organization = (int)($payment["id_organization"] ?? 0);
    $id_school = (int)($payment["id_school"] ?? 0);
    if ($id_organization > 0)
    {
        billing_reconcile_paid_invoices_for_organization($id_organization, $id_school);
        billing_archive_paid_invoices_for_organization($id_organization, $id_school);
        return ;
    }

    $id_user = (int)($payment["id_user"] ?? 0);
    if ($id_user <= 0)
        return ;
    billing_reconcile_paid_invoices($id_user);
    billing_archive_paid_invoices($id_user);
}

function billing_invoice_existing_file_path($entry)
{
    if (empty($entry["invoice_filename"]))
        return ("");
    $state = !empty($entry["deleted"]) ? "deleted" : (!empty($entry["paid_date"]) ? true : false);
    if (billing_is_credit_note($entry) && empty($entry["deleted"]))
        $state = false;
    $dir = billing_entry_document_directory($entry, $state);
    if ($dir == "")
        return ("");
    $path = $dir.$entry["invoice_filename"];
    if (!file_exists($path) || is_dir($path))
        return ("");
    if (substr((string)@file_get_contents($path, false, NULL, 0, 4), 0, 4) !== "%PDF")
        return ("");
    return ($path);
}


function billing_invoice_pdf_response($id_entry)
{
    $entry = billing_entry_with_user($id_entry, true);
    if ($entry == NULL || !billing_entry_is_managed($entry))
        return (false);

    $ref = billing_invoice_document_reference($entry);
    $filename = billing_invoice_existing_pdf_filename($entry, $ref);

    $path = billing_invoice_existing_file_path($entry);
    if ($path != "")
        $content = file_get_contents($path);
    else
    {
        // An external invoice can only be displayed when its original PDF was
        // imported. Never manufacture an Infosphere invoice in its place.
        if (billing_is_external_invoice($entry))
            return (false);
        $tmp = tempnam(sys_get_temp_dir(), "infosphere_invoice_");
        if ($tmp === false)
            return (false);
        @unlink($tmp);
        $path = $tmp.".pdf";
        $content = billing_build_invoice_document($entry, $ref, $path);
        @unlink($path);
        if ($content === NULL)
            return (false);
    }

    return ([
        "filename" => $filename,
        "content" => $content,
        "content_type" => "application/pdf",
        "disposition" => "inline",
    ]);
}

function billing_invoice_preview_pdf_response($id_entry, array $data)
{
    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_entry_is_managed($entry))
        return (["error" => "NotFound"]);
    if (!empty($entry["sent_date"]) || billing_is_external_invoice($entry) ||
        billing_is_credit_note($entry) || (int)$entry["amount"] <= 0)
        return (["error" => "BillingDraftCannotEdit"]);

    $reference = trim((string)($data["invoice_reference"] ?? ""));
    if ($reference == "")
        return (["error" => "BillingInvoiceReferenceRequired"]);
    if (strlen($reference) > 128)
        return (["error" => "BillingInvoiceReferenceTooLong"]);
    if (!billing_invoice_reference_is_available($reference, (int)$entry["id"]))
        return (["error" => "BillingInvoiceReferenceAlreadyUsed"]);

    // Preview the values currently visible in the draft editor without
    // persisting them. This lets the operator inspect the exact document that
    // would be issued even when the last edits have not been saved yet.
    $label = trim((string)($data["label"] ?? ($entry["label"] ?? "")));
    $amount_ht = billing_amount_to_cents($data["amount"] ?? NULL);
    $due_date = db_form_date($data["due_date"] ?? ($entry["due_date"] ?? ""));
    if ($label == "" || $amount_ht === NULL || $amount_ht <= 0 ||
        date_to_timestamp($due_date) === NULL)
        return (["error" => "InvalidParameter"]);

    $invoice_type = billing_normalize_invoice_type($data["invoice_type"] ?? ($entry["invoice_type"] ?? "school"));
    $vat_rate = billing_normalize_vat_rate($data["vat_rate"] ?? ($entry["vat_rate"] ?? 0));
    $id_organization = (int)($data["id_organization"] ?? ($entry["id_organization"] ?? 0));
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (["error" => "NotFound"]);
    $buyer_routing_code = trim((string)($data["buyer_routing_code"] ?? ($entry["buyer_routing_code"] ?? "")));
    if (strlen($buyer_routing_code) > 255)
        return (["error" => "InvalidParameter"]);
    $school = fetch_school((int)$entry["id_school"]);
    if ($school instanceof ErrorResponse || !is_array($school) ||
        !billing_invoice_type_is_allowed_for_school($school, $invoice_type))
        return (["error" => "BillingInvoiceTypeUnavailable"]);

    $entry["label"] = $label;
    $entry["amount_ht"] = $amount_ht;
    $entry["amount"] = billing_amount_ttc_from_ht($amount_ht, $vat_rate);
    $entry["due_date"] = $due_date;
    $entry["invoice_type"] = $invoice_type;
    $entry["vat_rate"] = $vat_rate;
    $entry["id_organization"] = $id_organization > 0 ? $id_organization : NULL;
    $entry["buyer_routing_code"] = $buyer_routing_code;
    $entry["invoice_reference"] = $reference;
    // A generated preview must look like the final invoice, but this issue date
    // exists only in the in-memory copy of the row and is never written to DB.
    $entry["sent_date"] = dbnow();

    $tmp = tempnam(sys_get_temp_dir(), "infosphere_invoice_preview_");
    if ($tmp === false)
        return (["error" => "CannotBuildInvoice"]);
    @unlink($tmp);
    $path = $tmp.".pdf";
    $content = billing_build_invoice_document($entry, $reference, $path);
    @unlink($path);
    if ($content === NULL)
        return (["error" => "CannotBuildInvoice"]);

    return ([
        "filename" => billing_invoice_safe_filename($reference),
        "content" => $content,
        "content_type" => "application/pdf",
        "disposition" => "inline",
    ]);
}


function billing_invoice_reference_is_available($reference, $id_entry)
{
    global $Database;

    $reference = $Database->real_escape_string(trim((string)$reference));
    $id_entry = (int)$id_entry;
    if ($reference == "")
        return (false);
    return (db_select_one("
        id
        FROM billing_entry
        WHERE invoice_reference = '$reference'
          AND deleted IS NULL
          AND id != $id_entry
    ") == NULL);
}

function billing_unlink_invoice_files($entry)
{
    if (empty($entry["invoice_filename"]))
        return ;
    foreach ([false, true, "deleted"] as $state)
    {
        $dir = billing_entry_document_directory($entry, $state);
        if ($dir == "")
            continue ;
        $path = $dir.$entry["invoice_filename"];
        if (file_exists($path))
            @unlink($path);
    }
}


function billing_store_external_invoice_pdf(&$entry, $content)
{
    if (!is_string($content) || strlen($content) < 5 || substr($content, 0, 5) !== "%PDF-")
        return (false);
    if (strlen($content) > 20 * 1024 * 1024)
        return (false);

    $dir = billing_entry_document_directory($entry, false);
    if ($dir == "")
        return (false);
    new_directory($dir."index.php");
    $filename = "external-".billing_invoice_safe_filename($entry["invoice_reference"] ?? "invoice");
    $path = $dir.$filename;
    if (file_put_contents($path, $content) === false)
    {
        @unlink($path);
        return (false);
    }
    @chmod($path, 0664);
    $entry["invoice_filename"] = $filename;
    return ($filename);
}

function billing_payment_coverage_for_user($id_user)
{
    $id_user = (int)$id_user;
    $paid = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_user = $id_user
          AND id_organization IS NULL
          AND deleted IS NULL
    ");
    $remaining_cash = max(0, (int)($paid["amount"] ?? 0));
    $coverage = [];

    foreach (billing_positive_invoices_for_user($id_user, true) as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        $credit = min($amount, billing_credit_note_total_for_entry((int)$entry["id"], true));
        $cash_needed = max(0, $amount - $credit);
        $cash = min($cash_needed, $remaining_cash);
        $remaining_cash -= $cash;
        $coverage[(int)$entry["id"]] = $credit + $cash;
    }
    return ($coverage);
}

function billing_payment_coverage_for_organization($id_organization, $id_school)
{
    $id_organization = (int)$id_organization;
    $id_school = (int)$id_school;
    if ($id_organization <= 0 || $id_school <= 0)
        return ([]);
    $paid = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_organization = $id_organization
          AND id_school = $id_school
          AND deleted IS NULL
    ");
    $remaining_cash = max(0, (int)($paid["amount"] ?? 0));
    $coverage = [];

    foreach (billing_positive_invoices_for_organization($id_organization, $id_school, true) as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        $credit = min($amount, billing_credit_note_total_for_entry((int)$entry["id"], true));
        $cash_needed = max(0, $amount - $credit);
        $cash = min($cash_needed, $remaining_cash);
        $remaining_cash -= $cash;
        $coverage[(int)$entry["id"]] = $credit + $cash;
    }
    return ($coverage);
}

function billing_payment_coverage_for_student($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);

    $coverage = billing_payment_coverage_for_user($id_user);
    $organization_coverages = [];
    foreach (billing_positive_invoices_for_student($id_user, true) as $entry)
    {
        $id_organization = (int)($entry["id_organization"] ?? 0);
        $id_school = (int)($entry["id_school"] ?? 0);
        if ($id_organization <= 0 || $id_school <= 0)
            continue ;
        $key = $id_school.":".$id_organization;
        if (!isset($organization_coverages[$key]))
            $organization_coverages[$key] = billing_payment_coverage_for_organization($id_organization, $id_school);
        if (isset($organization_coverages[$key][(int)$entry["id"]]))
            $coverage[(int)$entry["id"]] = $organization_coverages[$key][(int)$entry["id"]];
    }
    return ($coverage);
}

function billing_organization_cash_paid_for_student($id_user, $coverage = NULL)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (0);
    if (!is_array($coverage))
        $coverage = billing_payment_coverage_for_student($id_user);

    $paid = 0;
    foreach (billing_positive_invoices_for_student($id_user, true) as $entry)
    {
        if ((int)($entry["id_organization"] ?? 0) <= 0)
            continue ;
        $amount = max(0, (int)$entry["amount"]);
        $credit = min($amount, billing_credit_note_total_for_entry((int)$entry["id"], true));
        $covered = min($amount, (int)($coverage[(int)$entry["id"]] ?? 0));
        $paid += max(0, min($amount - $credit, $covered - $credit));
    }
    return ($paid);
}

function billing_paid_entry_ids_for_student($id_user)
{
    $covered = [];
    $coverage = billing_payment_coverage_for_student($id_user);
    foreach (billing_positive_invoices_for_student($id_user, true) as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        if ($amount > 0 && isset($coverage[(int)$entry["id"]]) && $coverage[(int)$entry["id"]] >= $amount)
            $covered[(int)$entry["id"]] = true;
    }
    return ($covered);
}


function billing_paid_entry_ids_for_user($id_user)
{
    $covered = [];
    $coverage = billing_payment_coverage_for_user($id_user);

    foreach (billing_positive_invoices_for_user($id_user, true) as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        if ($amount > 0 && isset($coverage[(int)$entry["id"]]) && $coverage[(int)$entry["id"]] >= $amount)
            $covered[(int)$entry["id"]] = true;
    }
    return ($covered);
}

function billing_paid_archivable_entries($id_user)
{
    $id_user = (int)$id_user;
    $paid_entries = billing_paid_entry_ids_for_user($id_user);
    $entries = [];
    foreach (billing_positive_invoices_for_user($id_user, true) as $entry)
        if (isset($paid_entries[(int)$entry["id"]]) && empty($entry["paid_date"]))
            $entries[] = $entry;
    return ($entries);
}

function billing_paid_entry_ids_for_organization($id_organization, $id_school)
{
    $covered = [];
    $coverage = billing_payment_coverage_for_organization($id_organization, $id_school);

    foreach (billing_positive_invoices_for_organization($id_organization, $id_school, true) as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        if ($amount > 0 && isset($coverage[(int)$entry["id"]]) && $coverage[(int)$entry["id"]] >= $amount)
            $covered[(int)$entry["id"]] = true;
    }
    return ($covered);
}

function billing_paid_archivable_entries_for_organization($id_organization, $id_school)
{
    $paid_entries = billing_paid_entry_ids_for_organization($id_organization, $id_school);
    $entries = [];
    foreach (billing_positive_invoices_for_organization($id_organization, $id_school, true) as $entry)
        if (isset($paid_entries[(int)$entry["id"]]) && empty($entry["paid_date"]))
            $entries[] = $entry;
    return ($entries);
}

function billing_mark_invoice_sent($id_entry, $reference)
{
    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_entry_is_managed($entry))
        return (["error" => "NotFound"]);
    if (!empty($entry["sent_date"]))
        return (["error" => "BillingInvoiceAlreadyIssued"]);
    if (billing_is_credit_note($entry) || billing_is_external_invoice($entry))
        return (["error" => "BillingDraftCannotEdit"]);

    $reference = trim((string)$reference);
    if ($reference == "")
        return (["error" => "BillingInvoiceReferenceRequired"]);
    if (strlen($reference) > 128)
        return (["error" => "BillingInvoiceReferenceTooLong"]);
    if (!billing_invoice_reference_is_available($reference, $entry["id"]))
        return (["error" => "BillingInvoiceReferenceAlreadyUsed"]);

    // This path records a delivery already performed outside Infosphere.  It
    // therefore finalizes the exact same accounting artefact as Send invoice,
    // but deliberately does not call send_mail().
    $issue_date = dbnow();
    $entry["invoice_reference"] = $reference;
    $entry["sent_date"] = $issue_date;

    $file = billing_write_invoice_placeholder($entry, $reference, false);
    if ($file == NULL)
    {
        billing_unlink_invoice_files($entry);
        return (["error" => "CannotBuildInvoice"]);
    }

    if (db_update_one("billing_entry", (int)$entry["id"], [
        "invoice_reference" => $reference,
        "sent_date" => $issue_date,
        "invoice_filename" => $file["filename"],
    ]) === NULL)
    {
        if (!empty($file["path"]) && file_exists($file["path"]))
            @unlink($file["path"]);
        return (["error" => "BillingInvoiceReferenceAlreadyUsed"]);
    }

    billing_reconcile_entry_account($entry);
    if (function_exists("billing_einvoice_track_issued_entry"))
        billing_einvoice_track_issued_entry((int)$entry["id"]);

    $updated = billing_entry_with_user($entry["id"]);
    if ($updated != NULL)
        $file["relative_path"] = billing_invoice_relative_path($updated);
    return ($file);
}

function billing_send_invoice_placeholder($id_entry, $reference, $custom_message = "")
{
    global $Dictionnary;

    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_entry_is_managed($entry))
        return (["error" => "CannotSendMail"]);
    if (!empty($entry["sent_date"]))
        return (["error" => "BillingInvoiceAlreadyIssued"]);

    $reference = trim((string)$reference);
    if ($reference == "")
        return (["error" => "BillingInvoiceReferenceRequired"]);
    if (strlen($reference) > 128)
        return (["error" => "BillingInvoiceReferenceTooLong"]);
    if (!billing_invoice_reference_is_available($reference, $entry["id"]))
        return (["error" => "BillingInvoiceReferenceAlreadyUsed"]);

    $recipients = billing_invoice_recipients($entry["id_user"], $entry["id_organization"] ?? 0);
    if (!count($recipients))
        return (["error" => "CannotSendMail"]);

    // A credit note has an explicit issue date chosen when its draft is prepared.
    // Normal invoices keep the historical behaviour: their issue date is the
    // instant at which they are actually sent.
    $issue_date = billing_is_credit_note($entry) ? $entry["due_date"] : dbnow();
    $entry["invoice_reference"] = $reference;
    $entry["sent_date"] = $issue_date;

    $file = billing_write_invoice_placeholder($entry, $reference, false);
    if ($file == NULL)
    {
        billing_unlink_invoice_files($entry);
        return (["error" => "CannotBuildInvoice"]);
    }

    // Persist exactly the number chosen by the operator immediately before
    // delivery. This checks uniqueness, but does not calculate, reserve or
    // validate any sequence. A failed delivery rolls the issue back entirely.
    if (db_update_one("billing_entry", (int)$entry["id"], [
        "invoice_reference" => $reference,
        "sent_date" => $issue_date,
        "invoice_filename" => $file["filename"],
    ]) === NULL)
    {
        if (!empty($file["path"]) && file_exists($file["path"]))
            @unlink($file["path"]);
        return (["error" => "BillingInvoiceReferenceAlreadyUsed"]);
    }

    $body = billing_invoice_mail_body($entry, $reference, $file["relative_path"], $custom_message);
    $billing_copy = "";
    if (function_exists("school_mailbox_resolve") && (int)($entry["id_school"] ?? 0) > 0)
        $billing_copy = school_mailbox_resolve((int)$entry["id_school"], "billing", false);
    if ($billing_copy != "")
        foreach ($recipients as $recipient)
            if (strcasecmp(trim((string)$recipient), $billing_copy) == 0)
            {
                $billing_copy = "";
                break ;
            }

    $mail = send_mail(
        $recipients,
        billing_invoice_mail_subject($entry, $reference),
        $body,
        NULL,
        [$file["filename"] => $file["content"]],
        false,
        NULL,
        $billing_copy == "" ? NULL : $billing_copy
    );
    if ($mail->is_error())
    {
        if (!empty($file["path"]) && file_exists($file["path"]))
            @unlink($file["path"]);
        db_update_one("billing_entry", (int)$entry["id"], [
            "invoice_reference" => NULL,
            "sent_date" => NULL,
            "invoice_filename" => NULL,
        ]);
        return (["error" => "CannotSendMail"]);
    }

    billing_reconcile_entry_account($entry);
    if (function_exists("billing_einvoice_track_issued_entry"))
        billing_einvoice_track_issued_entry((int)$entry["id"]);

    $updated = billing_entry_with_user($entry["id"]);
    if ($updated != NULL)
        $file["relative_path"] = billing_invoice_relative_path($updated);
    return ($file);
}

function billing_archive_paid_invoice_entries(array $entries)
{
    $count = 0;
    foreach ($entries as $entry)
    {
        if (billing_is_credit_note($entry))
            continue ;
        $entry = billing_entry_with_user($entry["id"]);
        if ($entry == NULL)
            continue ;
        $ref = billing_invoice_document_reference($entry);
        if (empty($entry["invoice_filename"]) && !billing_is_external_invoice($entry))
            $entry["invoice_filename"] = billing_invoice_safe_filename($ref);

        $source_dir = billing_entry_document_directory($entry, false);
        $target_dir = billing_entry_document_directory($entry, true);
        if ($source_dir == "" || $target_dir == "")
            continue ;
        new_directory($target_dir."index.php");
        $source = $source_dir.$entry["invoice_filename"];
        $target = $target_dir.$entry["invoice_filename"];

        if (!empty($entry["invoice_filename"]) && file_exists($source))
        {
            if (!@rename($source, $target))
                continue ;
        }
        else if (!billing_is_external_invoice($entry))
        {
            $file = billing_write_invoice_placeholder($entry, $ref, true);
            if ($file == NULL)
                continue ;
        }

        // An imported invoice may legitimately have no PDF. Its accounting
        // state can still be archived without inventing a replacement PDF.
        $update = ["paid_date" => dbnow()];
        if (!empty($entry["invoice_filename"]))
            $update["invoice_filename"] = $entry["invoice_filename"];
        db_update_one("billing_entry", (int)$entry["id"], $update);
        ++$count;
    }
    return ($count);
}

function billing_archive_paid_invoices($id_user)
{
    $id_user = (int)$id_user;
    if (!billing_user_is_managed($id_user))
        return (0);
    return (billing_archive_paid_invoice_entries(billing_paid_archivable_entries($id_user)));
}

function billing_archive_paid_invoices_for_organization($id_organization, $id_school)
{
    $id_organization = (int)$id_organization;
    $id_school = (int)$id_school;
    if ($id_organization <= 0 || $id_school <= 0 || !billing_organization_exists($id_organization) ||
        !is_billing_manager_for_school($id_school))
        return (0);
    return (billing_archive_paid_invoice_entries(
        billing_paid_archivable_entries_for_organization($id_organization, $id_school)
    ));
}

function billing_reconcile_paid_invoice_entries(array $entries, array $paid_entries)
{
    $count = 0;
    foreach ($entries as $entry)
    {
        if (billing_is_credit_note($entry))
            continue ;
        if (empty($entry["paid_date"]) || isset($paid_entries[(int)$entry["id"]]))
            continue ;
        $entry = billing_entry_with_user($entry["id"]);
        if ($entry == NULL)
            continue ;
        if (!empty($entry["invoice_filename"]))
        {
            $paid_dir = billing_entry_document_directory($entry, true);
            $to_pay_dir = billing_entry_document_directory($entry, false);
            if ($paid_dir != "" && $to_pay_dir != "")
            {
                new_directory($to_pay_dir."index.php");
                $source = $paid_dir.$entry["invoice_filename"];
                $target = $to_pay_dir.$entry["invoice_filename"];
                if (file_exists($source))
                    @rename($source, $target);
            }
        }
        db_update_one("billing_entry", (int)$entry["id"], ["paid_date" => NULL]);
        ++$count;
    }
    return ($count);
}

function billing_reconcile_paid_invoices($id_user)
{
    $id_user = (int)$id_user;
    if (!billing_user_is_managed($id_user))
        return (0);
    return (billing_reconcile_paid_invoice_entries(
        billing_positive_invoices_for_user($id_user, true),
        billing_paid_entry_ids_for_user($id_user)
    ));
}

function billing_reconcile_paid_invoices_for_organization($id_organization, $id_school)
{
    $id_organization = (int)$id_organization;
    $id_school = (int)$id_school;
    if ($id_organization <= 0 || $id_school <= 0 || !billing_organization_exists($id_organization) ||
        !is_billing_manager_for_school($id_school))
        return (0);
    return (billing_reconcile_paid_invoice_entries(
        billing_positive_invoices_for_organization($id_organization, $id_school, true),
        billing_paid_entry_ids_for_organization($id_organization, $id_school)
    ));
}

function billing_move_invoice_to_deleted(&$entry)
{
    if (empty($entry["invoice_filename"]))
    {
        if (empty($entry["sent_date"]) || billing_is_external_invoice($entry))
            return (true);
        $ref = billing_invoice_document_reference($entry);
        $entry["invoice_filename"] = billing_invoice_safe_filename($ref);
        return (billing_write_invoice_placeholder($entry, $ref, "deleted") !== NULL);
    }

    $target_dir = billing_entry_document_directory($entry, "deleted");
    if ($target_dir == "")
        return (false);
    new_directory($target_dir."index.php");
    $target = $target_dir.$entry["invoice_filename"];
    $sources = [];

    if (!empty($entry["paid_date"]))
        $sources[] = billing_entry_document_directory($entry, true).$entry["invoice_filename"];
    $sources[] = billing_entry_document_directory($entry, false).$entry["invoice_filename"];
    $sources[] = billing_entry_document_directory($entry, true).$entry["invoice_filename"];

    foreach (array_unique($sources) as $source)
    {
        if ($source == $target || !file_exists($source))
            continue ;
        if (@rename($source, $target))
            return (true);
    }

    if (file_exists($target))
        return (true);
    if (!empty($entry["sent_date"]))
    {
        if (billing_is_external_invoice($entry))
            return (true);
        $ref = billing_invoice_document_reference($entry);
        return (billing_write_invoice_placeholder($entry, $ref, "deleted") !== NULL);
    }
    return (true);
}

function billing_update_draft_invoice($id_entry, array $data)
{
    global $User;

    $id_entry = (int)$id_entry;
    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_entry_is_managed($entry))
        return (["ok" => false, "error" => "NotFound"]);
    if (!empty($entry["sent_date"]) || billing_is_external_invoice($entry) ||
        billing_is_credit_note($entry) || (int)$entry["amount"] <= 0)
        return (["ok" => false, "error" => "BillingDraftCannotEdit"]);

    $label = trim((string)($data["label"] ?? ""));
    $amount_ht = billing_amount_to_cents($data["amount"] ?? NULL);
    $due_date = db_form_date($data["due_date"] ?? "");
    if ($label == "" || $amount_ht === NULL || $amount_ht <= 0 ||
        date_to_timestamp($due_date) === NULL)
        return (["ok" => false, "error" => "InvalidParameter"]);

    $invoice_type = billing_normalize_invoice_type($data["invoice_type"] ?? ($entry["invoice_type"] ?? "school"));
    $vat_rate = billing_normalize_vat_rate($data["vat_rate"] ?? ($entry["vat_rate"] ?? 0));
    $id_organization = (int)($data["id_organization"] ?? ($entry["id_organization"] ?? 0));
    if ($id_organization > 0 && !billing_organization_exists($id_organization))
        return (["ok" => false, "error" => "NotFound"]);
    if ((int)($entry["id_user"] ?? 0) <= 0 && $id_organization <= 0)
        return (["ok" => false, "error" => "InvalidParameter"]);
    $buyer_routing_code = trim((string)($data["buyer_routing_code"] ?? ($entry["buyer_routing_code"] ?? "")));
    if (strlen($buyer_routing_code) > 255)
        return (["ok" => false, "error" => "InvalidParameter"]);
    $amount = billing_amount_ttc_from_ht($amount_ht, $vat_rate);
    $fields = [
        "label" => $label,
        "amount" => $amount,
        "amount_ht" => $amount_ht,
        "invoice_type" => $invoice_type,
        "vat_rate" => $vat_rate,
        "id_organization" => $id_organization > 0 ? $id_organization : NULL,
        "buyer_routing_code" => $buyer_routing_code,
        "due_date" => $due_date,
    ];
    if (isset($User["id"]))
        $fields["id_actor"] = (int)$User["id"];

    if (db_update_one("billing_entry", $id_entry, $fields) == NULL)
        return (["ok" => false, "error" => "CannotRegister"]);

    billing_reconcile_entry_account($entry);
    return (["ok" => true, "entry" => billing_entry_with_user($id_entry)]);
}


function billing_delete_entry($id_entry)
{
    global $Database;

    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_entry_is_managed($entry))
        return (false);

    if (empty($entry["sent_date"]))
    {
        billing_unlink_invoice_files($entry);
        if ($Database->query("DELETE FROM billing_entry WHERE id = ".(int)$entry["id"]) == NULL)
            return (false);
        billing_reconcile_entry_account($entry);
        return (true);
    }

    if (!billing_move_invoice_to_deleted($entry))
        return (false);
    $update = ["deleted" => dbnow()];
    $reference = trim((string)($entry["invoice_reference"] ?? ""));
    if ($reference != "")
    {
        // Keep the historical number on the deleted record, but release the
        // active unique key so an operator can reuse a number for a document
        // that was generated by mistake and never actually delivered.
        $update["deleted_invoice_reference"] = $reference;
        $update["invoice_reference"] = NULL;
    }
    if (!empty($entry["invoice_filename"]))
        $update["invoice_filename"] = $entry["invoice_filename"];
    if (db_update_one("billing_entry", (int)$entry["id"], $update) == NULL)
        return (false);
    billing_reconcile_entry_account($entry);
    return (true);
}

function billing_delete_payment($id_payment)
{
    $payment = billing_payment_with_user($id_payment);
    if ($payment == NULL || !is_billing_manager_for_school((int)$payment["id_school"]))
        return (false);
    if (db_update_one("billing_payment", (int)$payment["id"], ["deleted" => dbnow()]) == NULL)
        return (false);
    if (function_exists("billing_einvoice_payment_removed"))
        billing_einvoice_payment_removed((int)$payment["id"]);
    billing_reconcile_payment_account($payment);
    return (true);
}

function billing_bank_utf8($value)
{
    $value = (string)$value;
    if ($value == "" || preg_match('//u', $value))
        return ($value);
    $converted = @iconv("Windows-1252", "UTF-8//IGNORE", $value);
    return ($converted === false ? $value : $converted);
}

function billing_bank_normalize_text($value)
{
    $value = trim(billing_bank_utf8($value));
    if ($value == "")
        return ("");
    $value = strtr($value, [
        "À"=>"A","Á"=>"A","Â"=>"A","Ã"=>"A","Ä"=>"A","Å"=>"A",
        "à"=>"a","á"=>"a","â"=>"a","ã"=>"a","ä"=>"a","å"=>"a",
        "Ç"=>"C","ç"=>"c","È"=>"E","É"=>"E","Ê"=>"E","Ë"=>"E",
        "è"=>"e","é"=>"e","ê"=>"e","ë"=>"e","Ì"=>"I","Í"=>"I",
        "Î"=>"I","Ï"=>"I","ì"=>"i","í"=>"i","î"=>"i","ï"=>"i",
        "Ñ"=>"N","ñ"=>"n","Ò"=>"O","Ó"=>"O","Ô"=>"O","Õ"=>"O",
        "Ö"=>"O","ò"=>"o","ó"=>"o","ô"=>"o","õ"=>"o","ö"=>"o",
        "Ù"=>"U","Ú"=>"U","Û"=>"U","Ü"=>"U","ù"=>"u","ú"=>"u",
        "û"=>"u","ü"=>"u","Ý"=>"Y","Ÿ"=>"Y","ý"=>"y","ÿ"=>"y",
        "Œ"=>"OE","œ"=>"oe","Æ"=>"AE","æ"=>"ae",
    ]);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return (trim(preg_replace('/\s+/', ' ', $value)));
}

function billing_bank_normalize_header($value)
{
    return (str_replace(" ", "_", billing_bank_normalize_text($value)));
}

function billing_bank_parse_date($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return (NULL);
    foreach (["d/m/Y", "d-m-Y", "Y-m-d", "d/m/y", "d-m-y"] as $format)
    {
        $date = DateTime::createFromFormat("!".$format, $value);
        $errors = DateTime::getLastErrors();
        if ($date !== false && ($errors === false || ($errors["warning_count"] == 0 && $errors["error_count"] == 0)))
            return ($date->format("Y-m-d H:i:s"));
    }
    $stamp = date_to_timestamp($value);
    if ($stamp === NULL)
        return (NULL);
    return (date("Y-m-d H:i:s", $stamp));
}

function billing_bank_parse_amount($value)
{
    $value = trim((string)$value);
    if ($value == "")
        return (NULL);
    $value = str_replace(["€", "EUR", "eur", "\xc2\xa0", " "], "", $value);
    $negative = false;
    if (preg_match('/^\((.*)\)$/', $value, $m))
    {
        $negative = true;
        $value = $m[1];
    }
    if (substr($value, 0, 1) == "+")
        $value = substr($value, 1);
    else if (substr($value, 0, 1) == "-")
    {
        $negative = true;
        $value = substr($value, 1);
    }
    if (strpos($value, ",") !== false && strpos($value, ".") !== false)
    {
        if (strrpos($value, ",") > strrpos($value, "."))
            $value = str_replace([".", ","], ["", "."], $value);
        else
            $value = str_replace(",", "", $value);
    }
    else
        $value = str_replace(",", ".", $value);
    if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $value))
        return (NULL);
    $amount = (int)round(((float)$value) * 100);
    return ($negative ? -$amount : $amount);
}

function billing_bank_detect_delimiter($line)
{
    $best = ";";
    $best_count = 0;
    foreach ([";", "\t", ","] as $delimiter)
    {
        $count = count(str_getcsv($line, $delimiter, '"', "\\"));
        if ($count > $best_count)
        {
            $best_count = $count;
            $best = $delimiter;
        }
    }
    return ($best);
}

function billing_bank_header_index($headers, $aliases)
{
    foreach ($aliases as $alias)
    {
        $alias = billing_bank_normalize_header($alias);
        if (isset($headers[$alias]))
            return ((int)$headers[$alias]);
    }
    return (NULL);
}

function billing_bank_import_csv($file, $id_school)
{
    global $Database;
    global $User;

    $id_school = (int)$id_school;
    if ($id_school <= 0 || !is_billing_manager_for_school($id_school) || !is_file($file))
        return (["ok" => false, "error" => "CannotReadFile"]);
    $handle = @fopen($file, "rb");
    if (!$handle)
        return (["ok" => false, "error" => "CannotReadFile"]);
    $first = fgets($handle);
    if ($first === false)
    {
        fclose($handle);
        return (["ok" => false, "error" => "CannotReadFile"]);
    }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $delimiter = billing_bank_detect_delimiter($first);
    rewind($handle);
    $header_row = fgetcsv($handle, 0, $delimiter, '"', "\\");
    if (!is_array($header_row))
    {
        fclose($handle);
        return (["ok" => false, "error" => "CannotReadFile"]);
    }
    $headers = [];
    foreach ($header_row as $index => $header)
        $headers[billing_bank_normalize_header($header)] = $index;
    $idx_date = billing_bank_header_index($headers, ["Date opération", "Date operation", "Date", "Operation date"]);
    $idx_value_date = billing_bank_header_index($headers, ["Date valeur", "Value date"]);
    $idx_label = billing_bank_header_index($headers, ["Libellé opération", "Libelle operation", "Libellé", "Libelle", "Description", "Label"]);
    $idx_amount = billing_bank_header_index($headers, ["Montant opération", "Montant operation", "Montant", "Amount"]);
    $idx_pointage = billing_bank_header_index($headers, ["Pointage opération", "Pointage operation", "Pointage", "Référence", "Reference"]);
    $idx_comment = billing_bank_header_index($headers, ["Commentaire opération", "Commentaire operation", "Commentaire", "Comment"]);
    if ($idx_date === NULL || $idx_label === NULL || $idx_amount === NULL)
    {
        fclose($handle);
        return (["ok" => false, "error" => "BillingBankCsvColumns"]);
    }

    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $occurrences = [];
    $inserted = 0;
    $known = 0;
    $ignored = 0;
    while (($row = fgetcsv($handle, 0, $delimiter, '"', "\\")) !== false)
    {
        if (!is_array($row) || !count(array_filter($row, function($v) { return (trim((string)$v) !== ""); })))
            continue ;
        $operation_date = billing_bank_parse_date($row[$idx_date] ?? "");
        $label = trim(billing_bank_utf8($row[$idx_label] ?? ""));
        $amount = billing_bank_parse_amount($row[$idx_amount] ?? "");
        if ($operation_date === NULL || $label == "" || $amount === NULL || $amount == 0)
        {
            ++$ignored;
            continue ;
        }
        $value_date = $idx_value_date === NULL ? NULL : billing_bank_parse_date($row[$idx_value_date] ?? "");
        $pointage = $idx_pointage === NULL ? "" : trim(billing_bank_utf8($row[$idx_pointage] ?? ""));
        $comment = $idx_comment === NULL ? "" : trim(billing_bank_utf8($row[$idx_comment] ?? ""));
        $base = implode("|", [
            date("Y-m-d", date_to_timestamp($operation_date)),
            $value_date === NULL ? "" : date("Y-m-d", date_to_timestamp($value_date)),
            billing_bank_normalize_text($label),
            (string)$amount,
            billing_bank_normalize_text($pointage),
            billing_bank_normalize_text($comment),
        ]);
        $occurrence = ($occurrences[$base] ?? 0) + 1;
        $occurrences[$base] = $occurrence;
        $fingerprint = hash("sha256", $base."|".$occurrence);
        $exists = db_select_one("id FROM billing_bank_transaction WHERE id_school = $id_school AND fingerprint = '".db_escape($fingerprint)."'");
        if ($exists != NULL)
        {
            ++$known;
            continue ;
        }
        $edate = db_escape($operation_date);
        $evalue = $value_date === NULL ? "NULL" : "'".db_escape($value_date)."'";
        $elabel = db_escape(substr($label, 0, 512));
        $epointage = db_escape(substr($pointage, 0, 255));
        $ecomment = db_escape($comment);
        $efingerprint = db_escape($fingerprint);
        if ($Database->query("
            INSERT INTO billing_bank_transaction
            (id_school, fingerprint, operation_date, value_date, label, amount, pointage, bank_comment, id_actor)
            VALUES
            ($id_school, '$efingerprint', '$edate', $evalue, '$elabel', $amount, '$epointage', '$ecomment', $actor)
        ") !== false)
            ++$inserted;
    }
    fclose($handle);
    return (["ok" => true, "inserted" => $inserted, "known" => $known, "ignored" => $ignored]);
}

function billing_fetch_bank_transactions($limit = 500)
{
    global $Language;

    $limit = max(1, min(2000, (int)$limit));
    $student_name = "TRIM(CONCAT(COALESCE(student.first_name, ''), ' ', COALESCE(student.family_name, '')))";
    $organization_name = "COALESCE(NULLIF(org.{$Language}_name, ''), NULLIF(org.name, ''), NULLIF(org.legal_name, ''), org.codename)";
    return (db_select_all("
        billing_bank_transaction.*,
        school.codename as school_codename,
        $student_name as matched_student_name,
        student.codename as matched_student_codename,
        $organization_name as matched_organization_name,
        org.codename as matched_organization_codename,
        billing_entry.invoice_reference as matched_invoice_reference
        FROM billing_bank_transaction
        LEFT JOIN school ON school.id = billing_bank_transaction.id_school
        LEFT JOIN user as student ON student.id = billing_bank_transaction.id_user
        LEFT JOIN organization as org ON org.id = billing_bank_transaction.id_organization
        LEFT JOIN billing_entry ON billing_entry.id = billing_bank_transaction.id_billing_entry
        WHERE school.deleted IS NULL
        ".billing_school_filter("billing_bank_transaction")."
        ORDER BY billing_bank_transaction.operation_date DESC, billing_bank_transaction.id DESC
        LIMIT $limit
    "));
}

function billing_bank_students()
{
    $student_authority = user_school_student_authority_sql();
    return (db_select_all("
        DISTINCT user.id, user.codename, user.first_name, user.family_name, user_school.id_school, school.codename as school_codename
        FROM user_school
        LEFT JOIN user ON user.id = user_school.id_user
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.authority = $student_authority
          AND user.deleted IS NULL
          AND school.deleted IS NULL
        ".billing_school_filter("user_school")."
        ORDER BY user.family_name, user.first_name, user.codename
    "));
}

function billing_bank_invoices()
{
    global $Language;

    return (db_select_all("
        billing_entry.id,
        billing_entry.id_user,
        billing_entry.id_organization,
        billing_entry.id_school,
        billing_entry.invoice_reference,
        billing_entry.amount,
        billing_entry.sent_date,
        user.codename,
        user.first_name,
        user.family_name,
        client_organization.codename as organization_codename,
        COALESCE(NULLIF(client_organization.{$Language}_name, ''), NULLIF(client_organization.name, ''), NULLIF(client_organization.legal_name, ''), client_organization.codename) as organization_name
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN organization client_organization ON client_organization.id = billing_entry.id_organization
        WHERE billing_entry.sent_date IS NOT NULL
          AND billing_entry.invoice_reference IS NOT NULL
          AND billing_entry.invoice_reference != ''
          AND billing_entry.deleted IS NULL
          AND billing_entry.entry_type != 'credit_note'
          AND (user.id IS NULL OR user.deleted IS NULL)
          AND (client_organization.id IS NULL OR client_organization.deleted IS NULL)
        ".billing_school_filter("billing_entry")."
        ORDER BY billing_entry.sent_date DESC, billing_entry.id DESC
    "));
}

function billing_bank_user_in_school($id_user, $id_school)
{
    $id_user = (int)$id_user;
    $id_school = (int)$id_school;
    $student_authority = user_school_student_authority_sql();
    return (db_select_one("
        id FROM user_school
        WHERE id_user = $id_user AND id_school = $id_school AND authority = $student_authority
    ") != NULL);
}

function billing_bank_transaction($id)
{
    $id = (int)$id;
    if ($id <= 0)
        return (NULL);
    return (db_select_one("
        billing_bank_transaction.*,
        school.codename as school_codename
        FROM billing_bank_transaction
        LEFT JOIN school ON school.id = billing_bank_transaction.id_school
        WHERE billing_bank_transaction.id = $id
          AND school.deleted IS NULL
    "));
}

function billing_bank_alias_matches($haystack, $alias)
{
    $alias = billing_bank_normalize_text($alias);
    if (strlen(str_replace(" ", "", $alias)) < 4)
        return (false);
    return (strpos($haystack, $alias) !== false);
}

function billing_bank_existing_suggestion($transaction)
{
    if (!is_array($transaction))
        return (NULL);
    $id_school = (int)$transaction["id_school"];
    $date = date("Y-m-d", date_to_timestamp($transaction["operation_date"]));
    $amount = (int)$transaction["amount"];
    $haystack = billing_bank_normalize_text(($transaction["label"] ?? "")." ".($transaction["pointage"] ?? "")." ".($transaction["bank_comment"] ?? ""));

    $payments = billing_bank_existing_payments_for_suggestions();
    $strong = [];
    foreach ($payments as $payment)
    {
        if ((int)$payment["id_school"] !== $id_school ||
            date("Y-m-d", date_to_timestamp($payment["payment_date"])) != $date ||
            (int)$payment["amount"] !== $amount)
            continue ;
        $full = trim(($payment["first_name"] ?? "")." ".($payment["family_name"] ?? ""));
        $organization_name = trim((string)($payment["organization_name"] ?? ""));
        $reference = trim((string)($payment["transfer_reference"] ?? ""));
        if (($reference != "" && billing_bank_alias_matches($haystack, $reference)) ||
            billing_bank_alias_matches($haystack, $payment["codename"] ?? "") ||
            billing_bank_alias_matches($haystack, $full) ||
            billing_bank_alias_matches($haystack, $payment["organization_codename"] ?? "") ||
            billing_bank_alias_matches($haystack, $organization_name))
            $strong[] = $payment;
    }
    if (count($strong) == 1)
    {
        $payment = $strong[0];
        $is_organization = (int)($payment["id_organization"] ?? 0) > 0;
        $name = $is_organization
            ? trim((string)($payment["organization_name"] ?? $payment["organization_codename"] ?? ""))
            : trim(($payment["first_name"] ?? "")." ".($payment["family_name"] ?? ""));
        if ($name == "")
            $name = $payment["codename"] ?? "";
        return ([
            "kind" => "payment",
            "id" => (int)$payment["id"],
            "id_user" => (int)($payment["id_user"] ?? 0),
            "id_organization" => (int)($payment["id_organization"] ?? 0),
            "label" => ($is_organization ? "Règlement client existant — " : "Mouvement élève existant — ").$name,
            "existing" => true,
        ]);
    }

    $movement_type = $amount < 0 ? "debit" : "credit";
    $absolute = abs($amount);
    $entries = db_select_all("
        organization_account_entry.id, organization_account_entry.id_organization,
        organization_account_entry.reference, organization_account_entry.label,
        organization.codename, organization.name, organization.legal_name
        FROM organization_account_entry
        LEFT JOIN organization ON organization.id = organization_account_entry.id_organization
        WHERE organization_account_entry.id_school = $id_school
          AND DATE(organization_account_entry.movement_date) = '".db_escape($date)."'
          AND organization_account_entry.movement_type = '".db_escape($movement_type)."'
          AND organization_account_entry.amount = $absolute
          AND organization_account_entry.deleted IS NULL
          AND organization.deleted IS NULL
    ");
    $strong = [];
    foreach ($entries as $entry)
    {
        $name = $entry["name"] ?: ($entry["legal_name"] ?: ($entry["codename"] ?? ""));
        $reference = trim((string)($entry["reference"] ?? ""));
        if (($reference != "" && billing_bank_alias_matches($haystack, $reference)) ||
            billing_bank_alias_matches($haystack, $entry["codename"] ?? "") ||
            billing_bank_alias_matches($haystack, $name))
            $strong[] = $entry;
    }
    if (count($strong) == 1)
    {
        $entry = $strong[0];
        $name = $entry["name"] ?: ($entry["legal_name"] ?: ($entry["codename"] ?? ""));
        return ([
            "kind" => "organization_entry",
            "id" => (int)$entry["id"],
            "id_organization" => (int)$entry["id_organization"],
            "label" => "Mouvement entreprise existant — ".$name,
            "existing" => true,
        ]);
    }
    return (NULL);
}

function billing_bank_suggest_association($transaction, $students, $organizations, $invoices)
{
    $existing = billing_bank_existing_suggestion($transaction);
    if ($existing !== NULL)
        return ($existing);
    $haystack_raw = (string)($transaction["label"] ?? "")." ".(string)($transaction["pointage"] ?? "")." ".(string)($transaction["bank_comment"] ?? "");
    $haystack = billing_bank_normalize_text($haystack_raw);
    $id_school = (int)$transaction["id_school"];

    foreach ($invoices as $invoice)
    {
        if ((int)$invoice["id_school"] !== $id_school)
            continue ;
        $reference = trim((string)($invoice["invoice_reference"] ?? ""));
        if ($reference != "" && stripos($haystack_raw, $reference) !== false)
        {
            $name = (int)($invoice["id_organization"] ?? 0) > 0
                ? trim((string)($invoice["organization_name"] ?? $invoice["organization_codename"] ?? ""))
                : trim(($invoice["first_name"] ?? "")." ".($invoice["family_name"] ?? ""));
            if ($name == "") $name = $invoice["codename"] ?? "";
            return ([
                "kind" => "invoice", "id" => (int)$invoice["id"],
                "id_user" => (int)($invoice["id_user"] ?? 0),
                "id_organization" => (int)($invoice["id_organization"] ?? 0),
                "label" => "Facture ".$reference." — ".$name,
                "existing" => false,
            ]);
        }
    }

    $student_matches = [];
    foreach ($students as $student)
    {
        if ((int)$student["id_school"] !== $id_school)
            continue ;
        $full = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? ""));
        if (billing_bank_alias_matches($haystack, $student["codename"] ?? "") || billing_bank_alias_matches($haystack, $full))
            $student_matches[] = $student;
    }
    if (count($student_matches) == 1)
    {
        $student = $student_matches[0];
        $name = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? ""));
        if ($name == "") $name = $student["codename"] ?? "";
        return (["kind" => "student", "id" => (int)$student["id"], "id_user" => (int)$student["id"], "label" => "Élève — ".$name, "existing" => false]);
    }

    $organization_matches = [];
    foreach ($organizations as $organization)
    {
        $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"]));
        if (billing_bank_alias_matches($haystack, $organization["codename"] ?? "") || billing_bank_alias_matches($haystack, $name) || billing_bank_alias_matches($haystack, $organization["legal_name"] ?? ""))
            $organization_matches[] = $organization;
    }
    if (count($organization_matches) == 1)
    {
        $organization = $organization_matches[0];
        $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"]));
        return (["kind" => "organization", "id" => (int)$organization["id"], "id_organization" => (int)$organization["id"], "label" => "Entreprise — ".$name, "existing" => false]);
    }
    return (NULL);
}

function billing_bank_mark_matched($id_transaction, $match_type, $match_id, $id_user = NULL, $id_organization = NULL, $id_billing_entry = NULL)
{
    global $Database;

    $id_transaction = (int)$id_transaction;
    $match_id = (int)$match_id;
    $id_user = $id_user === NULL ? "NULL" : (int)$id_user;
    $id_organization = $id_organization === NULL ? "NULL" : (int)$id_organization;
    $id_billing_entry = $id_billing_entry === NULL ? "NULL" : (int)$id_billing_entry;
    $etype = db_escape(substr((string)$match_type, 0, 32));
    return ($Database->query("
        UPDATE billing_bank_transaction
        SET status = 'matched', match_type = '$etype', match_id = $match_id,
            id_user = $id_user, id_organization = $id_organization, id_billing_entry = $id_billing_entry,
            updated_at = NOW()
        WHERE id = $id_transaction AND status = 'pending'
    ") !== false && $Database->affected_rows == 1);
}

function billing_bank_reconcile($id_transaction, $kind, $target_id, $document = NULL)
{
    global $Database;
    global $User;

    $transaction = billing_bank_transaction($id_transaction);
    if ($transaction == NULL || !is_billing_manager_for_school((int)$transaction["id_school"]) || $transaction["status"] != "pending")
        return (["ok" => false, "error" => "NotFound"]);
    $kind = trim((string)$kind);
    $target_id = (int)$target_id;
    if ($target_id <= 0)
        return (["ok" => false, "error" => "NotFound"]);
    $amount = (int)$transaction["amount"];
    $absolute = abs($amount);
    $date = db_escape($transaction["operation_date"]);
    $reference = trim((string)($transaction["pointage"] ?? ""));
    if ($reference == "")
        $reference = trim((string)($transaction["label"] ?? ""));
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";

    if ($kind == "payment")
    {
        $payment = db_select_one("* FROM billing_payment WHERE id = $target_id AND deleted IS NULL");
        if ($payment == NULL || (int)$payment["id_school"] !== (int)$transaction["id_school"] || (int)$payment["amount"] !== $amount)
            return (["ok" => false, "error" => "NotFound"]);
        $id_payment_organization = (int)($payment["id_organization"] ?? 0);
        $id_payment_user = (int)($payment["id_user"] ?? 0);
        $match_type = $id_payment_organization > 0 ? "organization_payment_existing" : "student_payment_existing";
        if (!billing_bank_mark_matched(
            $transaction["id"], $match_type, $target_id,
            $id_payment_user > 0 ? $id_payment_user : NULL,
            $id_payment_organization > 0 ? $id_payment_organization : NULL,
            NULL
        ))
            return (["ok" => false, "error" => "CannotRegister"]);
        return (["ok" => true]);
    }
    if ($kind == "organization_entry")
    {
        $entry = billing_fetch_organization_account_entry($target_id);
        $expected = $amount < 0 ? "debit" : "credit";
        if ($entry == NULL || (int)$entry["id_school"] !== (int)$transaction["id_school"] || (int)$entry["amount"] !== $absolute || $entry["movement_type"] != $expected)
            return (["ok" => false, "error" => "NotFound"]);
        if ($document !== NULL && ($document["content"] ?? NULL) !== NULL && empty($entry["document_path"]))
            if (!billing_store_organization_entry_document($target_id, $document))
                return (["ok" => false, "error" => "CannotWriteFile"]);
        if (!billing_bank_mark_matched($transaction["id"], "organization_entry_existing", $target_id, NULL, (int)$entry["id_organization"], NULL))
            return (["ok" => false, "error" => "CannotRegister"]);
        return (["ok" => true]);
    }

    $id_user = NULL;
    $id_organization = NULL;
    $id_invoice = NULL;
    if ($kind == "invoice")
    {
        $invoice = db_select_one("* FROM billing_entry WHERE id = $target_id AND sent_date IS NOT NULL AND deleted IS NULL");
        if ($invoice == NULL || (int)$invoice["id_school"] !== (int)$transaction["id_school"] ||
            billing_is_credit_note($invoice))
            return (["ok" => false, "error" => "NotFound"]);
        $id_invoice = (int)$invoice["id"];
        $id_organization = (int)($invoice["id_organization"] ?? 0);
        if ($id_organization <= 0)
            $id_user = (int)($invoice["id_user"] ?? 0);
        else if ($amount <= 0)
            return (["ok" => false, "error" => "BillingBankMatchInvalid"]);
    }
    else if ($kind == "student")
        $id_user = $target_id;

    if ($id_organization !== NULL && $id_organization > 0)
    {
        if (!billing_organization_exists($id_organization))
            return (["ok" => false, "error" => "NotFound"]);
        $eref = db_escape(substr($reference, 0, 255));
        $comment = db_escape("Import bancaire: ".trim((string)$transaction["label"]));
        if ($Database->query("
            INSERT INTO billing_payment
            (id_user, id_organization, id_school, amount, payment_date, transfer_reference, comment, id_actor)
            VALUES
            (NULL, $id_organization, ".(int)$transaction["id_school"].", $amount, '$date', '$eref', '$comment', $actor)
        ") === false)
            return (["ok" => false, "error" => "CannotRegister"]);
        $id_payment = (int)$Database->insert_id;
        if (function_exists("billing_einvoice_track_payment"))
            billing_einvoice_track_payment($id_payment);
        billing_reconcile_payment_account([
            "id_user" => NULL,
            "id_organization" => $id_organization,
            "id_school" => (int)$transaction["id_school"],
        ]);
        if (!billing_bank_mark_matched($transaction["id"], "organization_payment", $id_payment, NULL, $id_organization, $id_invoice))
        {
            billing_delete_payment($id_payment);
            return (["ok" => false, "error" => "CannotRegister"]);
        }
        return (["ok" => true]);
    }

    if ($id_user !== NULL)
    {
        if (!billing_bank_user_in_school($id_user, (int)$transaction["id_school"]))
            return (["ok" => false, "error" => "NotFound"]);
        if ($amount < 0)
        {
            $account = billing_account_for_user($id_user);
            if ($absolute > max(0, -(int)($account["balance"] ?? 0)))
                return (["ok" => false, "error" => "BillingRefundExceedsCredit"]);
        }
        $eref = db_escape(substr($reference, 0, 255));
        $comment = db_escape("Import bancaire: ".trim((string)$transaction["label"]));
        if ($Database->query("
            INSERT INTO billing_payment
            (id_user, id_organization, id_school, amount, payment_date, transfer_reference, comment, id_actor)
            VALUES
            ($id_user, NULL, ".(int)$transaction["id_school"].", $amount, '$date', '$eref', '$comment', $actor)
        ") === false)
            return (["ok" => false, "error" => "CannotRegister"]);
        $id_payment = (int)$Database->insert_id;
        if (function_exists("billing_einvoice_track_payment"))
            billing_einvoice_track_payment($id_payment);
        billing_reconcile_payment_account([
            "id_user" => $id_user,
            "id_organization" => NULL,
            "id_school" => (int)$transaction["id_school"],
        ]);
        if (!billing_bank_mark_matched($transaction["id"], $amount < 0 ? "student_refund" : "student_payment", $id_payment, $id_user, NULL, $id_invoice))
        {
            billing_delete_payment($id_payment);
            return (["ok" => false, "error" => "CannotRegister"]);
        }
        return (["ok" => true]);
    }

    if ($kind == "organization")
    {
        if (!billing_organization_exists($target_id))
            return (["ok" => false, "error" => "NotFound"]);
        $movement_type = $amount < 0 ? "debit" : "credit";
        $id_entry = billing_add_organization_entry(
            (int)$transaction["id_school"], $target_id, $movement_type, $absolute,
            $transaction["operation_date"], $transaction["label"], $reference,
            trim((string)($transaction["bank_comment"] ?? ""))
        );
        if (!$id_entry)
            return (["ok" => false, "error" => "CannotRegister"]);
        if ($document !== NULL && ($document["content"] ?? NULL) !== NULL && !billing_store_organization_entry_document($id_entry, $document))
        {
            billing_delete_organization_entry($id_entry);
            return (["ok" => false, "error" => "CannotWriteFile"]);
        }
        if (!billing_bank_mark_matched($transaction["id"], "organization_entry", $id_entry, NULL, $target_id, NULL))
        {
            billing_delete_organization_entry($id_entry);
            return (["ok" => false, "error" => "CannotRegister"]);
        }
        return (["ok" => true]);
    }
    return (["ok" => false, "error" => "NotFound"]);
}

function billing_bank_ignore($id_transaction)
{
    global $Database;
    $transaction = billing_bank_transaction($id_transaction);
    if ($transaction == NULL || !is_billing_manager_for_school((int)$transaction["id_school"]) || $transaction["status"] != "pending")
        return (false);
    $id_transaction = (int)$transaction["id"];
    return ($Database->query("
        UPDATE billing_bank_transaction
        SET status = 'ignored', match_type = NULL, match_id = NULL, updated_at = NOW()
        WHERE id = $id_transaction AND status = 'pending'
    ") !== false);
}

function billing_bank_reopen($id_transaction)
{
    global $Database;
    $transaction = billing_bank_transaction($id_transaction);
    if ($transaction == NULL || !is_billing_manager_for_school((int)$transaction["id_school"]) || $transaction["status"] == "pending")
        return (false);
    if ($transaction["status"] == "matched")
    {
        $match_type = (string)($transaction["match_type"] ?? "");
        $match_id = (int)($transaction["match_id"] ?? 0);
        if (in_array($match_type, ["student_payment", "student_refund", "organization_payment"], true) && $match_id > 0)
        {
            if (!billing_delete_payment($match_id))
                return (false);
        }
        else if ($match_type == "organization_entry" && $match_id > 0)
        {
            if (!billing_delete_organization_entry($match_id))
                return (false);
        }
        // *_existing means that the movement pre-dated the bank import: unlink it only.
    }
    $id_transaction = (int)$transaction["id"];
    return ($Database->query("
        UPDATE billing_bank_transaction
        SET status = 'pending', match_type = NULL, match_id = NULL,
            id_user = NULL, id_organization = NULL, id_billing_entry = NULL, updated_at = NOW()
        WHERE id = $id_transaction
    ") !== false);
}

function billing_bank_create_supplier_invoice($id_school, $id_organization, $amount, $invoice_date, $reference, $label, $comment, $document, $id_bank_transaction = 0)
{
    $id_school = (int)$id_school;
    $id_organization = (int)$id_organization;
    $amount = (int)$amount;
    $id_bank_transaction = (int)$id_bank_transaction;
    if ($id_school <= 0 || $id_organization <= 0 || $amount <= 0 || !is_billing_manager_for_school($id_school) || !billing_organization_exists($id_organization))
        return (["ok" => false, "error" => "NotFound"]);
    if ($document === NULL || ($document["content"] ?? NULL) === NULL)
        return (["ok" => false, "error" => "InvalidBillingOrganizationDocument"]);
    $invoice_date = billing_bank_parse_date($invoice_date);
    if ($invoice_date === NULL)
        return (["ok" => false, "error" => "BadDate"]);
    $label = trim((string)$label);
    if ($label == "")
    {
        $org = db_select_one("codename, name, legal_name FROM organization WHERE id = $id_organization AND deleted IS NULL");
        $name = $org == NULL ? "Entreprise" : ($org["name"] ?: ($org["legal_name"] ?: $org["codename"]));
        $label = "Facture ".$name;
    }
    $id_entry = billing_add_organization_entry($id_school, $id_organization, "debit", $amount, $invoice_date, $label, $reference, $comment);
    if (!$id_entry)
        return (["ok" => false, "error" => "CannotRegister"]);
    if (!billing_store_organization_entry_document($id_entry, $document))
    {
        billing_delete_organization_entry($id_entry);
        return (["ok" => false, "error" => "CannotWriteFile"]);
    }
    if ($id_bank_transaction > 0)
    {
        $transaction = billing_bank_transaction($id_bank_transaction);
        if ($transaction == NULL || $transaction["status"] != "pending" ||
            (int)$transaction["id_school"] !== $id_school || (int)$transaction["amount"] >= 0 ||
            abs((int)$transaction["amount"]) !== $amount)
        {
            billing_delete_organization_entry($id_entry);
            return (["ok" => false, "error" => "BillingBankMatchInvalid"]);
        }
        if (!billing_bank_mark_matched($id_bank_transaction, "organization_entry_existing", $id_entry, NULL, $id_organization, NULL))
        {
            billing_delete_organization_entry($id_entry);
            return (["ok" => false, "error" => "CannotRegister"]);
        }
    }
    return (["ok" => true, "id_entry" => $id_entry]);
}

function billing_bank_existing_payments_for_suggestions()
{
    global $Language;

    return (db_select_all("
        billing_payment.id, billing_payment.id_user, billing_payment.id_organization, billing_payment.id_school,
        billing_payment.amount, billing_payment.payment_date, billing_payment.transfer_reference,
        user.codename, user.first_name, user.family_name,
        client_organization.codename as organization_codename,
        COALESCE(NULLIF(client_organization.{$Language}_name, ''), NULLIF(client_organization.name, ''), NULLIF(client_organization.legal_name, ''), client_organization.codename) as organization_name
        FROM billing_payment
        LEFT JOIN user ON user.id = billing_payment.id_user
        LEFT JOIN organization client_organization ON client_organization.id = billing_payment.id_organization
        WHERE billing_payment.deleted IS NULL
        ".billing_school_filter("billing_payment")."
        ORDER BY billing_payment.payment_date DESC, billing_payment.id DESC
    "));
}

function billing_bank_existing_entries_for_suggestions()
{
    return (db_select_all("
        organization_account_entry.id, organization_account_entry.id_school,
        organization_account_entry.id_organization, organization_account_entry.amount,
        organization_account_entry.movement_type, organization_account_entry.movement_date,
        organization_account_entry.reference, organization_account_entry.label,
        organization.codename, organization.name, organization.legal_name
        FROM organization_account_entry
        LEFT JOIN organization ON organization.id = organization_account_entry.id_organization
        WHERE organization_account_entry.deleted IS NULL
          AND organization.deleted IS NULL
        ".billing_school_filter("organization_account_entry")."
        ORDER BY organization_account_entry.movement_date DESC, organization_account_entry.id DESC
    "));
}

function billing_bank_suggest_association_cached($transaction, $students, $organizations, $invoices, $payments, $organization_entries)
{
    $haystack_raw = (string)($transaction["label"] ?? "")." ".(string)($transaction["pointage"] ?? "")." ".(string)($transaction["bank_comment"] ?? "");
    $haystack = billing_bank_normalize_text($haystack_raw);
    $id_school = (int)$transaction["id_school"];
    $amount = (int)$transaction["amount"];
    $day = date("Y-m-d", date_to_timestamp($transaction["operation_date"]));

    $same_payment = [];
    $strong = [];
    foreach ($payments as $payment)
    {
        if ((int)$payment["id_school"] !== $id_school || (int)$payment["amount"] !== $amount || date("Y-m-d", date_to_timestamp($payment["payment_date"])) != $day)
            continue ;
        $same_payment[] = $payment;
        $full = trim(($payment["first_name"] ?? "")." ".($payment["family_name"] ?? ""));
        $organization_name = trim((string)($payment["organization_name"] ?? ""));
        $reference = trim((string)($payment["transfer_reference"] ?? ""));
        if (($reference != "" && billing_bank_alias_matches($haystack, $reference)) ||
            billing_bank_alias_matches($haystack, $payment["codename"] ?? "") ||
            billing_bank_alias_matches($haystack, $full) ||
            billing_bank_alias_matches($haystack, $payment["organization_codename"] ?? "") ||
            billing_bank_alias_matches($haystack, $organization_name))
            $strong[] = $payment;
    }
    if (count($strong) == 1 || (count($strong) == 0 && count($same_payment) == 1))
    {
        $payment = count($strong) == 1 ? $strong[0] : $same_payment[0];
        $is_organization = (int)($payment["id_organization"] ?? 0) > 0;
        $name = $is_organization
            ? trim((string)($payment["organization_name"] ?? $payment["organization_codename"] ?? ""))
            : trim(($payment["first_name"] ?? "")." ".($payment["family_name"] ?? ""));
        if ($name == "") $name = $payment["codename"] ?? "";
        return ([
            "kind"=>"payment",
            "id"=>(int)$payment["id"],
            "id_user"=>(int)($payment["id_user"] ?? 0),
            "id_organization"=>(int)($payment["id_organization"] ?? 0),
            "label"=>($is_organization ? "Règlement client existant — " : "Mouvement élève existant — ").$name,
            "existing"=>true
        ]);
    }

    $movement_type = $amount < 0 ? "debit" : "credit";
    $absolute = abs($amount);
    $strong = [];
    $bank_stamp = date_to_timestamp($transaction["operation_date"]);
    foreach ($organization_entries as $entry)
    {
        if ((int)$entry["id_school"] !== $id_school || (int)$entry["amount"] !== $absolute || $entry["movement_type"] != $movement_type)
            continue ;
        $entry_stamp = date_to_timestamp($entry["movement_date"]);
        if ($entry_stamp === NULL || $bank_stamp === NULL || abs($bank_stamp - $entry_stamp) > 120 * 86400)
            continue ;
        $name = $entry["name"] ?: ($entry["legal_name"] ?: ($entry["codename"] ?? ""));
        $reference = trim((string)($entry["reference"] ?? ""));
        if (($reference != "" && billing_bank_alias_matches($haystack, $reference)) || billing_bank_alias_matches($haystack, $entry["codename"] ?? "") || billing_bank_alias_matches($haystack, $name))
            $strong[] = $entry;
    }
    if (count($strong) == 1)
    {
        $entry = $strong[0];
        $name = $entry["name"] ?: ($entry["legal_name"] ?: ($entry["codename"] ?? ""));
        return (["kind"=>"organization_entry", "id"=>(int)$entry["id"], "id_organization"=>(int)$entry["id_organization"], "label"=>"Mouvement entreprise existant — ".$name, "existing"=>true]);
    }

    foreach ($invoices as $invoice)
    {
        if ((int)$invoice["id_school"] !== $id_school)
            continue ;
        $reference = trim((string)($invoice["invoice_reference"] ?? ""));
        if ($reference != "" && stripos($haystack_raw, $reference) !== false)
        {
            $name = (int)($invoice["id_organization"] ?? 0) > 0
                ? trim((string)($invoice["organization_name"] ?? $invoice["organization_codename"] ?? ""))
                : trim(($invoice["first_name"] ?? "")." ".($invoice["family_name"] ?? ""));
            if ($name == "") $name = $invoice["codename"] ?? "";
            return ([
                "kind"=>"invoice",
                "id"=>(int)$invoice["id"],
                "id_user"=>(int)($invoice["id_user"] ?? 0),
                "id_organization"=>(int)($invoice["id_organization"] ?? 0),
                "label"=>"Facture ".$reference." — ".$name,
                "existing"=>false
            ]);
        }
    }

    $student_matches = [];
    foreach ($students as $student)
    {
        if ((int)$student["id_school"] !== $id_school)
            continue ;
        $full = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? ""));
        if (billing_bank_alias_matches($haystack, $student["codename"] ?? "") || billing_bank_alias_matches($haystack, $full))
            $student_matches[] = $student;
    }
    if (count($student_matches) == 1)
    {
        $student = $student_matches[0];
        $name = trim(($student["first_name"] ?? "")." ".($student["family_name"] ?? ""));
        if ($name == "") $name = $student["codename"] ?? "";
        return (["kind"=>"student", "id"=>(int)$student["id"], "id_user"=>(int)$student["id"], "label"=>"Élève — ".$name, "existing"=>false]);
    }

    $organization_matches = [];
    foreach ($organizations as $organization)
    {
        $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"]));
        if (billing_bank_alias_matches($haystack, $organization["codename"] ?? "") || billing_bank_alias_matches($haystack, $name) || billing_bank_alias_matches($haystack, $organization["legal_name"] ?? ""))
            $organization_matches[] = $organization;
    }
    if (count($organization_matches) == 1)
    {
        $organization = $organization_matches[0];
        $name = $organization["localized_name"] ?: ($organization["name"] ?: ($organization["legal_name"] ?: $organization["codename"]));
        return (["kind"=>"organization", "id"=>(int)$organization["id"], "id_organization"=>(int)$organization["id"], "label"=>"Entreprise — ".$name, "existing"=>false]);
    }
    return (NULL);
}
