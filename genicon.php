<?php
// Appelle le vrai genicon
if (!isset($_GET["function"]))
    exit ;
header ("Content-type: image/png");
if (!file_exists("./dres/medals/".$_GET["function"].".png"))
    system ("/usr/bin/genicon ".$_GET["function"]. " > ./dres/medals/".$_GET["function"]."/icon.png");
system("cat ./dres/medals/".$_GET["function"]."/icon.png");
