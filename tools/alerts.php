<?php

function retrieve_alerts()
{
    global $OriginalUser;
    global $Dictionnary;

    if (!am_i_director())
	return (NULL);
    $alert = [];
    if ((disk_free_space("./") / disk_total_space("./")) <= 0.2)
    {
	$alert["space"] = $Dictionnary["DiskFull"]." : ".intval(disk_free_space("./") / disk_total_space("./") * 100)."% ".$Dictionnary["remaining"].". (".(sprintf("%.1f", disk_free_space("./") / 1e9))."Go)";
    }

    $last = db_select_one("log_date FROM log WHERE id_user = 1 AND type = 0 AND message = 'Albedo stops.' ORDER BY log_date DESC");
    $lastcheck = db_select_one("log_date FROM log WHERE id_user = 1 AND type = 0 AND message = 'Infosphere hand runs.' ORDER BY log_date DESC");
    $trace_ddos = db_select_all("* FROM log WHERE type = 6 AND DATEDIFF(log_date, NOW()) < 2 GROUP BY ip ORDER BY log_date DESC");
    if (count($trace_ddos))
    {
	$alert["ddos"] = "";
	foreach ($trace_ddos as $td)
	    $alert["ddos"] .= $td["message"]."<br />";
    }
    
    // Il doit s'executer toutes les 2 minutes, et ca fait 3 minutes qu'il n'y a rien
    if (!$last || date_to_timestamp($last["log_date"]) < now() - 60 * 3)
	$alert["albedo"] = "<span class=\"blink\">".$Dictionnary["AlbedoDoesNotRun"]."</span>";
    // Aucune connexion réussie depuis plus de 3 mins.
    if (!$lastcheck || date_to_timestamp($lastcheck["log_date"]) < now() - 60 - 60 * 3)
	$alert["hand"] = "<span class=\"blink\">".$Dictionnary["InfosphereHandDoesNotRun"]."</span>";

    return ($alert);
}
