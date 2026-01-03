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
    <div class="tabpanel">
	<div class="tablist">
	    <?php foreach ($files as $k => $val) { ?>
		<a onclick="dis<?=$idd; ?>('<?=md5($k); ?>');">
		    <div
			class="<?=$listclass; ?> disbut<?=$idd; ?> <?=$default == $k ? "selected" : ""; ?>"
			style="width: <?=100 / count($files) - 0.1; ?>%;"
			id="dis<?=$idd."_".md5($k); ?>"
		    >
			<?=$k; ?>
		    </div>
		</a>
	    <?php } ?>
	</div>
	<div class="tabcontent">
	    <?php $i = 0; ?>
	    <?php foreach ($files as $k => $val) { ?>
		<div
		    id="<?=md5($k); ?>"
		    class="<?=$contentclass; ?>"
		    style="display: none;"
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

