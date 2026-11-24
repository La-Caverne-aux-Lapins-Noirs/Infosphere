<?php

function install_upload_profile($max_mib = 4096)
{
    if (!is_int($max_mib) || $max_mib < 1 || $max_mib > 65536)
        throw new InvalidArgumentException("La taille maximale doit être comprise entre 1 et 65536 Mio.");
    $root = str_replace(["\\", '"'], ["\\\\", '\\"'], dirname(__DIR__));
    return ([
        "max_mib" => $max_mib,
        "php" => "; Managed by Infosphere installer.\n".
            "upload_max_filesize = ".$max_mib."M\n".
            "post_max_size = ".($max_mib + 32)."M\n".
            "max_input_time = 3600\n",
        // Apache versions capped at INT_MAX cannot express a finite 4 GiB
        // limit. Restrict this override to this installation; PHP enforces
        // the finite file/POST limits, including multipart overhead.
        "apache" => "# Managed by Infosphere installer.\n".
            '<Directory "'.$root.'">'."\n  LimitRequestBody 0\n".
            "  <IfModule mod_mime.c>\n    AddType text/vtt .vtt\n  </IfModule>\n</Directory>\n"
    ]);
}

function install_upload_restore($backups, $enabled, $previously_enabled)
{
    foreach ($backups as $path => $content)
        if ($content === NULL)
            @unlink($path);
        else
            @file_put_contents($path, $content);
    if (!$previously_enabled && is_link($enabled))
        @unlink($enabled);
}

function install_upload_configuration(&$warnings, $max_mib = 4096, $php_version = NULL)
{
    if (PHP_INT_SIZE < 8 && $max_mib >= 2048)
    {
        install_append_warning($warnings, "PHP 64 bits est nécessaire pour cette limite d'envoi.");
        return (false);
    }
    $profile = install_upload_profile($max_mib);
    $version = $php_version ?? (PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION);
    if (!preg_match('/^[0-9]+\.[0-9]+$/', $version))
        throw new InvalidArgumentException("Version PHP invalide.");
    @mkdir("res/install/php", 0775, true);
    @mkdir("res/install/apache", 0775, true);
    $php_fallback = "res/install/php/99-infosphere-upload.ini";
    $apache_fallback = "res/install/apache/infosphere-upload.conf";
    if (!install_write_file_if_different($php_fallback, $profile["php"])
        || !install_write_file_if_different($apache_fallback, $profile["apache"]))
    {
        install_append_warning($warnings, "Impossible d'écrire les fichiers de configuration des envois.");
        return (false);
    }
    $targets = [];
    $sapis = [];
    foreach (["apache2", "fpm"] as $sapi)
    {
        $directory = "/etc/php/".$version."/".$sapi."/conf.d";
        if (is_dir($directory))
        {
            $targets[$directory."/99-infosphere-upload.ini"] = $profile["php"];
            $sapis[] = $sapi;
        }
    }
    $apache = "/etc/apache2/conf-available/infosphere-upload.conf";
    $enabled = "/etc/apache2/conf-enabled/infosphere-upload.conf";
    $targets[$apache] = $profile["apache"];
    $writable = count($sapis) > 0;
    foreach ($targets as $path => $content)
        if (!(file_exists($path) ? (is_writable($path) && is_readable($path)) : is_writable(dirname($path))))
            $writable = false;
    if (!file_exists($enabled) && !is_writable(dirname($enabled)))
        $writable = false;
    if (!$writable)
    {
        install_append_warning($warnings,
            "Les configurations ont été préparées dans res/install/. Pour les appliquer : ".
            "sudo php tools/configure_upload_limits.php --max-mib=".$max_mib." --php-version=".$version.
            ". Vérifier ensuite les valeurs effectives du PHP qui sert Infosphère.");
        return (false);
    }
    $apachectl = install_command_path("apache2ctl");
    if ($apachectl == "")
    {
        install_append_warning($warnings, "apache2ctl est nécessaire pour valider la configuration avant son application.");
        return (false);
    }
    $backups = [];
    $previously_enabled = file_exists($enabled) || is_link($enabled);
    foreach ($targets as $path => $content)
    {
        $backups[$path] = file_exists($path) ? file_get_contents($path) : NULL;
        if ($backups[$path] === false)
        {
            unset($backups[$path]);
            install_upload_restore($backups, $enabled, $previously_enabled);
            install_append_warning($warnings, "Impossible de sauvegarder ".$path." ; aucune modification de ce fichier.");
            return (false);
        }
        if (!install_write_file_if_different($path, $content))
        {
            install_upload_restore($backups, $enabled, $previously_enabled);
            install_append_warning($warnings, "Impossible d'écrire ".$path." ; configuration précédente restaurée.");
            return (false);
        }
        @chmod($path, 0644);
    }
    if (!$previously_enabled && !@symlink($apache, $enabled))
    {
        install_upload_restore($backups, $enabled, $previously_enabled);
        install_append_warning($warnings, "Impossible d'activer infosphere-upload.conf ; configuration précédente restaurée.");
        return (false);
    }
    $commands = [escapeshellarg($apachectl)." configtest 2>&1"];
    if (in_array("fpm", $sapis))
    {
        $fpm = install_command_path("php-fpm".$version);
        if ($fpm != "")
            $commands[] = escapeshellarg($fpm)." -t 2>&1";
    }
    foreach ($commands as $command)
    {
        $output = [];
        $status = 1;
        install_exec($command, $output, $status);
        if ($status != 0)
        {
            install_upload_restore($backups, $enabled, $previously_enabled);
            install_append_warning($warnings, "Validation refusée ; configuration précédente restaurée : ".implode(" ", $output));
            return (false);
        }
    }
    if (!function_exists("posix_geteuid") || posix_geteuid() != 0)
    {
        install_append_warning($warnings, "Configuration des envois écrite et validée ; recharger Apache et PHP-FPM pour l'appliquer.");
        return (true);
    }
    $services = ["apache2"];
    if (in_array("fpm", $sapis))
        $services[] = "php".$version."-fpm";
    $success = true;
    foreach ($services as $service)
    {
        $output = [];
        $status = 1;
        install_exec("systemctl reload ".escapeshellarg($service)." 2>&1", $output, $status);
        if ($status != 0)
            install_exec("service ".escapeshellarg($service)." reload 2>&1", $output, $status);
        if ($status != 0)
        {
            install_append_warning($warnings, "La configuration est valide mais ".$service." doit être rechargé manuellement : ".implode(" ", $output));
            $success = false;
        }
    }
    return ($success);
}
