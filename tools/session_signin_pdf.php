<?php

function session_signin_pdf_path($id_session)
{
    global $Configuration;
    $dir = $Configuration->SessionsDir((int)$id_session);
    return ($dir === NULL ? NULL : $dir."emargement.pdf");
}

function session_signin_valid_pause($morning_end, $afternoon_start)
{
    $pattern = '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D';
    return (is_string($morning_end) && is_string($afternoon_start)
        && preg_match($pattern, $morning_end) && preg_match($pattern, $afternoon_start)
        && $morning_end <= $afternoon_start);
}

function session_signin_day_bounds($begin, $end)
{
    $out = [];
    for ($day = date_to_timestamp(datex("Y-m-d", $begin)." 00:00:00");
         $day < $end; $day = $next)
    {
        $next = date_to_timestamp(datex("Y-m-d", $day)." +1 day");
        $out[] = [max($begin, $day), min($end, $next)];
        if ($next <= $day)
            break ;
    }
    if (!$out)
        $out[] = [$begin, $end];
    return ($out);
}

function session_signin_document_model()
{
    global $Language;

    $language = preg_replace('/[^a-zA-Z0-9_-]+/', '', (string)$Language);
    $candidates = [];
    if ($language != "")
        $candidates[] = __DIR__."/../res/docs/".$language."/emargement.dab";
    $candidates[] = __DIR__."/../res/docs/fr/emargement.dab";
    foreach (array_unique($candidates) as $candidate)
        if (is_file($candidate) && is_readable($candidate))
            return ($candidate);
    return (NULL);
}

function session_signin_school_context($school)
{
    $context = [];
    if (is_array($school))
    {
        $id_school = (int)($school["id"] ?? ($school["id_school"] ?? 0));
        if ($id_school > 0 && function_exists("document_context_school"))
        {
            $tmp = document_context_school($id_school);
            if (is_array($tmp))
                $context = $tmp;
        }
        if (!$context)
            $context = $school;
    }

    if (function_exists("dabsic_pascalcase_array"))
        $context = dabsic_pascalcase_array($context);

    $defaults = [
        "Name" => "",
        "DocumentLogo" => "",
        "MainInfo" => "",
        "SchoolInfo" => "",
        "FormationInfo" => "",
        "AlternationInfo" => "",
        "Phone" => "",
        "Mail" => "",
        "TrainingAddress" => "",
    ];
    return (array_replace($defaults, is_array($context) ? $context : []));
}

function session_signin_people_context(array $people, $present = false)
{
    $out = [];
    foreach ($people as $person)
    {
        if (!is_array($person))
            continue ;
        $entry = ["Identity" => session_signin_person_name($person)];
        if ($present)
        {
            $entry["Present"] = 1;
            $signature = function_exists("user_identity_document_signature_file")
                ? user_identity_document_signature_file($person) : "";
            $entry["Signature"] = ($signature != "" && is_file($signature)) ? $signature : "";
        }
        $out[] = $entry;
    }
    return ($out);
}

function session_signin_pdf_configuration($activity, $session, array $data,
                                          $morning_end, $afternoon_start)
{
    $room_names = [];
    foreach ($data["rooms"] as $room)
    {
        $name = trim((string)(($room["name"] ?? "") ?: ($room["codename"] ?? "")));
        if ($name != "")
            $room_names[] = $name;
    }
    $rooms = implode(", ", array_values(array_unique($room_names)));
    $activity_name = trim((string)($activity->name ?: $activity->codename));
    $groups = $data["students"] ?: [0 => []];
    $sheets = [];
    $primary_school = NULL;

    foreach ($groups as $id_cycle => $students)
    {
        $cycle = $data["cycles"][$id_cycle] ?? NULL;
        $school = $data["schools"][$id_cycle] ?? NULL;
        if (!$school && !empty($data["schools"]))
        {
            $fallback = reset($data["schools"]);
            if (is_array($fallback))
                $school = $fallback;
        }
        if (!$school)
            foreach ($data["rooms"] as $room)
                if ((int)($room["id_school"] ?? 0) > 0)
                {
                    $school = fetch_school((int)$room["id_school"]);
                    if (is_array($school))
                        break ;
                }

        $school_context = session_signin_school_context($school);
        if ($primary_school === NULL && trim((string)$school_context["Name"]) != "")
            $primary_school = $school_context;

        $school_name = trim((string)$school_context["Name"]);
        if ($school_name == "" && is_array($school))
            $school_name = trim((string)($school["name"] ?? ($school["codename"] ?? "")));
        if ($school_name == "")
            $school_name = "Établissement non renseigné";

        $address = trim((string)$school_context["TrainingAddress"]);
        if ($address == "" && is_array($school))
            $address = trim((string)($school["school_address"] ?? ""));

        $cycle_name = $cycle
            ? (trim((string)($cycle["fr_name"] ?? "")) ?: (string)$cycle["codename"])
            : "Élèves sans cycle associé";

        foreach (session_signin_day_bounds($session->begin_date, $session->end_date) as $bounds)
        {
            [$begin, $end] = $bounds;
            $date = datex("Y-m-d", $begin);
            $trainers = $data["trainers"][$date] ?? [];
            $pause_begin = date_to_timestamp($date." ".$morning_end.":00");
            $pause_end = date_to_timestamp($date." ".$afternoon_start.":00");
            $pause_seconds = max(0, min($end, $pause_end) - max($begin, $pause_begin));
            $minutes = max(0, (int)round(($end - $begin - $pause_seconds) / 60));
            $morning = $begin < $pause_begin
                ? datex("H:i", $begin)."-".datex("H:i", min($end, $pause_begin)) : "sans objet";
            $afternoon = $end > $pause_end
                ? datex("H:i", max($begin, $pause_end))."-".datex("H:i", $end) : "sans objet";

            $sheets[] = [
                "SchoolName" => $school_name,
                "CycleName" => $cycle_name,
                "ActivityName" => $activity_name,
                "DateTime" => datex("d/m/Y H:i", $begin)." - ".datex("H:i", $end),
                "Address" => $address,
                "Rooms" => $rooms,
                "Duration" => intdiv($minutes, 60)." h ".sprintf("%02d", $minutes % 60),
                "SessionId" => (int)$session->id,
                "Morning" => $morning,
                "Afternoon" => $afternoon,
                "MorningApplicable" => $begin < $pause_begin ? 1 : 0,
                "AfternoonApplicable" => $end > $pause_end ? 1 : 0,
                "Capacity" => max(0, (int)$data["capacity"]),
                "Students" => session_signin_people_context($students),
                "Trainers" => session_signin_people_context($trainers, true),
            ];
        }
    }

    if ($primary_school === NULL)
        $primary_school = session_signin_school_context(NULL);

    return ([
        "School" => $primary_school,
        "Sheets" => $sheets,
    ]);
}

function session_signin_run_docbuilder(array $configuration)
{
    $model = session_signin_document_model();
    if ($model === NULL)
        return (["ok" => false, "error" => "Le modèle DocBuilder res/docs/fr/emargement.dab est introuvable."]);

    $tmp = tempnam(sys_get_temp_dir(), "infosphere_signin_");
    if ($tmp === false)
        return (["ok" => false, "error" => "Impossible de préparer le document DocBuilder."]);
    @unlink($tmp);
    $json_file = $tmp.".json";
    $pdf_file = $tmp.".pdf";
    $json = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($json_file, $json, LOCK_EX) === false)
    {
        @unlink($json_file);
        return (["ok" => false, "error" => "Impossible d'écrire la configuration DocBuilder temporaire."]);
    }

    $command = ["docbuilder", "-i", $model, $json_file, "-o", $pdf_file];
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];
    $process = proc_open($command, $descriptors, $pipes, NULL, NULL, ["bypass_shell" => true]);
    if (!is_resource($process))
    {
        @unlink($json_file);
        return (["ok" => false, "error" => "Impossible de lancer DocBuilder."]);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $content = is_file($pdf_file) ? file_get_contents($pdf_file) : false;
    @unlink($json_file);
    @unlink($pdf_file);

    if ($status != 0 || $content === false || substr($content, 0, 4) != "%PDF")
    {
        $error = trim((string)$stderr."\n".(string)$stdout);
        if ($error == "")
            $error = "DocBuilder a terminé avec le statut $status sans produire de PDF.";
        else if (stripos($error, "Invalid document type signinsheet") !== false)
            $error .= "\nLe DocBuilder installé ne contient pas le type SignInSheet. "
                ."Installe le patch DocBuilder en même temps que le patch Infosphere.";
        return (["ok" => false, "error" => $error]);
    }
    return (["ok" => true, "content" => $content]);
}

function session_signin_pdf_generate($id_session, $actor = 0,
                                    $morning_end = "13:00", $afternoon_start = "14:00")
{
    global $Database;

    $id_session = (int)$id_session;
    if (!session_signin_schema_ready())
        return (["ok" => false, "error" => "Les tables SQL de l'émargement de session doivent être installées."]);
    $row = db_select_one("* FROM session WHERE id = $id_session AND deleted IS NULL AND id_activity > 0");
    if (!$row || !session_signin_valid_pause($morning_end, $afternoon_start))
        return (["ok" => false, "error" => "Session ou horaires de pause invalides."]);
    if (!$row["end_date"] || date_to_timestamp($row["end_date"]) > now())
        return (["ok" => false, "error" => "La session n'est pas encore terminée."]);
    ($activity = new FullActivity)->build((int)$row["id_activity"], false, false);
    $session = NULL;
    foreach ($activity->session as $item)
        if ((int)$item->id === $id_session)
            $session = $item;
    if (!$session)
        return (["ok" => false, "error" => "Session introuvable dans l'activité."]);
    $path = session_signin_pdf_path($id_session);
    if ($path === NULL)
        return (["ok" => false, "error" => "Impossible de déterminer le dossier de session."]);
    $dir = dirname($path)."/";
    if (!is_dir($dir) && !@mkdir($dir, 0750, true))
        return (["ok" => false, "error" => "Impossible de créer le dossier de session."]);
    $lock = @fopen($dir.".generate.lock", "c");
    if (!$lock || !flock($lock, LOCK_EX))
        return (["ok" => false, "error" => "Impossible de verrouiller le dossier de session."]);
    $target = $dir."emargement.pdf";
    $error = NULL;
    do
    {
        if (is_file($target))
        {
            if (substr((string)@file_get_contents($target, false, NULL, 0, 4), 0, 4) !== "%PDF")
            {
                $error = "Le fichier archivé existe mais n'est pas un PDF valide.";
                break ;
            }
            if (db_select_one("id FROM session WHERE id = $id_session AND signin_generated_at IS NOT NULL"))
                break ;
            $hash = hash_file("sha256", $target);
        }
        else
        {
            $data = session_signin_data($activity, $session);
            $configuration = session_signin_pdf_configuration(
                $activity, $session, $data, $morning_end, $afternoon_start
            );
            $built = session_signin_run_docbuilder($configuration);
            if (!$built["ok"])
            {
                $error = $built["error"];
                break ;
            }
            $pending = $dir.".pending_".bin2hex(random_bytes(8)).".pdf";
            if (file_put_contents($pending, $built["content"], LOCK_EX) !== strlen($built["content"]))
            {
                @unlink($pending);
                $error = "Impossible d'écrire le PDF.";
                break ;
            }
            @chmod($pending, 0640);
            if (!@rename($pending, $target))
            {
                @unlink($pending);
                $error = "Impossible d'archiver le PDF.";
                break ;
            }
            $hash = hash("sha256", $built["content"]);
        }
        $actor_sql = (int)$actor > 0 ? (string)(int)$actor : "NULL";
        $sql = "UPDATE session SET signin_generated_at = NOW(),
            signin_id_actor = $actor_sql, signin_morning_end = '$morning_end',
            signin_afternoon_start = '$afternoon_start', signin_sha256 = '$hash'
            WHERE id = $id_session";
        if (!$Database->query($sql))
            $error = "Impossible d'enregistrer la génération du PDF.";
    } while (false);
    flock($lock, LOCK_UN);
    fclose($lock);
    return ($error === NULL ? ["ok" => true, "path" => $target] : ["ok" => false, "error" => $error]);
}

function session_signin_archive_items($scope, $id)
{
    $id = (int)$id;
    if ($id <= 0 || !in_array($scope, ["cycle", "matter"], true))
        return ([]);
    $join = $scope === "cycle"
        ? "INNER JOIN activity_cycle ON activity_cycle.id_cycle = $id
             AND activity_cycle.id_activity IN (activity.id, activity.parent_activity)"
        : "";
    $where = $scope === "matter" ? "AND (activity.id = $id OR activity.parent_activity = $id)" : "";
    $items = [];
    foreach (db_select_all("
        DISTINCT session.id, session.begin_date, activity.codename
        FROM session
        INNER JOIN activity ON activity.id = session.id_activity AND activity.deleted IS NULL
        $join
        WHERE session.deleted IS NULL AND session.signin_generated_at IS NOT NULL $where
        ORDER BY session.begin_date, session.id
    ") as $row)
    {
        $path = session_signin_pdf_path((int)$row["id"]);
        if ($path && is_file($path))
            $items[(int)$row["id"]] = ["path" => $path,
                "name" => datex("Y-m-d", date_to_timestamp($row["begin_date"]))."_session_".(int)$row["id"].".pdf"];
    }
    return ($items);
}
