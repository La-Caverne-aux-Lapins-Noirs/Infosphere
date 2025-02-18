<?php

$Operations = [
    "run_albedo.php",
    "backup.php",
    "ping_hand.php",
    "new_ldap_user.php",
    "test_mail.php",
    "antispam.php",
    "regen_medals.php",
    "cipher.php",
    "new_home.php",
    "deploy_exam.php",
    "workspy_test.php",
    "get_versions.php"
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

