<?php

// Si rename ne vaut pas chaine vide, c'est que cod contient un nom de fichier complet (hors extension)
function display_ressources($cod, $rename = "", $subdirs = "", $teacher = false)
{
    $cnt = 0;
    if (($dir = @glob($cod)) == NULL)
	$dir = [];
    if (count($dir) == 0)
	return (0);

    $cod = explode("/", $cod);
    unset($cod[count($cod) - 1]);
    $cod = implode("/", $cod)."/";

    foreach ($dir as $d)
    {
	$d = explode("/", $d);
	$d = $d[count($d) - 1];

	// private, afin de pouvoir aussi mettre des sources en lignes
	// comme des fichiers odt par exemple...
	if ($d == "." || $d == ".." || $d == "index.htm" || $d == "index.php" || (($d == "private" || $d == "admin") && $teacher == false))
	    continue ;
	if (is_dir($cod."/".$d))
	    $cnt += display_ressources($cod.$d."/*", "", $subdirs.$d."/");
	else
	{
	    echo '<li style="width: 95%; height: 50px; font-size: 30px; line-height: 50px; text-align: center; background-color: rgba(255, 255, 255, 0.25); border-radius: 10px; margin-bottom: 10px; margin-left: 2.5%; list-style-type: none;">';
	    echo "<a href='$cod/$d' style='color: black; text-decoration: none;' target='_blank'>";
	    if ($rename == "")
		echo $subdirs.$d;
	    else
		echo $rename;
	    echo "</a>";
	    echo "</li>";
	    $cnt += 1;
	}
    }
    return ($cnt);
}

