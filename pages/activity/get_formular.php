<?php

$activity = new FullActivity;
$activity->build($id_module);

if ($activity->parent_activity != -1)
{
    $module = db_select_one("* FROM activity WHERE id = ".$activity->parent_activity);
    $short_codename = substr($activity->codename, strlen($module["codename"]));
    if (substr($short_codename, 0, 1) == "-")
	$short_codename = substr($short_codename, 1);
}
else
    $short_codename = $activity->codename;
?>
<form method="post" action="<?=unrollurl(); ?>" enctype="multipart/form-data" style="height: 100%; width: 100%;">
    <input type="hidden" name="action" value="edit_activity" />
    <input type="hidden" name="is_template" value="<?=$activity->is_template; ?>" />
    <input type="hidden" name="parent_activity" value="<?=$activity->parent_activity; ?>" />

    <table style="width: 100%; height: 100%;" class="edit_formular">
	<tr><td colspan="4" style="height: 25px; text-align: center;">
	    <h1>
		<?php if ($activity->is_template) { ?>
		    <?=$Dictionnary["ActivityTemplate"]; ?>
		<?php } else { ?>
		    <?=$Dictionnary["ActivityInstance"]; ?>
		<?php } ?>
	    </h1>
	    <h1>
		<?=$Dictionnary["EditTheActivity"]; ?>
	    </h1>
	</td></tr>
	<tr><td>
	    <h5><?=$Dictionnary["MainInformations"]; ?></h5>
	    <div class="form_entry">

		<?php if ($activity->parent_activity == -1) { ?>

		<?php } else { ?>
		    <div>
			<label for="min_team_size"><?=$Dictionnary["MinTeamSize"]; ?></label>
			<input type="text" name="min_team_size" value="<?=$activity->min_team_size; ?>" />
		    </div>
		    <div>
			<label for="max_team_size"><?=$Dictionnary["MaxTeamSize"]; ?></label>
			<input type="text" name="max_team_size" value="<?=$activity->max_team_size; ?>" />
		    </div>
		    <div>
			<?php
			if ($activity->reference_activity != NULL)
			    $ref = db_select_one("
                                 codename
                                 FROM activity
                                 WHERE id = {$activity->reference_activity}
			    ");
			else
			    $ref["codename"] = "";
			?>
			<label for="reference_activity"><?=$Dictionnary["ReferenceActivity"]; ?></label>
			<input type="text" name="reference_activity" value="<?=$ref["codename"]; ?>" />
		    </div>
		    <div>
			<label for="repository_name"><?=$Dictionnary["RepositoryName"]; ?></label>
			<input type="text" name="repository_name" value="<?=$activity->repository_name; ?>" />
		    </div>
		    <div>
			<label for="reference_repository"><?=$Dictionnary["ReferenceRepository"]; ?></label>
			<input type="text" name="reference_repository" value="<?=$activity->reference_repository; ?>" />
		    </div>
		    <div>
			<label for="mark"><?=$Dictionnary["Money"]; ?></label>
			<input type="text" name="mark" value="<?=$activity->mark; ?>" />
		    </div>
		    <div>
			<label for="estimated_work_duration"><?=$Dictionnary["EstimatedWorkDuration"]; ?></label>
			<input type="text" name="estimated_work_duration" value="<?=$activity->estimated_work_duration; ?>" />
		    </div>
		    <div>
			<label for="slot_duration"><?=$Dictionnary["SlotDuration"]; ?></label>
			<input type="text" name="slot_duration" value="<?=$activity->slot_duration; ?>" />
		    </div>
		<?php } ?>
		<div>
		    <label for="allow_unregistration"><?=$Dictionnary["AllowUnregistration"]; ?></label>
		    <select name="allow_unregistration">
			<option value="1" <?=$activity->allow_unregistration ? "selected" : ""; ?>><?=$Dictionnary["Yes"]; ?></option>
			<option value="0" <?=$activity->allow_unregistration ? "" : "selected"; ?>><?=$Dictionnary["No"]; ?></option>
		    </select>
		</div>
		<div>
		    <label for="subscription"><?=$Dictionnary["Subscription"]; ?></label>
		    <select name="subscription">
			<option value="0" <?=$activity->subscription == 0 ? "selected" : ""; ?>><?=$Dictionnary["Manual"]; ?></option>
			<option value="1" <?=$activity->subscription == 1 ? "selected" : ""; ?>><?=$Dictionnary["Mandatory"]; ?></option>
			<option value="2" <?=$activity->subscription == 2 ? "selected" : ""; ?>><?=$Dictionnary["Automatic"]; ?></option>
		    </select>
		</div>
	    </div>
	</td><td>
	    <h5><?=$Dictionnary["Date"]; ?></h5>
	    <div style="text-align: center;" class="date_entry">
		<?php
		if ($activity->parent_activity == -1)
		    $dates = ["emergence", "done", "registration", "close"];
		else
		{
		    $dates = [
			"registration",
			"close",
			"subject" => "subject_appeir_date",
			"pickup",
			"subject_remove" => "subject_disappeir_date"
		    ];
		}
		foreach ($dates as $idx => $dtype)
		{
		    if (is_number($idx))
		    {
			$composer = $dtype;
			$aggreg = $dtype;
		    }
		    else
		    {
			$composer = $idx;
			$aggreg = $dtype;
		    }
		?>
		    <div>
			<label><?=$Dictionnary[ucfirst($composer)."Date"]; ?></label><br />
			<?php
			if ($Position == "ActivitiesMenu")
			    print_weekday_selector(
				@$_POST[$aggreg."_date"],
				[
				    "week_$composer\_date",
				    "day_$composer\_date",
				    "hour_$composer\_date"
				]
			    );
			else
			    print_datetime($aggreg."_date", $_POST);
			?>
		    </div>
		<?php } ?>
	    </div>
	</td><td colspan="2">
	    <h5><?=$Dictionnary["Medals"]; ?></h5>
	    <div>
		<div style="display: inline-block;
			    width: 30%;
			    background-color: white;
			    border-radius: 2px;
			    position: relative;
			    margin-bottom: 5px;
			    margin-left: 2%;
			    font-size: x-small;
			    text-align: center;
			    "
		>
		    <div style="width: 100%; height: 40px; float: left; text-align: center;">
			<br />
			<?=$Dictionnary["AddAMedal"]; ?>
		    </div>
		    <div style="float: left; width: 100%;">
			<input
			    type="text"
			    name="add_medal"
			    placeholder="<?=$Dictionnary["CodeName"]; ?>"
			    style="width: 90%;"
			/>
		    </div>
		    <div style="float: left; width: 100%; margin-bottom: 5px;">
			<input
			    type="button"
			    onclick=""
			    value="+"
			    style="width: 90%;"
			/>
			<br />
		    </div>
		</div>

		<?php
		foreach (sort_by_medal_grade($activity->medal) as $medal)
		{
		    $medx[] = $medal["codename"];
		    $color = [
			"rgba(255, 255, 0, 0.3)",
			"rgba(255, 255, 255, 0.3)",
			"rgba(0, 255, 0, 0.3)",
			"rgba(0, 0, 255, 0.3)",
			"rgba(255, 0, 0, 0.3)"
		    ];
		    $color = $color[$medal["role"]];
		?>
		    <div style="display: inline-block;
				width: 30%;
				background-color: <?=$color; ?>;
				border-radius: 2px;
				position: relative;
				margin-bottom: 5px;
				margin-left: 2%;
				font-size: x-small;
				"
		    >
			<a href="index.php?p=MedalsMenu&amp;a=<?=$medal["id"]; ?>">
			    <div style="width: 100%; height: 40px; float: left; text-align: center;">
				<div
				    style="
					   <?php if ($medal["icon"] != "") { ?>
					   width: 40px; height: 40px;
					   background-image: url('<?=$medal["icon"]; ?>');
					   <?php } else { ?>
					   width: 160px; height: 40px;
					   background-image: url('genicon.php?function=<?=$medal["codename"]; ?>');
					   box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.9);
					   border-radius: 10px;
					   <?php } ?>
					   background-size: 100% 100%;
					   display: inline-block;
					   "
				    title="<?=$medal["name"]; ?>: <?=$medal["description"]; ?>"
				>
				</div>
			    </div>
			</a>

			<?php $label = ["Bonus", "GradeD", "GradeC", "GradeB", "GradeA"]; ?>
			<?php for ($i = 4; $i >= 0; --$i) { ?>
			    <div style="float: left; width: 32%;">
				<input
				    type="radio"
				    name="role_<?=$medal["codename"]; ?>"
				    value="<?=$i; ?>"
				    id="<?=$label[$i]; ?>_<?=$medal["codename"]; ?>"
				    <?=$medal["role"] == $i ? "checked" : ""; ?>
				/>
				<label for="<?=$label[$i]; ?>_<?=$medal["codename"]; ?>">
				    <?=$Dictionnary[$label[$i]]; ?>
				</label>
			    </div>
			<?php } ?>
			<div style="float: left; width: 32%;">
			    <input
				type="checkbox"
				name="local"
				<?=$medal["local"] ? "checked" : ""; ?>
			    />
			    <label for="local" style="margin-right: 5px;">
				<?=$Dictionnary["Local"]; ?>
			    </label>
			</div>
			<div style="float: left; width: 32%;">
			    <input
				type="checkbox"
				name="strong"
				<?=$medal["strong"] ? "checked" : ""; ?>
			    />
			    <label for="strong" style="margin-right: 5px;">
				<?=$Dictionnary["Strong"]; ?>
			    </label>
			</div>
		    </div>
		<?php } ?>
	    </div>
	</td></tr><tr><td>
	    <h5><?=$Dictionnary["Files"]; ?></h5>
	    <div class="form_entry">

		<div>
		    <label for="configuration_file"><?=$Dictionnary["ConfigurationFile"]; ?></label>
		    <input type="file" name="configuration_file" />
		</div>
		<?php if (file_exists($activity->configuration)) { ?>
		    <div
			class="form_entry download_button"
			onclick="window.open('<?=$activity->configuration; ?>', '_blank');"
		    >
			<?=$Dictionnary["DownloadConfiguration"]; ?>
		    </div>
		    <div
			class="form_entry delete_button"
			onclick="delete_file(<?=$activity->id; ?>, 'configuration');"
		    >&#10007;</div>
		<?php } ?>

		<div>
		    <label for="subject_file"><?=$Dictionnary["SubjectFile"]; ?></label>
		    <input type="file" name="subject_file" />
		</div>
		<?php if (file_exists($activity->subject)) { ?>
		    <div
			class="form_entry download_button"
			onclick="window.open('<?=$activity->subject; ?>', '_blank');"
		    >
			<?=$Dictionnary["DownloadSubject"]; ?>
		    </div>
		    <div
			class="form_entry delete_button"
			onclick="delete_file(<?=$activity->id; ?>, 'subject');"
		    >&#10007;</div>
		<?php } ?>

		<div>
		    <label for="wallpaper_file"><?=$Dictionnary["WallpaperFile"]; ?></label>
		    <input type="file" name="wallpaper_file" />
		</div>
		<?php if (file_exists($activity->wallpaper)) { ?>
		    <div
			class="form_entry download_button"
			onclick="window.open('<?=$activity->wallpaper; ?>', '_blank');"
		    >
			<?=$Dictionnary["DownloadWallpaper"]; ?>
		    </div>
		    <div
			class="form_entry delete_button"
			onclick="delete_file(<?=$activity->id; ?>, 'wallpaper');"
		    >&#10007;</div>
		<?php } ?>

		<div>
		    <label for="ressource_files"><?=$Dictionnary["RessourceFiles"]; ?></label>
		    <input type="file" name="ressource_files" />
		</div>
		<?php
		if (($dir = @scandir($activity->ressources)) != false)
		{
		    foreach ($dir as $d)
		    {
			$file = $activity->ressources.$d;
			if ($d == "index.htm" || substr($d, 0, 1) == ".")
			    continue ;
		?>
		    <div
			class="form_entry download_button"
			onclick="window.open('<?=$file; ?>', '_blank');"
		    >
			<?=$d; ?>
		    </div>
		    <div
			class="form_entry delete_button"
			onclick="delete_file(<?=$activity->id; ?>, '<?=$file; ?>');"
		    >&#10007;</div>
		<?php
		    }
		}
		?>
	    </div>
	</td><td>
	    <h5>
		<?=$Dictionnary["Targets"]; ?>
	    </h5>
	    <div class="form_entry">
		<div>
		    <label for="manage_cycle"><?=$Dictionnary["Cycle"]; ?></label>
		    <input type="text" name="manage_cycle" placeholder="<?=$Dictionnary["CodeName"]; ?>" />
		</div>
		<?php foreach ($activity->cycle as $t) { ?>
		    <a
			target="_blank"
			href="index.php?p=CycleMenu&amp;a=<?=$t["id"]; ?>"
		    >
			<div class="form_entry download_button">
			    <?=$t["codename"]; ?>
			</div>
		    </a>
		    <div
			class="form_entry delete_button"
			onclick="unlink('cycle', <?=$activity->id; ?>, <?=$t["id"]; ?>);"
		    >&#10007;</div>
		<?php } ?>

		<div>
		    <label for="manage_teacher"><?=$Dictionnary["Teacher"]; ?></label>
		    <input type="text" name="manage_teacher" placeholder="<?=$Dictionnary["CodeName"]; ?>" />
		</div>
		<?php foreach ($activity->teacher as $t) { ?>
		    <a
			target="_blank"
			href="index.php?p=ProfileMenu&amp;a=<?=$t["id"]; ?>"
		    >
			<div class="form_entry download_button">
			    <?=$t["codename"]; ?><?=$t["ref"] ? "" : ""; ?>
			</div>
		    </a>
		    <div
			class="form_entry delete_button"
			onclick="unlink('teacher', <?=$activity->id; ?>, <?=$t["id"]; ?>);"
		    >&#10007;</div>
		<?php } ?>

		<?php if ($Position == "InstancesMenu") { ?>
		    <div>
			<label for="manage_subscription"><?=$Dictionnary["Students"]; ?></label>
			<input type="text" name="manage_subscription" placeholder="<?=$Dictionnary["CodeName"]; ?>" />
		    </div>
		    <?php foreach ($activity->team as $t) { ?>
			<div
			    class="form_entry download_button"
			    style="width: 100%;"
			>
			    <?php foreach ($t["user"] as $usr) { ?>
				<a
				    target="_blank"
				    href="index.php?p=ProfileMenu&amp;a=<?=$t["id"]; ?>"
				>
				    <div class="form_entry download_button">
					<?=$usr["codename"]; ?>
				    </div>
				</a>
				<div
				    class="form_entry delete_button"
				    onclick="unlink('teacher', <?=$activity->id; ?>, <?=$t["id"]; ?>);"
				>&#10007;</div>
			    <?php } ?>
			</div>
		    <?php } ?>
		<?php } ?>
	    </div>
	</td><td>
	    <h5><?=$Dictionnary["Class"]; ?></h5>
	    <div class="form_entry">
		<div>
		    <label for="manage_class"><?=$Dictionnary["Class, Activity, Asset"]; ?></label>
		    <input type="text" name="manage_class" placeholder="<?=$Dictionnary["CodeName"]; ?>" />
		</div>
		<?php foreach ($activity->support as $t) { ?>
		    <a
			target="_blank"
			<?php if ($t["type"] == 0) { ?>
			 href="index.php?p=<?=$t["position"]; ?>&amp;a=<?=$t["id_class"]; ?>"
			<?php } else if ($t["type"] == 1) { ?>
			 href="index.php?p=<?=$t["position"]; ?>&amp;a=<?=$t["id_class"]; ?>&amp;b=<?=$t["id_class_asset"]; ?>"
			<?php } else if ($t["type"] == 2) { ?>
			 href="index.php?p=<?=$t["position"]; ?>&amp;a=<?=$t["id_subactivity"]; ?>"
			<?php } ?>
		    >
			<div class="form_entry download_button">
			    <?=$t["prefix"]; ?><?=$t["codename"]; ?>
			</div>
		    </a>
		    <div
			class="form_entry delete_button"
			onclick="unlink('class', <?=$activity->id; ?>, <?=$t["id"]; ?>);"
		    >&#10007;</div>
		<?php } ?>
	    </div>
	</td><td>
	    <h5>Actions</h5>
	    <div>
		<?php
		if ($Position == "ActivitiesMenu") // On est sur les templates
		    require_once ("template_instantiation_formular.php");
		else
		    require_once ("instance_edit_formular.php");
		?>

		<?php if ($activity->parent_activity != -1) { // C'est une activité ?>

		    <div>
			<label for="suffix"><?=$Dictionnary["Instantiate"]; ?></label>
			<input type="text" name="suffix" placeholder="<?=$Dictionnary["Suffix"]; ?>" />
			<input type="datetime-local" name="first_day" />
		    </div>

		    <?php
		    $instances = db_select_all("
  		          activity.id, activity.codename
 		          FROM activity
		          WHERE id_template = {$content->parent_activity} AND deleted = 0
		    ");
		    ?>
		    <select name="parent">
			<?php foreach ($instances as $t) { ?>
			    <option value="<?=$t["id"]; ?>"><?=$t["codename"]; ?></option>
			<?php } ?>
		    </select>

		<?php } else { ?>

		    <div style="width: 210px;
				background-color: gray;
				display: inline-block;
				color: white;"
		    >
			<?=$Dictionnary["Instances"]; ?>
		    </div>
		    <?php
		    $subs = db_select_all("
		           id, codename, template_link
		           FROM activity
		           WHERE id_template = $content->id
			   AND (done_date IS NULL OR done_date > NOW()) AND deleted = 0
		    ");
		    ?>
		    <?php foreach ($instances as $t) { ?>
			<a
			    target="_blank"
				    href="index.php?p=ProfileMenu&amp;a=<?=$t["id"]; ?>"
			>
			    <div class="form_entry download_button" style="width: 100%;">
				<?=$t["codename"]; ?><?=$t["ref"] ? "" : ""; ?>
			    </div>
			</a>
		    <?php } ?>

		<?php } ?>
	    </div>
	</td></tr><tr><td colspan="4" style="height: 160px;">
	    <?php
	    forge_language_formular([
		"name" => "text",
		"description" => "textarea"
	    ], $_POST);
	    ?>
	</td></tr><tr><td colspan="4" style="height: 30px;">
	    <input
		type="button"
		onclick="silent_submit(this);"
		value="&#10003;"
		style="height: 29px; width: 100%;"
	    />
	</td></tr>
    </table>
</form>
