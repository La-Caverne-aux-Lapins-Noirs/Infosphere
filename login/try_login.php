<?php

// On cherche à modifier son status de connexion
if (isset($_POST["logaction"]))
{
    // On veut se connecter
    if ($_POST["logaction"] == "login")
	$Msg = get_login_info($_POST["login"], $_POST["password"]);
    // On veut se deconnecter
    else if ($_POST["logaction"] == "logout")
    {
	$Msg = new Response;
	set_cookie("login", "", time() + 1);
	set_cookie("password", "", time() + 1);
    }
    // On veut s'inscrire
    else if ($_POST["logaction"] == "subscribe")
    {
	if (($Msg = try_subscribe(
	    $_POST["login"], $_POST["mail"], $_POST["password"], $_POST["repassword"])
	)->is_error())
	// Si l'inscription a échoué, on retourne a la page d'inscription.
	{
	    $PreviousPosition = $Position;
	    $Position = "Subscribe";
	}
    }
}
// Peut-être qu'on est déjà connecté?
else if (isset($_COOKIE["login"]) && isset($_COOKIE["password"]))
    $Msg = get_login_info($_COOKIE["login"], $_COOKIE["password"], false);
// On est pas connecté
else
    $Msg = new ErrorResponse();
$User = NULL;
$ErrorMsg = "";
$LogMsg = "";
if ($Msg->is_error())
    $ErrorMsg = strval($Msg);
else
    $User = $Msg->value;
