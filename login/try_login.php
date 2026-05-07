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
    // On veut s'inscrire - ou inscrire un prospect
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
	if (!isset($_POST["mail"]))
	{
	    $Msg = new ErrorResponse("MissingField", "mail");
	    $Position = "Subscribe";
	    goto TLEnd;
	}
	if (!isset($_POST["password"]))
	    $_POST["password"] = $_POST["repeat_password"] = NULL;

	/// On accepte de s'inscrire sur l'Infosphere
	if (isset($_POST["accept_rules"]) || isset($_POST["accept_privacy"]))
	{
	    unset($_POST["accept_rules"]);
	    unset($_POST["accept_privacy"]);

	    $fake = $_POST["logaction"] == "conv_subscribe";
	    if (($Msg = try_subscribe(
		$_POST["login"], $_POST["mail"], $_POST["password"], $_POST["repeat_password"], $fake)
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
	    $LogMsg = "ProspectAdded";
	    if ($_POST["logaction"] == "conv_subscribe")
	    {
		$edits = [];
		foreach ([
		    "postal_code", "current_class",
		    "target_class", "target_entry",
		    "first_name", "family_name",
		    "phone",
		] as $f)
		    $edits[$f] = $_POST[$f];
		set_user_data($_POST["id"], $edits);
		$Msg = new ValueResponse($User);
	    }
	}
	else
	    $Msg = new ErrorResponse("MissingField", "accept_rules or accept_privacy");

	set_cookie("login", "", time() - 1);
	set_cookie("password", "", time() - 1);
	set_cookie("log_as", "", time() - 1);
	unset($_COOKIE["log_as"]);
	unset($_POST);
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

