<?php
// Est-t-on connecté, tentons nous de nous connecter, de nous inscrire ou de partir?
require_once ("try_login.php");
// Gestion de la capture d'un compte utilisateur par un administrateur (A PROGRAMMER: Ou un prof)
require_once ("try_log_as.php");
// Log as des parents
require_once ("try_log_as_parent.php");
// Influence de cette connexion sur les temps de log de l'utilisateur
compute_student_log();
// Implémentation du mode administrateur
require_once ("try_log_as_admin.php");

