<?php

// Si on est en heure d'été et que la date est d'hiver, alors on ajoute 1.
// Si on est en heure d'hiver et que la date est en heure d'été, alors on enlève 1.
// Sinon on ne fait rien

function hourshift($hour, $date = NULL, $shift = true)
{
    if ($hour == NULL)
	return (NULL);
    if (!is_number($hour))
	$hour = date_to_timestamp($hour);
    if ($date == NULL)
	$date = $hour; // A ne faire que si $hour est un vrai timestamp

    if (is_number($date))
	$datestring = datetime_local($date);
    else
	$datestring = $date;

    $nowoff = floor((new DateTime(datetime_local(time())))->getOffset() / (60 * 60));
    $dateoff = floor((new DateTime($datestring))->getOffset() / (60 * 60));
    if (($hourformat = ($hour >= 0 && $hour < 24)))
	$offset = 1;
    else
	$offset = 60 * 60;

    //// CECI EST LA FACON DONT CA AURAIT DU FONCTIONNER MAIS CA NE FONCTIONNE PAS
    // Si c'est l'été maintenant (+2)
    // et hiver la bas (+1)
    // Alors c'est pour ca que sans modification, on recule d'une heure
    // Il faut donc ajouter 1h. (2 - 1)

    // Si c'est l'hiver maintenant (+1)
    // et été la bas (+2)
    // Alors il faut enlever 1h (1 - 2)

    // Si on est en hiver et que c'est une date hiver ou que c'est été et que c'est
    // une date été, on ajoute 0 heures.

    //// RUSTINE
    if (($shift_length = $nowoff - $dateoff) == -1)
	$hour = $hour;
    else if ($shift_length == 0)
	$hour = $hour + $offset * ($shift ? 1 : -1);
    else if ($shift_length == 1) // CA, C'EST NON TESTE ET CA A L'AIR D'ETRE DU CACA
	$hour = $hour - $offset * ($shift ? 1 : -1);

    if ($hourformat)
	return ($hour);
    return (american_date($hour));
}

function hourunshift($hour, $date = NULL)
{
    return (hourshift($hour, $date, false));
}
