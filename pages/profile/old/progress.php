<?php

if ($module->validation_by_percent || $module->grade_module || $module->old_validation)
{
    $module->configuration["Completion"] = $module->current_percent;
    $module->configuration["Mandatory"] = $module->grade >= 2;
    $module->configuration["Note"] = [];
    $module->configuration["Grade"]["A"] = $module->grade_a / 100.0;
    $module->configuration["Grade"]["B"] = $module->grade_b / 100.0;
    $module->configuration["Grade"]["C"] = $module->grade_c / 100.0;
    $module->configuration["Grade"]["D"] = $module->grade_d / 100.0;
    if ($module->grade_module)
    {
	foreach ($module->medal as $i => $med)
	{
	    if (substr($i, 0, 4) == "note")
		$module->configuration["Note"][(int)substr($i, 4)] = $med["success"];
	}
    }

?>
<div class="final_box" style="width: 100%; height: 50px; overflow-y: auto; background-color: black !important; background-image: url('./tools/progress_bar.php?w=900&amp;h=50&amp;data=<?=base64url_encode(json_encode($module->configuration, JSON_UNESCAPED_SLASHES)); ?>'); background-size: contain; background-repeat: no-repeat;">
</div>
<?php
}
else
{
    $module->configuration["Grade"]["A"] = $module->grade_a / 100.0;
    $module->configuration["Grade"]["B"] = $module->grade_b / 100.0;
    $module->configuration["Grade"]["C"] = $module->grade_c / 100.0;
    $module->configuration["Grade"]["D"] = $module->grade_d / 100.0;
    $module->configuration["Grade"]["E"] = $module->grade_bonus / 100.0;
    $module->configuration["Validation"]["A"] = $module->valid_grade_a / 100.0;
    $module->configuration["Validation"]["B"] = $module->valid_grade_b / 100.0;
    $module->configuration["Validation"]["C"] = $module->valid_grade_c / 100.0;
    $module->configuration["Validation"]["D"] = $module->valid_grade_d / 100.0;
    $module->configuration["Validation"]["E"] = $module->valid_grade_e / 100.0;
?>
<div class="final_box" style="width: 100%; height: 200px; overflow-y: auto; background-color: black !important; background-image: url('./tools/histo_bar.php?w=900&amp;h=200&amp;data=<?=base64url_encode(json_encode($module->configuration, JSON_UNESCAPED_SLASHES)); ?>'); background-size: contain; background-repeat: no-repeat;">
</div>
<?php
}
?>
