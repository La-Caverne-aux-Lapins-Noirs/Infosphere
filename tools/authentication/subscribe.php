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

function subscribe($login, $mail, $password = NULL, $cookie = true, $fake = false)
{
    global $Database;

    if ($password == NULL && $fake == false)
	$password = generate_password();
    $bddpassword = generate_password();
    if (@strlen($login) < 2)
	return (new ErrorResponse("BadLogin", $login));
    if ($fake == false)
    {
	if (@strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-zA-Z]+#", $password))
	    return (new ErrorResponse("BadPassword"));
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

    if ($fake == false)
    {
	$local_salt = openssl_random_pseudo_bytes(256);
	$salt = openssl_random_pseudo_bytes(256);

	if (($cookiehash = hash_method($local_salt.$password)) == false)
	    return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore
	if (($hash = hash_method($salt.$cookiehash)) == false)
	    return (new ErrorResponse("CannotHash")); // @codeCoverageIgnore

	$salt = base64_encode($salt);
	$local_salt = base64_encode($local_salt);
    }
    else
    {
	$hash = "";
	$salt = "";
	$local_salt = "";
    }
    

    if (($Database->query("
      INSERT INTO user (codename, password, registration_date, salt, local_salt, mail)
      VALUES ('$login', '$hash', NOW(), '$salt', '$local_salt', '$mail')
    ")) == false)
    {
	if (!INSTALLATION)
	    add_log(TRACE, "Insertion failed.", 0); // @codeCoverageIgnore
	return (new ErrorResponse("CannotRegister")); // @codeCoverageIgnore
    }
    $new_user_id = $Database->insert_id;

    if ($fake == false)
    {
	if (count($fl = explode(".", $login)) == 2)
	{
	    $first = $fl[0];
	    $last = $fl[1];
	}
	else
	{
	    $first = "First";
	    $last = "Last";
	}
    
	$out = hand_request([
	    "command" => "newuser",
	    "user" => $login,
	    "id" => $new_user_id,
	    "first_name" => $first,
	    "last_name" => $last,
	    "mail" => $mail,
	    "password" => $password,
	    "bddpassword" => $bddpassword,
	    "school" => "efrits"
	]);
	if ($out != NULL)
	    $Database->query("UPDATE user SET uid = ".$out["uid"]." WHERE codename = '$login'");
    }
    
    if (!UNIT_TEST && $cookie && $fake == false)
    {
	set_cookie("login", $login, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
	set_cookie("password", $cookiehash, time() + 365 * 24 * 60 * 60); // @codeCoverageIgnore
    }

    $user_query = $Database->query("SELECT * FROM user WHERE id = '$new_user_id'");
    $usr = $user_query->fetch_assoc();
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
