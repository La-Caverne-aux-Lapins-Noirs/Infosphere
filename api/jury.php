<?php

function JuryCanManage($id = -1)
{
    return (am_i_director());
}

function JuryCleanData($data)
{
    foreach ([
        "codename", "mail", "first_name", "family_name",
        "nickname", "phone", "titles", "note", "fr_name", "en_name",
        "code", "fr_description", "en_description", "skills"
    ] as $field)
        if (isset($data[$field]))
            $data[$field] = trim((string)$data[$field]);
    foreach ($data as $key => $value)
        if (preg_match('/^skill_reference_[0-9]+$/', $key))
            $data[$key] = trim((string)$value);
    return ($data);
}

function JuryListResponse($msg = "")
{
    $juries = fetch_juries();
    $jury_titles = fetch_jury_titles();
    $certification_skills = fetch_certification_skills();
    ob_start();
    require ("./pages/jury/list.phtml");
    $value = ["content" => ob_get_clean()];
    if ($msg != "")
        $value["msg"] = $msg;
    return (new ValueResponse($value));
}

function JuryResolveUserId($id)
{
    if ($id == -1)
        return (new ErrorResponse("MissingParameter", "jury"));
    if (($ret = resolve_codename("user", $id))->is_error())
        return ($ret);
    $id = (int)$ret->value;
    $jury = db_select_one("id FROM user WHERE id = $id AND profile_status = 'jury'");
    if ($jury == NULL)
        return (new ErrorResponse("UserNotFound"));
    return (new ValueResponse($id));
}

function JuryUserFields($data)
{
    $fields = [];
    foreach (["first_name", "family_name", "nickname", "mail", "phone"] as $field)
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    $fields["profile_status"] = "jury";
    return ($fields);
}


function JurySubmittedTitleFields($data)
{
    foreach ($data as $key => $value)
        if (preg_match('/^title_[0-9]+$/', $key))
            return (true);
    return (false);
}

function JuryExtractTitles($data)
{
    if (JurySubmittedTitleFields($data) == false)
        return ($data["titles"] ?? NULL);

    $titles = [];
    foreach (fetch_jury_titles() as $title)
    {
        $field = "title_".$title["id"];
        if (isset($data[$field]) && (int)$data[$field] != 0)
            $titles[] = $title["codename"];
    }
    return (implode(";", $titles));
}


function CertificationUpdateSubmittedSkillReferences($id_title, $data)
{
    foreach ($data as $key => $value)
    {
        if (!preg_match('/^skill_reference_([0-9]+)$/', $key, $m))
            continue ;
        if (($ret = handle_links(
            (int)$id_title,
            (int)$m[1],
            "title",
            "skill",
            false,
            "title_skill",
            false,
            "",
            "",
            ["reference" => trim((string)$value)]
        ))->is_error())
            return ($ret);
    }
    return (new ValueResponse(true));
}

function CertificationHandleSubmittedSkillLinks($id_title, $data)
{
    $changed = false;

    if (($ret = CertificationUpdateSubmittedSkillReferences($id_title, $data))->is_error())
        return ($ret);
    if (isset($data["skills"]) && trim((string)$data["skills"]) != "")
    {
        if (($ret = handle_links(
            (int)$id_title,
            $data["skills"],
            "title",
            "skill",
            false,
            "title_skill"
        ))->is_error())
            return ($ret);
        $changed = true;
    }
    if ($changed || CertificationSubmittedSkillReferenceFields($data))
        CertificationRefreshUsersForTitle($id_title);
    return (new ValueResponse(true));
}

function CertificationSubmittedSkillReferenceFields($data)
{
    foreach ($data as $key => $value)
        if (preg_match('/^skill_reference_[0-9]+$/', $key))
            return (true);
    return (false);
}

function CertificationRefreshUsersForTitle($id_title)
{
    foreach (db_select_all("
        user_title.id_user
        FROM user_title
        LEFT JOIN user ON user.id = user_title.id_user
        WHERE user_title.id_title = ".((int)$id_title)."
          AND user.profile_status = 'jury'
    ") as $row)
        jury_refresh_user($row["id_user"]);
}

function DisplayJury($id, $data, $method, $output, $module)
{
    return (JuryListResponse());
}

function AddJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if ($id != -1)
        bad_request();
    $data = JuryCleanData($data);

    if (($ret = subscribe_jury(
        $data["first_name"] ?? "",
        $data["family_name"] ?? "",
        $data["mail"] ?? "",
        $data["phone"] ?? ""
    ))->is_error())
        return ($ret);
    $id_user = (int)$ret->value["id"];

    if (($ret = set_user_data($id_user, JuryUserFields($data)))->is_error())
        return ($ret);
    if (($ret = jury_set_user_titles($id_user, JuryExtractTitles($data) ?? ""))->is_error())
        return ($ret);
    if (array_key_exists("note", $data))
        if (($ret = jury_set_note($id_user, $data["note"]))->is_error())
            return ($ret);
    add_log(CREATIVE_OPERATION, "Jury $id_user added", $id_user);
    return (JuryListResponse($Dictionnary["JuryAdded"] ?? "Jury ajouté"));
}

function EditJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = JuryResolveUserId($id))->is_error())
        return ($ret);
    $id_user = (int)$ret->value;
    $data = JuryCleanData($data);
    if (($ret = set_user_data($id_user, JuryUserFields($data)))->is_error())
        return ($ret);
    $titles = JuryExtractTitles($data);
    if ($titles !== NULL)
    {
        if (($ret = jury_set_user_titles($id_user, $titles))->is_error())
            return ($ret);
    }
    else
        jury_refresh_user($id_user);
    if (array_key_exists("note", $data))
        if (($ret = jury_set_note($id_user, $data["note"]))->is_error())
            return ($ret);
    add_log(EDITING_OPERATION, "Jury #$id_user edited", $id_user);
    return (JuryListResponse($Dictionnary["JuryModified"] ?? "Jury modifié"));
}

function DeleteJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = JuryResolveUserId($id))->is_error())
        return ($ret);
    $id_user = (int)$ret->value;
    if (($ret = set_user_data($id_user, ["deleted" => db_form_date(now())]))->is_error())
        return ($ret);
    add_log(DESTRUCTIVE_OPERATION, "Jury #$id_user archived", $id_user);
    return (JuryListResponse($Dictionnary["JuryArchived"] ?? "Jury archivé"));
}

function RestoreJury($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = JuryResolveUserId($id))->is_error())
        return ($ret);
    $id_user = (int)$ret->value;
    if (($ret = set_user_data($id_user, ["deleted" => NULL, "profile_status" => "jury"]))->is_error())
        return ($ret);
    jury_refresh_user($id_user);
    add_log(EDITING_OPERATION, "Jury #$id_user restored", $id_user);
    return (JuryListResponse($Dictionnary["JuryRestored"] ?? "Jury restauré"));
}

function AddJuryTitle($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
        bad_request();
    $data = JuryCleanData($data);
    $codename = $data["codename"] ?? "";
    if ($codename == "" || !is_symbol($codename))
        return (new ErrorResponse("MissingCodeName", $codename));
    $codename_sql = $Database->real_escape_string($codename);
    if (db_select_one("id FROM `title` WHERE codename = '$codename_sql'") != NULL)
        return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    $code = $Database->real_escape_string($data["code"] ?? "");
    $fr = $Database->real_escape_string($data["fr_name"] ?? $codename);
    $en = $Database->real_escape_string($data["en_name"] ?? ($data["fr_name"] ?? $codename));
    if ($Database->query("INSERT INTO `title` (codename, code, fr_name, en_name) VALUES ('$codename_sql', '$code', '$fr', '$en')") == false)
        return (new ErrorResponse("CannotAdd"));
    $id_title = (int)$Database->insert_id;
    if (($ret = CertificationHandleSubmittedSkillLinks($id_title, $data))->is_error())
        return ($ret);
    add_log(CREATIVE_OPERATION, "Certification title $codename added");
    return (JuryListResponse($Dictionnary["JuryTitleAdded"] ?? "Certification ajoutée"));
}

function EditJuryTitle($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = fetch_jury_title($id))->is_error())
        return ($ret);
    $title = $ret->value;
    $data = JuryCleanData($data);
    $fields = [];
    foreach (["code", "fr_name", "en_name"] as $field)
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    if (count($fields))
        if (db_update_one("title", (int)$title["id"], $fields) === NULL)
            return (new ErrorResponse("CannotEdit"));
    if (($ret = CertificationHandleSubmittedSkillLinks($title["id"], $data))->is_error())
        return ($ret);
    add_log(EDITING_OPERATION, "Certification title {$title["codename"]} edited");
    return (JuryListResponse($Dictionnary["JuryTitleModified"] ?? "Certification modifiée"));
}

function DeleteJuryTitle($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = fetch_jury_title($id))->is_error())
        return ($ret);
    $title = $ret->value;
    if (db_update_one("title", (int)$title["id"], ["deleted" => db_form_date(now())]) === NULL)
        return (new ErrorResponse("CannotEdit"));
    CertificationRefreshUsersForTitle($title["id"]);
    add_log(DESTRUCTIVE_OPERATION, "Certification title {$title["codename"]} archived");
    return (JuryListResponse($Dictionnary["JuryTitleArchived"] ?? "Certification archivée"));
}

function AddCertificationSkill($id, $data, $method, $output, $module)
{
    global $Dictionnary;
    global $Database;

    if ($id != -1)
        bad_request();
    $data = JuryCleanData($data);
    $codename = $data["codename"] ?? "";
    if ($codename == "" || !is_symbol($codename))
        return (new ErrorResponse("MissingCodeName", $codename));
    $codename_sql = $Database->real_escape_string($codename);
    if (db_select_one("id FROM skill WHERE codename = '$codename_sql'") != NULL)
        return (new ErrorResponse("CodeNameAlreadyUsed", $codename));
    $fr = $Database->real_escape_string($data["fr_description"] ?? "");
    $en = $Database->real_escape_string($data["en_description"] ?? "");
    if ($Database->query("INSERT INTO skill (codename, fr_description, en_description) VALUES ('$codename_sql', '$fr', '$en')") == false)
        return (new ErrorResponse("CannotAdd"));
    add_log(CREATIVE_OPERATION, "Skill $codename added");
    return (JuryListResponse($Dictionnary["SkillAdded"] ?? "Compétence ajoutée"));
}

function EditCertificationSkill($id, $data, $method, $output, $module)
{
    global $Dictionnary;

    if (($ret = fetch_certification_skill($id))->is_error())
        return ($ret);
    $skill = $ret->value;
    $data = JuryCleanData($data);
    $fields = [];
    foreach (["fr_description", "en_description"] as $field)
        if (isset($data[$field]))
            $fields[$field] = $data[$field];
    if (count($fields))
        if (db_update_one("skill", (int)$skill["id"], $fields) === NULL)
            return (new ErrorResponse("CannotEdit"));
    foreach (db_select_all("id_title FROM title_skill WHERE id_skill = ".((int)$skill["id"])) as $row)
        CertificationRefreshUsersForTitle($row["id_title"]);
    add_log(EDITING_OPERATION, "Skill {$skill["codename"]} edited");
    return (JuryListResponse($Dictionnary["SkillModified"] ?? "Compétence modifiée"));
}

$Tab = [
    "GET" => [
        "" => [
            "JuryCanManage",
            "DisplayJury",
        ],
    ],
    "POST" => [
        "" => [
            "JuryCanManage",
            "AddJury",
        ],
        "title" => [
            "JuryCanManage",
            "AddJuryTitle",
        ],
        "skill" => [
            "JuryCanManage",
            "AddCertificationSkill",
        ],
    ],
    "PUT" => [
        "" => [
            "JuryCanManage",
            "EditJury",
        ],
        "restore" => [
            "JuryCanManage",
            "RestoreJury",
        ],
        "title" => [
            "JuryCanManage",
            "EditJuryTitle",
        ],
        "skill" => [
            "JuryCanManage",
            "EditCertificationSkill",
        ],
    ],
    "DELETE" => [
        "" => [
            "JuryCanManage",
            "DeleteJury",
        ],
        "title" => [
            "JuryCanManage",
            "DeleteJuryTitle",
        ],
    ],
];
