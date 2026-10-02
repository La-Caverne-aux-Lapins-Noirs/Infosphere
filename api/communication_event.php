<?php

function communication_event_api_result(array $result, $success_message = "")
{
    if (empty($result["ok"]))
        return (new ErrorResponse($result["error"] ?? "CannotExecute", $result["details"] ?? ""));
    return (new ValueResponse([
        "msg" => $success_message,
        "id" => (int)($result["event"]["id"] ?? $result["id_session"] ?? 0),
        "url" => isset($result["event"]) ? communication_event_public_url($result["event"]) : "",
    ]));
}

function AddCommunicationEvent($id, $data, $method, $output, $module)
{
    global $User;
    if ((int)$id != -1)
        bad_request();
    return (communication_event_api_result(
        communication_event_create(
            (int)($data["school"] ?? 0),
            $data["name"] ?? "",
            $data["description"] ?? "",
            (int)($User["id"] ?? 0),
            !empty($data["parental_authorization_required"])
        ),
        "Évènement créé."
    ));
}

function EditCommunicationEvent($id, $data, $method, $output, $module)
{
    return (communication_event_api_result(
        communication_event_edit((int)$id, $data),
        "Évènement modifié."
    ));
}

function DeleteCommunicationEvent($id, $data, $method, $output, $module)
{
    return (communication_event_api_result(
        communication_event_delete((int)$id),
        "Évènement supprimé."
    ));
}

function AddCommunicationEventSession($id, $data, $method, $output, $module)
{
    return (communication_event_api_result(
        communication_event_add_session((int)$id, $data),
        "Créneau ajouté."
    ));
}

function DeleteCommunicationEventSession($id, $data, $method, $output, $module)
{
    global $SUBID;
    return (communication_event_api_result(
        communication_event_delete_session((int)$id, abs((int)$SUBID)),
        "Créneau supprimé."
    ));
}

$Tab = [
    "POST" => [
        "" => ["logged_in", "AddCommunicationEvent"],
        "session" => ["logged_in", "AddCommunicationEventSession"],
    ],
    "PUT" => [
        "" => ["logged_in", "EditCommunicationEvent"],
    ],
    "DELETE" => [
        "" => ["logged_in", "DeleteCommunicationEvent"],
        "session" => ["logged_in", "DeleteCommunicationEventSession"],
    ],
];
