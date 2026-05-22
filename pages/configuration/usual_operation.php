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

foreach (["custom_*.php", "operation_*.php", "usual_*.php"] as $pattern)
    foreach (glob(__DIR__."/".$pattern) as $file)
        if (!in_array(basename($file), $Operations))
            $Operations[] = basename($file);

if (function_exists("configuration_write_htaccess"))
    configuration_write_htaccess();
else
{
    $out = "<Files .htaccess>\n  Order allow,deny\n  Deny from all\n</Files>\n";
    foreach ($Operations as $ope)
    {
        $ope = basename($ope);
        if (!preg_match('/^[a-zA-Z0-9_\-.]+\.php$/', $ope) || !file_exists(__DIR__."/".$ope))
            continue ;
        $out .= "\n<Files $ope>\n  Order allow,deny\n  Deny from all\n</Files>\n";
    }
    $get = file_exists(__DIR__."/.htaccess") ? file_get_contents(__DIR__."/.htaccess") : "";
    if ($get != $out)
        file_put_contents(__DIR__."/.htaccess", $out);
}
