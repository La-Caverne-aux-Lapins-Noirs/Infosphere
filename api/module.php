<?php

require ("activity.php");

// Les quelques différences existantes entre activité générale et module.
$Tab["GET"][""][1] = "DisplayModule";
$Tab["POST"][""][1] = "AddModule";
$Tab["PUT"][""][0] = "is_teacher";
