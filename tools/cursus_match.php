<?php

function cursus_match($cyc, $a, $b)
{
    // On boucle sur les cursus de la matiere
    foreach ($a as $aa)
    {
	if ($aa == "")
	    continue ;
	// On boucle sur les cycles de l'utilisateur
	foreach ($b as $bb)
	{
	    // On a trouvé le cycle
	    if ($bb["id_cycle"] != $cyc)
		continue ;
	    // On boucle sur les cursus du cycle
	    foreach ($bb["cursus"] as $cur)
	    {
		// On trouve le cursus
		if (strcasecmp($aa, $cur) == 0)
		    return (true);
	    }
	}
    }
    return (false);
}

