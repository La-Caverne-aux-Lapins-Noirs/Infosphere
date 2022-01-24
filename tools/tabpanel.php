<?php

function tabpanel(array $files, $position, $default, $listclass = "", $contentclass = "")
{
    extract($GLOBALS);

    $PanelScript = true;
    $id = uniqid();
    if (isset($_COOKIE[$position]))
    {
	$found = false;
	foreach ($files as $k => $v) {
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
	    <?php foreach ($files as $k => $v) { ?>
		<a onclick="dis<?=$id; ?>('tab_<?=md5($v); ?>', '<?=addslashes($k); ?>');">
		    <div class="<?=$listclass; ?>" style="width: <?=100 / count($files) - 0.1; ?>%;">
			<?=$k; ?>
		    </div>
		</a>
	    <?php } ?>
	</div>
	<div class="tabcontent">
	    <?php foreach ($files as $k => $v) { ?>
		<div id="tab_<?=md5($v); ?>" class="<?=$contentclass; ?>" style="visibility: <?=$k == $default ? "visible" : "hidden"; ?>;">
		    <?php require ($v); ?>
		</div>
	    <?php } ?>
	</div>
    </div>
    <script>
     var tablist_<?=md5($v); ?> = [
	 <?php foreach ($files as $k => $v) { ?>
	 "tab_<?=md5($v); ?>",
	 <?php } ?>
     ];
     function dis<?=$id; ?>(lst, label)
     {
	 var i;
	 for (i = 0; i < tablist_<?=md5($v); ?>.length; ++i)
	 {
	     document.getElementById(tablist_<?=md5($v); ?>[i]).style.visibility = "hidden";
	 }
	 setcookie('<?=addslashes($position); ?>', label);
	 document.getElementById(lst).style.visibility = "visible";
     }
    </script>
<?php
}

