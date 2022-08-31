<?php

function remove_ressource_file($type, $id, $file)
{
    global $Dictionnary;
    global $Configuration;

    // On déplace à la corbeille en signifiant que ce fichier était rattaché a l'activité $id
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $filename = pathinfo($file, PATHINFO_FILENAME);
    $now = now();
    $new_path = "./dres/trash/{$type}_{$id}_{$filename}_{$now}.{$ext}";
    $file = escapeshellarg($file);
    $new_path = escapeshellarg($new_path);
    system("mv $file $new_path");
    return (true);
}
