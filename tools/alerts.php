<?php

require_once (__DIR__."/distrans_status.php");

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
    $lastcheck = distrans_last_running_log(1, defined("TRACE") ? TRACE : "0");
    $report_type = defined("REPORT") ? REPORT : "6";
    $trace_ddos = db_select_all("
        ip,
        MAX(log_date) as last_report,
        COUNT(*) as report_count,
        SUBSTRING_INDEX(GROUP_CONCAT(message ORDER BY log_date DESC SEPARATOR '\n'), '\n', 1) as message
        FROM log
        WHERE type = $report_type
        AND log_date >= DATE_SUB(NOW(), INTERVAL 2 DAY)
        AND message LIKE 'Trace report%'
        GROUP BY ip
        ORDER BY last_report DESC
        LIMIT 20
    ");
    if (count($trace_ddos))
    {
	$alert["ddos"] = "";
	foreach ($trace_ddos as $td)
	    $alert["ddos"] .= $td["message"]." (".$td["report_count"]." reports, dernier: ".$td["last_report"].")<br />";
    }
    
    // Il doit s'executer toutes les 2 minutes, et ca fait 3 minutes qu'il n'y a rien
    if (!$last || date_to_timestamp($last["log_date"]) < now() - 60 * 3)
	$alert["albedo"] = "<span class=\"blink\">".$Dictionnary["AlbedoDoesNotRun"]."</span>";
    // Aucune connexion réussie depuis plus de 3 mins.
    if (!$lastcheck || date_to_timestamp($lastcheck["log_date"]) < now() - 60 - 60 * 3)
	$alert["hand"] = "<span class=\"blink\">".$Dictionnary["InfosphereHandDoesNotRun"]."</span>";

    return ($alert);
}
