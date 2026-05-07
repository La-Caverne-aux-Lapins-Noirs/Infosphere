<?php

function generate_password($len = 12)
{
    // Pas de O majuscule (pour ne pas confondre avec zéro)
    // Pas de \, ni `, ni ", ni ', ni ~ ou ^.
    // Pas de $ ou * pour limiter les risques avec les passages dans le shell.
    $alf = "azertyuiopqsdfghjkmwxcvbnAZERYTUIPQSDFGHJKMWXCVBN";
    $num = "0123456789";
    $sym = "&#{([-_@)]=}+%,?.:/!";
    $randpool = $alf.$num.$sym;
    $letter = false;
    $number = false;
    $symbol = false;

    $rnd = "";
    for ($i = 0; $i < $len; ++$i)
    {
	$r = rand(0, strlen($randpool) - 1);
	$c = substr($randpool, $r, 1);
	if (ctype_alpha($c))
	    $letter = true;
	else if (ctype_digit($c))
	    $number = true;
	else
	    $symbol = true;
	$randpool = str_replace($c, "", $randpool);
	$rnd = $rnd.$c;
    }
    if ($letter == false)
	$rnd = $rnd.substr($alf, rand(0, strlen($alf) - 1), 1);
    if ($number == false)
	$rnd = $rnd.substr($num, rand(0, strlen($num) - 1), 1);
    if ($symbol == false)
	$rnd = $rnd.substr($sym, rand(0, strlen($sym) - 1), 1);
    return ($rnd);
}
