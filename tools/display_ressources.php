<?php

// Si rename ne vaut pas chaine vide, c'est que cod contient un nom de fichier complet (hors extension)
function display_ressources($cod, $rename = "")
{
    $cnt = 0;
    if (($dir = @glob($cod)) == NULL)
	$dir = [];
    if (count($dir) == 0)
	return (0);
    $cod = explode("/", $cod);
    unset($cod[count($cod) - 1]);
    $cod = implode("/", $cod)."/";

    echo "<ul>";
    foreach ($dir as $d)
    {
	$d = explode("/", $d);
	$d = $d[count($d) - 1];

	if ($d == "." || $d == ".." || $d == "index.htm")
	    continue ;
        ?>
    <li style="width: 95%; height: 50px; font-size: 30px; line-height: 50px; text-align: center; background-color: rgba(255, 255, 255, 0.25); border-radius: 10px; margin-bottom: 10px; margin-left: 2.5%; list-style-type: none;">
	<?php

	if (is_dir($cod."/".$d))
	    $cnt += display_ressources($cod."/".$d);
	else
	{
	    echo "<a href='".$cod."/"."$d' style=\"color: black; text-decoration: none\" target=\"_blank\">";
	    if ($rename == "")
		echo $d;
	    else
		echo $rename;
	    echo "</a>";
	    $cnt += 1;
	}

	echo "</li>";
    }
    echo "</ul>";
    return ($cnt);
}

