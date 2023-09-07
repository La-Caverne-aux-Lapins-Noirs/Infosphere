<?php
$Convention = false;

// On cherche à modifier son status de connexion
if (isset($_POST["logaction"]))
{
    // On veut se connecter
    if ($_POST["logaction"] == "login")
    {
	$Msg = get_login_info($_POST["login"], $_POST["password"]);
	unset($_COOKIE["log_as"]);
    }
    // On veut se deconnecter
    else if ($_POST["logaction"] == "logout")
    {
	$Msg = new Response;
	set_cookie("login", "", time() - 1);
	set_cookie("password", "", time() - 1);
	set_cookie("log_as", "", time() - 1);
	unset($_COOKIE["log_as"]);
    }
    // On veut s'inscrire
    else if ($_POST["logaction"] == "subscribe" || $_POST["logaction"] == "conv_subscribe")
    {
	if (!isset($_POST["login"]))
	{
	    if (!isset($_POST["first_name"]) || !isset($_POST["family_name"]))
	    {
		$Msg = new ErrorResponse("MissingField", "first_name, family_name");
		$Position = "Subscribe";
		goto TLEnd;
	    }
	    else
	    {
		$first_name = convert_to_codename($_POST["first_name"]);
		$family_name =  convert_to_codename($_POST["family_name"]);
		$_POST["login"] = "$first_name.$family_name";
	    }
	}
	if (!isset($_POST["mail"]) || !isset($_POST["repeat_mail"]))
	{
	    $Msg = new ErrorResponse("MissingField", "mail");
	    $Position = "Subscribe";
	    goto TLEnd;
	}
	else if ($_POST["mail"] != $_POST["repeat_mail"])
	{
	    $Msg = new ErrorResponse("InvalidMailRepeat");
	    $Position = "Subscribe";
	    goto TLEnd;
	}
	unset($_POST["repeat_mail"]);

	if (!isset($_POST["password"]))
	    $_POST["password"] = $_POST["repeat_password"] = NULL;

	/// On accepte de s'inscrire sur l'Infosphere
	if (isset($_POST["accept_rules"]))
	{
	    unset($_POST["accept_rules"]);
	    if (($Msg = try_subscribe(
		$_POST["login"], $_POST["mail"], $_POST["password"], $_POST["repeat_password"])
	    )->is_error())
	    // Si l'inscription a échoué, on retourne a la page d'inscription.
	    {
		$PreviousPosition = $Position;
		$Position = "Subscribe";
		goto TLEnd;
	    }
	    else // Sinon on va enrichir POST avec l'id obtenu
		$_POST["id"] = $Msg->value["id"];
	    set_cookie("log_as", "", time() - 1);
	    unset($_COOKIE["log_as"]);
	}
	else if ($_POST["logaction"] == "subscribe")
	    $Msg = new ErrorResponse("MissingField", "accept_rules");

	if (isset($_POST["accept_privacy"]) && $_POST["logaction"] == "conv_subscribe")
	{
	    $_POST["codename"] = $_POST["login"];
	    unset($_POST["login"]);
	    unset($_POST["accept_privacy"]);
	    unset($_POST["logaction"]);
	    unset($_POST["repeat_password"]);
	    if (($Msg = set_user_data(-1, $_POST, [], true, true))->is_error())
	    {
		$PreviousPosition = $Position;
		$Position = "Subscribe";
		goto TLEnd;
	    }
	    else
	    {
		$Convention = true;
		$LogMsg = "DataStored";
		$_POST = [];
	    }
	}
	else
	    $Msg = new ErrorResponse("MissingField", "accept_privacy");
	set_cookie("login", "", time() - 1);
	set_cookie("password", "", time() - 1);
	set_cookie("log_as", "", time() - 1);
	unset($_COOKIE["log_as"]);
    }
}
// Peut-être qu'on est déjà connecté?
else if (isset($_COOKIE["login"]) && isset($_COOKIE["password"]))
    $Msg = get_login_info($_COOKIE["login"], $_COOKIE["password"], false);
// On est pas connecté
else
    $Msg = new ErrorResponse();
if ($Convention)
    return ;
TLEnd:
$User = NULL;
$ErrorMsg = "";
$LogMsg = "";
if ($Msg->is_error())
    $ErrorMsg = strval($Msg);
else
    $User = $Msg->value;
