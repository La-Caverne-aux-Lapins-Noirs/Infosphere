<?php
// Appelle le vrai genicon
if (!isset($_GET["function"]))
    exit ;
//header ("Content-type: image/png");
if (!preg_match("/[a-zA-Z0-9_]+/", $_GET["function"]))
    die();
$rt = "./dres/medals/".$_GET["function"];
if (!file_exists($rt."/icon.png"))
{
    require_once ("tools/index.php");

    new_directory("$rt/band.png");
    system(
	"DISPLAY=:1 genicon band".
	" ".$_GET["function"].
	" 	-c dres/medals/.ressources/.default_style.dab".
	" > $rt/band.png"
    );
    system(
	"DISPLAY=:1 genicon sband".
	" ".$_GET["function"].
	" 	-c dres/medals/.ressources/.default_style.dab ".
	" > $rt/icon.png"
    );
}
if (!isset($_GET["shape"]))
    $_GET["shape"] = "sband";
if ($_GET["shape"] != "band")
    $_GET["shape"] = "icon";
system("cat ./dres/medals/".$_GET["function"]."/".$_GET["shape"].".png");
