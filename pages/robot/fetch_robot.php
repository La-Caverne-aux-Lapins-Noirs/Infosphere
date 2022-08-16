<?php

function fetch_robot()
{
    global $Database;
    global $Language;

    $robot = [];
    $robot_query = $Database->query("
      SELECT robot.codename as codename,
             robot.version as version,
             robot.file as file,
             robot.id as id,
             robot.complaint as complaint,
             robot.deleted as deleted
      FROM robot
      GROUP BY robot.id
      ORDER BY id DESC
    ");
    while (($robots = $robot_query->fetch_assoc()))
	$robot[] = $robots;
    return ($robot);
}
