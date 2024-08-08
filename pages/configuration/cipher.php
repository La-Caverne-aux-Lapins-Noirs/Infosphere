<?php

$acc = $Configuration->Properties["handaccount"];
$url = $Configuration->Properties["handurl"];
$data = secure_data($data_to_cipher, $acc.$url."hand_request");
$data = base64_encode($data);
