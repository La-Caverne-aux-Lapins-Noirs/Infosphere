<?php

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
    $entry = db_select_one("id_user FROM billing_entry WHERE id = $id_entry");
    if ($entry == NULL)
        return (false);
    return (billing_user_is_managed($entry["id_user"]));
}

function is_billing_manager_for_billing_payment($id_payment)
{
    if (is_admin())
        return (true);
    $id_payment = (int)$id_payment;
    if ($id_payment <= 0)
        return (am_i_billing_manager());
    $payment = db_select_one("id_user FROM billing_payment WHERE id = $id_payment AND deleted IS NULL");
    if ($payment == NULL)
        return (false);
    return (billing_user_is_managed($payment["id_user"]));
}

function is_billing_manager_for_user($id_user)
{
    return (billing_user_is_managed($id_user));
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

function billing_user_main_school($id_user)
{
    $id_user = (int)$id_user;
    $student_authority = user_school_student_authority_sql();
    return (db_select_one("
        school.id as id_school,
        school.codename as school_codename
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
    global $Language;

    $id_user = (int)$id_user;
    $cycles = db_select_all("
        cycle.id,
        cycle.codename,
        cycle.{$Language}_name as name,
        cycle.first_day,
        cycle.cycle as cycle_level
        FROM user_cycle
        LEFT JOIN cycle ON cycle.id = user_cycle.id_cycle
        WHERE user_cycle.id_user = $id_user
          AND cycle.deleted IS NULL
        ORDER BY cycle.first_day ASC, cycle.id ASC
    ");

    if (!count($cycles))
        return ([
            "index" => NULL,
            "year" => NULL,
            "trimester" => NULL,
            "cycle_count" => 0,
            "first" => NULL,
            "last" => NULL,
        ]);

    $first = $cycles[array_key_first($cycles)];
    $last = $cycles[array_key_last($cycles)];
    $index = (int)$first["cycle_level"] + count($cycles) - 1;

    return ([
        "index" => $index,
        "year" => floor($index / 4) + 1,
        "trimester" => $index % 4 + 1,
        "cycle_count" => count($cycles),
        "first" => $first,
        "last" => $last,
    ]);
}

function billing_account_for_user($id_user)
{
    $id_user = (int)$id_user;
    $today = dbnow();
    // Draft credit notes are not commitments and therefore must not alter the
    // projected account before they are actually issued.
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
    $paid = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_user = $id_user
          AND deleted IS NULL
    ");
    $planned = (int)$planned["amount"];
    $issued = (int)$issued["amount"];
    $due = (int)$due["amount"];
    $to_invoice = (int)$to_invoice["amount"];
    $paid = (int)$paid["amount"];
    $balance = $issued - $paid;
    $projected_balance = $planned - $paid;
    $coverage = billing_payment_coverage_for_user($id_user);
    $late = 0;
    $today_stamp = now();
    foreach (billing_positive_invoices_for_user($id_user, true) as $entry)
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


function billing_account_statement($entries, $payments)
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

    $order = ["invoice" => 0, "credit_note" => 1, "payment" => 2, "refund" => 3];
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
        $student["account_statement"] = billing_account_statement($student["entries"], $student["payments"]);
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
          AND deleted IS NULL
        ORDER BY payment_date ASC, id ASC
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
        COALESCE(NULLIF(organization.{$Language}_name, ''), NULLIF(organization.name, ''), NULLIF(organization.legal_name, ''), school.codename) as school_name,
        billing_template.name as template_name,
        billing_template.tariff_year as template_tariff_year
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
        LEFT JOIN organization ON organization.id = school.id_organization
        LEFT JOIN billing_template ON billing_template.id = billing_entry.id_template
        WHERE billing_entry.sent_date IS NOT NULL
        ".billing_school_filter("billing_entry")."
        ORDER BY billing_entry.sent_date DESC, billing_entry.id DESC
    ");

    $coverages = [];
    foreach ($invoices as &$invoice)
    {
        $id_user = (int)$invoice["id_user"];
        if (!isset($coverages[$id_user]))
            $coverages[$id_user] = billing_payment_coverage_for_user($id_user);
        $invoice["covered_amount"] = $coverages[$id_user][(int)$invoice["id"]] ?? 0;
        $invoice["credit_capacity"] = 0;
        if (empty($invoice["deleted"]) && !billing_is_credit_note($invoice) && (int)$invoice["amount"] > 0)
            $invoice["credit_capacity"] = max(0, (int)$invoice["amount"] - billing_credit_note_total_for_entry((int)$invoice["id"], false));
        $invoice["status_key"] = "issued";
        if (!empty($invoice["deleted"]))
            $invoice["status_key"] = "deleted";
        else if (billing_is_credit_note($invoice))
            $invoice["status_key"] = "credit_note";
        else if (!empty($invoice["paid_date"]))
            $invoice["status_key"] = "paid";
        else if ($invoice["covered_amount"] >= (int)$invoice["amount"])
            $invoice["status_key"] = "covered";
        else if ($invoice["covered_amount"] > 0)
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
    ]);
}

function billing_normalize_invoice_type($type)
{
    $type = strtolower(trim((string)$type));
    if ($type == "ecole")
        $type = "school";
    if (!array_key_exists($type, billing_invoice_types()))
        $type = "school";
    return ($type);
}

function billing_invoice_type_label($type)
{
    $types = billing_invoice_types();
    $type = billing_normalize_invoice_type($type);
    return ($types[$type] ?? $type);
}

function billing_add_entry($id_user, $label, $amount, $due_date, $id_template = NULL, $entry_type = "tuition", $invoice_type = "school")
{
    global $Database;
    global $User;

    $id_user = (int)$id_user;
    $amount = (int)$amount;
    if (!billing_user_is_managed($id_user) || $amount <= 0 || trim($label) == "")
        return (false);
    $school = billing_user_main_school($id_user);
    if ($school == NULL)
        return (false);
    $label = $Database->real_escape_string(trim($label));
    $due_date = $Database->real_escape_string(db_form_date($due_date));
    $entry_type = trim((string)$entry_type);
    if ($entry_type == "")
        $entry_type = "tuition";
    $entry_type = $Database->real_escape_string($entry_type);
    $invoice_type = $Database->real_escape_string(billing_normalize_invoice_type($invoice_type));
    $id_template = $id_template === NULL ? "NULL" : (int)$id_template;
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    return ($Database->query("
        INSERT INTO billing_entry
        (id_user, id_school, id_template, label, amount, entry_type, invoice_type, due_date, id_actor)
        VALUES
        ($id_user, {$school["id_school"]}, $id_template, '$label', $amount, '$entry_type', '$invoice_type', '$due_date', $actor)
    ") != NULL);
}


function billing_add_credit_note($id_user, $related_entry_id, $label, $amount, $date)
{
    global $Database;
    global $User;

    $id_user = (int)$id_user;
    $related_entry_id = (int)$related_entry_id;
    $amount = (int)$amount;
    if (!billing_user_is_managed($id_user) || $amount <= 0 || $related_entry_id <= 0)
        return (false);
    $related = billing_entry_with_user($related_entry_id);
    if ($related == NULL || (int)$related["id_user"] != $id_user || empty($related["sent_date"]) ||
        billing_is_credit_note($related) || (int)$related["amount"] <= 0)
        return (false);
    $already = billing_credit_note_total_for_entry($related_entry_id, false);
    if ($already + $amount > (int)$related["amount"])
        return (false);
    $school = billing_user_main_school($id_user);
    if ($school == NULL)
        return (false);
    $label = trim((string)$label);
    if ($label == "")
        $label = "Avoir sur facture ".billing_invoice_document_reference($related);
    $label = $Database->real_escape_string($label);
    $date = $Database->real_escape_string(db_form_date($date));
    $invoice_type = $Database->real_escape_string(billing_normalize_invoice_type($related["invoice_type"] ?? "school"));
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    $negative = -$amount;
    return ($Database->query("
        INSERT INTO billing_entry
        (id_user, id_school, id_template, label, amount, entry_type, related_entry_id, invoice_type, due_date, id_actor)
        VALUES
        ($id_user, {$school["id_school"]}, NULL, '$label', $negative, 'credit_note', $related_entry_id, '$invoice_type', '$date', $actor)
    ") != NULL);
}

function billing_apply_template($id_user, $id_template, $first_due_date, $schedule)
{
    $id_user = (int)$id_user;
    $id_template = (int)$id_template;
    if (!billing_user_is_managed($id_user))
        return (0);
    $template = db_select_one("* FROM billing_template WHERE id = $id_template AND deleted IS NULL");
    if ($template == NULL)
        return (0);
    if (!is_admin() && !in_array((int)$template["id_school"], billing_managed_school_ids()))
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
            $template["invoice_type"] ?? "school"
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
        if (billing_add_entry($id_user, $template["name"]." - échéance ".($idx + 1)."/".count($offsets), $amount, $date->format("Y-m-d H:i:s"), $id_template, "tuition", $template["invoice_type"] ?? "school"))
            ++$count;
    }
    return ($count);
}

function billing_invoice_recipients($id_user)
{
    $id_user = (int)$id_user;
    $mails = [];
    foreach (db_select_all("mail FROM user WHERE id = $id_user AND mail IS NOT NULL AND mail != ''") as $m)
        $mails[] = $m["mail"];

    $relations = db_select_all("
        user.mail, parent_child.relation
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_parent
        WHERE parent_child.id_child = $id_user
          AND user.mail IS NOT NULL
          AND user.mail != ''
        ORDER BY parent_child.id ASC
    ");
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
        $mails[] = $relation["mail"];
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

function billing_invoice_document_reference($entry)
{
    if (empty($entry["sent_date"]))
        return (billing_invoice_draft_reference());

    $reference = trim((string)($entry["invoice_reference"] ?? ""));
    if ($reference != "")
        return ($reference);
    return (billing_invoice_missing_reference());
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
          AND deleted IS NULL
          AND amount > 0
          AND entry_type != 'credit_note'
          $issued_filter
        ORDER BY due_date ASC, id ASC
    "));
}

function billing_payment_schedule_for_user($id_user)
{
    $id_user = (int)$id_user;
    $payments = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_user = $id_user
          AND deleted IS NULL
    ");
    $available = max(0, (int)($payments["amount"] ?? 0));
    $rows = [];

    foreach (billing_positive_invoices_for_user($id_user, false) as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        $credit = min($amount, billing_credit_note_total_for_entry((int)$entry["id"], true));
        $effective = max(0, $amount - $credit);
        $cash = min($effective, $available);
        $available -= $cash;
        $remaining = $effective - $cash;
        if ($remaining <= 0)
            continue ;
        $rows[] = [
            "id" => (int)$entry["id"],
            "date" => $entry["due_date"],
            "label" => $entry["label"],
            "reference" => !empty($entry["sent_date"]) ? billing_invoice_document_reference($entry) : "",
            "issued" => !empty($entry["sent_date"]),
            "remaining" => $remaining,
        ];
    }
    return ($rows);
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
    $id_entry = (int)$id_entry;
    $deleted_filter = $include_deleted ? "" : "AND billing_entry.deleted IS NULL";
    return (db_select_one("
        billing_entry.*,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        school.codename as school_codename
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
        WHERE billing_entry.id = $id_entry
          $deleted_filter
    "));
}

function billing_payment_with_user($id_payment)
{
    $id_payment = (int)$id_payment;
    return (db_select_one("
        billing_payment.*,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail
        FROM billing_payment
        LEFT JOIN user ON user.id = billing_payment.id_user
        WHERE billing_payment.id = $id_payment
          AND billing_payment.deleted IS NULL
    "));
}

function billing_invoice_text($entry, $reference)
{
    global $Dictionnary;

    $name = trim(($entry["first_name"] ?? "")." ".($entry["family_name"] ?? ""));
    if ($name == "")
        $name = $entry["codename"] ?? "";
    $piece = billing_entry_document_label($entry);
    $amount = billing_is_credit_note($entry) ? abs((int)$entry["amount"]) : (int)$entry["amount"];
    $body =
        $piece." : ".$reference."\n".
        $Dictionnary["Student"]." : ".$name."\n".
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


function billing_invoice_mail_body($entry, $reference, $relative_path = "")
{
    global $Dictionnary;

    $body = billing_invoice_text($entry, $reference);
    if ($relative_path != "")
        $body .= "\n".$Dictionnary["InvoiceCopySavedIn"]." : ".$relative_path."\n";
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

    if (billing_is_credit_note($entry) && $state !== "deleted")
        return ($Configuration->UsersDir($entry["codename"])."admin/credit_notes/");
    return (billing_invoice_directory($entry["codename"], $state));
}

function billing_entry_document_relative_path($entry, $state = false)
{
    if (empty($entry["invoice_filename"]))
        return ("");
    if (billing_is_credit_note($entry) && $state !== "deleted")
        return ("admin/credit_notes/".$entry["invoice_filename"]);
    return (billing_invoice_relative_path_for_state($state, $entry["invoice_filename"]));
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
    $invoice = [
        "id" => (int)$entry["id"],
        "reference" => $reference,
        "label" => $entry["label"] ?? "",
        "type" => billing_normalize_invoice_type($entry["invoice_type"] ?? "school"),
        "type_label" => billing_invoice_type_label($entry["invoice_type"] ?? "school"),
        "entry_type" => $entry["entry_type"] ?? "",
        "amount_cents" => (int)($entry["amount"] ?? 0),
        "amount" => billing_euros($entry["amount"] ?? 0),
        "amount_euros" => billing_euros($entry["amount"] ?? 0),
        "due_date" => $entry["due_date"] ?? "",
        "due_date_label" => billing_document_date_label($entry["due_date"] ?? ""),
        "sent_date" => $entry["sent_date"] ?? "",
        "sent_date_label" => empty($entry["sent_date"]) ? "" : billing_document_date_label($entry["sent_date"]),
        "paid_date" => $entry["paid_date"] ?? "",
        "paid_date_label" => empty($entry["paid_date"]) ? "" : billing_document_date_label($entry["paid_date"]),
    ];
    document_context_flatten($fields, "Invoice", $invoice);
    document_context_flatten($fields, "Billing", $invoice);

    $student = document_context_person((int)$entry["id_user"]);
    if ($student != NULL)
    {
        document_context_flatten($fields, "Student", $student);
        document_context_flatten($fields, "Destination", $student);
        document_context_flatten($fields, "User", $student);
    }

    $parent = document_context_parent_for_user((int)$entry["id_user"]);
    if ($parent != NULL)
    {
        document_context_flatten($fields, "Parent", $parent);
        document_context_flatten($fields, "FinancialResponsible", $parent);
    }


    $finance = function_exists("document_context_relation_user")
        ? document_context_relation_user((int)$entry["id_user"], "financial", 0) : NULL;
    if ($finance == NULL)
        $finance = $student;
    if ($finance != NULL)
    {
        document_context_flatten($fields, "Finance", $finance);
        document_context_flatten($fields, "FinancialResponsible", $finance);
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

function billing_invoice_existing_file_path($entry)
{
    if (empty($entry["invoice_filename"]))
        return ("");
    $state = !empty($entry["deleted"]) ? "deleted" : (!empty($entry["paid_date"]) ? true : false);
    if (billing_is_credit_note($entry) && empty($entry["deleted"]))
        $state = false;
    $path = billing_entry_document_directory($entry, $state).$entry["invoice_filename"];
    if (!file_exists($path) || is_dir($path))
        return ("");
    if (substr((string)@file_get_contents($path, false, NULL, 0, 4), 0, 4) !== "%PDF")
        return ("");
    return ($path);
}


function billing_invoice_pdf_response($id_entry)
{
    $entry = billing_entry_with_user($id_entry, true);
    if ($entry == NULL || !billing_user_is_managed($entry["id_user"]))
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
          AND id != $id_entry
    ") == NULL);
}

function billing_unlink_invoice_files($entry)
{
    if (empty($entry["invoice_filename"]))
        return ;
    foreach ([false, true, "deleted"] as $state)
    {
        $path = billing_entry_document_directory($entry, $state).$entry["invoice_filename"];
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

    $dir = billing_invoice_directory($entry["codename"], false);
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


function billing_paid_entry_ids_for_user($id_user)
{
    $covered = [];
    $coverage = billing_payment_coverage_for_user($id_user);

    foreach (billing_fetch_entries($id_user) as $entry)
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
    foreach (billing_fetch_entries($id_user) as $entry)
        if (isset($paid_entries[(int)$entry["id"]]) && !empty($entry["sent_date"]) && empty($entry["paid_date"]))
            $entries[] = $entry;
    return ($entries);
}

function billing_send_invoice_placeholder($id_entry, $reference)
{
    global $Dictionnary;

    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_user_is_managed($entry["id_user"]))
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

    $recipients = billing_invoice_recipients($entry["id_user"]);
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

    $body = billing_invoice_mail_body($entry, $reference, $file["relative_path"]);
    $mail = send_mail(
        $recipients,
        billing_entry_document_label($entry)." ".$reference,
        $body,
        NULL,
        [$file["filename"] => $file["content"]],
        false
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

    billing_reconcile_paid_invoices($entry["id_user"]);
    billing_archive_paid_invoices($entry["id_user"]);

    $updated = billing_entry_with_user($entry["id"]);
    if ($updated != NULL)
        $file["relative_path"] = billing_invoice_relative_path($updated);
    return ($file);
}

function billing_archive_paid_invoices($id_user)
{
    $id_user = (int)$id_user;
    if (!billing_user_is_managed($id_user))
        return (0);

    $count = 0;
    foreach (billing_paid_archivable_entries($id_user) as $entry)
    {
        if (billing_is_credit_note($entry))
            continue ;
        $entry = billing_entry_with_user($entry["id"]);
        if ($entry == NULL)
            continue ;
        $ref = billing_invoice_document_reference($entry);
        if (empty($entry["invoice_filename"]) && !billing_is_external_invoice($entry))
            $entry["invoice_filename"] = billing_invoice_safe_filename($ref);

        $source_dir = billing_invoice_directory($entry["codename"], false);
        $target_dir = billing_invoice_directory($entry["codename"], true);
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

function billing_reconcile_paid_invoices($id_user)
{
    $id_user = (int)$id_user;
    if (!billing_user_is_managed($id_user))
        return (0);

    $paid_entries = billing_paid_entry_ids_for_user($id_user);
    $count = 0;
    foreach (billing_fetch_entries($id_user) as $entry)
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
            $paid_dir = billing_invoice_directory($entry["codename"], true);
            $to_pay_dir = billing_invoice_directory($entry["codename"], false);
            new_directory($to_pay_dir."index.php");
            $source = $paid_dir.$entry["invoice_filename"];
            $target = $to_pay_dir.$entry["invoice_filename"];
            if (file_exists($source))
                @rename($source, $target);
        }
        db_update_one("billing_entry", (int)$entry["id"], ["paid_date" => NULL]);
        ++$count;
    }
    return ($count);
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

    $target_dir = billing_invoice_directory($entry["codename"], "deleted");
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

function billing_delete_entry($id_entry)
{
    global $Database;

    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_user_is_managed($entry["id_user"]))
        return (false);

    if (empty($entry["sent_date"]))
    {
        billing_unlink_invoice_files($entry);
        if ($Database->query("DELETE FROM billing_entry WHERE id = ".(int)$entry["id"]) == NULL)
            return (false);
        billing_reconcile_paid_invoices($entry["id_user"]);
        billing_archive_paid_invoices($entry["id_user"]);
        return (true);
    }

    if (!billing_move_invoice_to_deleted($entry))
        return (false);
    $update = ["deleted" => dbnow()];
    if (!empty($entry["invoice_filename"]))
        $update["invoice_filename"] = $entry["invoice_filename"];
    if (db_update_one("billing_entry", (int)$entry["id"], $update) == NULL)
        return (false);
    billing_reconcile_paid_invoices($entry["id_user"]);
    billing_archive_paid_invoices($entry["id_user"]);
    return (true);
}

function billing_delete_payment($id_payment)
{
    $payment = billing_payment_with_user($id_payment);
    if ($payment == NULL || !billing_user_is_managed($payment["id_user"]))
        return (false);
    if (db_update_one("billing_payment", (int)$payment["id"], ["deleted" => dbnow()]) == NULL)
        return (false);
    billing_reconcile_paid_invoices($payment["id_user"]);
    billing_archive_paid_invoices($payment["id_user"]);
    return (true);
}
