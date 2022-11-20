<?php ob_start(); ?>
<?php
$data_to_cipher = ob_get_clean();

$acc = $Configuration->Properties["handaccount"];
$url = $Configuration->Properties["handurl"];
$data = secure_data($data_to_cipher, $acc.$url."hand_request");
$data = base64_encode($data);

echo $data;

