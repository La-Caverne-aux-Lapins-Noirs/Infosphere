<?php

/*
** files: tableau [ONGLET => FICHIER]
** position: marqueur unique du tabpanel
** default: ONGLET
*/
function tabpanel(array $files, $position, $default, $listclass = "", $contentclass = "")
{
    extract($GLOBALS);

    $PanelScript = true;
    $idd = uniqid();
?>
    <div class="tabpanel">
	<div class="tablist">
	    <?php foreach ($files as $k => $val) { ?>
		<a onclick="dis<?=$idd; ?>('<?=md5($k); ?>');">
		    <div class="<?=$listclass; ?>" style="width: <?=100 / count($files) - 0.1; ?>%;">
			<?=$k; ?>
		    </div>
		</a>
	    <?php } ?>
	</div>
	<div class="tabcontent">
	    <?php foreach ($files as $k => $val) { ?>
		<div
		    id="<?=md5($k); ?>"
		    class="<?=$contentclass; ?>"
		    style="visibility: hidden;"
		>
		    <?php require ($val); ?>
		</div>
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
		 nod.style.visibility = "visible";
	     else
		 nod.style.visibility = "hidden";
	 }
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

