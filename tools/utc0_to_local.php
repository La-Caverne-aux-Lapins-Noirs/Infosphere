<?php
define("TO_DB", false);
define("FROM_DB", true);

// Tout dans la base de donnée est en UTC+0
function get_timezone_offset($remote_tz)
{
    $origin_dtz = new DateTimeZone("Etc/UTC"); // UTC+0
    $remote_dtz = new DateTimeZone($remote_tz); // UTC+X
    $origin_dt = new DateTime("now", $origin_dtz);
    $remote_dt = new DateTime("now", $remote_dtz); // Prend automatiquement l'heure d'été/d'hiver a partir de remote_dtz

    // On récupère le décalage horaire a appliquer
    return ($origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt));
}

// Tout dans la BDD est en UTC+0
// Et lorsqu'on veut afficher un element depuis la BDD, alors on le place dans l'UTC local
// L'UTC local dépend de l'endroit mais également de la date (heure d'été, heure d'hiver)
function utc0_to_local($direction, $dt)
{
    global $Localisation;

    if ($direction != TO_DB && $direction != FROM_DB)
    return (NULL);

    // On convertit en int si on a un format date texte
    if (($text = !is_number($dt)))
    $dt = date_to_timestamp($dt);

    $off = get_timezone_offset($Localisation);
    // Si on prend depuis la BDD, alors on ajoute le décalage horaire
    // Si on met dans la BDD, alors on enlève le décalage horaire
    $dt = $dt + ($direction == FROM_DB ? +$off : -$off);

    // On remet dans le format donné en paramètre
    if ($text)
    $dt = db_form_date($dt);

    // On renvoi le resultat
    return $dt;
    #return $res;
}