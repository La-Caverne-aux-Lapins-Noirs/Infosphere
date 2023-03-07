<?php

/*
** La carte n'est pas neutre: il y a des obstacles sur la route, y compris des passages ou il n'est pas possible de se rendre.
**
** Les ressources sont:
** - Acier, Quartz, Carburant, Electricité
** - Nourriture, Eau, Ciment, Fibre
**
**
**
**
**
**
*/

/*
** Note: lorsqu'une attaque est mené contre une base,
** une demande de refresh de la base est placée juste avant
** la demande de calcul du combat.
** C'est pareil pour toute interactivite de ce style.
*/

function GetEvents()
{
    $auth = implode(",", []);
    $events = db_select_all("
      id as id_event, element, id_element, event_date
      FROM game_event
      WHERE done = 0
      AND event_date < NOW(3)
      AND IN($auth)
      ORDER BY event_date ASC
    ");
    foreach ($events as &$e)
    {
	$e = array_merge($e, db_select_all("
          * FROM `{$e["element"]}` WHERE id = {$e["id_element"]}
	  "));
	$e["delta"] = microtime(true) - date_to_timestamp($data["event_date"]);
    }
    return ($events);
}

function RefreshBase($elm)
{
    
}
