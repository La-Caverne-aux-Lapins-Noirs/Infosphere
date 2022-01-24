<?php

function new_team_name()
{
    global $AdjectiveOrder;
    global $Dictionnary;

    $Element = [
	"Android",
	"Cyborg",
	"Human",
	"Dog",
	"Cat",
	"Goose",
	"Horse",
    ];
    $Adjective = [
	"Brave",
	"Pink",
	"White",
	"Black",
	"Gray",
	"Tricky",
	"Complex",
	"Wise"
    ];
    $flt = rand();
    $Element = $Element[$flt % count($Element)];
    $Adjective = $Adjective[$flt % count($Adjective)];
    if ($AdjectiveOrder == "After")
	return (ucfirst($Dictionnary[$Element]." ".$Dictionnary[$Adjective]));
    return (ucfirst($Dictionnary[$Adjective]." ".$Dictionnary[$Element]));
}

