<?php

/**
 * Generic role-based obligations attached to a document workflow.
 *
 * A task describes what a role must do (currently fill or sign).  The role is
 * the stable semantic identity; id_assignee_user is optional so a task may be
 * assigned later, or completed by any staff member entitled to act for that
 * role.  This also allows future workflows to materialize several tasks with
 * the same role (for example one Teacher signature per intervening teacher).
 */

function document_task_table_available()
{
    return (in_array("document_task", db_get_tables(), true));
}

function document_task_action_is_valid($action)
{
    return (in_array((string)$action, ["fill", "sign"], true));
}

function document_task_role_is_valid($role)
{
    return (is_string($role) && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,127}$/D', $role));
}

/**
 * A semantic role may be materialized several times.  The slot is the stable,
 * unique technical identifier of one occurrence while role keeps the business
 * meaning (Teacher, Student, Director, ...).
 */
function document_task_plan_slot_is_valid($slot)
{
    return (is_string($slot) && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/D', $slot));
}

function document_task_plan_bool($value, $default = true)
{
    if (is_bool($value))
        return ($value);
    if (is_int($value) || is_float($value))
        return ($value != 0);
    $value = strtolower(trim((string)$value));
    if ($value === "")
        return ((bool)$default);
    if (in_array($value, ["0", "false", "no", "non", "optional"], true))
        return (false);
    if (in_array($value, ["1", "true", "yes", "oui", "required"], true))
        return (true);
    return ((bool)$default);
}

/**
 * Normalize a task plan without touching the database.
 *
 * A plan is keyed by a unique technical slot. Several slots may deliberately
 * share the same semantic Role, which is how Teacher x N is represented.
 */
function document_task_plan_normalize(array $plan)
{
    $out = [];
    foreach ($plan as $slot => $definition)
    {
        if (!is_array($definition))
            continue ;
        if (is_int($slot))
            $slot = $definition["slot"] ?? ($definition["Slot"] ?? "");
        $slot = trim((string)$slot);
        if (!document_task_plan_slot_is_valid($slot))
            continue ;

        $action = strtolower(trim((string)($definition["action"] ?? ($definition["Action"] ?? ""))));
        $role = trim((string)($definition["role"] ?? ($definition["Role"] ?? "")));
        if (!document_task_action_is_valid($action) || !document_task_role_is_valid($role))
            continue ;

        $label = trim((string)($definition["role_label"] ?? ($definition["RoleLabel"] ?? "")));
        if ($label == "")
            $label = $role;
        $assignee = (int)($definition["id_assignee_user"]
            ?? ($definition["AssigneeUserId"] ?? ($definition["assignee_user_id"] ?? 0)));
        $assignee_label = trim((string)($definition["assignee_label"] ?? ($definition["AssigneeLabel"] ?? "")));
        $required = document_task_plan_bool($definition["required"] ?? ($definition["Required"] ?? true), true);
        $metadata = $definition["metadata"] ?? ($definition["Metadata"] ?? []);
        if (!is_array($metadata))
            $metadata = [];

        $out[$slot] = [
            "action" => $action,
            "role" => $role,
            "role_label" => $label,
            "id_assignee_user" => max(0, $assignee),
            "assignee_label" => $assignee_label,
            "required" => $required,
            "metadata" => $metadata,
        ];
    }
    return ($out);
}

function document_task_plan_key($scope, $scope_id, $slot, array $definition)
{
    return (hash("sha256", implode("|", [
        (string)$scope,
        (string)$scope_id,
        (string)$slot,
        (string)$definition["action"],
        (string)$definition["role"],
        (string)(int)$definition["id_assignee_user"],
    ])));
}

function document_task_metadata(array $task)
{
    $metadata = json_decode((string)($task["metadata"] ?? "{}"), true);
    return (is_array($metadata) ? $metadata : []);
}

/**
 * Persist a complete materialized plan for one form or one workflow instance.
 * Pending tasks created by the same namespace but no longer present in the
 * plan are expired rather than deleted, preserving an audit trail when the
 * source population changes before completion.
 */
function document_task_materialize_plan($id_owner_user, $id_form, $instance_id,
    array $plan, $namespace = "dynamic")
{
    global $Database;

    if (!document_task_table_available())
        return (new ValueResponse(["available" => false, "tasks" => []]));
    $id_owner_user = (int)$id_owner_user;
    $id_form = (int)$id_form;
    $instance_id = trim((string)$instance_id);
    $namespace = trim((string)$namespace);
    if ($id_owner_user <= 0 || $namespace == "")
        return (new ErrorResponse("InvalidParameter", "document task plan"));
    if ($id_form > 0)
    {
        $scope = "form";
        $scope_id = $id_form;
    }
    else if ($instance_id != "")
    {
        $scope = "instance";
        $scope_id = $instance_id;
    }
    else
        return (new ErrorResponse("InvalidParameter", "document task scope"));

    $normalized = document_task_plan_normalize($plan);
    $expected = [];
    foreach ($normalized as $slot => $definition)
    {
        $key = document_task_plan_key($scope, $scope_id, $slot, $definition);
        $expected[$key] = true;
        $metadata = $definition["metadata"];
        $metadata["plan_namespace"] = $namespace;
        $metadata["slot"] = $slot;
        if ($definition["assignee_label"] != "")
            $metadata["assignee_label"] = $definition["assignee_label"];
        $created = document_task_create(
            $key,
            $id_owner_user,
            $id_form,
            $instance_id,
            $definition["action"],
            $definition["role"],
            $definition["role_label"],
            $definition["id_assignee_user"],
            $definition["required"],
            $metadata
        );
        if ($created->is_error())
            return ($created);
    }

    $rows = $id_form > 0
        ? document_task_rows_for_form($id_form)
        : document_task_rows_for_instance($instance_id, $id_owner_user);
    foreach ($rows as $row)
    {
        $metadata = document_task_metadata($row);
        if ((string)($metadata["plan_namespace"] ?? "") !== $namespace)
            continue ;
        $key = (string)($row["task_key"] ?? "");
        if (isset($expected[$key]) || ($row["status"] ?? "") !== "pending")
            continue ;
        $Database->query("UPDATE document_task SET status = 'expired', expired_at = NOW() WHERE id = ".(int)$row["id"]." AND status = 'pending'");
    }

    $rows = $id_form > 0
        ? document_task_rows_for_form($id_form)
        : document_task_rows_for_instance($instance_id, $id_owner_user);
    $materialized = [];
    foreach ($rows as $row)
    {
        $metadata = document_task_metadata($row);
        if ((string)($metadata["plan_namespace"] ?? "") === $namespace)
            $materialized[] = $row;
    }
    return (new ValueResponse([
        "available" => true,
        "tasks" => $materialized,
        "plan" => $normalized,
    ]));
}

function document_task_key($scope, $scope_id, $action, $role, $assignee = 0, $ordinal = 0)
{
    return (hash("sha256", implode("|", [
        (string)$scope,
        (string)$scope_id,
        (string)$action,
        (string)$role,
        (string)(int)$assignee,
        (string)(int)$ordinal,
    ])));
}

function document_task_create($task_key, $id_owner_user, $id_form, $instance_id,
    $action, $role, $role_label, $id_assignee_user = 0, $required = true,
    array $metadata = [])
{
    global $Database;

    if (!document_task_table_available())
        return (new ValueResponse(["available" => false, "created" => false]));
    $id_owner_user = (int)$id_owner_user;
    $id_form = (int)$id_form;
    $id_assignee_user = (int)$id_assignee_user;
    if ($id_owner_user <= 0 || !document_task_action_is_valid($action)
        || !document_task_role_is_valid($role)
        || !is_string($task_key) || !preg_match('/^[a-f0-9]{64}$/D', $task_key))
        return (new ErrorResponse("InvalidParameter", "document task"));

    $task_key_sql = $Database->real_escape_string($task_key);
    $instance_sql = $Database->real_escape_string(trim((string)$instance_id));
    $action_sql = $Database->real_escape_string((string)$action);
    $role_sql = $Database->real_escape_string((string)$role);
    $label_sql = $Database->real_escape_string(trim((string)$role_label));
    $metadata_json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($metadata_json === false)
        return (new ErrorResponse("CannotEdit", "document task metadata"));
    $metadata_sql = $Database->real_escape_string($metadata_json);
    $form_sql = $id_form > 0 ? (string)$id_form : "NULL";
    $assignee_sql = $id_assignee_user > 0 ? (string)$id_assignee_user : "NULL";
    $required_sql = $required ? 1 : 0;

    $existing = db_select_one("id FROM document_task WHERE task_key = '$task_key_sql'");
    if ($existing != NULL)
        return (new ValueResponse(["available" => true, "created" => false, "id" => (int)$existing["id"]]));

    if (!$Database->query("INSERT INTO document_task
        (task_key, id_owner_user, id_form, instance_id, task_action, role, role_label,
         id_assignee_user, required, status, metadata)
        VALUES ('$task_key_sql', $id_owner_user, $form_sql, '$instance_sql', '$action_sql',
                '$role_sql', '$label_sql', $assignee_sql, $required_sql, 'pending', '$metadata_sql')"))
        return (new ErrorResponse("CannotEdit", "document task"));
    return (new ValueResponse(["available" => true, "created" => true, "id" => (int)$Database->insert_id]));
}

function document_task_rows_for_form($id_form)
{
    if (!document_task_table_available())
        return ([]);
    $id_form = (int)$id_form;
    if ($id_form <= 0)
        return ([]);
    return (db_select_all("* FROM document_task WHERE id_form = $id_form
        ORDER BY task_action ASC, id ASC"));
}

function document_task_rows_for_instance($instance_id, $id_owner_user = 0)
{
    global $Database;

    if (!document_task_table_available())
        return ([]);
    $instance_id = trim((string)$instance_id);
    if ($instance_id == "")
        return ([]);
    $instance_sql = $Database->real_escape_string($instance_id);
    $owner = (int)$id_owner_user > 0 ? " AND id_owner_user = ".(int)$id_owner_user : "";
    return (db_select_all("* FROM document_task WHERE instance_id = '$instance_sql'$owner
        ORDER BY task_action ASC, id ASC"));
}

function document_task_complete_form_role($id_form, $role, $completed_by_user = 0, $assignee_user = 0)
{
    global $Database;

    if (!document_task_table_available())
        return (new ValueResponse(["available" => false, "completed" => false]));
    $id_form = (int)$id_form;
    $completed_by_user = (int)$completed_by_user;
    $assignee_user = (int)$assignee_user;
    if ($id_form <= 0 || !document_task_role_is_valid($role))
        return (new ErrorResponse("InvalidParameter", "document task"));
    $role_sql = $Database->real_escape_string((string)$role);
    $row = db_select_one("* FROM document_task
        WHERE id_form = $id_form AND task_action = 'fill' AND role = '$role_sql'");
    if ($row == NULL)
        return (new ValueResponse(["available" => true, "completed" => false, "missing" => true]));
    if (($row["status"] ?? "") === "completed")
        return (new ValueResponse(["available" => true, "completed" => true, "id" => (int)$row["id"]]));
    if (($row["status"] ?? "") !== "pending")
        return (new ErrorResponse("InvalidParameter", "document task status"));

    $id = (int)$row["id"];
    $completed_sql = $completed_by_user > 0 ? (string)$completed_by_user : "NULL";
    $assignee_clause = "";
    if ((int)($row["id_assignee_user"] ?? 0) <= 0 && $assignee_user > 0)
        $assignee_clause = ", id_assignee_user = $assignee_user";
    if (!$Database->query("UPDATE document_task
        SET status = 'completed', completed_at = NOW(), completed_by_user = $completed_sql$assignee_clause
        WHERE id = $id AND status = 'pending'"))
        return (new ErrorResponse("CannotEdit", "document task"));
    return (new ValueResponse(["available" => true, "completed" => true, "id" => $id]));
}

function document_task_complete_instance_role($instance_id, $role, $completed_by_user = 0, $assignee_user = 0)
{
    global $Database;

    if (!document_task_table_available())
        return (new ValueResponse(["available" => false, "completed" => false]));
    $instance_id = trim((string)$instance_id);
    $completed_by_user = (int)$completed_by_user;
    $assignee_user = (int)$assignee_user;
    if ($instance_id == "" || !document_task_role_is_valid($role))
        return (new ErrorResponse("InvalidParameter", "document task"));
    $instance_sql = $Database->real_escape_string($instance_id);
    $role_sql = $Database->real_escape_string((string)$role);
    $rows = db_select_all("* FROM document_task
        WHERE instance_id = '$instance_sql' AND task_action = 'sign' AND role = '$role_sql'
        ORDER BY id ASC");
    $row = NULL;
    foreach ($rows as $candidate)
    {
        $expected = (int)($candidate["id_assignee_user"] ?? 0);
        if ($assignee_user > 0 && $expected > 0 && $expected !== $assignee_user)
            continue ;
        if (($candidate["status"] ?? "") === "pending")
        {
            $row = $candidate;
            break ;
        }
        if (($candidate["status"] ?? "") === "completed" && ($expected <= 0 || $expected === $assignee_user))
            return (new ValueResponse(["available" => true, "completed" => true, "id" => (int)$candidate["id"]]));
    }
    if ($row == NULL)
        return (new ValueResponse(["available" => true, "completed" => false, "missing" => true]));

    $id = (int)$row["id"];
    $completed_sql = $completed_by_user > 0 ? (string)$completed_by_user : "NULL";
    $assignee_clause = "";
    if ((int)($row["id_assignee_user"] ?? 0) <= 0 && $assignee_user > 0)
        $assignee_clause = ", id_assignee_user = $assignee_user";
    if (!$Database->query("UPDATE document_task
        SET status = 'completed', completed_at = NOW(), completed_by_user = $completed_sql$assignee_clause
        WHERE id = $id AND status = 'pending'"))
        return (new ErrorResponse("CannotEdit", "document task"));
    return (new ValueResponse(["available" => true, "completed" => true, "id" => $id]));
}

function document_task_complete_instance_slot($instance_id, $slot, $completed_by_user = 0, $assignee_user = 0)
{
    global $Database;

    if (!document_task_table_available())
        return (new ValueResponse(["available" => false, "completed" => false]));
    $instance_id = trim((string)$instance_id);
    $slot = trim((string)$slot);
    $completed_by_user = (int)$completed_by_user;
    $assignee_user = (int)$assignee_user;
    if ($instance_id == "" || !document_task_plan_slot_is_valid($slot))
        return (new ErrorResponse("InvalidParameter", "document task slot"));

    foreach (document_task_rows_for_instance($instance_id) as $row)
    {
        if (($row["task_action"] ?? "") !== "sign")
            continue ;
        $metadata = document_task_metadata($row);
        if ((string)($metadata["slot"] ?? "") !== $slot)
            continue ;
        $expected = (int)($row["id_assignee_user"] ?? 0);
        if ($assignee_user > 0 && $expected > 0 && $expected !== $assignee_user)
            return (new ErrorResponse("PermissionDenied", "document task assignee"));
        if (($row["status"] ?? "") === "completed")
            return (new ValueResponse(["available" => true, "completed" => true, "id" => (int)$row["id"]]));
        if (($row["status"] ?? "") !== "pending")
            return (new ErrorResponse("InvalidParameter", "document task status"));

        $id = (int)$row["id"];
        $completed_sql = $completed_by_user > 0 ? (string)$completed_by_user : "NULL";
        $assignee_clause = "";
        if ($expected <= 0 && $assignee_user > 0)
            $assignee_clause = ", id_assignee_user = $assignee_user";
        if (!$Database->query("UPDATE document_task
            SET status = 'completed', completed_at = NOW(), completed_by_user = $completed_sql$assignee_clause
            WHERE id = $id AND status = 'pending'"))
            return (new ErrorResponse("CannotEdit", "document task"));
        return (new ValueResponse(["available" => true, "completed" => true, "id" => $id]));
    }
    return (new ValueResponse(["available" => true, "completed" => false, "missing" => true]));
}

function document_task_expire_form($id_form)
{
    global $Database;
    if (!document_task_table_available())
        return (true);
    $id_form = (int)$id_form;
    if ($id_form <= 0)
        return (false);
    return ((bool)$Database->query("UPDATE document_task
        SET status = 'expired', expired_at = NOW()
        WHERE id_form = $id_form AND status = 'pending'"));
}

function document_task_expire_instance($instance_id)
{
    global $Database;
    if (!document_task_table_available())
        return (true);
    $instance_id = trim((string)$instance_id);
    if ($instance_id == "")
        return (false);
    $instance_sql = $Database->real_escape_string($instance_id);
    return ((bool)$Database->query("UPDATE document_task
        SET status = 'expired', expired_at = NOW()
        WHERE instance_id = '$instance_sql' AND status = 'pending'"));
}

function document_task_progress(array $tasks, $action = NULL)
{
    $total = 0;
    $completed = 0;
    $pending = [];
    foreach ($tasks as $task)
    {
        if (!is_array($task) || empty($task["required"]) || ($task["status"] ?? "") === "expired")
            continue ;
        if ($action !== NULL && (string)($task["task_action"] ?? "") !== (string)$action)
            continue ;
        ++$total;
        if (($task["status"] ?? "") === "completed")
            ++$completed;
        else if (($task["status"] ?? "") === "pending")
            $pending[] = $task;
    }
    return (["completed" => $completed, "total" => $total, "pending" => $pending]);
}
