<?php

$out = ping_hand();
if ($out === false)
    echo "Cannot reach the Hand";
else
    echo "Ping ok!";

