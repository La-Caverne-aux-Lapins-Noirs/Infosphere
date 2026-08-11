<?php
/**
 * Crée un nouvel utilisateur dans le système.
 *
 * Cette fonction gère l'inscription d'un utilisateur en validant les données fournies
 * (login, email, mot de passe), en vérifiant l'unicité du login et de l'email,
 * puis en enregistrant l'utilisateur en base de données avec un mot de passe sécurisé (hash + salt).
 *
 * Elle peut également :
 * - Générer automatiquement un mot de passe si non fourni
 * - Créer un utilisateur "fake" (sans validation ni hash) - utile si on enregistre un(e) prospect et non un(e) véritable étudiant(e)
 * - Initialiser un cookie de session
 * - Notifier un service externe via une requête
 * - Envoyer un email de confirmation
 *
 * @param string  $login    Identifiant de l'utilisateur (minimum 2 caractères)
 * @param string  $mail     Adresse email valide de l'utilisateur
 * @param string|null $password Mot de passe en clair (généré automatiquement si null et non fake)
 * @param bool    $cookie   Indique si un cookie de connexion doit être créé (true par défaut)
 * @param bool    $fake     Mode fake : bypass des validations et du hash (false par défaut)
 *
 * @return ValueResponse|ErrorResponse
 *         - ValueResponse : contient les données de l'utilisateur créé (sans infos sensibles)
 *         - ErrorResponse : en cas d'erreur (login invalide, email invalide, déjà utilisé, etc.)
 *
 * @throws Aucun explicitement, mais peut retourner des erreurs via ErrorResponse
 *
 * @notes
 * - Le mot de passe est stocké de manière sécurisée avec double salt et hash
 * - Les champs sensibles (password, salt) sont supprimés avant retour
 * - La fonction dépend de la variable globale $Database
 * - Des effets secondaires sont possibles (logs, cookies, email, appel externe)
 */

function build_user_password_material($password)
{
    if (@strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-zA-Z]+#", $password))
	return (new ErrorResponse("BadPassword"));

    $local_salt = openssl_random_pseudo_bytes(256);
    $salt = openssl_random_pseudo_bytes(256);
    if ($local_salt === false || $salt === false)
	return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore

    if (($cookiehash = hash_method($local_salt.$password)) == false)
	return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore
    if (($hash = hash_method($salt.$cookiehash)) == false)
	return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore

    return (new ValueResponse([
	"hash" => $hash,
	"salt" => base64_encode($salt),
	"local_salt" => base64_encode($local_salt),
	"cookiehash" => $cookiehash,
    ]));
}

function get_distrans_user_names($user)
{
    $first = trim(@$user["first_name"]);
    $last = trim(@$user["family_name"]);

    if ($first != "" && $last != "")
	return (["first" => $first, "last" => $last]);

    if (count($fl = explode(".", $user["codename"])) == 2)
	return (["first" => $fl[0], "last" => $fl[1]]);

    return (["first" => "First", "last" => "Last"]);
}

function create_distrans_user_payload($command, $user, $names, $password, $bddpassword)
{
    $payload = [
	"command" => $command,
	"id" => $user["id"],
	"first_name" => $names["first"],
	"last_name" => $names["last"],
	"mail" => $user["mail"],
	"password" => $password,
	"school" => "efrits"
    ];

    if ($command == "save_user")
    {
	$payload["login"] = $user["codename"];
	$payload["db_password"] = $bddpassword;
    }
    else
    {
	// Legacy InfosphereHand protocol. Kept as fallback only.
	$payload["user"] = $user["codename"];
	$payload["bddpassword"] = $bddpassword;
    }

    return ($payload);
}

function distrans_response_reason($out)
{
    if (!is_array($out))
	return ("");
    foreach (["message", "msg", "error", "content"] as $field)
	if (isset($out[$field]) && trim((string)$out[$field]) != "")
	    return (trim((string)$out[$field]));
    return ("");
}

function create_distrans_user($user, $password, $bddpassword, $required = false)
{
    global $Database;

    $names = get_distrans_user_names($user);

    // Distrans replaced the old InfosphereHand `newuser` command with the
    // idempotent `save_user` command.  The field names changed as well:
    //   old: user + bddpassword
    //   new: login + db_password
    $out = hand_request(create_distrans_user_payload(
	"save_user",
	$user,
	$names,
	$password,
	$bddpassword
    ));

    // Compatibility with a still-old InfosphereHand deployment. Current
    // Distrans should never need this path, but it makes the Infosphere side
    // less brittle during migration.
    $reason = distrans_response_reason($out);
    if (is_array($out)
	&& isset($out["result"])
	&& $out["result"] != "ok"
	&& strpos($reason, "UnknownCommand save_user") !== false)
    {
	$out = hand_request(create_distrans_user_payload(
	    "newuser",
	    $user,
	    $names,
	    $password,
	    $bddpassword
	));
    }

    if (!is_array($out) || (isset($out["result"]) && $out["result"] != "ok"))
    {
	if ($required)
	    return (new ErrorResponse("InfosphereHandDoesNotRun"));
	return (new ValueResponse(NULL));
    }

    if (isset($out["uid"]) && is_numeric($out["uid"]))
    {
	$id = (int)$user["id"];
	$uid = (int)$out["uid"];
	$Database->query("UPDATE user SET uid = $uid WHERE id = $id");
    }
    return (new ValueResponse($out));
}

function add_default_user_todolist($id_user)
{
    global $Database;
    global $Dictionnary;

    $id_user = (int)$id_user;
    if (db_select_one("id FROM user_todolist WHERE id_user = $id_user") != NULL)
	return (new Response);

    if ($Database->query("
          INSERT INTO user_todolist (id_user, content) VALUES
	  ($id_user, '".$Database->real_escape_string($Dictionnary["ThisIsATodoList"])."'),
	  ($id_user, '".$Database->real_escape_string($Dictionnary["YouCanWriteAnything"])."')
    ") == false)
	return (new ErrorResponse("CannotRegister"));
    return (new Response);
}

function transform_prospect_school_context($id)
{
    $id = (int)$id;
    $links = db_select_all("
        DISTINCT user_school.id_school as id_school
        FROM user_school
        LEFT JOIN school ON school.id = user_school.id_school
        WHERE user_school.id_user = $id
          AND school.id IS NOT NULL
          AND school.deleted IS NULL
        ORDER BY user_school.id_school ASC
    ");
    if (count($links))
        return (new ValueResponse([
            "school_ids" => array_map(
                fn($school) => (int)$school["id_school"],
                $links
            ),
            "inferred" => false,
        ]));

    // Plusieurs anciennes fiches prospect n'ont jamais reçu leur lien user_school :
    // le formulaire passait jadis deux chaînes vides à handle_linksf(). Dans une
    // installation ne contenant qu'une école, le rattachement attendu est sans
    // ambiguïté et peut être réparé lors de la transformation.
    $schools = db_select_all("
        id
        FROM school
        WHERE deleted IS NULL
        ORDER BY id ASC
    ");
    if (!count($schools))
        return (new ErrorResponse("NoSchool"));
    if (count($schools) != 1)
        return (new ErrorResponse("MissingField", "school"));

    return (new ValueResponse([
        "school_ids" => [(int)$schools[0]["id"]],
        "inferred" => true,
    ]));
}

function transform_prospect_legacy_assignments()
{
    $assignments = [];
    if (in_array("prospect", db_select_rows("user"), true))
        $assignments[] = "`prospect` = 0";
    return ($assignments);
}

function transform_prospect($id)
{
    global $Database;
    global $Dictionnary;

    $id = (int)$id;
    if ($id == -1)
	bad_request();

    $user = db_select_one("*
	FROM user
	WHERE id = $id
	AND password = ''
	AND profile_status = 'prospect'
    ");
    if ($user == NULL)
	return (new ErrorResponse("UserNotFound"));

    $password = generate_password();
    $bddpassword = generate_password();
    if (($material = build_user_password_material($password))->is_error())
	return ($material);
    $material = $material->value;

    if (($school_context = transform_prospect_school_context($id))->is_error())
	return ($school_context);
    $school_context = $school_context->value;

    if (($request = create_distrans_user($user, $password, $bddpassword, true))->is_error())
	return ($request);

    $hash = $Database->real_escape_string($material["hash"]);
    $salt = $Database->real_escape_string($material["salt"]);
    $local_salt = $Database->real_escape_string($material["local_salt"]);
    if ($Database->query("START TRANSACTION") === NULL)
	return (new ErrorResponse("CannotUpdate")); // @codeCoverageIgnore
    $assignments = [
        "password = '$hash'",
        "salt = '$salt'",
        "local_salt = '$local_salt'",
        "profile_status = 'member'",
        "deleted = NULL",
        "cache = '{}'",
    ];
    $assignments = array_merge($assignments, transform_prospect_legacy_assignments());
    if ($Database->query("
	UPDATE user
	SET ".implode(",\n            ", $assignments)."
	WHERE id = $id
	AND password = ''
	AND profile_status = 'prospect'
    ") === NULL)
    {
	$Database->query("ROLLBACK");
	return (new ErrorResponse("CannotUpdate")); // @codeCoverageIgnore
    }
    if ($Database->affected_rows != 1)
    {
	$Database->query("ROLLBACK");
	return (new ErrorResponse("UserNotFound"));
    }

    // Un prospect rattaché à une école devient un élève lors de son activation.
    // Les anciennes valeurs NULL ou les rôles provisoires ne doivent pas le laisser
    // absent des listes d'élèves après la transformation.
    $student_authority = user_school_student_authority_sql();
    if ($school_context["inferred"])
    {
        $id_school = (int)$school_context["school_ids"][0];
        if ($Database->query("
            INSERT INTO user_school (id_user, id_school, authority)
            VALUES ($id, $id_school, $student_authority)
        ") === NULL)
        {
            $Database->query("ROLLBACK");
            return (new ErrorResponse("CannotUpdate")); // @codeCoverageIgnore
        }
    }
    else if ($Database->query("
	UPDATE user_school
	SET authority = $student_authority
	WHERE id_user = $id
    ") === NULL)
    {
	$Database->query("ROLLBACK");
	return (new ErrorResponse("CannotUpdate")); // @codeCoverageIgnore
    }

    // Les invitations d'inscription encore actives n'ont plus de sens une fois
    // le compte activé. Le mécanisme user_form est désormais générique : les
    // formulaires administratifs ou documentaires ne doivent pas être révoqués.
    if (in_array("user_form", db_get_tables(), true)
        && $Database->query("
            UPDATE user_form
            SET revoked_at = NOW()
            WHERE id_user = $id
              AND kind IN ('ECL', 'OF', 'OFA', 'CFA')
              AND completed_at IS NULL
              AND revoked_at IS NULL
        ") === NULL)
    {
        $Database->query("ROLLBACK");
        return (new ErrorResponse("CannotUpdate")); // @codeCoverageIgnore
    }

    $converted = db_select_one("
        user.id
        FROM user
        LEFT JOIN user_school ON user_school.id_user = user.id
        WHERE user.id = $id
          AND user.password != ''
          AND user.profile_status = 'member'
          AND user.deleted IS NULL
          AND user_school.id IS NOT NULL
          AND user_school.authority = $student_authority
    ");
    if ($converted == NULL)
    {
        $Database->query("ROLLBACK");
        return (new ErrorResponse("CannotUpdate"));
    }

    if ($Database->query("COMMIT") === NULL)
    {
	$Database->query("ROLLBACK");
	return (new ErrorResponse("CannotUpdate")); // @codeCoverageIgnore
    }

    if (($request = add_default_user_todolist($id))->is_error())
	return ($request);
    refresh_user($id);
    send_subscribe_mail($user["id"], $user["codename"], $user["mail"], $password, $bddpassword);
    add_log(CRITICAL_USER_DATA, "Prospect ".$user["codename"]." transformed into user", $user["id"]);

    return (new ValueResponse([
	"msg" => $Dictionnary["ProspectTransformed"],
    ]));
}

function subscribe($login, $mail, $password = NULL, $cookie = true, $fake = false, $profile_status = NULL)
{
    global $Database;

    if ($profile_status === NULL)
	$profile_status = $fake ? "prospect" : "member";
    $profile_status = user_profile_status($profile_status);
    if (user_profile_status_is_fake($profile_status))
	$fake = true;
    if ($password == NULL && $fake == false)
	$password = generate_password();
    $bddpassword = generate_password();
    if (@strlen($login) < 2)
	return (new ErrorResponse("BadLogin", $login));

    if ($fake == false)
    {
	if (($material = build_user_password_material($password))->is_error())
	    return ($material);
	$material = $material->value;
	$hash = $material["hash"];
	$salt = $material["salt"];
	$local_salt = $material["local_salt"];
	$cookiehash = $material["cookiehash"];
    }
    else
    {
	$hash = "";
	$salt = "";
	$local_salt = "";
    }

    if (filter_var($mail, FILTER_VALIDATE_EMAIL) == false)
	return (new ErrorResponse("BadMail", $mail));

    if (!INSTALLATION)
	add_log(TRACE, "User $login is trying to subscribe", 0);
    $login = $Database->real_escape_string($login);
    $mail = $Database->real_escape_string($mail);
    $user_query = $Database->query("
      SELECT codename, mail
      FROM user
      WHERE codename = '$login' OR mail = '$mail'
    ");    
    if (($usr = $user_query->fetch_assoc()) != NULL)
    {
	if ($usr["codename"] == $login && $usr["mail"] == $mail)
	    return (new ErrorResponse("LoginAndMailUsed", $login." ".$mail));
	if ($usr["codename"] == $login)
	    return (new ErrorResponse("LoginUsed", $login));
	return (new ErrorResponse("MailUsed", $mail));
    }

    $profile_status_sql = $Database->real_escape_string($profile_status);
    if (($Database->query("
      INSERT INTO user (codename, password, registration_date, salt, local_salt, mail, profile_status)
      VALUES ('$login', '$hash', NOW(), '$salt', '$local_salt', '$mail', '$profile_status_sql')
    ")) == false)
    {
	if (!INSTALLATION)
	    add_log(TRACE, "Insertion failed.", 0); // @codeCoverageIgnore
	return (new ErrorResponse("CannotRegister")); // @codeCoverageIgnore
    }
    $new_user_id = $Database->insert_id;

    $user_query = $Database->query("SELECT * FROM user WHERE id = '$new_user_id'");
    $usr = $user_query->fetch_assoc();

    if ($fake == false)
    {
	create_distrans_user($usr, $password, $bddpassword, false);
	$user_query = $Database->query("SELECT * FROM user WHERE id = '$new_user_id'");
	$usr = $user_query->fetch_assoc();
    }

    if (!UNIT_TEST && $cookie && $fake == false)
    {
	set_cookie("login", $login, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
	set_cookie("password", $cookiehash, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
    }

    unset($usr["salt"]);
    unset($usr["local_salt"]);
    unset($usr["password"]);
    if (!INSTALLATION && $fake == false)
    {
	send_subscribe_mail($usr["id"], $login, $mail, $password, $bddpassword);
	add_log(CRITICAL_USER_DATA, "User ".$usr["codename"]." added", $usr["id"]);
    }
    return (new ValueResponse($usr));
}
