<?php

function admission_certificate_target_classes()
{
    return ([
        1 => ["label" => "EF1", "year" => 1],
        2 => ["label" => "EF2", "year" => 2],
        3 => ["label" => "EF3", "year" => 3],
        4 => ["label" => "EF4", "year" => 4],
        5 => ["label" => "EF5", "year" => 5],
        6 => ["label" => "EF2X", "year" => 2],
        7 => ["label" => "EF3X", "year" => 3],
    ]);
}

function admission_certificate_year_label($year)
{
    $labels = [
        1 => "première année",
        2 => "deuxième année",
        3 => "troisième année",
        4 => "quatrième année",
        5 => "cinquième année",
    ];
    return ($labels[(int)$year] ?? ((int)$year."e année"));
}

function admission_certificate_entry_specs()
{
    return ([
        0 => ["label" => "rentrée de septembre"],
        1 => ["label" => "rentrée de janvier"],
        2 => ["label" => "rentrée d’avril"],
    ]);
}

function admission_certificate_parse_date($value)
{
    if ($value === NULL || $value === "")
        return (NULL);
    if (is_int($value) || ctype_digit((string)$value))
        return ((int)$value);
    $stamp = date_to_timestamp($value);
    if ($stamp !== NULL && $stamp !== false)
        return ($stamp);
    $stamp = strtotime((string)$value);
    return ($stamp === false ? NULL : $stamp);
}

function admission_certificate_christmas_break_monday($year)
{
    global $NoLocalisation;

    $christmas = new DateTimeImmutable(sprintf("%04d-12-25 12:00:00", (int)$year), $NoLocalisation);
    return ($christmas->modify("-".((int)$christmas->format("N") - 1)." days"));
}

function admission_certificate_entry_candidate($target_entry, $year)
{
    $target_entry = (int)$target_entry;
    $year = (int)$year;
    if ($target_entry == 0)
    {
        // La rentrée de septembre ouvre un trimestre de treize semaines qui
        // s’achève au début des vacances de Noël.
        return (admission_certificate_christmas_break_monday($year)->modify("-13 weeks"));
    }

    // Les vacances de Noël occupent deux semaines complètes. Le lundi qui
    // suit est la rentrée de janvier ; celle d’avril est treize semaines plus
    // tard.
    $january = admission_certificate_christmas_break_monday($year - 1)->modify("+2 weeks");
    if ($target_entry == 1)
        return ($january);
    if ($target_entry == 2)
        return ($january->modify("+13 weeks"));
    return (NULL);
}

function admission_certificate_entry_date(array $prospect, array $options = [])
{
    global $NoLocalisation;

    if (isset($options["entry_date"]) && ($stamp = admission_certificate_parse_date($options["entry_date"])) !== NULL)
        return ($stamp);

    $target_entry = (int)($prospect["target_entry"] ?? -1);
    $specs = admission_certificate_entry_specs();
    if (!isset($specs[$target_entry]))
        return (NULL);

    $base = admission_certificate_parse_date($prospect["registration_date"] ?? NULL);
    if ($base === NULL)
        $base = now();
    $base_day = new DateTimeImmutable(datex("Y-m-d", $base)." 00:00:00", $NoLocalisation);
    $base_year = (int)$base_day->format("Y");

    for ($year = $base_year - 1; $year <= $base_year + 3; ++$year)
    {
        $candidate = admission_certificate_entry_candidate($target_entry, $year);
        if ($candidate !== NULL && $candidate >= $base_day)
            return ($candidate->getTimestamp());
    }
    return (NULL);
}

function admission_certificate_school_year($entry_stamp)
{
    if ($entry_stamp === NULL)
        return ("");
    $year = (int)datex("Y", $entry_stamp);
    $month = (int)datex("m", $entry_stamp);
    $start = ($month >= 9 ? $year : $year - 1);
    return ($start."-".($start + 1));
}

function admission_certificate_school_city(array $school)
{
    foreach (["city", "training_city"] as $field)
        if (isset($school[$field]) && trim((string)$school[$field]) != "")
            return (trim((string)$school[$field]));

    $address = trim((string)($school["address"] ?? ""));
    if (preg_match('/\b[0-9]{5}\s+([^,\n]+)$/u', $address, $match))
        return (trim($match[1]));
    return ("");
}

function admission_certificate_school_for_prospect(array $prospect, array $options = [])
{
    if (isset($options["id_school"]) && (int)$options["id_school"] > 0)
    {
        $school = fetch_school((int)$options["id_school"]);
        if (is_object($school) && $school->is_error())
            return ($school);
        if (!is_array($school) || !count($school))
            return (new ErrorResponse("InvalidParameter", "school"));
        return (new ValueResponse($school));
    }

    $row = db_select_one("
        school.id as id_school
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = ".((int)$prospect["id"])."
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY user_school.id DESC
    ");
    if ($row == NULL)
        return (new ErrorResponse("MissingParameter", "school"));

    $school = fetch_school((int)$row["id_school"]);
    if (is_object($school) && $school->is_error())
        return ($school);
    if (!is_array($school) || !count($school))
        return (new ErrorResponse("InvalidParameter", "school"));
    return (new ValueResponse($school));
}

function admission_certificate_director(array $school, array $options = [])
{
    $directors = $school["director"] ?? [];
    $wanted = isset($options["id_director"]) ? (int)$options["id_director"] : 0;
    $selected = NULL;

    if ($wanted > 0)
    {
        foreach ($directors as $director)
        {
            $id = (int)($director["id_director"] ?? $director["id_user"] ?? $director["id"] ?? 0);
            if ($id == $wanted)
            {
                $selected = $director;
                break ;
            }
        }
        if ($selected === NULL)
            return (new ErrorResponse("InvalidParameter", "director"));
    }
    else
    {
        if (!count($directors))
            return (new ErrorResponse("MissingParameter", "director"));
        if (count($directors) > 1)
            return (new ErrorResponse("MissingParameter", "director: several directors are available"));
        $selected = $directors[array_key_first($directors)];
    }

    $director = document_builder_fetch_full_user_from_row($selected);
    if (!count($director))
        return (new ErrorResponse("InvalidParameter", "director"));
    if (isset($director["id"]) && (int)$director["id"] > 0)
        refresh_user((int)$director["id"]);

    $context = document_builder_person_context($director);
    $context["identity"] = document_builder_name($director);
    $context["role"] = document_builder_director_role($director)." de ".($school["name"] ?? ($school["codename"] ?? "l’école"));

    $school_codename = $school["codename"] ?? "";
    $signature = document_builder_director_document_path($director, $school_codename, "signature");
    if ($signature != "")
        $context["signature"] = $signature;
    return (new ValueResponse($context));
}

function admission_certificate_tariff(array $school, $tariff_year, array $options = [])
{
    $id_school = (int)($school["id"] ?? 0);
    $tariff_year = (int)$tariff_year;
    if ($id_school <= 0 || $tariff_year <= 0)
        return (new ErrorResponse("InvalidParameter", "billing tariff"));

    $where_template = "";
    if (isset($options["id_template"]) && (int)$options["id_template"] > 0)
        $where_template = " AND billing_template.id = ".((int)$options["id_template"]);

    $template = db_select_one("
        billing_template.*
        FROM billing_template
        WHERE billing_template.id_school = $id_school
          AND billing_template.tariff_year = $tariff_year
          AND billing_template.invoice_type = 'school'
          AND billing_template.deleted IS NULL
          $where_template
        ORDER BY billing_template.id DESC
    ");
    if ($template == NULL)
        return (new ErrorResponse("MissingFile", "billing tariff for school year ".$tariff_year));
    return (new ValueResponse($template));
}

function admission_certificate_paid_amount($id_user, $id_school)
{
    $row = db_select_one("
        COALESCE(SUM(amount), 0) as amount
        FROM billing_payment
        WHERE id_user = ".((int)$id_user)."
          AND id_school = ".((int)$id_school)."
          AND deleted IS NULL
    ");
    return ((int)($row["amount"] ?? 0));
}

function admission_certificate_reference(array $prospect, array $school, $entry_stamp)
{
    $school_code = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', "", (string)($school["codename"] ?? "SCHOOL")));
    $year = $entry_stamp === NULL ? datex("Y") : datex("Y", $entry_stamp);
    return ("ADM-".$school_code."-".$year."-".str_pad((string)((int)$prospect["id"]), 5, "0", STR_PAD_LEFT));
}

function admission_certificate_context(array $prospect, array $options = [])
{
    if (!array_key_exists("is_foreign", $options) && !array_key_exists("foreign", $options))
        return (new ErrorResponse("MissingParameter", "is_foreign"));
    $is_foreign = !empty($options["is_foreign"] ?? $options["foreign"]);

    $target_class = (int)($prospect["target_class"] ?? 0);
    $classes = admission_certificate_target_classes();
    if (!isset($classes[$target_class]))
        return (new ErrorResponse("InvalidParameter", "target_class"));
    $class = $classes[$target_class];

    if (($ret = admission_certificate_school_for_prospect($prospect, $options))->is_error())
        return ($ret);
    $school = $ret->value;
    if (($refresh = refresh_school($school))->is_error())
        return ($refresh);
    $school = fetch_school((int)$school["id"]);
    if (is_object($school) && $school->is_error())
        return ($school);

    if (($ret = admission_certificate_director($school, $options))->is_error())
        return ($ret);
    $director = $ret->value;

    if (($ret = admission_certificate_tariff($school, $class["year"], $options))->is_error())
        return ($ret);
    $template = $ret->value;

    $registration_fee = (int)$template["registration_fee"];
    $annual_tuition = (int)$template["amount_once"];
    if ($registration_fee <= 0 || ($is_foreign && $annual_tuition <= 0))
        return (new ErrorResponse("InvalidParameter", "billing tariff amounts"));
    $foreign_supplement = $is_foreign ? (int)round($annual_tuition / 6) : 0;
    $required_amount = $registration_fee + $foreign_supplement;
    $paid_amount = admission_certificate_paid_amount((int)$prospect["id"], (int)$school["id"]);
    $payment_confirmed = array_key_exists("payment_confirmed", $options)
        ? !empty($options["payment_confirmed"])
        : ($required_amount > 0 && $paid_amount >= $required_amount);

    $entry_stamp = admission_certificate_entry_date($prospect, $options);
    if ($entry_stamp === NULL)
        return (new ErrorResponse("InvalidParameter", "target_entry"));
    $entry_specs = admission_certificate_entry_specs();
    $target_entry = (int)$prospect["target_entry"];
    $entry_label = ($entry_specs[$target_entry]["label"] ?? "rentrée")." ".datex("Y", $entry_stamp);

    $student = document_builder_person_context($prospect);
    $student["identity"] = document_builder_name($prospect);
    $birth_stamp = admission_certificate_parse_date($student["birth_date"] ?? ($prospect["birth_date"] ?? NULL));
    $student["birth_date"] = $birth_stamp === NULL ? "" : datex("d/m/Y", $birth_stamp);
    $student["birth_place"] = $student["birth_place"] ?? ($prospect["birth_place"] ?? ($prospect["birth_city"] ?? ""));

    $registration_stamp = admission_certificate_parse_date($prospect["registration_date"] ?? NULL);
    $issue_stamp = isset($options["issue_date"]) ? admission_certificate_parse_date($options["issue_date"]) : now();
    if ($issue_stamp === NULL)
        $issue_stamp = now();

    $context = [
        "school" => document_builder_school_context($school),
        "student" => $student,
        "signature_sources" => [
            "director" => document_builder_signatory_context($director, "Director"),
        ],
        "admission" => [
            "target_class" => $options["course_name"] ?? $class["label"],
            "target_class_code" => $class["label"],
            "target_year" => $class["year"],
            "target_year_label" => admission_certificate_year_label($class["year"]),
            "entry_date" => datex("d/m/Y", $entry_stamp),
            "entry_label" => $entry_label,
            "school_year" => admission_certificate_school_year($entry_stamp),
            "prospect_registration_date" => $registration_stamp === NULL ? "" : datex("d/m/Y", $registration_stamp),
            "is_foreign" => $is_foreign ? 1 : 0,
            "tariff_template_id" => (int)$template["id"],
            "tariff_template_name" => $template["name"] ?? "",
            "registration_fee_cents" => $registration_fee,
            "annual_tuition_cents" => $annual_tuition,
            "foreign_supplement_cents" => $foreign_supplement,
            "required_amount_cents" => $required_amount,
            "paid_amount_cents" => $paid_amount,
            "registration_fee" => billing_euros($registration_fee),
            "annual_tuition" => billing_euros($annual_tuition),
            "foreign_supplement" => billing_euros($foreign_supplement),
            "required_amount" => billing_euros($required_amount),
            "paid_amount" => billing_euros($paid_amount),
            "payment_confirmed" => $payment_confirmed ? 1 : 0,
            "issue_place" => $options["issue_place"] ?? admission_certificate_school_city($school),
            "issue_date" => datex("d/m/Y", $issue_stamp),
            "reference" => $options["reference"] ?? admission_certificate_reference($prospect, $school, $entry_stamp),
            "automatic_signature" => !empty($options["automatic_signature"]) ? 1 : 0,
        ],
    ];
    return (new ValueResponse(dabsic_pascalcase_array($context)));
}

function build_admission_certificate($id_prospect, array $options = [])
{
    global $Configuration;
    global $Language;

    if (($ret = document_builder_full_student($id_prospect))->is_error())
        return ($ret);
    $prospect = $ret->value;
    if (($prospect["profile_status"] ?? "") != "prospect")
        return (new ErrorResponse("InvalidParameter", "user is not a prospect"));

    if (($ret = admission_certificate_context($prospect, $options))->is_error())
        return ($ret);
    $context_data = $ret->value;

    $definitive = !array_key_exists("definitive", $options) || !empty($options["definitive"]);
    $model_name = $options["model"] ?? ($definitive ? "attestation_admission_definitive" : "attestation_admission");
    $model = document_builder_find_model($model_name, $Language);
    if ($model === NULL)
        return (new ErrorResponse("MissingFile", "admission certificate model: ".$model_name));

    $user_dir = $Configuration->UsersDir($prospect["codename"]);
    $document_dir = $user_dir."admin/admission/";
    $context_file = $document_dir."admission_certificate_context.dab";
    if (($ret = generate_dabsic($context_data, $context_file))->is_error())
        return ($ret);

    $output_name = $options["output"] ?? (datex("Ymd_His")."_attestation_admission".($definitive ? "_definitive" : "").".pdf");
    if (!preg_match('/^[a-zA-Z0-9_\-.]+$/', $output_name))
        return (new ErrorResponse("InvalidParameter", "output"));
    $output = $document_dir.$output_name;

    $include_paths = document_builder_model_dirs($Language);
    $include_paths[] = dirname($model)."/";
    $include_paths[] = $user_dir;
    $include_paths[] = $user_dir."admin/";
    $include_paths[] = $document_dir;
    if (isset($context_data["School"]["Codename"]) && $context_data["School"]["Codename"] != "")
        $include_paths[] = $Configuration->SchoolsDir($context_data["School"]["Codename"]);

    $result = build_document_from_parts($output, [
        ["file" => $model],
        ["file" => $context_file],
    ], $include_paths);
    if (!$result->is_error())
        add_log(EDITING_OPERATION, "Admission certificate generated for prospect ".((int)$prospect["id"]));
    return ($result);
}
