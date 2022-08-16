<div class="full_box_with_title final_box">
    <h4><?=$Dictionnary["OtherSessions"]; ?></h4>
    <div>
	<?php $i = 0; $max = count($activity->session) - 1; ?>
	<?php foreach ($activity->session as $s) { ?>
	    <?php if ($s->id != $activity->unique_session->id) { ?>
		<a href="index.php?<?=unrollget(["b" => $s->id]); ?>">
		    <p class="button_paragraph">
			<?=litteral_date($s->begin_date); ?> - <?=datex("H:i", $s->end_date); ?>
			<br />
			<?=$Dictionnary["Room"]; ?> :
			<?php if (count($s->room)) { ?>
			    <?php foreach ($s->room as $r) { ?>
				<?=@$r["name"]; ?>
			    <?php } ?>
			<?php } else { ?>
			    <?=$Dictionnary["None"]; ?>
			<?php } ?>
		    </p>
		</a>
		<?php $i += 1; ?>
	    <?php } ?>
	<?php } ?>
    </div>
</div>
