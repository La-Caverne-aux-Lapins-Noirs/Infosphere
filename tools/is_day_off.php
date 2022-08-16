<?php

function is_day_off($ts)
{
    global $NoLocalisation;
    global $Language;

    if ($Language == "fr")
    {
	if (datex("d/m", $ts) == "01/01")
	    return (true);
	$paque = easter_date(datex("Y", $ts));
	$d = new DateTime("today", $NoLocalisation);
	$d->setTimestamp($paque);
	$d->modify("next monday");
	if (datex("d/m", $ts) == datex("d/m", $d->getTimestamp()))
	    return (true);
	if (datex("d/m", $ts) == "01/05")
	    return (true);
	if (datex("d/m", $ts) == "08/05")
	    return (true);
	if (datex("d/m", $ts) == "13/05")
	    return (true);
	if (datex("d/m", $ts) == "24/05")
	    return (true);
	if (datex("d/m", $ts) == "14/07")
	    return (true);
	if (datex("d/m", $ts) == "15/08")
	    return (true);
	if (datex("d/m", $ts) == "01/11")
	    return (true);
	if (datex("d/m", $ts) == "11/11")
	    return (true);
	if (datex("d/m", $ts) == "25/12")
	    return (true);
    }
    return (false);
}
