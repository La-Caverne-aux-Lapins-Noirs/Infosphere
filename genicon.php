<?php
// Appelle le vrai genicon
if (!isset($_GET["function"]) || !file_exists("/usr/bin/genicon"))
    exit ;
header ("Content-type: image/png");
if (!file_exists("./dres/medals/".$_GET["function"].".png"))
    system ("/usr/bin/genicon ".$_GET["function"]. " > ./dres/medals/".$_GET["function"].".png");
system("cat ./dres/medals/".$_GET["function"].".png");
