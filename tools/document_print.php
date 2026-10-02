<?php

require_once (__DIR__."/document_tasks.php");

/**
 * Postal/physical document handling queue.
 *
 * The obligation itself lives in document_task (task_action = print).  The
 * exact PDF is frozen in the target user's private admin directory and the
 * task only stores a basename + SHA-256, never an arbitrary readable path.
 */

function document_print_table_available()
{
    return (document_task_table_available());
}

function document_print_user_is_global_admin($id_user)
{
    global $User;

    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);

    // Respect Infosphere's admin-mode switch for the current session.  The
    // authority constants are not privilege levels (TEACHER is 2 while
    // ADMINISTRATOR is 1), so never compare them with >= here.
    if (is_array($User) && (int)($User["id"] ?? 0) === $id_user)
        return (is_admin());

    // For an arbitrary user (notably Albedo's background scan), identify the
    // administrator account exactly; there is no session admin_mode to test.
    $user = db_select_one("id, authority FROM user WHERE id = $id_user AND authority != -1");
    if (!is_array($user))
        return (false);
    return ((int)$user["id"] === 1 || (int)$user["authority"] === ADMINISTRATOR);
}

function document_print_user_is_cycle_director($id_user, $id_cycle)
{
    $id_user = (int)$id_user;
    $id_cycle = (int)$id_cycle;
    if ($id_user <= 0 || $id_cycle <= 0)
        return (false);
    return (db_select_one("
        cycle_teacher.id
        FROM cycle_teacher
        LEFT JOIN laboratory ON laboratory.id = cycle_teacher.id_laboratory
        LEFT JOIN user_laboratory
          ON user_laboratory.id_laboratory = laboratory.id
         AND user_laboratory.id_user = $id_user
        WHERE cycle_teacher.id_cycle = $id_cycle
          AND (cycle_teacher.id_user = $id_user OR user_laboratory.id_user = $id_user)
    ") != NULL);
}

function document_print_school_id_for_cycle($id_cycle)
{
    $id_cycle = (int)$id_cycle;
    if ($id_cycle <= 0)
        return (0);
    $row = db_select_one("
        school_cycle.id_school
        FROM school_cycle
        LEFT JOIN school ON school.id = school_cycle.id_school
        WHERE school_cycle.id_cycle = $id_cycle
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY school_cycle.id_school ASC
    ");
    return (is_array($row) ? (int)$row["id_school"] : 0);
}

function document_print_school_id_for_user($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (0);
    $row = db_select_one("
        user_school.id_school
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id_user
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY user_school.id ASC
    ");
    if (is_array($row))
        return ((int)$row["id_school"]);
    $cycle = db_select_one("
        user_cycle.id_cycle
        FROM user_cycle
        WHERE user_cycle.id_user = $id_user
        ORDER BY user_cycle.id DESC
    ");
    return (is_array($cycle) ? document_print_school_id_for_cycle((int)$cycle["id_cycle"]) : 0);
}

function document_print_user_cycle_ids($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);
    return (array_map(function($row) {
        return ((int)$row["id_cycle"]);
    }, db_select_all("id_cycle FROM user_cycle WHERE id_user = $id_user")));
}

function document_print_user_can_manage_context($id_user, array $context)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);
    if (document_print_user_is_global_admin($id_user))
        return (true);

    $type = strtolower(trim((string)($context["type"] ?? "user")));
    $owner_user_id = (int)($context["owner_user_id"] ?? ($context["user_id"] ?? 0));
    $cycle_id = (int)($context["cycle_id"] ?? 0);
    $school_id = (int)($context["school_id"] ?? 0);
    if ($school_id <= 0 && $cycle_id > 0)
        $school_id = document_print_school_id_for_cycle($cycle_id);
    if ($school_id <= 0 && $owner_user_id > 0)
        $school_id = document_print_school_id_for_user($owner_user_id);

    if ($type === "finance")
        return ($school_id > 0 && (
            user_has_school_authority($id_user, "DIRECTOR", $school_id)
            || user_has_school_authority($id_user, "ACCOUNTANT", $school_id)
        ));

    if ($type === "prospect")
        return ($school_id > 0 && (
            user_has_school_authority($id_user, "DIRECTOR", $school_id)
            || user_has_school_authority($id_user, "SECRETARIAT", $school_id)
            || user_has_school_authority($id_user, "COMMERCIAL", $school_id)
        ));

    if ($type === "library")
        return ($school_id > 0 && (
            user_has_school_authority($id_user, "DIRECTOR", $school_id)
            || user_has_school_authority($id_user, "LIBRARIAN", $school_id)
        ));

    if ($type === "cycle")
        return (($cycle_id > 0 && document_print_user_is_cycle_director($id_user, $cycle_id))
            || ($school_id > 0 && (
                user_has_school_authority($id_user, "DIRECTOR", $school_id)
                || user_has_school_authority($id_user, "SECRETARIAT", $school_id)
            )));

    if ($type === "school")
        return ($school_id > 0 && (
            user_has_school_authority($id_user, "DIRECTOR", $school_id)
            || user_has_school_authority($id_user, "SECRETARIAT", $school_id)
        ));

    // User/profile documents: school administration or the director of one of
    // the learner's cycles.  This is deliberately not granted to teachers.
    if ($owner_user_id > 0)
        foreach (document_print_user_cycle_ids($owner_user_id) as $owner_cycle_id)
            if (document_print_user_is_cycle_director($id_user, $owner_cycle_id))
                return (true);
    return ($school_id > 0 && (
        user_has_school_authority($id_user, "DIRECTOR", $school_id)
        || user_has_school_authority($id_user, "SECRETARIAT", $school_id)
    ));
}

function document_print_current_user_can_manage_context(array $context)
{
    global $User;
    return (is_array($User) && document_print_user_can_manage_context((int)$User["id"], $context));
}

function document_print_storage_directory($id_owner_user)
{
    global $Configuration;

    $id_owner_user = (int)$id_owner_user;
    if ($id_owner_user <= 0)
        return (NULL);
    $user = db_select_one("codename FROM user WHERE id = $id_owner_user AND authority != -1");
    if (!is_array($user) || trim((string)($user["codename"] ?? "")) == "")
        return (NULL);
    return ($Configuration->UsersDir($user["codename"])."admin/print_queue/");
}

function document_print_safe_filename($filename)
{
    $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename((string)$filename));
    if ($filename == "" || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== "pdf")
        $filename = (pathinfo($filename, PATHINFO_FILENAME) ?: "document").".pdf";
    return ($filename);
}

function document_print_existing_task_by_key($task_key)
{
    global $Database;
    if (!document_print_table_available())
        return (NULL);
    $key = $Database->real_escape_string((string)$task_key);
    return (db_select_one("* FROM document_task WHERE task_key = '$key' AND task_action = 'print'"));
}

function document_print_queue_content_for_actor($actor_user_id, $content, $filename, $label, array $context = [])
{
    $actor_user_id = (int)$actor_user_id;
    if (!is_string($content) || substr($content, 0, 4) !== "%PDF")
        return (new ErrorResponse("InvalidParameter", "print PDF"));
    if (!document_print_table_available())
        return (new ErrorResponse("MissingTable", "document_task"));
    if ($actor_user_id <= 0)
        return (new ErrorResponse("PermissionDenied", "document print actor"));
    $owner_user_id = (int)($context["owner_user_id"] ?? ($context["user_id"] ?? 0));
    if ($owner_user_id <= 0)
        return (new ErrorResponse("InvalidParameter", "print owner"));
    $context["owner_user_id"] = $owner_user_id;
    if (!document_print_user_can_manage_context($actor_user_id, $context))
        return (new ErrorResponse("PermissionDenied", "document print context"));

    $label = trim((string)$label);
    if ($label == "")
        $label = "Document";
    $source_key = trim((string)($context["source_key"] ?? ""));
    if ($source_key == "")
        $source_key = "manual";
    $sha256 = hash("sha256", $content);
    $base_task_key = hash("sha256", "print|".$owner_user_id."|".$source_key."|".$sha256);
    $task_key = $base_task_key;
    $existing = document_print_existing_task_by_key($task_key);
    if (is_array($existing) && ($existing["status"] ?? "") === "pending")
        return (new ValueResponse([
            "available" => true,
            "created" => false,
            "id" => (int)$existing["id"],
            "status" => "pending",
        ]));

    // A completed/expired obligation stays in history.  Re-queueing the same
    // exact PDF creates a new obligation instead of silently returning the old
    // completed task.
    if (is_array($existing))
    {
        $ordinal = 2;
        do
        {
            $task_key = hash("sha256", $base_task_key."|requeue|".$ordinal);
            $existing = document_print_existing_task_by_key($task_key);
            ++$ordinal;
        }
        while (is_array($existing));
    }

    $directory = document_print_storage_directory($owner_user_id);
    if ($directory == NULL)
        return (new ErrorResponse("UserNotFound"));
    new_directory($directory."index.php");
    $safe = document_print_safe_filename($filename);
    $stem = pathinfo($safe, PATHINFO_FILENAME);
    $stored = datex("Ymd_His")."_".substr($sha256, 0, 12)."_".$stem.".pdf";
    $path = $directory.$stored;
    if (file_put_contents($path, $content, LOCK_EX) !== strlen($content))
        return (new ErrorResponse("CannotWriteFile", "print queue"));
    @chmod($path, 0640);

    $metadata = [
        "namespace" => "print",
        "label" => $label,
        "filename" => $stored,
        "original_filename" => $safe,
        "sha256" => $sha256,
        "source_key" => $source_key,
        "context_type" => strtolower(trim((string)($context["type"] ?? "user"))),
        "school_id" => (int)($context["school_id"] ?? 0),
        "cycle_id" => (int)($context["cycle_id"] ?? 0),
        "prospect_user_id" => (int)($context["prospect_user_id"] ?? 0),
        "billing_entry_id" => (int)($context["billing_entry_id"] ?? 0),
        "book_user_id" => (int)($context["book_user_id"] ?? 0),
        "recipient_user_id" => (int)($context["recipient_user_id"] ?? 0),
        "recipient_label" => trim((string)($context["recipient_label"] ?? "")),
        "created_by_user" => $actor_user_id,
    ];
    $task = document_task_create(
        $task_key,
        $owner_user_id,
        0,
        "",
        "print",
        "Print",
        "Impression / envoi postal",
        0,
        true,
        $metadata
    );
    if ($task->is_error())
    {
        @unlink($path);
        return ($task);
    }
    return (new ValueResponse([
        "available" => true,
        "created" => true,
        "id" => (int)($task->value["id"] ?? 0),
        "status" => "pending",
    ]));
}

function document_print_queue_content($content, $filename, $label, array $context = [])
{
    global $User;

    return (document_print_queue_content_for_actor(
        (int)($User["id"] ?? 0),
        $content,
        $filename,
        $label,
        $context
    ));
}

function document_print_queue_file($file, $label, array $context = [])
{
    if (!is_string($file) || !is_file($file))
        return (new ErrorResponse("InvalidParameter", "print PDF file"));
    $content = file_get_contents($file);
    if ($content === false)
        return (new ErrorResponse("CannotReadFile", "print PDF file"));
    return (document_print_queue_content($content, basename($file), $label, $context));
}

/**
 * Expire older pending print obligations for one or more semantic sources.
 * Completed/expired tasks remain untouched as history.  The currently queued
 * replacement may be excluded by id.
 */
function document_print_expire_pending_sources($owner_user_id, array $source_keys, $except_task_id = 0)
{
    $owner_user_id = (int)$owner_user_id;
    $except_task_id = (int)$except_task_id;
    $source_keys = array_values(array_unique(array_filter(array_map(function($key) {
        return (trim((string)$key));
    }, $source_keys), function($key) {
        return ($key !== "");
    })));
    if ($owner_user_id <= 0 || !count($source_keys) || !document_print_table_available())
        return (true);

    $rows = db_select_all("*
        FROM document_task
        WHERE id_owner_user = $owner_user_id
          AND task_action = 'print'
          AND status = 'pending'
        ORDER BY id ASC
    ");
    foreach ($rows as $row)
    {
        $id = (int)($row["id"] ?? 0);
        if ($id <= 0 || $id === $except_task_id)
            continue ;
        $metadata = document_task_metadata($row);
        if (!in_array(trim((string)($metadata["source_key"] ?? "")), $source_keys, true))
            continue ;

        // The queue copy is no longer useful once its obligation is superseded.
        $file = document_print_file_for_task($row);
        if (!$file->is_error() && is_file($file->value))
            @unlink($file->value);
        if (!document_task_expire_id($id))
            return (false);
    }
    return (true);
}

function document_print_task_context(array $task)
{
    $metadata = document_task_metadata($task);
    return ([
        "type" => (string)($metadata["context_type"] ?? "user"),
        "owner_user_id" => (int)($task["id_owner_user"] ?? 0),
        "school_id" => (int)($metadata["school_id"] ?? 0),
        "cycle_id" => (int)($metadata["cycle_id"] ?? 0),
        "prospect_user_id" => (int)($metadata["prospect_user_id"] ?? 0),
        "billing_entry_id" => (int)($metadata["billing_entry_id"] ?? 0),
        "book_user_id" => (int)($metadata["book_user_id"] ?? 0),
        "recipient_user_id" => (int)($metadata["recipient_user_id"] ?? 0),
    ]);
}

function document_print_user_can_manage_task($id_user, array $task)
{
    if (($task["task_action"] ?? "") !== "print")
        return (false);
    return (document_print_user_can_manage_context((int)$id_user, document_print_task_context($task)));
}

function document_print_current_user_can_manage_task(array $task)
{
    global $User;
    return (is_array($User) && document_print_user_can_manage_task((int)$User["id"], $task));
}

function document_print_visible_tasks_for_user($id_user, $limit = 1000, $include_completed = false)
{
    if (!document_print_table_available())
        return ([]);
    $id_user = (int)$id_user;
    $limit = max(1, min(5000, (int)$limit));
    $status = $include_completed
        ? " AND document_task.status IN ('pending', 'completed') "
        : " AND document_task.status = 'pending' ";
    $rows = db_select_all("
        document_task.*,
        user.codename AS owner_codename,
        user.first_name AS owner_first_name,
        user.family_name AS owner_family_name
        FROM document_task
        LEFT JOIN user ON user.id = document_task.id_owner_user
        WHERE document_task.task_action = 'print'
          $status
        ORDER BY document_task.created_at ASC, document_task.id ASC
        LIMIT $limit
    ");
    $out = [];
    foreach ($rows as $row)
        if (document_print_user_can_manage_task($id_user, $row))
        {
            $row["print_metadata"] = document_task_metadata($row);
            $out[] = $row;
        }
    return ($out);
}

function document_print_visible_tasks($limit = 1000)
{
    global $User;
    if (!is_array($User))
        return ([]);
    return (document_print_visible_tasks_for_user((int)$User["id"], $limit));
}

function document_print_can_access_page()
{
    global $User;
    if (!is_array($User))
        return (false);
    if (document_print_user_is_global_admin((int)$User["id"]))
        return (true);
    if (user_has_school_authority($User["id"], "DIRECTOR")
        || user_has_school_authority($User["id"], "SECRETARIAT")
        || user_has_school_authority($User["id"], "COMMERCIAL")
        || user_has_school_authority($User["id"], "ACCOUNTANT")
        || user_has_school_authority($User["id"], "LIBRARIAN"))
        return (true);
    return (is_cycle_director_of((int)$User["id"]));
}

function document_print_file_for_task(array $task)
{
    if (($task["task_action"] ?? "") !== "print")
        return (new ErrorResponse("InvalidParameter", "print task"));
    $metadata = document_task_metadata($task);
    $filename = basename((string)($metadata["filename"] ?? ""));
    $sha256 = strtolower(trim((string)($metadata["sha256"] ?? "")));
    if ($filename == "" || !preg_match('/^[A-Za-z0-9_.-]+$/D', $filename)
        || !preg_match('/^[a-f0-9]{64}$/D', $sha256))
        return (new ErrorResponse("InvalidParameter", "print task file"));
    $directory = document_print_storage_directory((int)$task["id_owner_user"]);
    if ($directory == NULL)
        return (new ErrorResponse("UserNotFound"));
    $path = $directory.$filename;
    if (!is_file($path) || !hash_equals($sha256, hash_file("sha256", $path)))
        return (new ErrorResponse("InvalidFileHash", "print task"));
    return (new ValueResponse($path));
}

function document_print_task_url(array $task)
{
    return ("/?p=DocumentPrintPdf&silent=1&task=".(int)($task["id"] ?? 0));
}

function document_print_complete_task($id_task)
{
    global $User;

    $task = document_task_row((int)$id_task);
    if (!is_array($task) || ($task["task_action"] ?? "") !== "print")
        return (new ErrorResponse("DocumentTaskNotFound"));
    if (!document_print_current_user_can_manage_task($task))
        return (new ErrorResponse("PermissionDenied"));
    return (document_task_complete_id((int)$task["id"], (int)($User["id"] ?? 0), (int)($User["id"] ?? 0)));
}

function document_print_context_label(array $task)
{
    $metadata = document_task_metadata($task);
    $type = strtolower((string)($metadata["context_type"] ?? "user"));
    if ($type === "finance")
        return ("Facturation");
    if ($type === "prospect")
        return ("Prospection / admission");
    if ($type === "library")
        return ("Bibliothèque");
    if ($type === "cycle")
    {
        $id_cycle = (int)($metadata["cycle_id"] ?? 0);
        if ($id_cycle > 0)
        {
            $cycle = db_select_one("codename FROM cycle WHERE id = $id_cycle");
            if (is_array($cycle) && trim((string)($cycle["codename"] ?? "")) != "")
                return ("Cycle ".$cycle["codename"]);
        }
        return ("Cycle");
    }
    if ($type === "school")
        return ("Établissement");
    return ("Profil / élève");
}

function document_print_recipient_label(array $task)
{
    $metadata = document_task_metadata($task);
    $label = trim((string)($metadata["recipient_label"] ?? ""));
    if ($label != "")
        return ($label);
    $identity = trim((string)($task["owner_first_name"] ?? "")." ".(string)($task["owner_family_name"] ?? ""));
    if ($identity != "")
        return ($identity);
    return ((string)($task["owner_codename"] ?? "—"));
}

function document_print_assistant_summary($id_user)
{
    $tasks = document_print_visible_tasks_for_user((int)$id_user, 500);
    $labels = [];
    foreach (array_slice($tasks, 0, 3) as $task)
    {
        $metadata = document_task_metadata($task);
        $label = trim((string)($metadata["label"] ?? "Document"));
        $recipient = document_print_recipient_label($task);
        $labels[] = $label.($recipient != "" ? " — ".$recipient : "");
    }
    return ([
        "count" => count($tasks),
        "oldest" => count($tasks) ? (string)($tasks[0]["created_at"] ?? "") : "",
        "labels" => $labels,
        "task_ids" => array_map(function($task) { return ((int)$task["id"]); }, $tasks),
        "url" => "/?p=DocMenu",
    ]);
}
