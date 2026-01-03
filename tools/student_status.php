
<?php
$curpos = (($logcursor[0] - $logcursor[1]) / ($logcursor[2] - $logcursor[1])) * 100;
$refpos = (($logcursor[3] - $logcursor[1]) / ($logcursor[2] - $logcursor[1])) * 100;
?>
<div class="logcursor">
    <div class="label"><?=$label; ?></div>
    <div class="minvalue">
	<!--<?=$logcursor[1]; ?>-->
    </div>
    <div
	class="curvalue"
	style="left: calc(<?=$curpos; ?>% - 15px);"
    >
	<?=(int)$logcursor[0]; ?>
    </div>
    <div
	class="line curline"
	style="left: calc(<?=$curpos; ?>% - 3px);"
    ></div>
 
    <div
	class="refvalue"
	style="left: calc(<?=$refpos; ?>% - 15px);"
    >
	<?=(int)$logcursor[3]; ?>
    </div>
    <div
	class="line"
	style="left: calc(<?=$refpos; ?>% - 3px);"
    ></div>

    <div class="maxvalue">
	<!--<?=$logcursor[2]; ?>-->
    </div>
    <div
	class="logbarl"
	style="left: 0px; width: <?=$refpos; ?>%;"
    ></div>
    <div
	class="logbarr"
	style="right: 0px; width: <?=100 - $refpos; ?>%;"
    ></div>
</div>

