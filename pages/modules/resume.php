<?php
// Cette page affiche les matières encore ouvertes, tout cycle confondu
// et ou l'utilisateur n'est pas encore inscrit.
$min_cred = 0;
$max_cred = 0;
$opt_min_cred = 0;
$opt_max_cred = 0;
$man_min_cred = 0;
$man_max_cred = 0;
$total = 0;
$mandatory = 0;
$option = 0;
$min_cycle_credits = 0;
$max_cycle_credits = 0;
ob_start();
?>
###RESUME
<?php foreach ($datas as $data) { ?>
    <?php $cycle = $data["cycle"]; ?>
    <?php if (date_to_timestamp($cycle->first_day) > now()) continue ; ?>
    <?php if (date_to_timestamp($cycle->last_day) < now()) continue ; ?>

    <?php foreach ($data["matter_to_sort"] as $matter) { ?>

	<?php if ($matter->registered) { ?>
	    <?php $min_cycle_credits += $matter->credit_d; ?>
	    <?php $max_cycle_credits += $matter->credit_a; ?>
	    <?php continue ; ?>
	<?php } ?>
	<?php if ($matter->registration_date && date_to_timestamp($matter->registration_date) > now()) continue ; ?>
	<?php if ($matter->close_date && date_to_timestamp($matter->close_date) < now()) continue ; ?>
	<?php if ($matter->subscription == FullActivity::MANUAL_SUBSCRIPTION) {?>
	    <?php $opt_min_cred += $matter->credit_d; ?>
	    <?php $opt_max_cred += $matter->credit_a; ?>
	    <?php $option += 1; ?>
	<?php } else { ?>
	    <?php $man_min_cred += $matter->credit_d; ?>
	    <?php $man_max_cred += $matter->credit_a; ?>
	    <?php $mandatory += 1; ?>
	<?php } ?>
	<?php $min_cred += $matter->credit_d; ?>
	<?php $max_cred += $matter->credit_a; ?>
	<?php $total += 1; ?>
	
	<?php if ($matter->subscription == FullActivity::AUTOMATIC_SUBSCRIPTION) continue ; ?>

	<div
	    class="resume_module"
	    <?php if ($matter->full_activity->current_wallpaper) { ?>
		style="background-image: url('<?=$matter->full_activity->current_wallpaper; ?>');"
	    <?php } ?>
	    id="resume_matter_<?=$matter->id; ?>"
	>
	    <?php if ((isset($cycle) && cursus_match($cycle->id, $matter->cursus, $User["cycle"]))) { ?>
		<img
		    src="res/mandatory.png"
		    style="float: left; width: 20px; height: 20px; position: relative; left: -5px;"
		    title="<?=$Dictionnary["Mandatory"]; ?>"
		/>
	    <?php } ?>
	    <h3 style="width: 70%;">
		<?=strlen($matter->name) ? $matter->name : $matter->codename; ?>
	    </h3>
	    <div class="registration">
		<?=datex("d/m", $matter->registration_date); ?>
		-
		<?=datex("d/m", $matter->close_date); ?>
	    </div>
	    <p
		class="module_description"
		style="text-indent: 40px;"
	    >
		<?=$matter->description; ?>
	    </p>
	    <table class="module_scale">
		<tr class="grade_a">
		    <td>A</td><td><?=$matter->credit_a; ?></td>
		</tr><tr class="grade_b">
		    <td>B</td><td><?=$matter->credit_b; ?></td>
		</tr><tr class="grade_c">
		    <td>C</td><td><?=$matter->credit_c; ?></td>
		</tr><tr class="grade_d">
		    <td>D</td><td><?=$matter->credit_d; ?></td>
		</tr>
	    </table>
	    <div class="module_button" onclick="display_panel('module', 'matter_<?=$matter->id; ?>');">
		<?=$Dictionnary["SeeMore"]; ?>
	    </div>
	</div>

    <?php } ?>
<?php } ; ?>
<?php $Content = ob_get_clean(); ?>
<?php ob_start(); ?>

<h2 style="width: 95%; margin-left: 10px;"><?=$Dictionnary["AvailableMatters"]; ?></h2>
<div class="resume_module" style="height: 70px;">
    <?=$Dictionnary["AvailableCredits"]; ?> : <?=$min_cred; ?> - <?=$max_cred; ?><br />
    <?=$Dictionnary["MandatoryCredits"]; ?> : <?=$man_min_cred; ?> - <?=$man_max_cred; ?><br />
    <?=$Dictionnary["OptionCredits"]; ?> : <?=$opt_min_cred; ?> - <?=$opt_max_cred; ?>
</div>
<div class="resume_module" style="height: 70px;">
    <?=$Dictionnary["AvailableMatters"]; ?> : <?=$total; ?><br />
    <?=$Dictionnary["MandatoryMatters"]; ?> : <?=$mandatory; ?><br />
    <?=$Dictionnary["OptionalMatters"]; ?> : <?=$option; ?>
</div>
<div class="resume_module" style="height: 70px;">
    <?=$Dictionnary["SubscribedCredits"]; ?> : <?=$min_cycle_credits; ?> - <?=$max_cycle_credits; ?><br />
</div>

<?=str_replace("###RESUME", ob_get_clean(), $Content); ?>

<?php if ($total == 0) { ?>
    <div style="width: 100%; text-align: center; margin-top: 20%; font-size: xx-large; font-style: italic;">
	 <?php echo $Dictionnary["NoAvailableMatter"]; ?>
    </div>
<?php } ?>
