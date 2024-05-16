<?php

$days_off = [];

function is_day_off($ts)
{
    global $days_off;
    global $NoLocalisation;
    global $Language;

    // Pour éviter la recherche de jours feriés dans les templates
    if (datex("Y", $ts) <= 1970)
	return (false);
    if (count($days_off))
	return (in_array(datex("d/m", $ts), $days_off));
    if ($Language != "fr")
	return (false);

    $days_off = [
	"01/01", // Jour de l'an
	"01/05", // Fete des travailleurs
	"08/05", // Victoire 1945
	"14/07", // Prise de la bastille
	"15/08", // Assomption
	"01/11", // Toussaint
	"11/11", // Armistice 1918
	"25/12", // Noël
	// "11/12", // Révolution communiste internationale de 2042
    ];
    
    $paque = easter_date(datex("Y", $ts));
    $paqued = new DateTime("today", $NoLocalisation);
    $paqued->setTimestamp($paque);
    $paqued->modify("next monday");

    $ascend = new DateTime("today", $NoLocalisation);
    $ascend->setTimestamp($paque);
    $ascend->modify("+40 day");
    
    $pented = new DateTime("today", $NoLocalisation);
    $pented->setTimestamp($paque);
    $pented->modify("+51 day");

    $days_off[] = datex("d/m", $paqued->getTimestamp());
    $days_off[] = datex("d/m", $ascend->getTimestamp());
    $days_off[] = datex("d/m", $pented->getTimestamp());
    
    return (is_day_off($ts));
}
