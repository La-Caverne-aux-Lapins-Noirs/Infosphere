<?php

if (file_exists(__DIR__."/handle_request.php")
    && $User != NULL && $User["authority"] >= ADMINISTRATOR
    && isset($_POST["action"]))
    require_once (__DIR__."/handle_request.php");

if (file_exists(__DIR__."/handle_parameters.php"))
    require_once (__DIR__."/handle_parameters.php");

require_once (__DIR__."/../error_net.php");

$schools = db_select_all("
  *, {$Language}_name as name FROM school
  WHERE deleted IS NULL
  ORDER BY codename ASC
");

?>

<h2><?=$Dictionnary["School"]; ?></h2>
<?php
if ($unique == false)
    require ("list_school.php");
else
    require ("list_users.php");
