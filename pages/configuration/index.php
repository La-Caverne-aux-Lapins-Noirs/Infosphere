<?php
require_once (__DIR__."/model.php");
require_once (__DIR__."/fetch_log.php");
require_once (__DIR__."/usual_operation.php");

if (!is_admin())
{
    http_response_code(404);
    die();
}

$result = "";
$outfile = NULL;
if (file_exists(__DIR__."/handle_request.php")
    && is_admin()
    && isset($_POST["action"]))
    require_once (__DIR__."/handle_request.php");

if (file_exists(__DIR__."/handle_parameters.php"))
    require_once (__DIR__."/handle_parameters.php");

require_once (__DIR__."/../error_net.php");
require_once (__DIR__."/style.php");

$configuration_panels = [
    "Stats et alertes" => __DIR__."/stats_alerts_panel.php",
    "Logs" => __DIR__."/logs_panel.php",
    "Configuration" => __DIR__."/configuration_panel.php",
    "Commandes usuelles" => __DIR__."/operations_panel.php"
];
?>

<div class="configuration-admin configuration-tabbed-admin">
    <?php tabpanel(
        $configuration_panels,
        "configuration-admin-tabs",
        "Logs",
        "configuration-tab-button",
        "configuration-tab-content"
    ); ?>
</div>
