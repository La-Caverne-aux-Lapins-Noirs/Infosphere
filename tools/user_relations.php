<?php

function user_relation_definitions()
{
    return ([
        "financial" => ["bit" => 1, "label" => "Responsable financier"],
        "legal" => ["bit" => 2, "label" => "Responsable légal"],
        "emergency" => ["bit" => 4, "label" => "Contact d'urgence"],
        "internship" => ["bit" => 8, "label" => "Tuteur de stage / alternance"],
        "log_as" => ["bit" => 16, "label" => "Autoriser le Log as"],
    ]);
}

function user_relation_values($relation)
{
    $definitions = user_relation_definitions();
    $out = [];

    if ($relation === NULL || $relation === "")
        return ($out);

    if (is_array($relation))
    {
        foreach ($relation as $name)
            if (isset($definitions[$name]) && !in_array($name, $out, true))
                $out[] = $name;
        return ($out);
    }

    // Compatibilité avec les anciennes bases où relation était un bitfield.
    if (is_numeric($relation) && preg_match('/^-?[0-9]+$/', (string)$relation))
    {
        $mask = (int)$relation;
        foreach ($definitions as $name => $definition)
            if (($mask & $definition["bit"]) != 0)
                $out[] = $name;
        return ($out);
    }

    foreach (preg_split('/\s*,\s*/', (string)$relation, -1, PREG_SPLIT_NO_EMPTY) as $name)
        if (isset($definitions[$name]) && !in_array($name, $out, true))
            $out[] = $name;
    return ($out);
}

function user_relation_has($relation, $name)
{
    return (in_array($name, user_relation_values($relation), true));
}

function user_relation_value($relation)
{
    if (!is_array($relation))
        $relation = user_relation_values($relation);
    $definitions = user_relation_definitions();
    $out = [];
    foreach ($relation as $name)
        if (isset($definitions[$name]) && !in_array($name, $out, true))
            $out[] = $name;
    return (implode(",", $out));
}

function user_relation_labels($relation)
{
    $definitions = user_relation_definitions();
    $out = [];
    foreach (user_relation_values($relation) as $name)
        $out[] = $definitions[$name]["label"];
    return ($out);
}

function user_relation_management_school_ids($id_user)
{
    if (!logged_in())
        return ([]);

    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);

    // Use the regular access helpers. They already implement Infosphere's
    // administrator override, so an administrator can act as the operational
    // fallback while non-admin users must really hold one of these roles for
    // the school concerned.
    $out = [];
    foreach (user_school_ids($id_user) as $id_school)
        if (is_director_for_school($id_school)
            || is_secretariat_for_school($id_school)
            || is_commercial_for_school($id_school))
            $out[] = (int)$id_school;
    return ($out);
}

function can_manage_user_relations($id_user)
{
    return (count(user_relation_management_school_ids($id_user)) != 0);
}

function user_relation_candidate_parents($id_child)
{
    $id_child = (int)$id_child;
    $schools = user_relation_management_school_ids($id_child);
    if ($id_child <= 0 || !count($schools))
        return ([]);

    $school_ids = implode(",", array_map("intval", $schools));
    return (db_select_all("\n        DISTINCT user.id, user.codename, user.first_name, user.family_name\n        FROM user\n        INNER JOIN user_school ON user_school.id_user = user.id\n        WHERE user.id != $id_child\n          AND user.deleted IS NULL\n          AND user.authority != ".BANISHED."\n          AND user.profile_status = 'member'\n          AND user.password != ''\n          AND user_school.id_school IN ($school_ids)\n          AND NOT EXISTS (\n              SELECT parent_child.id FROM parent_child\n              WHERE parent_child.id_parent = user.id\n                AND parent_child.id_child = $id_child\n          )\n        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC\n    "));
}

function can_assign_user_relation_parent($id_child, $id_parent)
{
    $id_child = (int)$id_child;
    $id_parent = (int)$id_parent;
    if ($id_child <= 0 || $id_parent <= 0 || $id_child == $id_parent)
        return (false);

    $schools = user_relation_management_school_ids($id_child);
    if (!count($schools))
        return (false);

    // Un parent déjà relié à l'utilisateur doit rester modifiable même s'il
    // s'agit d'un contact externe. Les comptes externes n'ont volontairement
    // ni user_school, ni mot de passe actif : leur imposer les critères
    // ci-dessous rendait impossible la modification d'une relation existante
    // depuis le profil et provoquait un Forbidden dans SetUserRelation().
    if (db_select_one("
        parent_child.id
        FROM parent_child
        INNER JOIN user ON user.id = parent_child.id_parent
        WHERE parent_child.id_parent = $id_parent
          AND parent_child.id_child = $id_child
          AND user.deleted IS NULL
          AND user.authority != ".BANISHED."
    ") != NULL)
        return (true);

    // Pour créer une nouvelle relation vers un compte déjà existant, conserver
    // en revanche la règle historique : le parent doit être un membre actif
    // d'une des écoles que l'opérateur est autorisé à administrer.
    $school_ids = implode(",", array_map("intval", $schools));
    return (db_select_one("
        user.id
        FROM user
        INNER JOIN user_school ON user_school.id_user = user.id
        WHERE user.id = $id_parent
          AND user.deleted IS NULL
          AND user.authority != ".BANISHED."
          AND user.profile_status = 'member'
          AND user.password != ''
          AND user_school.id_school IN ($school_ids)
    ") != NULL);
}

function user_relation_request_values(array $data)
{
    $relations = user_relation_values($data["relation"] ?? []);
    foreach (user_relation_definitions() as $name => $definition)
        if (!empty($data["relation_".$name]) && !in_array($name, $relations, true))
            $relations[] = $name;
    return ($relations);
}

/*
 * A contact externe peut parfaitement posséder un accès à l'Infosphère sans
 * devenir un membre de l'école. C'est précisément le cas d'un parent auquel
 * on autorise un Log as : lui donner profile_status = 'member' serait faux
 * fonctionnellement et pourrait lui faire hériter de comportements réservés
 * aux membres. On active donc uniquement ses identifiants de connexion.
 */
function user_relation_enable_external_login($id_user)
{
    global $Database;
    global $Configuration;
    global $Dictionnary;

    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (new ErrorResponse("UserNotFound"));

    $user = db_select_one("
        *
        FROM user
        WHERE id = $id_user
          AND deleted IS NULL
          AND authority != ".BANISHED."
    ");
    if ($user == NULL)
        return (new ErrorResponse("UserNotFound"));

    // Déjà authentifiable : rien à faire, quel que soit son statut métier.
    if (trim((string)($user["password"] ?? "")) != "")
        return (new Response);

    // Ne jamais transformer implicitement un prospect, un juré, etc.
    if (($user["profile_status"] ?? "") != "extern")
        return (new ErrorResponse("CannotEdit"));
    if (!filter_var((string)($user["mail"] ?? ""), FILTER_VALIDATE_EMAIL))
        return (new ErrorResponse("BadMail", (string)($user["mail"] ?? "")));

    $password = generate_password();
    if (($material = build_user_password_material($password))->is_error())
        return ($material);
    $material = $material->value;

    $hash = $Database->real_escape_string($material["hash"]);
    $salt = $Database->real_escape_string($material["salt"]);
    $local_salt = $Database->real_escape_string($material["local_salt"]);
    if ($Database->query("
        UPDATE user
        SET password = '$hash',
            salt = '$salt',
            local_salt = '$local_salt',
            cache = '{}'
        WHERE id = $id_user
          AND profile_status = 'extern'
          AND password = ''
          AND deleted IS NULL
    ") === false || $Database->affected_rows != 1)
        return (new ErrorResponse("CannotUpdate"));

    $domain = @$Configuration->Properties["domain"];
    $content = sprintf(
        $Dictionnary["RelationLoginActivatedContent"]
            ?? "Bonjour,\n\nUn accès à l'Infosphère %s vient de vous être ouvert.\nVotre identifiant est %s et votre mot de passe est \"%s\".\n\nCordialement\nAlbedo",
        $domain,
        (string)$user["codename"],
        $password
    );
    $mail = send_mail(
        (string)$user["mail"],
        $Dictionnary["RelationLoginActivatedTitle"] ?? "Accès à l'Infosphère",
        $content
    );
    if ($mail->is_error())
    {
        // Sans remise du mot de passe, ne pas laisser derrière nous un compte
        // dont personne ne connaît les identifiants.
        $Database->query("
            UPDATE user
            SET password = '', salt = '', local_salt = '', cache = '{}'
            WHERE id = $id_user
              AND profile_status = 'extern'
              AND password = '$hash'
        ");
        return ($mail);
    }

    add_log(
        CRITICAL_USER_DATA,
        "External relation account ".$user["codename"]." enabled for login",
        $id_user
    );
    return (new Response);
}

function user_relation_set_existing_parent($id_child, $id_parent, $relations)
{
    $id_child = (int)$id_child;
    $id_parent = (int)$id_parent;
    if (!can_manage_user_relations($id_child)
        || !can_assign_user_relation_parent($id_child, $id_parent))
        return (new ErrorResponse("CannotEdit"));

    $relation = user_relation_value($relations);
    if ($relation == "")
        return (new ErrorResponse("MissingField", "relation"));

    // Autoriser un Log as sans identifiants serait une permission inutilisable.
    // Pour un contact externe existant, l'activation est donc automatique.
    if (user_relation_has($relation, "log_as"))
    {
        $activation = user_relation_enable_external_login($id_parent);
        if ($activation->is_error())
            return ($activation);
    }

    return (add_link(
        $id_parent,
        $id_child,
        "user",
        "user",
        false,
        ["relation" => $relation],
        "parent_child",
        false,
        "parent",
        "child"
    ));
}

function user_relation_remove_parent($id_child, $id_parent)
{
    $id_child = (int)$id_child;
    $id_parent = (int)$id_parent;
    if (!can_manage_user_relations($id_child))
        return (new ErrorResponse("CannotEdit"));
    return (remove_link(
        $id_parent,
        $id_child,
        "user",
        "user",
        false,
        "parent_child",
        "parent",
        "child"
    ));
}

function create_external_user_relation($id_child, array $data)
{
    global $Database;

    $id_child = (int)$id_child;
    if ($id_child <= 0 || db_select_one("id FROM user WHERE id = $id_child AND authority != -1") == NULL)
        return (new ErrorResponse("UserNotFound"));
    if (!can_manage_user_relations($id_child))
        return (new ErrorResponse("CannotEdit"));

    $relations = user_relation_values($data["relation"] ?? []);
    if (!count($relations))
        return (new ErrorResponse("MissingField", "relation"));

    $first_name = trim((string)($data["first_name"] ?? ""));
    $family_name = trim((string)($data["family_name"] ?? ""));
    $mail = trim((string)($data["mail"] ?? ""));
    $phone = trim((string)($data["phone"] ?? ""));
    if ($first_name == "" || $family_name == "")
        return (new ErrorResponse("MissingField", "first_name, family_name"));

    // Sur le formulaire administratif d'ajout d'une relation, une omission
    // du mail équivaut au marqueur historique `nomail`. Une valeur non vide
    // continue en revanche de passer par la validation stricte de subscribe().
    if ($mail == "")
        $mail = "nomail";

    // `nomail` est un marqueur réservé à la création administrative d'une
    // relation. Il n'est jamais stocké : l'absence de mail est représentée
    // par une chaîne vide afin qu'aucun document ou envoi ne puisse reprendre
    // accidentellement le marqueur comme s'il s'agissait d'une adresse.
    $without_mail = strcasecmp($mail, "nomail") == 0;
    if ($without_mail)
    {
        if (user_relation_has($relations, "log_as"))
            return (new ErrorResponse("BadMail", "nomail (Log as)"));
        $mail = "";
    }

    $login = build_named_user_login($first_name, $family_name);
    $request = subscribe($login, $mail, NULL, false, true, "extern", $without_mail);
    if ($request->is_error())
        return ($request);
    $external = $request->value;
    $id_parent = (int)$external["id"];

    $request = set_user_data($id_parent, [
        "first_name" => $first_name,
        "family_name" => $family_name,
        "phone" => $phone,
        "visibility" => HIDDEN,
    ]);
    if ($request->is_error())
    {
        $Database->query("DELETE FROM user WHERE id = $id_parent AND profile_status = 'extern' AND password = ''");
        return ($request);
    }

    $relation = $Database->real_escape_string(user_relation_value($relations));
    if ($Database->query("\n        INSERT INTO parent_child (id_parent, id_child, relation)\n        VALUES ($id_parent, $id_child, '$relation')\n    ") === false)
    {
        $Database->query("DELETE FROM user WHERE id = $id_parent AND profile_status = 'extern' AND password = ''");
        return (new ErrorResponse("CannotRegister"));
    }

    // Une délégation Log as n'a de sens que si le contact peut réellement se
    // connecter. Les contacts externes restent des profils externes, mais on
    // active automatiquement leurs identifiants lorsque cette permission est
    // demandée dès la création de la relation.
    if (user_relation_has($relation, "log_as"))
    {
        $activation = user_relation_enable_external_login($id_parent);
        if ($activation->is_error())
        {
            $Database->query("
                DELETE FROM parent_child
                WHERE id_parent = $id_parent AND id_child = $id_child
            ");
            $Database->query("
                DELETE FROM user
                WHERE id = $id_parent
                  AND profile_status = 'extern'
                  AND password = ''
            ");
            return ($activation);
        }
    }

    add_log(EDITING_OPERATION,
        "External relation user ".$external["codename"]." added for user $id_child with relation $relation",
        $id_child
    );
    return (new ValueResponse([
        "id" => $id_parent,
        "codename" => $external["codename"],
        "relation" => $relations,
    ]));
}


function user_relation_is_administrative_contact($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);
    foreach (db_select_all("
        parent_child.relation
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_parent
        WHERE parent_child.id_parent = $id_user
          AND user.authority != -1
    ") as $row)
        if (user_relation_has($row["relation"] ?? "", "legal")
            || user_relation_has($row["relation"] ?? "", "financial"))
            return (true);
    return (false);
}

function user_relation_managed_children($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return ([]);
    return (db_select_all("
        user.id, user.codename, user.first_name, user.family_name, parent_child.relation
        FROM parent_child
        LEFT JOIN user ON user.id = parent_child.id_child
        WHERE parent_child.id_parent = $id_user
          AND user.id IS NOT NULL
          AND user.authority != -1
        ORDER BY parent_child.id ASC
    "));
}

function can_manage_relation_administrative_user($id_user)
{
    if (is_admin())
        return (true);
    if (!logged_in() || !user_relation_is_administrative_contact($id_user))
        return (false);
    foreach (user_relation_managed_children($id_user) as $child)
        if (is_identity_authority_for_user((int)$child["id"]) || am_i_commercial())
            return (true);
    return (false);
}

function can_manage_user_administrative_profile($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0 || !logged_in())
        return (false);
    if (is_identity_authority_for_user($id_user))
        return (true);
    $user = db_select_one("profile_status FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (false);
    if (($user["profile_status"] ?? "") == "prospect" && am_i_commercial())
        return (true);
    return (can_manage_relation_administrative_user($id_user));
}

function can_send_user_administrative_form($id_user)
{
    $id_user = (int)$id_user;
    if (!logged_in())
        return (false);
    $user = db_select_one("profile_status, mail FROM user WHERE id = $id_user AND authority != -1");
    if ($user == NULL)
        return (false);
    $mail = trim((string)($user["mail"] ?? ""));
    if ($mail == "" || strcasecmp($mail, "nomail") == 0)
        return (false);
    $status = $user["profile_status"] ?? "";
    if (user_relation_is_administrative_contact($id_user))
        return (is_admin() || am_i_commercial() || can_manage_relation_administrative_user($id_user));
    if ($status == "prospect")
        return (is_admin() || am_i_commercial() || is_identity_authority_for_user($id_user));
    return (false);
}

function can_view_user_relations($id_user)
{
    global $User;

    if (!$User)
        return (false);
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);
    if (is_me($id_user) || is_admin() || can_manage_user_relations($id_user))
        return (true);

    $me = (int)$User["id"];
    return (db_select_one("\n        id FROM parent_child\n        WHERE (id_parent = $me AND id_child = $id_user)\n           OR (id_child = $me AND id_parent = $id_user)\n    ") != NULL);
}

function fetch_user_relation_summary($id_user)
{
    global $User;

    $id_user = (int)$id_user;
    if (!can_view_user_relations($id_user))
        return (["responsible_for" => [], "responsible_by" => []]);

    $viewer = (int)$User["id"];
    $can_view_all = is_me($id_user) || is_admin() || can_manage_user_relations($id_user);
    $parent_filter = $can_view_all ? "" : " AND parent_child.id_child = $viewer ";
    $child_filter = $can_view_all ? "" : " AND parent_child.id_parent = $viewer ";

    $responsible_for = db_select_all("\n        user.id, user.codename, user.first_name, user.family_name, user.profile_status,\n        parent_child.relation, parent_child.id as id_relation\n        FROM parent_child\n        LEFT JOIN user ON user.id = parent_child.id_child\n        WHERE parent_child.id_parent = $id_user\n          AND user.id IS NOT NULL\n          AND user.authority != -1\n          $parent_filter\n        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC\n    ");
    $responsible_by = db_select_all("\n        user.id, user.codename, user.first_name, user.family_name, user.profile_status,\n        parent_child.relation, parent_child.id as id_relation\n        FROM parent_child\n        LEFT JOIN user ON user.id = parent_child.id_parent\n        WHERE parent_child.id_child = $id_user\n          AND user.id IS NOT NULL\n          AND user.authority != -1\n          $child_filter\n        ORDER BY user.family_name ASC, user.first_name ASC, user.codename ASC\n    ");

    return ([
        "responsible_for" => $responsible_for,
        "responsible_by" => $responsible_by,
    ]);
}

function user_relation_display_name($user)
{
    $name = trim((string)($user["first_name"] ?? "")." ".(string)($user["family_name"] ?? ""));
    if ($name == "")
        $name = (string)($user["codename"] ?? "");
    return ($name);
}
