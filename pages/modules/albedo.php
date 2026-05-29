<?php
if (!isset($albedo) || $albedo != 1)
    return ;

///////////////////////////////////////////////////////
// INSCRIPTIONS AUTOMATIQUES AUX MATIERES/ACTIVITES //
///////////////////////////////////////////////////////

// Albedo ne doit pas refaire toute la base à chaque passage. On répare donc
// un petit nombre d'inscriptions manquantes par exécution. Les inscriptions
// immédiates restent déclenchées par l'ajout au cycle ou l'inscription à une
// matière; ce passage sert surtout de filet de sécurité.
$ret = automatic_subscription_repair(100);
if ($ret->is_error())
    add_log(WARNING, "Automatic subscription repair failed: ".strval($ret), 1);
else if ($ret->value["done"] > 0 || count($ret->value["errors"]) > 0)
{
    add_log(
	TRACE,
	"Automatic subscription repair: ".
	$ret->value["done"]." subscription(s), ".
	count($ret->value["errors"])." error(s)",
	1
    );
}

?>
