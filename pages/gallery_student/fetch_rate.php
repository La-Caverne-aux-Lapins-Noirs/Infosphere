<?php

function fetch_rate($id = -1)
{
    global $Database;

    if ($id != -1)
	$forge = " WHERE stud_rate.id = $id ";
    else
	$forge = "";
    $rate = [];
    $rate_query = $Database->query("
      SELECT stud_rate.id as id
      FROM stud_rate
      $forge
      GROUP BY stud_rate.id
      ORDER BY id DESC
    ");
    while (($rates = $rate_query->fetch_assoc()))
    {
	if ($id != -1)
	{
	    $endpoint = $Database->query
	    ("
              SELECT stud_endpoint.id as id,
                     stud_endpoint.codename as codename,
                     stud_endpoint.valrange as valrange
              FROM stud_endpoint
              LEFT JOIN stud_rate ON stud_rate.id = stud_endpoint.id_stud_rate
              WHERE stud_rate.id = ".$id."
	    ");
	    $rates["endpoint"] = [];
	    while (($s = $endpoint->fetch_assoc()))
	    {
		$rates["endpoint"][] = $s;
	    }
	}
	$rate[] = $rates;
    }
    if ($id == -1)
	return ($rate);
    if ($rate == NULL)
	return ;
    return ($rate[0]);
}
