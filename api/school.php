<?php
require_once (__DIR__."/../tools/school_timeline.php");

function DisplaySchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Configuration;

    $page = $module;
    $school = fetch_school($id);
    if ($output == "json")
    {
        // The diploma seed is a credential, not school profile data. Never
        // expose it through the generic school JSON endpoint.
        if (is_array($school))
        {
            if (isset($school["id"]))
                unset($school["diploma_secret"]);
            else
                foreach ($school as &$entry)
                    if (is_array($entry))
                        unset($entry["diploma_secret"]);
        }
        return (new ValueResponse(["content" => json_encode($school, JSON_UNESCAPED_SLASHES)]));
    }
    ob_start();
    require ("./pages/school/list_school.phtml");
    return (new ValueResponse(["content" => ob_get_clean()]));
}

function ExportSchoolStudentTimeline($id, $data, $method, $output, $module)
{
    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $ret = school_timeline_generate(
        $id,
        $data["start_year"] ?? NULL,
        $data["end_year"] ?? NULL
    );
    if ($ret->is_error())
        return ($ret);
    add_log(CREATIVE_OPERATION, "school student timeline exported", $id);
    return ($ret);
}

function GenerateDabsic($school)
{

}

function AddSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id != -1)
	bad_request();

    foreach ($data["schools"] as $school)
    {
	if (($ret = add_school($school["codename"], $school["icon"], $school))->is_error())
	    return ($ret);
    }

    $ret = DisplaySchool(-1, [], "GET", $output, $module);
    $ret->value = array_merge(["msg" => $Dictionnary["Added"]], $ret->value);
    return ($ret);
}

function EditSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = edit_school($id, $data))->is_error())
	return ($ret);
    return (new ValueResponse(["msg" => $Dictionnary["Edited"]]));
}

function EditSchoolTechnoCore($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));

    $ret = school_technocore_save((int)$id, $data, (int)($User["id"] ?? 0));
    if ($ret->is_error())
        return ($ret);

    add_log(EDITING_OPERATION, "School TechnoCore configuration edited", (int)$id);
    return (new ValueResponse([
        "msg" => $Dictionnary["Edited"] ?? "Modifié",
    ]));
}

function PreviewSchoolDiploma($id, $data, $method, $output, $module)
{
    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));
    return (diploma_read_school_preview($school));
}

function RefreshSchoolDiplomaPreview($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));
    $ret = diploma_render_school_preview($school);
    if ($ret->is_error())
        return ($ret);
    $ret->value["msg"] = $Dictionnary["DiplomaPreviewGenerated"] ?? "Aperçu du diplôme actualisé";
    return ($ret);
}

function EditSchoolDiploma($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));

    // The embedded Dabsic editor sends its own content/hash pair. Keep the
    // route scoped to this school: the client-provided file path is ignored.
    if (array_key_exists("content", $data) || array_key_exists("hash", $data))
    {
        $result = dabsic_editor_save_file(
            diploma_school_configuration_path($school, false),
            $data["content"] ?? NULL,
            $data["hash"] ?? ""
        );
        if (!$result["ok"])
            return (new ErrorResponse($result["error"], $result["details"] ?? ""));
        add_log(EDITING_OPERATION, "School diploma Dabsic configuration edited", (int)$id);
        return (new ValueResponse([
            "msg" => $Dictionnary["DiplomaSchoolConfigurationSaved"] ?? "Configuration des diplômes enregistrée",
            "hash" => $result["hash"],
            "size" => $result["size"],
            "mtime" => $result["mtime"],
        ]));
    }

    if (($ret = diploma_school_save_configuration($school, $data))->is_error())
        return ($ret);
    add_log(EDITING_OPERATION, "School diploma configuration edited", (int)$id);
    return (new ValueResponse([
        "msg" => $Dictionnary["DiplomaSchoolConfigurationSaved"] ?? "Configuration des diplômes enregistrée",
    ]));
}


function PreviewSchoolIdCard($id, $data, $method, $output, $module)
{
    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));
    return (id_card_render_school_preview($school));
}

function GenerateSchoolIdCardSheet($id, $data, $method, $output, $module)
{
    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));

    $students = $data["students"] ?? [];
    if (!is_array($students))
        $students = [$students];
    $study_years = $data["study_years"] ?? [];
    if (!is_array($study_years))
        $study_years = [];
    $ret = id_card_generate_school_sheet(
        $school,
        $students,
        $data["skip"] ?? "",
        $data["sheet_output"] ?? "print",
        $study_years
    );
    if ($ret->is_error())
        return ($ret);
    add_log(CREATIVE_OPERATION, "School student card sheet generated", (int)$id);
    return ($ret);
}

function EditSchoolIdCard($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));

    if (array_key_exists("content", $data) || array_key_exists("hash", $data))
    {
        $result = dabsic_editor_save_file(
            id_card_school_configuration_path($school, false),
            $data["content"] ?? NULL,
            $data["hash"] ?? ""
        );
        if (!$result["ok"])
            return (new ErrorResponse($result["error"], $result["details"] ?? ""));
        add_log(EDITING_OPERATION, "School student card Dabsic configuration edited", (int)$id);
        return (new ValueResponse([
            "msg" => $Dictionnary["IdCardSchoolConfigurationSaved"] ?? "Configuration des cartes enregistrée",
            "hash" => $result["hash"],
            "size" => $result["size"],
            "mtime" => $result["mtime"],
        ]));
    }

    if (($ret = id_card_school_save_configuration($school, $data))->is_error())
        return ($ret);
    add_log(EDITING_OPERATION, "School student card configuration edited", (int)$id);
    return (new ValueResponse([
        "msg" => $Dictionnary["IdCardSchoolConfigurationSaved"] ?? "Configuration des cartes enregistrée",
    ]));
}

function DeleteSchool($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    if (($ret = mark_as_deleted("school", $id))->is_error())
	return ($ret);
    $ret = DisplaySchool(-1, [], "GET", $output, $module);
    $ret->value = array_merge(["msg" => $Dictionnary["Deleted"]], $ret->value);
    return ($ret);
}

function SetRole($id, $data, $role)
{
    global $Dictionnary;

    $params = [
	"left_value" => $data[$role],
	"right_value" => $id,
	"left_field_name" => "user",
	"right_field_name" => "school",
	"properties" => [
	    "authority" => user_school_authority_value(strtoupper($role))
	],
	"allow_duplicate" => true
    ];
    if (($ret = handle_linksf($params))->is_error())
	return ($ret);
    $school = fetch_school($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "school",
	    "hook_id" => $id,
	    "linked_name" => [
		"table" => "user",
		"name" => $role,
		"placeholder" => ucfirst($role),
		"" => $role,
	    ],
	    "linked_elems" => $school[$role],
	    "admin_func" => "only_admin",
    ])]));
}

function SetDirector($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "director"));
}

function SetCommercial($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "commercial"));
}

function SetLibrarian($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "librarian"));
}

function SetAccountant($id, $data, $method, $output, $module)
{
    if ($id == -1)
        bad_request();
    return (SetRole($id, $data, "accountant"));
}

function SetTeacher($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "teacher"));
}

function SetSecretariat($id, $data, $method, $output, $module)
{
    if ($id == -1)
	bad_request();
    return (SetRole($id, $data, "secretariat"));
}

function SetSchoolResponsibility($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $responsibility_value = $data["responsibility"] ?? NULL;
    $user_value = $data["user"] ?? NULL;
    $definition = school_responsibility_definition($id, $responsibility_value);
    if ($definition === NULL || $user_value === NULL)
        bad_request();

    $resolved = resolve_codename("user", $user_value);
    if ($resolved->is_error())
        return ($resolved);
    $id_user = (int)$resolved->value;
    if ($id_user <= 0)
        bad_request();

    $enabled = strtoupper((string)$method) != "DELETE";
    $ret = school_responsibility_set(
        $id,
        $id_user,
        $definition["id"],
        $enabled,
        (int)($User["id"] ?? 0)
    );
    if ($ret->is_error())
        return ($ret);

    add_log(
        EDITING_OPERATION,
        ($enabled ? "School responsibility assigned: " : "School responsibility removed: ").$definition["codename"],
        $id_user
    );
    return (new ValueResponse([
        "msg" => $Dictionnary["Edited"] ?? "Modifié",
        "active" => $enabled,
        "responsibility" => (int)$definition["id"],
        "codename" => $definition["codename"],
        "label" => $definition["label"],
        "user" => $id_user,
    ]));
}

function EditSchoolResponsibilityDefinition($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $User;

    $id = (int)$id;
    if ($id <= 0)
        bad_request();
    $actor = (int)($User["id"] ?? 0);
    $method = strtoupper((string)$method);

    if ($method == "DELETE")
    {
        $definition = school_responsibility_definition($id, $data["responsibility"] ?? NULL);
        if ($definition === NULL || !empty($definition["builtin"]))
            bad_request();
        $ret = school_responsibility_delete_definition($id, $definition["id"], $actor);
        if ($ret->is_error())
            return ($ret);
        add_log(EDITING_OPERATION, "School custom responsibility deleted: ".$definition["codename"], $id);
        return (new ValueResponse([
            "msg" => $Dictionnary["Deleted"] ?? "Supprimé",
            "responsibility" => (int)$definition["id"],
            "codename" => $definition["codename"],
        ]));
    }

    $ret = school_responsibility_create($id, $data, $actor);
    if ($ret->is_error())
        return ($ret);
    $created_id = (int)$ret->value;
    $definition = $created_id > 0 ? school_responsibility_definition($id, $created_id) : NULL;
    add_log(
        EDITING_OPERATION,
        "School custom responsibility created: ".($definition["codename"] ?? ($data["codename"] ?? $data["fr_name"] ?? "")),
        $id
    );
    return (new ValueResponse([
        "msg" => $Dictionnary["Edited"] ?? "Modifié",
        "responsibility" => $created_id,
    ]));
}

function SetStudent($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    $params = [
	"left_value" => $data["user"],
	"right_value" => $id,
	"left_field_name" => "user",
	"right_field_name" => "school",
	"properties" => [
	    "authority" => user_school_student_authority_value()
	]
    ];
    if (($ret = handle_linksf($params))->is_error())
	return ($ret);
    $school = fetch_school($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "school",
	    "hook_id" => $id,
	    "linked_name" => "user",
	    "linked_elems" => $school["user"],
	    "admin_func" => "is_director_for_school",
    ])]));
}

function SetCycle($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    
    if ($id == -1)
	bad_request();
    $params = [
	"left_value" => $id,
	"right_value" => $data["cycle"],
	"left_field_name" => "school",
	"right_field_name" => "cycle",
    ];
    if (($ret = handle_linksf($params))->is_error())
	return ($ret);
    $school = fetch_school($id);
    return (new ValueResponse([
	"msg" => $Dictionnary["Edited"],
	"content" => list_of_linksb([
	    "hook_name" => "school",
	    "hook_id" => $id,
	    "linked_name" => "cycle",
	    "linked_elems" => $school["cycle"],
	    "admin_func" => "is_director_for_school",
    ])]));
}

function SchoolMailTargetAuthority($target)
{
    static $authorities = [
        "students" => "STUDENT",
        "secretariat" => "SECRETARIAT",
        "librarian" => "LIBRARIAN",
        "director" => "DIRECTOR",
        "teacher" => "TEACHER",
        "accountant" => "ACCOUNTANT",
        "commercial" => "COMMERCIAL",
    ];
    return ($authorities[$target] ?? NULL);
}

function IdentifySchoolNfcCard($id, $data, $method, $output, $module)
{
    $school = fetch_school((int)$id);
    if (!is_array($school) || !isset($school["id"]))
        return (new ErrorResponse("InvalidParameter", "school"));

    $owner = nfc_card_owner_lookup($data["token"] ?? "");
    if ($owner === NULL)
        bad_request();
    return (new ValueResponse($owner));
}

function SchoolMailRecipients($id, $target)
{
    $id = (int)$id;
    $target = trim((string)$target);
    $where = "";
    $authority = SchoolMailTargetAuthority($target);

    if ($authority != NULL)
	$where = " AND user_school.authority = ".user_school_authority_sql($authority)." ";
    else if ($target == "staff")
	$where = " AND user_school.authority <> ".user_school_authority_sql("STUDENT")." ";

    $rows = db_select_all("
        DISTINCT user.mail as mail
        FROM user_school
        LEFT JOIN user ON user.id = user_school.id_user
        WHERE user_school.id_school = $id
        AND user.id IS NOT NULL
        AND user.deleted IS NULL
        AND user.authority != -1
        AND user.mail IS NOT NULL
        AND user.mail != ''
        $where
        ORDER BY user.mail ASC
    ");
    $mails = [];
    foreach ($rows as $row)
        if (filter_var($row["mail"], FILTER_VALIDATE_EMAIL))
            $mails[] = $row["mail"];
    return (array_values(array_unique($mails)));
}

function SchoolMailAttachments($field = "attachments")
{
    if (!isset($_FILES[$field]))
        return (["ok" => true, "attachments" => []]);

    $upload = $_FILES[$field];
    $names = is_array($upload["name"] ?? NULL) ? $upload["name"] : [$upload["name"] ?? ""];
    $tmp_names = is_array($upload["tmp_name"] ?? NULL) ? $upload["tmp_name"] : [$upload["tmp_name"] ?? ""];
    $errors = is_array($upload["error"] ?? NULL) ? $upload["error"] : [$upload["error"] ?? UPLOAD_ERR_NO_FILE];
    $sizes = is_array($upload["size"] ?? NULL) ? $upload["size"] : [$upload["size"] ?? 0];

    $attachments = [];
    $total_size = 0;
    $count = 0;
    foreach ($names as $i => $original_name)
    {
        $error = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error == UPLOAD_ERR_NO_FILE)
            continue ;
        if ($error != UPLOAD_ERR_OK)
            return (["ok" => false, "error" => "SchoolMailAttachmentUploadError"]);
        if (++$count > 10)
            return (["ok" => false, "error" => "SchoolMailTooManyAttachments"]);

        $size = (int)($sizes[$i] ?? 0);
        $total_size += max(0, $size);
        if ($total_size > 20 * 1024 * 1024)
            return (["ok" => false, "error" => "SchoolMailAttachmentsTooLarge"]);

        $tmp = (string)($tmp_names[$i] ?? "");
        if ($tmp == "" || !is_uploaded_file($tmp))
            return (["ok" => false, "error" => "SchoolMailAttachmentUploadError"]);
        $content = @file_get_contents($tmp);
        if ($content === false)
            return (["ok" => false, "error" => "SchoolMailAttachmentReadError"]);

        $filename = basename(str_replace("\\", "/", (string)$original_name));
        $filename = trim((string)preg_replace('/[\x00-\x1F\x7F]/u', '', $filename));
        if ($filename == "")
            $filename = "piece-jointe-".$count;

        // send_mail() indexe les pièces jointes par nom. Conserver les deux
        // fichiers même si l'utilisateur en sélectionne deux portant le même nom.
        if (isset($attachments[$filename]))
        {
            $info = pathinfo($filename);
            $base = $info["filename"] ?? $filename;
            $ext = isset($info["extension"]) && $info["extension"] != "" ? ".".$info["extension"] : "";
            $suffix = 2;
            do
            $candidate = $base." (".$suffix++.")".$ext;
            while (isset($attachments[$candidate]));
            $filename = $candidate;
        }
        $attachments[$filename] = $content;
    }
    return (["ok" => true, "attachments" => $attachments]);
}

function SendSchoolMail($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id == -1)
        bad_request();

    $target = trim((string)($data["target"] ?? "all"));
    $targets = [
        "all", "students", "staff", "secretariat", "librarian",
        "director", "teacher", "accountant", "commercial"
    ];
    if (!in_array($target, $targets, true))
        $target = "all";

    $subject = trim((string)($data["subject"] ?? ""));
    $content = trim((string)($data["content"] ?? ""));
    if ($subject == "" || $content == "")
        return (new ErrorResponse("InvalidParameter"));

    $mails = SchoolMailRecipients($id, $target);
    if (!count($mails))
        return (new ErrorResponse("NoMail"));

    $loaded = SchoolMailAttachments();
    if (empty($loaded["ok"]))
        return (new ErrorResponse($loaded["error"] ?? "InvalidFile"));
    $attachments = $loaded["attachments"];

    if (($ret = send_mail($mails, $subject, $content, NULL, $attachments, true))->is_error())
        return ($ret);
    add_log(CREATIVE_OPERATION, "school mail $target with ".count($attachments)." attachment(s)", $id);
    return (new ValueResponse([
        "msg" => sprintf(
            $Dictionnary["SchoolMailSent"] ?? "Envoyé à %d destinataire(s), avec %d pièce(s) jointe(s).",
            count($mails),
            count($attachments)
        )
    ]));
}

$Tab = [
    "GET" => [
	"" => [
	    "am_i_teacher,am_i_director",
	    "DisplaySchool"
	],
	"diploma_preview" => [
	    "is_director_for_school",
	    "PreviewSchoolDiploma",
	],
	"id_card_preview" => [
	    "is_director_for_school",
	    "PreviewSchoolIdCard",
	],
        "student_timeline" => [
            "is_director_for_school",
            "ExportSchoolStudentTimeline",
        ]
    ],
    "PUT" => [
	"" => [
	    "is_director_for_school",
	    "EditSchool",
	],
	"technocore" => [
	    "is_director_for_school",
	    "EditSchoolTechnoCore",
	],
	"diploma" => [
	    "is_director_for_school",
	    "EditSchoolDiploma",
	],
	"diploma_preview" => [
	    "is_director_for_school",
	    "RefreshSchoolDiplomaPreview",
	],
	"id_card" => [
	    "is_director_for_school",
	    "EditSchoolIdCard",
	],
	"director" => [
	    "only_admin",
	    "SetDirector"
	],
	"commercial" => [
	    "is_director_for_school",
	    "SetCommercial",
	],
	"librarian" => [
	    "is_director_for_school",
	    "SetLibrarian",
	],
	"accountant" => [
	    "is_director_for_school",
	    "SetAccountant",
	],
	"teacher" => [
	    "is_director_for_school",
	    "SetTeacher",
	],
	"secretariat" => [
	    "is_director_for_school",
	    "SetSecretariat",
	],
	"responsibility" => [
	    "is_director_for_school",
	    "SetSchoolResponsibility",
	],
	"responsibility_definition" => [
	    "is_director_for_school",
	    "EditSchoolResponsibilityDefinition",
	],
	"user" => [
	    ["is_director_for_school", "is_commercial_for_school", "is_secretariat_for_school"],
	    "SetStudent",
	],
	"cycle" => [
	    ["is_director_for_school", "is_teacher_for_school"],
	    "SetCycle",
	]
    ],
    "POST" => [
	"" => [
	    "only_admin",
	    "AddSchool"
	],
	"mail" => [
	    "is_director_for_school",
	    "SendSchoolMail"
	],
	"nfc_card_owner" => [
	    "can_identify_school_nfc_card",
	    "IdentifySchoolNfcCard"
	],
        "id_card_sheet" => [
            "is_director_for_school",
            "GenerateSchoolIdCardSheet"
        ],
        "student_timeline" => [
            "is_director_for_school",
            "ExportSchoolStudentTimeline",
        ]
    ],
    "DELETE" => [
	"" => [
	    "only_admin",
	    "DeleteSchool"
	],
	"commercial" => [
	    "is_director_for_school",
	    "SetCommercial",
	],
	"librarian" => [
	    "is_director_for_school",
	    "SetLibrarian",
	],
	"accountant" => [
	    "is_director_for_school",
	    "SetAccountant",
	],
	"teacher" => [
	    "is_director_for_school",
	    "SetTeacher",
	],
	"secretariat" => [
	    "is_director_for_school",
	    "SetSecretariat",
	],
	"responsibility" => [
	    "is_director_for_school",
	    "SetSchoolResponsibility",
	],
	"responsibility_definition" => [
	    "is_director_for_school",
	    "EditSchoolResponsibilityDefinition",
	],
	"user" => [
	    ["is_director_for_school", "is_commercial_for_school", "is_secretariat_for_school"],
	    "SetStudent"
	],
	"director" => [
	    "only_admin",
	    "SetDirector"
	],
	"cycle" => [
	    ["is_director_for_school", "is_teacher_for_school"],
	    "SetCycle",
	]
    ]
];
