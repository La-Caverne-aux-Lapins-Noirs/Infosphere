<?php

function is_billing_manager($id_school = -1)
{
    if (is_admin())
        return (true);
    return (is_director_for_school($id_school) || is_secretariat($id_school));
}


function is_billing_manager_for_billing_entry($id_entry)
{
    if (is_admin())
        return (true);
    $id_entry = (int)$id_entry;
    if ($id_entry <= 0)
        return (is_billing_manager());
    $entry = db_select_one("id_user FROM billing_entry WHERE id = $id_entry AND deleted IS NULL");
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
        return (is_billing_manager());
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
    $rows = db_select_all("
        id_school
        FROM user_school
        WHERE id_user = {$User["id"]}
          AND (authority = 'DIRECTOR' OR authority = 'SECRETARIAT')
    ", "id_school");
    return (array_keys($rows));
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
    if ($id_user <= 0)
        return (false);
    if (is_admin())
        return (true);
    return (db_select_one("
        user_school.id
        FROM user_school
        WHERE user_school.id_user = $id_user
          AND user_school.authority = 'STUDENT'
        ".billing_school_filter("user_school")) != NULL);
}

function billing_user_main_school($id_user)
{
    $id_user = (int)$id_user;
    return (db_select_one("
        school.id as id_school,
        school.codename as school_codename
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user
          AND user_school.authority = 'STUDENT'
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
    $planned = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_entry
        WHERE id_user = $id_user
          AND deleted IS NULL
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
    $paid = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_user = $id_user
          AND deleted IS NULL
    ");
    $planned = (int)$planned["amount"];
    $issued = (int)$issued["amount"];
    $due = (int)$due["amount"];
    $paid = (int)$paid["amount"];
    return ([
        "planned" => $planned,
        "billed" => $issued,
        "due" => $due,
        "paid" => $paid,
        "balance" => $planned - $paid,
        "billed_unpaid" => max(0, $issued - $paid),
        "late" => max(0, $due - $paid),
        "advance" => max(0, $paid - $issued),
    ]);
}

function billing_fetch_students()
{
    global $Language;

    $students = db_select_all("
        DISTINCT user.id,
        user.codename,
        user.first_name,
        user.family_name,
        user.mail,
        NULL as deleted,
        school.codename as school_codename,
        school.{$Language}_name as school_name
        FROM user_school
        LEFT JOIN user ON user.id = user_school.id_user
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.authority = 'STUDENT'
          AND user.deleted IS NULL
          AND school.deleted IS NULL
        ".billing_school_filter("user_school")."
        ORDER BY school.codename, user.family_name, user.first_name, user.codename
    ");
    foreach ($students as &$student)
    {
        $student["tariff_year"] = billing_tariff_year_for_user($student["id"]);
        $student["account"] = billing_account_for_user($student["id"]);
        $student["entries"] = billing_fetch_entries($student["id"]);
        $student["payments"] = billing_fetch_payments($student["id"]);
    }
    return ($students);
}

function billing_fetch_templates()
{
    global $Language;

    return (db_select_all("
        billing_template.*,
        school.codename as school_codename,
        school.{$Language}_name as school_name
        FROM billing_template
        LEFT JOIN school ON school.id = billing_template.id_school
        WHERE billing_template.deleted IS NULL
        ".billing_school_filter("billing_template")."
        ORDER BY school.codename, billing_template.tariff_year ASC, billing_template.name
    "));
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
        school.codename as school_codename,
        school.{$Language}_name as school_name,
        billing_template.name as template_name,
        billing_template.tariff_year as template_tariff_year
        FROM billing_entry
        LEFT JOIN user ON user.id = billing_entry.id_user
        LEFT JOIN school ON school.id = billing_entry.id_school
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
        $invoice["status_key"] = "issued";
        if (!empty($invoice["deleted"]))
            $invoice["status_key"] = "deleted";
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
        LIMIT 1
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

function billing_add_entry($id_user, $label, $amount, $due_date, $id_template = NULL, $entry_type = "tuition")
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
    $id_template = $id_template === NULL ? "NULL" : (int)$id_template;
    $actor = isset($User["id"]) ? (int)$User["id"] : "NULL";
    return ($Database->query("
        INSERT INTO billing_entry
        (id_user, id_school, id_template, label, amount, entry_type, due_date, id_actor)
        VALUES
        ($id_user, {$school["id_school"]}, $id_template, '$label', $amount, '$entry_type', '$due_date', $actor)
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
            "registration_fee"
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
        if (billing_add_entry($id_user, $template["name"]." - échéance ".($idx + 1)."/".count($offsets), $amount, $date->format("Y-m-d H:i:s"), $id_template))
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
    foreach (db_select_all("
        user.mail
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_parent
        WHERE parent_child.id_child = $id_user
          AND user.mail IS NOT NULL
          AND user.mail != ''
    ") as $m)
        $mails[] = $m["mail"];
    return (array_values(array_unique($mails)));
}

function billing_invoice_reference_prefix($school_codename = "")
{
    global $Configuration;

    $base = trim((string)@$Configuration->Properties["billing_invoice_prefix"]);
    if ($base == "")
        $base = "INFSPH";
    $base = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '-', $base));
    $base = trim($base, "-");
    if ($base == "")
        $base = "INFSPH";

    $school = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$school_codename));
    $school = trim($school, "-");
    if ($school == "")
        return ($base);
    return ($base."-".$school);
}

function billing_school_codename_for_entry($id_entry)
{
    $id_entry = (int)$id_entry;
    $school = db_select_one("
        school.codename
        FROM billing_entry
        LEFT JOIN school ON school.id = billing_entry.id_school
        WHERE billing_entry.id = $id_entry
    ");

    if ($school == NULL)
        return ("");
    return ((string)$school["codename"]);
}

function billing_invoice_default_reference($entry)
{
    $id_entry = is_array($entry) ? (int)$entry["id"] : (int)$entry;
    $school_codename = "";

    if (is_array($entry))
        $school_codename = (string)($entry["school_codename"] ?? "");
    if ($school_codename == "")
        $school_codename = billing_school_codename_for_entry($id_entry);
    return (billing_invoice_reference_prefix($school_codename)."-".date("Y")."-".sprintf("%05d", $id_entry));
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
    return ($reference.".txt");
}

function billing_entry_with_user($id_entry)
{
    $id_entry = (int)$id_entry;
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
          AND billing_entry.deleted IS NULL
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
    return (
        $Dictionnary["BillingInvoicePlaceholderBody"]."\n\n".
        $Dictionnary["BillingInvoice"]." : ".$reference."\n".
        $Dictionnary["Student"]." : ".$name."\n".
        $Dictionnary["BillingLabel"]." : ".$entry["label"]."\n".
        $Dictionnary["Amount"]." : ".billing_euros($entry["amount"])."\n".
        $Dictionnary["DueDate"]." : ".billing_document_date_label($entry["due_date"])."\n".
        $Dictionnary["Reference"]." : ".$reference."\n"
    );
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

function billing_invoice_relative_path($entry)
{
    if (empty($entry["invoice_filename"]))
        return ("");
    if (!empty($entry["deleted"]))
        return (billing_invoice_relative_path_for_state("deleted", $entry["invoice_filename"]));
    if (!empty($entry["paid_date"]))
        return (billing_invoice_relative_path_for_state("paid", $entry["invoice_filename"]));
    return (billing_invoice_relative_path_for_state(false, $entry["invoice_filename"]));
}

function billing_invoice_builder_shell_command($placeholder, $output)
{
    // Later: replace this placeholder command with something like:
    // docbuilder <options> -o <output>
    return ("echo ".escapeshellarg($placeholder)." > ".escapeshellarg($output));
}

function billing_build_invoice_document($entry, $reference, $output)
{
    $placeholder = billing_invoice_text($entry, $reference);
    $command = billing_invoice_builder_shell_command($placeholder, $output);
    $lines = [];
    $status = 0;

    exec($command, $lines, $status);
    if ($status != 0 || !file_exists($output))
        return (NULL);
    @chmod($output, 0664);
    return ($placeholder);
}

function billing_write_invoice_placeholder($entry, $reference, $state = false)
{
    $dir = billing_invoice_directory($entry["codename"], $state);
    new_directory($dir."index.php");

    $filename = empty($entry["invoice_filename"])
        ? billing_invoice_safe_filename($reference)
        : $entry["invoice_filename"];
    $path = $dir.$filename;
    $content = billing_build_invoice_document($entry, $reference, $path);
    if ($content === NULL)
        return (NULL);
    return ([
        "filename" => $filename,
        "path" => $path,
        "relative_path" => billing_invoice_relative_path_for_state($state, $filename),
        "content" => $content,
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
        $path = billing_invoice_directory($entry["codename"], $state).$entry["invoice_filename"];
        if (file_exists($path))
            @unlink($path);
    }
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
    $remaining = (int)$paid["amount"];
    $coverage = [];

    foreach (billing_fetch_entries($id_user) as $entry)
    {
        $amount = max(0, (int)$entry["amount"]);
        $covered = min($amount, max(0, $remaining));

        $coverage[(int)$entry["id"]] = $covered;
        $remaining -= $covered;
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

function billing_send_invoice_placeholder($id_entry)
{
    global $Dictionnary;

    $entry = billing_entry_with_user($id_entry);
    if ($entry == NULL || !billing_user_is_managed($entry["id_user"]))
        return (false);

    $ref = trim((string)$entry["invoice_reference"]);
    if ($ref == "")
        $ref = billing_invoice_default_reference($entry);
    if (!billing_invoice_reference_is_available($ref, $entry["id"]))
        return (false);

    $file = billing_write_invoice_placeholder($entry, $ref, false);
    if ($file == NULL)
        return (false);

    $recipients = billing_invoice_recipients($entry["id_user"]);
    if (!count($recipients))
        return (false);

    $body = $file["content"]."\n".
        $Dictionnary["InvoiceCopySavedIn"]." : ".$file["relative_path"]."\n";
    $mail = send_mail(
        $recipients,
        $Dictionnary["BillingInvoice"]." ".$ref,
        $body,
        NULL,
        [$file["filename"] => $file["content"]],
        false
    );
    if ($mail->is_error())
        return (false);

    db_update_one("billing_entry", (int)$entry["id"], [
        "sent_date" => dbnow(),
        "invoice_reference" => $ref,
        "invoice_filename" => $file["filename"],
    ]);
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
        $entry = billing_entry_with_user($entry["id"]);
        if ($entry == NULL)
            continue ;
        $ref = trim((string)$entry["invoice_reference"]);
        if ($ref == "")
            $ref = billing_invoice_default_reference($entry);
        if (empty($entry["invoice_filename"]))
            $entry["invoice_filename"] = billing_invoice_safe_filename($ref);

        $source_dir = billing_invoice_directory($entry["codename"], false);
        $target_dir = billing_invoice_directory($entry["codename"], true);
        new_directory($target_dir."index.php");
        $source = $source_dir.$entry["invoice_filename"];
        $target = $target_dir.$entry["invoice_filename"];

        if (file_exists($source))
        {
            if (!@rename($source, $target))
                continue ;
        }
        else
        {
            $file = billing_write_invoice_placeholder($entry, $ref, true);
            if ($file == NULL)
                continue ;
        }

        db_update_one("billing_entry", (int)$entry["id"], [
            "paid_date" => dbnow(),
            "invoice_filename" => $entry["invoice_filename"],
        ]);
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
        if (empty($entry["sent_date"]))
            return (true);
        $ref = trim((string)$entry["invoice_reference"]);
        if ($ref == "")
            $ref = billing_invoice_default_reference($entry);
        $entry["invoice_filename"] = billing_invoice_safe_filename($ref);
        return (billing_write_invoice_placeholder($entry, $ref, "deleted") !== NULL);
    }

    $target_dir = billing_invoice_directory($entry["codename"], "deleted");
    new_directory($target_dir."index.php");
    $target = $target_dir.$entry["invoice_filename"];
    $sources = [];

    if (!empty($entry["paid_date"]))
        $sources[] = billing_invoice_directory($entry["codename"], true).$entry["invoice_filename"];
    $sources[] = billing_invoice_directory($entry["codename"], false).$entry["invoice_filename"];
    $sources[] = billing_invoice_directory($entry["codename"], true).$entry["invoice_filename"];

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
        $ref = trim((string)$entry["invoice_reference"]);
        if ($ref == "")
            $ref = billing_invoice_default_reference($entry);
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
    return (true);
}
