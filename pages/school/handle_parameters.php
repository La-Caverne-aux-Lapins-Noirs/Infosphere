<?php

$school = (int)@try_get($_GET, "a", -1);
if (($unique = $school != -1))
{
    $idschool = $school;
    $school = db_select_one("
       *, {$Language}_name as name FROM school
       WHERE id = $idschool
    ");
    $school["directors"] = db_select_all("
       user.id, user.codename, user.nickname FROM user_school
       LEFT JOIN user ON user.id = user_school.id_user
       WHERE user_school.id_school = $idschool
       AND user_school.authority = 1
       AND user.authority >= 0
    ");
    $school["users"] = db_select_all("
       user.id, user.codename, user.nickname FROM user_school
       LEFT JOIN user ON user.id = user_school.id_user
       WHERE user_school.id_school = $idschool
       AND user_school.authority = 0
       AND user.authority >= 0
    ");
    $school["rooms"] = db_select_all("
       room.id, room.codename, room.{$Language}_name as name FROM school_room
       LEFT JOIN room ON room.id = school_room.id_room
       WHERE school_room.id_school = $idschool
    ");
    $school["cycles"] = db_select_all("
       cycle.id, cycle.codename, cycle.{$Language}_name as name FROM school_cycle
       LEFT JOIN cycle ON cycle.id = school_cycle.id_cycle
       WHERE school_cycle.id_school = $idschool
         AND cycle.is_template = 1
    ");
    $school["laboratories"] = db_select_all("
       laboratory.id, laboratory.codename, laboratory.{$Language}_name as name FROM school_laboratory
       LEFT JOIN laboratory ON laboratory.id = school_laboratory.id_laboratory
       WHERE school_laboratory.id_school = $idschool
    ");
}
