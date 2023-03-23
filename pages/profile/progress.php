
<?php if ($module->validation == FullActivity::PERCENT_VALIDATION || $module->validation == FullActivity::GRADE_VALIDATION) { ?>
    
    <?php
    $module->configuration["Completion"] = $module->current_percent;
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
    <div class="final_box" style="width: 100%; height: 50px; overflow-y: auto; background-color: white !important; background-image: url('./tools/progress_bar.php?w=900&amp;h=50&amp;data=<?=base64url_encode(json_encode($module->configuration, JSON_UNESCAPED_SLASHES)); ?>'); background-size: contain; background-repeat: no-repeat; background-position: center center; border-radius: 0px;">
    </div>

<?php } else if ($module->validation != FullActivity::NO_VALIDATION) { ?>
    
    <?php
    $module->configuration["Grade"]["A"] = $module->grade_a / 100.0;
    $module->configuration["Grade"]["B"] = $module->grade_b / 100.0;
    $module->configuration["Grade"]["C"] = $module->grade_c / 100.0;
    $module->configuration["Grade"]["D"] = $module->grade_d / 100.0;
    $module->configuration["Grade"]["E"] = $module->grade_bonus / 100.0;
    $module->configuration["Validation"]["A"] = $module->valid_grade_a / 100.0;
    $module->configuration["Validation"]["B"] = $module->valid_grade_b / 100.0;
    $module->configuration["Validation"]["C"] = $module->valid_grade_c / 100.0;
    $module->configuration["Validation"]["D"] = $module->valid_grade_d / 100.0;
    if ($module->grade_bonus == 0)
	$module->configuration["Validation"]["E"] = 0;
    else
	$module->configuration["Validation"]["E"] = $module->valid_grade_e / 100.0;
    ?>
    <div class="final_box" style="width: 100%; height: 200px; overflow-y: auto; background-image: url('./tools/histo_bar.php?w=450&amp;h=200&amp;data=<?=base64url_encode(json_encode($module->configuration, JSON_UNESCAPED_SLASHES)); ?>'); background-size: contain; background-repeat: no-repeat; background-position: center center; border-radius: 0px;">
    </div>
    
<?php } ?>