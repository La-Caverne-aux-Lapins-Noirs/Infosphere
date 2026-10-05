<?php

/*
** Espaces de fichiers d'un utilisateur
** ------------------------------------
**
** La racine dres/users/<codename>/ est uniquement un conteneur.
**
**   public/   : fichiers publiquement lisibles
**   admin/    : fichiers administratifs
**   perso/    : fichiers privés du titulaire du compte
**   subjects/ : sujets personnalisés de l'élève, lisibles par lui et ses professeurs
**
** $User peut être remplacé par "log as". Pour toute décision concernant
** perso/, l'identité de session réelle est donc $OriginalUser.
*/

function user_storage_actor()
{
    global $OriginalUser;
    global $User;

    if (isset($OriginalUser) && is_array($OriginalUser))
        return ($OriginalUser);
    if (isset($User) && is_array($User))
        return ($User);
    return (NULL);
}

function user_storage_actor_id()
{
    $actor = user_storage_actor();

    if ($actor == NULL || !isset($actor["id"]))
        return (0);
    return ((int)$actor["id"]);
}

/*
** Identité actuellement incarnée. Contrairement à user_storage_actor_id(),
** celle-ci suit $User pendant un "log as". Les droits administratifs d'une
** école doivent être évalués avec cette identité, sinon un administrateur
** connecté en tant qu'élève conserve ses droits sur admin/.
*/
function user_storage_current_user_id()
{
    global $User;

    if (!isset($User) || !is_array($User) || !isset($User["id"]))
        return (0);
    return ((int)$User["id"]);
}

function user_storage_current_user_is_admin()
{
    $id = user_storage_current_user_id();

    if ($id <= 0)
        return (false);

    // On conserve le comportement de secours historique du compte #1.
    if ($id == 1)
        return (true);

    // Sans argument, is_admin() porte bien sur le $User courant.
    return (function_exists("is_admin") && is_admin());
}

function user_storage_configuration_bool($name, $default = false)
{
    global $Configuration;

    if (!isset($Configuration) || !is_object($Configuration)
        || !isset($Configuration->Properties[$name]))
        return (!!$default);

    $value = $Configuration->Properties[$name];
    if (is_bool($value))
        return ($value);
    $value = strtolower(trim((string)$value));
    return ($value != "" && $value != "0" && $value != "false"
            && $value != "no" && $value != "off" && $value != "null");
}

function user_storage_actor_is_admin()
{
    $id = user_storage_actor_id();

    if ($id <= 0)
        return (false);

    // Albedo reste le compte de secours même si admin_mode est désactivé.
    if ($id == 1)
        return (true);

    // is_admin($id) recharge le compte réel et tient compte du cookie
    // admin_mode. C'est nécessaire pendant un "log as", car $User désigne
    // alors le compte emprunté.
    return (function_exists("is_admin") && is_admin($id));
}

function user_storage_admin_personal_access_enabled()
{
    return (user_storage_configuration_bool(
        "admin_personal_storage_access",
        true
    ));
}

function user_storage_actor_is_owner($id_user)
{
    return (user_storage_actor_id() > 0
            && user_storage_actor_id() == (int)$id_user);
}

function user_storage_actor_has_student_school_role($id_user, $role)
{
    // Les rôles d'école suivent l'identité actuellement incarnée.
    $actor_id = user_storage_current_user_id();
    if ($actor_id <= 0)
        return (false);

    foreach (user_school_ids((int)$id_user, "STUDENT") as $id_school)
        if (user_has_school_authority($actor_id, $role, $id_school))
            return (true);
    return (false);
}

function user_storage_actor_has_school_staff_role_for_user($id_user)
{
    // Même règle ici : un "log as" élève ne doit pas conserver les rôles
    // d'école de l'administrateur réel.
    $actor_id = user_storage_current_user_id();
    if ($actor_id <= 0)
        return (false);

    /*
    ** Le dossier admin appartient à l'établissement, pas à un rôle précis.
    ** Dès lors que l'acteur et l'utilisateur cible appartiennent à une même
    ** école active, n'importe quel rôle autre que STUDENT donne accès.
    **
    ** Cela couvre notamment DIRECTOR, SECRETARIAT, COMMERCIAL, TEACHER,
    ** LIBRARIAN et ACCOUNTANT, tout en empêchant un élève d'obtenir cet accès
    ** simplement parce qu'il partage l'école avec l'utilisateur cible.
    */
    foreach (user_school_ids((int)$id_user) as $id_school)
    {
        $schools = user_school_authorities($actor_id, (int)$id_school);
        if (!isset($schools[(int)$id_school]))
            continue ;
        foreach ($schools[(int)$id_school] as $authority => $enabled)
            if ($enabled && normalize_school_authority($authority) != "STUDENT")
                return (true);
    }
    return (false);
}

function user_storage_can_manage_admin_space($id_user)
{
    // admin/ suit le compte actuellement incarné, pas $OriginalUser.
    if (user_storage_current_user_is_admin())
        return (true);

    return (user_storage_actor_has_school_staff_role_for_user(
        (int)$id_user
    ));
}

function user_storage_can_read_admin_path($id_user, $path)
{
    if (user_storage_can_manage_admin_space($id_user))
        return (true);

    $path = user_storage_normalize_path($path);
    if ($path === NULL)
        return (false);

    // Compatibilité avec les usages historiques du bouncer.
    if ($path === "admin/photo.png")
        return (logged_in());

    if (in_array($path, ["admin/contract.pdf", "admin/contract.dab"], true))
        return (user_storage_actor_has_student_school_role(
            (int)$id_user,
            "COMMERCIAL"
        ));

    return (false);
}

function user_storage_can_access_personal_space($id_user)
{
    if (user_storage_actor_is_owner($id_user))
        return (true);

    // Le compte #1 reste toujours un accès de secours.
    if (user_storage_actor_id() == 1)
        return (true);

    return (user_storage_admin_personal_access_enabled()
            && user_storage_actor_is_admin());
}

function user_storage_subject_activity($codename)
{
    global $Database;

    $codename = $Database->real_escape_string((string)$codename);
    return (db_select_one("
        id, reference_activity
        FROM activity
        WHERE codename = '$codename'
          AND deleted IS NULL
    "));
}

function user_storage_student_has_activity($id_user, array $activity)
{
    $id_user = (int)$id_user;
    $id_activity = (int)($activity["id"] ?? 0);
    $team_activity = (int)($activity["reference_activity"] ?? 0);
    if ($team_activity <= 0)
        $team_activity = $id_activity;
    if ($id_user <= 0 || $id_activity <= 0 || $team_activity <= 0)
        return (false);

    return (db_select_one("
        user_team.id
        FROM user_team
        LEFT JOIN team ON team.id = user_team.id_team
        WHERE user_team.id_user = $id_user
          AND user_team.status > 0
          AND team.id_activity = $team_activity
        LIMIT 1
    ") != NULL);
}

function user_storage_actor_can_read_student_subject_activity($id_user, array $activity)
{
    if (!user_storage_student_has_activity($id_user, $activity))
        return (false);

    $id_activity = (int)$activity["id"];
    return (is_teacher_or_director_for_activity($id_activity)
            || is_assistant_for_activity($id_activity));
}

function user_storage_actor_can_read_any_student_subject($id_user)
{
    $id_user = (int)$id_user;
    if ($id_user <= 0)
        return (false);

    foreach (db_select_all("
        DISTINCT activity.id, activity.reference_activity
        FROM user_team
        LEFT JOIN team ON team.id = user_team.id_team
        LEFT JOIN activity
          ON activity.id = team.id_activity
          OR activity.reference_activity = team.id_activity
        WHERE user_team.id_user = $id_user
          AND user_team.status > 0
          AND activity.id IS NOT NULL
          AND activity.deleted IS NULL
    ") as $activity)
        if (user_storage_actor_can_read_student_subject_activity($id_user, $activity))
            return (true);
    return (false);
}

function user_storage_can_read_subject_path($id_user, $path)
{
    $path = user_storage_normalize_path($path);
    if ($path === NULL)
        return (false);
    if (user_storage_actor_is_owner((int)$id_user))
        return (true);

    $parts = explode("/", $path);
    if (($parts[0] ?? "") !== "subjects")
        return (false);

    if (!isset($parts[1]) || $parts[1] == "")
        return (user_storage_actor_can_read_any_student_subject((int)$id_user));

    $activity = user_storage_subject_activity($parts[1]);
    if (!is_array($activity))
        return (false);
    return (user_storage_actor_can_read_student_subject_activity(
        (int)$id_user,
        $activity
    ));
}

function user_storage_normalize_path($path)
{
    if (!is_string($path) && !is_numeric($path))
        return (NULL);

    $path = trim(str_replace("\\", "/", (string)$path));
    if (strpos($path, "\0") !== false)
        return (NULL);
    $path = trim($path, "/");
    if ($path == "")
        return ("");

    $out = [];
    foreach (explode("/", $path) as $part)
    {
        if ($part == "" || $part == ".")
            continue ;
        if ($part == "..")
            return (NULL);
        $out[] = $part;
    }
    return (implode("/", $out));
}

function user_storage_path_space($path)
{
    $path = user_storage_normalize_path($path);
    if ($path === NULL || $path === "")
        return ($path);

    $parts = explode("/", $path, 2);
    return ($parts[0]);
}

function user_storage_is_root_space($path)
{
    $path = user_storage_normalize_path($path);

    return ($path !== NULL && in_array($path, [
        "admin", "public", "perso", "subjects"
    ], true));
}

function user_storage_can_read_path($id_user, $path)
{
    $space = user_storage_path_space($path);
    if ($space === NULL)
        return (false);

    // La racine est un conteneur : elle peut être parcourue, mais aucun
    // fichier directement posé dedans n'est considéré comme autorisé.
    if ($space === "")
        return (logged_in());

    // "public" signifie réellement public. Le bouncer peut donc le servir
    // même sans session.
    if ($space === "public")
        return (true);

    if ($space === "admin")
        return (user_storage_can_read_admin_path((int)$id_user, $path));

    if ($space === "perso")
        return (user_storage_can_access_personal_space((int)$id_user));

    if ($space === "subjects")
        return (user_storage_can_read_subject_path((int)$id_user, $path));

    // Tout ancien fichier directement placé à la racine est refusé. La
    // migration le déplacera vers perso/.
    return (false);
}

function user_storage_can_write_path($id_user, $path)
{
    $space = user_storage_path_space($path);
    if ($space === NULL || $space === "")
        return (false);

    if ($space === "perso")
        return (user_storage_can_access_personal_space((int)$id_user));

    if ($space === "admin")
        return (user_storage_can_manage_admin_space((int)$id_user));

    // subjects/ est produit exclusivement par le générateur de sujets.
    if ($space === "subjects")
        return (false);

    if ($space === "public")
        return (user_storage_actor_is_owner((int)$id_user)
                || user_storage_can_manage_admin_space((int)$id_user));

    return (false);
}

function user_storage_can_write_something($id_user)
{
    return (user_storage_actor_is_owner((int)$id_user)
            || user_storage_can_manage_admin_space((int)$id_user)
            || user_storage_can_access_personal_space((int)$id_user));
}

function user_storage_default_browse_space($id_user)
{
    if (user_storage_actor_is_owner((int)$id_user))
        return ("perso");
    if (user_storage_can_manage_admin_space((int)$id_user))
        return ("admin");
    return ("public");
}

function user_storage_absolute_is_inside_root($absolute)
{
    global $Configuration;

    if (!isset($Configuration) || !is_object($Configuration))
        return (false);

    $root = realpath($Configuration->UsersDir());
    $absolute = realpath($absolute);
    if ($root === false || $absolute === false)
        return (false);

    $root = rtrim($root, DIRECTORY_SEPARATOR);
    return ($absolute === $root
            || strncmp(
                $absolute,
                $root.DIRECTORY_SEPARATOR,
                strlen($root) + 1
            ) == 0);
}

function user_storage_absolute_context($absolute)
{
    global $Configuration;
    global $Database;

    if (!isset($Configuration) || !is_object($Configuration))
        return (NULL);

    $root = realpath($Configuration->UsersDir());
    $absolute = realpath($absolute);
    if ($root === false || $absolute === false)
        return (NULL);

    $root = rtrim($root, DIRECTORY_SEPARATOR);
    if ($absolute !== $root
        && strncmp($absolute, $root.DIRECTORY_SEPARATOR, strlen($root) + 1) != 0)
        return (NULL);
    if ($absolute === $root)
        return (NULL);

    $relative = str_replace(
        DIRECTORY_SEPARATOR,
        "/",
        substr($absolute, strlen($root) + 1)
    );
    $parts = explode("/", $relative, 2);
    if (!isset($parts[0]) || trim((string)$parts[0]) == "")
        return (NULL);

    $codename = $Database->real_escape_string($parts[0]);
    $user = db_select_one("id FROM user WHERE codename = '$codename'");
    if ($user == NULL)
        return (NULL);

    return ([
        "id_user" => (int)$user["id"],
        "path" => isset($parts[1]) ? $parts[1] : ""
    ]);
}

function user_storage_can_access_absolute($absolute, $write = false)
{
    $context = user_storage_absolute_context($absolute);
    if ($context == NULL)
        return (!user_storage_absolute_is_inside_root($absolute));

    if ($write)
        return (user_storage_can_write_path(
            $context["id_user"],
            $context["path"]
        ));

    return (user_storage_can_read_path(
        $context["id_user"],
        $context["path"]
    ));
}
