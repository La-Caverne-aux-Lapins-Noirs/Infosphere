<?php

function fetch_medals()
{
    global $Database;
    global $Language;

    $medals = [];
    $forge = forge_language_fields(["name", "description"], true, true);
    if (($medals_query = $Database->query("
      SELECT id, codename, $forge, icon
      FROM medal
      WHERE deleted = 0
      ORDER BY codename ASC
      ")) == false)
      return (NULL);
    while (($m = $medals_query->fetch_assoc()) != NULL)
	$medals[$m["codename"]] = $m;
    return ($medals);
}

