<?php

function tabpanel(array $files, $position, $default, $listclass = "", $contentclass = "")
{
    extract($GLOBALS);

    $PanelScript = true;
    $id = uniqid();
    if (isset($_COOKIE[$position]))
    {
	$found = false;
	foreach ($files as $k => $val) {
	    if ($k == $default) {
		$found = true;
		break ;
	    }
	}
	if ($found)
	    $default = $_COOKIE[$position];
	else
	    $position = $default;
    }
    else
	$position = $default;
?>
    <div class="tabpanel">
	<div class="tablist">
	    <?php foreach ($files as $k => $val) { ?>
		<a onclick="dis<?=$id; ?>('tab_<?=md5($val); ?>', '<?=addslashes($k); ?>');">
		    <div class="<?=$listclass; ?>" style="width: <?=100 / count($files) - 0.1; ?>%;">
			<?=$k; ?>
		    </div>
		</a>
	    <?php } ?>
	</div>
	<div class="tabcontent">
	    <?php foreach ($files as $k => $val) { ?>
		<div id="tab_<?=md5($val); ?>" class="<?=$contentclass; ?>" style="visibility: <?=$k == $default ? "visible" : "hidden"; ?>;">
		    <?php require ($val); ?>
		</div>
	    <?php } ?>
	</div>
    </div>
    <script>
     var tablist_<?=md5($position); ?> = [
	 <?php foreach ($files as $k => $val) { ?>
	 "tab_<?=md5($val); ?>",
	 <?php } ?>
     ];
     function dis<?=$id; ?>(lst, label)
     {
	 var i;

	 for (i = 0; i < tablist_<?=md5($position); ?>.length; ++i)
	 {
	     document.getElementById(tablist_<?=md5($position); ?>[i]).style.visibility = "hidden";
	 }
	 setCookie('<?=addslashes($position); ?>', label);
	 document.getElementById(lst).style.visibility = "visible";
     }
    </script>
<?php
}

