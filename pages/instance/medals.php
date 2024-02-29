<h4><?=$Dictionnary["Medals"]; ?></h4>

<?php if (is_admin()) { ?>
    <?php $listed = []; ?>
    <?php foreach ($activity->medal as $medal) { ?>
	<?php $listed[] = $medal["codename"]; ?>
    <?php } ?>
    <input type="text" value="<?=implode(";", $listed); ?>" />
<?php } ?>

<div style="text-align: center;">
    <?php $no_text = false; ?>
    <?php foreach ($activity->medal as $medal) { ?>
	<?php if ($medal["role"] < 0) continue; ?>
	<?php
	// Si c'est un examen ou assimilé et qu'on est pas prof, pas inscrit
	// ou que l'heure de l'exam est pas arrivé, on cache les médailles
	if (in_array($activity->type, [5, 6, 7, 8, 9])) {
	    if ($activity->is_teacher == false && $activity->is_registered == false)
		break ;
	    if ($activity->subject_appeir_date != NULL && $activity->subject_appeir_date < now())
		break ;
	}
	?>
	<div class="activity_single_medal_box">
	    <?php require ("single_medal.php"); ?>
	</div>
    <?php } ?>
</div>
