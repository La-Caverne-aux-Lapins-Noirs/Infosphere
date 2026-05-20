<?php

require ("cycle.php");

unset($Tab["PUT"]["user"]);
unset($Tab["DELETE"]["user"]);
unset($Tab["PUT"]["school"]);
unset($Tab["DELETE"]["school"]);
unset($Tab["POST"]["mail"]);
$Tab["POST"]["instantiate"] = [
    "is_director_for_cycle",
    "InstantiateCycle"
];

