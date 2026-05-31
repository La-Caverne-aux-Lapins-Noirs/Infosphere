<?php

$position = try_get($_GET, "table", "");
$id_position = (int)try_get($_GET, "id", -1);
$allowed_intercom_positions = [
    "cycle" => true,
    "activity" => true,
    "laboratory" => true,
    "team" => true,
    "school_staff" => true,
    "school" => true,
    "common" => true,
];
$unique = $position != "" && isset($allowed_intercom_positions[$position]) && $id_position > 0;

