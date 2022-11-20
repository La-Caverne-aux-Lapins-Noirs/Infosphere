<?php

$Operations = [
    "run_albedo.php",
    "test_mail.php",
    "regen_medals.php",
    "cipher.php",
    "ping_hand.php",
];

$out = "
<Files .htaccess>
  Order allow,deny
  Deny from all
</Files>
";

foreach ($Operations as $ope)
{
    $out .= "
<Files $ope>
  Order alllow,deny
  Deny from all
</Files>
";
}

$get = file_get_contents(__DIR__."/.htaccess");
if ($get != $out)
    file_put_contents(__DIR__."/.htaccess", $out);

