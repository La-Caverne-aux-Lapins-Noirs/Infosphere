
<?php if ($module->validation == FullActivity::PERCENT_VALIDATION || $module->validation == FullActivity::GRADE_VALIDATION) { ?>
    
    <?php
    $module->configuration["Completion"] = $module->current_percent;
    $module->configuration["Note"] = [];
    $module->configuration["Grade"]["A"] = $module->grade_a / 100.0;
    $module->configuration["Grade"]["B"] = $module->grade_b / 100.0;
    $module->configuration["Grade"]["C"] = $module->grade_c / 100.0;
    $module->configuration["Grade"]["D"] = $module->grade_d / 100.0;
    $module->configuration["Bonus"] = $module->bonus_grade_d / 100.0;
    if ($module->validation == FullActivity::GRADE_VALIDATION)
    {
	$module->configuration["IsNote"] = true;
	for ($li = 0; $li <= 20; ++$li)
	    $module->configuration["Note"][$li] = 0;
	foreach ($module->medal as $li => $med)
	    if (substr($med["codename"], 0, 5) == "token" && $med["success"] > 0)
		$module->configuration["Note"][intval(substr($med["codename"], 5))] += 1;
    }
    
    ?>
    <div class="final_box" style="width: 100%; height: 200px; overflow-y: auto; background-color: white !important; background-image: url('./tools/progress_bar.php?w=900&amp;h=300&amp;data=<?=base64url_encode(json_encode($module->configuration, JSON_UNESCAPED_SLASHES)); ?>'); background-size: contain; background-repeat: no-repeat; background-position: center center; border-radius: 0px;">
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
    $module->configuration["Bonus"]["A"] = $module->bonus_grade_a / 100.0;
    $module->configuration["Bonus"]["B"] = $module->bonus_grade_b / 100.0;
    $module->configuration["Bonus"]["C"] = $module->bonus_grade_c / 100.0;
    $module->configuration["Bonus"]["D"] = $module->bonus_grade_d / 100.0;
    $module->configuration["Bonus"]["Bonus"] = $module->bonus_grade_bonus / 100.0;
    
    if ($module->grade_bonus == 0)
	$module->configuration["Validation"]["E"] = 0;
    else
	$module->configuration["Validation"]["E"] = $module->valid_grade_e / 100.0;
    ?>
    <div class="final_box" style="width: 100%; height: 200px; overflow-y: auto; background-image: url('./tools/histo_bar.php?w=450&amp;h=200&amp;data=<?=base64url_encode(json_encode($module->configuration, JSON_UNESCAPED_SLASHES)); ?>'); background-size: contain; background-repeat: no-repeat; background-position: center center; border-radius: 0px;">
    </div>
    
<?php } ?>
