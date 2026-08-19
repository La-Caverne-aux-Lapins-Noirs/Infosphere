<?php

/*
** Migration idempotente depuis l'ancien modèle où la racine du répertoire
** utilisateur constituait implicitement son espace privé.
**
**   avatar.png -> public/
**   photo.png  -> admin/
**   tout le reste -> perso/
*/

function user_storage_migration_unique_destination($directory, $name)
{
    $directory = rtrim($directory, "/")."/";
    $candidate = $directory.$name;
    if (!file_exists($candidate) && !is_link($candidate))
        return ($candidate);

    $info = pathinfo($name);
    $base = $info["filename"] ?? $name;
    $extension = isset($info["extension"]) && $info["extension"] != ""
        ? ".".$info["extension"]
        : "";

    for ($i = 1; $i < 10000; ++$i)
    {
        $candidate = $directory.$base.".legacy".$i.$extension;
        if (!file_exists($candidate) && !is_link($candidate))
            return ($candidate);
    }
    return (NULL);
}

global $Configuration;

$users_root = rtrim($Configuration->UsersDir(), "/");
$directories = glob($users_root."/*", GLOB_ONLYDIR);
$moved = 0;
$skipped = 0;
$errors = [];

foreach ($directories as $directory)
{
    if (is_link($directory))
    {
        ++$skipped;
        $errors[] = "Lien symbolique ignoré : ".basename($directory);
        continue ;
    }

    $codename = basename($directory);
    $directory = $Configuration->UsersDir($codename);
    $perso = $directory."perso/";
    $public = $directory."public/";
    $admin = $directory."admin/";

    $entries = scandir($directory);
    if ($entries === false)
    {
        $errors[] = "Impossible de lire : ".$codename;
        continue ;
    }

    foreach ($entries as $name)
    {
        if (in_array($name, [
            ".", "..", "index.php", ".htaccess",
            "admin", "public", "perso"
        ], true))
            continue ;

        $source = $directory.$name;
        if (is_link($source))
        {
            ++$skipped;
            $errors[] = "Lien symbolique ignoré : ".$codename."/".$name;
            continue ;
        }

        if ($name === "avatar.png")
            $destination_dir = $public;
        else if ($name === "photo.png")
            $destination_dir = $admin;
        else
            $destination_dir = $perso;

        $destination = user_storage_migration_unique_destination(
            $destination_dir,
            $name
        );
        if ($destination === NULL)
        {
            $errors[] = "Collision impossible à résoudre : ".$codename."/".$name;
            continue ;
        }

        if (!@rename($source, $destination))
        {
            $errors[] = "Impossible de déplacer : ".$codename."/".$name;
            continue ;
        }
        ++$moved;
    }
}

echo "Migration des espaces utilisateurs terminée.\n";
echo "Éléments déplacés : ".$moved."\n";
echo "Éléments ignorés : ".$skipped."\n";
if (count($errors))
{
    echo "\nÀ vérifier manuellement :\n";
    foreach ($errors as $error)
        echo " - ".$error."\n";
}
