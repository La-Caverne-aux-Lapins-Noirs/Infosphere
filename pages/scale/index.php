<?php

if (file_exists(__DIR__."/handle_request.php")
    && is_admin()
    && isset($_POST["action"]))
    require_once (__DIR__."/handle_request.php");

if (file_exists(__DIR__."/handle_parameters.php"))
    require_once (__DIR__."/handle_parameters.php");

require_once (__DIR__."/../error_net.php");

?>

<h2><?=$Dictionnary["Scale"]; ?></h2>

<?php if ($unique == false) { ?>
    <?php require_once ("list_all_scales.php"); ?>
<?php } else { ?>
    <?php require_once ("display_scale.php"); ?>
<?php } ?>
