<?php

/*
** files: tableau [ONGLET => FICHIER]
** position: marqueur unique du tabpanel
** default: ONGLET
*/
function tabpanel(array $files, $position, $default, $listclass = "", $contentclass = "", $add_data = [])
{
    extract($GLOBALS);

    $PanelScript = true;
    $idd = uniqid();
?>
    <div class="tabpanel" data-tabpanel="<?=$idd; ?>">
	<div class="tablist">
	    <?php foreach ($files as $k => $val) { ?>
		<?php
		$tab_hash = md5($k);
		$button_id = "dis".$idd."_".$tab_hash;
		$panel_selector = '[data-tabpanel-content="'.$idd.'"]';
		$button_selector = '[data-tabpanel-button="'.$idd.'"]';
		$onclick =
		    "localStorage.setItem(".json_encode($position).", ".json_encode($tab_hash).");".
		    "document.querySelectorAll(".json_encode($panel_selector).").forEach(function(n){n.style.display=(n.getAttribute('data-tabpanel-tab')===".json_encode($tab_hash).")?'block':'none';});".
		    "document.querySelectorAll(".json_encode($button_selector).").forEach(function(n){n.classList.remove('selected');});".
		    "document.getElementById(".json_encode($button_id).").classList.add('selected');".
		    "return false;"
		;
		?>
		<a href="#" onclick="<?=htmlspecialchars($onclick, ENT_QUOTES); ?>">
		    <div
			class="<?=$listclass; ?> disbut<?=$idd; ?> <?=$default == $k ? "selected" : ""; ?>"
			style="width: <?=100 / count($files) - 0.1; ?>%;"
			id="<?=$button_id; ?>"
			data-tabpanel-button="<?=$idd; ?>"
			data-tabpanel-tab="<?=$tab_hash; ?>"
		    >
			<?=$k; ?>
		    </div>
		</a>
	    <?php } ?>
	</div>
	<div class="tabcontent">
	    <?php $i = 0; ?>
	    <?php foreach ($files as $k => $val) { ?>
		<?php $tab_hash = md5($k); ?>
		<div
		    id="<?=$tab_hash; ?>"
		    class="<?=$contentclass; ?>"
		    style="display: <?=$default == $k ? "block" : "none"; ?>;"
		    data-tabpanel-content="<?=$idd; ?>"
		    data-tabpanel-tab="<?=$tab_hash; ?>"
		>
		    <?php if (!isset($add_data[$i])) { ?>
			<?php $tab_data = NULL; ?>
		    <?php } else { ?>
			<?php $tab_data = $add_data[$i]; ?>
		    <?php } ?>
		    <?php require ($val); ?>
		</div>
		<?php $i += 1; ?>
	    <?php } ?>
	</div>
    </div>
    <script>
     var tablist_<?=md5($position); ?> = [
	 <?php foreach ($files as $k => $val) { ?>
	     "<?=md5($k); ?>",
	 <?php } ?>
     ];
     function dis<?=$idd; ?>(lst)
     {
	 var i;

	 localStorage.setItem('<?=addslashes($position); ?>', lst);
	 for (i = 0; i < tablist_<?=md5($position); ?>.length; ++i)
	 {
	     var nod = document.getElementById(tablist_<?=md5($position); ?>[i]);

	     if (nod.id == lst)
		 nod.style.display = "block";
	     else
		 nod.style.display = "none";
	 }
	 var all_buttons = document.getElementsByClassName("disbut<?=$idd; ?>");

	 Array.prototype.forEach.call(all_buttons, function(el) {
	     el.classList.remove("selected");
	 });
	 var but = document.getElementById("dis<?=$idd; ?>" + "_" + lst);

	 but.classList.add("selected");
     }
     var ls;

     if ((ls = localStorage.getItem('<?=addslashes($position); ?>')) == null)
	 dis<?=$idd; ?>('<?=md5($default); ?>');
     else
	 dis<?=$idd; ?>(ls);
     // console.log(ls);
    </script>
<?php
}

