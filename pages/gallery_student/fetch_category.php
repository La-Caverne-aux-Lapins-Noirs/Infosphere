<?php

function fetch_category($id = -1)
{
    global $Database;
    global $Language;

    if ($id != -1)
	$forge = " WHERE category.id = $id ";
    else
	$forge = "";
    $category = [];
    $category_query = $Database->query("
      SELECT category.codename as codename,
             category.".$Language."_name as catname,
             category.".$Language."_description as catdesc,
             category.id_stud_rate as id_rate,
             category.id as id
      FROM category
      $forge
      GROUP BY category.id
      ORDER BY id DESC
    ");
    while (($categories = $category_query->fetch_assoc()))
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
              LEFT JOIN category ON stud_rate.id = category.id_stud_rate
              WHERE category.id = ".$id."
	    ");
	    $categories["endpoint"] = [];
	    while (($s = $endpoint->fetch_assoc()))
	    {
		$categories["endpoint"][] = $s;
	    }
	}
	$category[] = $categories;
    }
    if ($id == -1)
	return ($category);
    if ($category == NULL)
	return ;
    return ($category[0]);
}
