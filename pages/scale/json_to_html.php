<?php

require_once (__DIR__."/../../language.php");
require_once (__DIR__."/../../tools/response.php");
require_once (__DIR__."/../../tools/load_configuration.php");
require_once (__DIR__."/../../tools/language_field.php");
require_once (__DIR__."/../../tools/unrollget.php");
require_once (__DIR__."/../../tools/unrollurl.php");

// The purpose of this file is to transform JSON into HMTL.
// The JSON will match the barema format of TechnoCore's Infosphere.

if (!isset($_POST["code"]) || ($desc = load_configuration($_POST["code"]))->is_error())
{
    http_response_code(400);
    echo strval($desc);
    return ;
}
$desc = $desc->value;
http_response_code(200);
?>
<form method="POST" action="<?=unrollurl(); ?>"-->
    <?php foreach ($desc["Exercises"] as $ex) { ?>
	<h2><?=language_field($ex["Name"]); ?></h2>
	<p><?=language_field($ex["Description"]); ?></p>
	<?php if (count(@$ex["Medals"]) > 0) { ?>
	    <?php foreach ($ex["Medals"] as $med) { ?>
		<?=$med; ?>
	    <?php } ?>
	<?php } ?>
    <?php } ?>

</form>
