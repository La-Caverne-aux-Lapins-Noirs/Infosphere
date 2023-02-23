<?php
require_once ("fetch_log.php");
require_once ("usual_operation.php");

if (!is_admin())
{
    http_response_code(404);
    die();
}

$result = "";
if (file_exists(__DIR__."/handle_request.php")
    && is_admin()
    && isset($_POST["action"]))
    require_once (__DIR__."/handle_request.php");

if (file_exists(__DIR__."/handle_parameters.php"))
    require_once (__DIR__."/handle_parameters.php");

require_once (__DIR__."/../error_net.php");

if ($result != "")
{
    echo "<h2>".$Dictionnary["Results"]."</h2>";
    echo '<div style="width: 100%; height: 600px; overflow: auto; border: 3px black solid; background-color: #ff69b4;">';
    $result = str_replace("\n", "<br/>", $result);
    $result = str_replace(" ", "&nbsp;", $result);
    echo $result;
    echo '</div>';
}
?>

<h2><?=$Dictionnary["Storage"]; ?></h2>
<div class="bar">
    <div class="fill_bar" style="float: left; width: <?=100-disk_free_space('./') / disk_total_space('./')*100; ?>%; background-color: <?=((disk_free_space('./')/disk_total_space('./')) >= 0.5 ? "green" : ((disk_free_space('./')/disk_total_space('./')) >= 0.2 ? "yellow" : "red")) ?>">
	<?=intval(100-disk_free_space('./') / disk_total_space('./')*100); ?>%
    </div>
    <div class="fill_bar" style="float: right; width: <?=(disk_free_space('./') / disk_total_space('./')*100); ?>%;">
	<?=intval(disk_free_space('./') / disk_total_space('./')*100); ?>%
    </div>
</div>

<p class="storage-text"><?=intval(disk_free_space('./')/1e+9); ?>Go restant</p>

<h2><?=$Dictionnary["Logs"]; ?></h2>

<div style="width: 100%;">
    <div style="width: 49%; float: left;">
	<table class="content_table">
	    <tr>
		<th rowspan="2">#</th>
		<th colspan="2"><?=$Dictionnary["User"]; ?></th>
		<th rowspan="2"><?=$Dictionnary["Date"]; ?></th>
		<th rowspan="2"><?=$Dictionnary["Type"]; ?></th>
		<th rowspan="2"><?=$Dictionnary["Message"]; ?></th>
		<th rowspan="2"><?=$Dictionnary["IP"]; ?></th>
	    </tr>
	    <tr>
		<td style="color: blue;">#</td>
		<td style="color: blue;"><?=$Dictionnary["Nickname"]; ?></td>
	    </tr>
	    <?php
	    $cnt = 0;
	    foreach (fetch_log() as $y) {
	    ?>
		<tr class="content_<?=$cnt++ % 2 ? "even" : "odd"; ?>">
		    <td><?=$y["id"]; ?></td>
		    <td><?=$y["id_user"]; ?></td>
		    <td><?=$y["user"]; ?></td>
		    <td><?=strftime("%d %b %Y à %H:%M:%S", strtotime($y["date"])); ?></td>
		    <td><?=$y["type"]; ?></td>
		    <td><?=$y["message"]; ?></td>
		    <td><?=$y["ip"]; ?></td>
		</tr>
	    </a>
	    <?php } ?>
	</table>
    </div>
    <div style="width: 49%; float: left; margin-left: 2%; height: 49%;">
	<table class="content_table">
	    <tr>
		<th style="width: 50%;">#</th>
		<th>Value</th>
	    </tr>
	    <?php foreach (db_select_all("* FROM configuration") as $v) { ?>
		<tr>
		    <td><?=$v["codename"]; ?></td>
		    <td>
			<?php if (strstr($v["codename"], "password") != null) { ?>
			    <span class="hidden_mouse">
			<?php } ?>
			<?=$v["value"]; ?>
			<?php if (strstr($v["codename"], "password") != null) { ?>
			    </span>
			<?php } ?>
		    </td>
		</tr>
	    <?php } ?>
	</table>
    </div>
    <div style="width: 49%; float: left; margin-left: 2%; height: 49%;">
	<table class="content_table">
	    <tr>
		<th style="width: 5%;">#</th>
		<th>SQL</th>
		<th style="width: 12%;"><img src="./res/configuration.png" /></th>
	    </tr>
	    <?php foreach ($Operations as $i => $ope) { ?>
		<tr style="border: 1px solid black;">
		    <td><?=$i; ?></td>
		    <td>
			<div style="overflow: auto; width; height: 200px; text-align: left;">
			    <?php highlight_file(__DIR__."/".$ope); ?>
			</div>
		    </td>
		    <td>
			<form method="post" action="index.php?<?=unrollget(); ?>">
			    <input type="hidden" name="action" value="execute_query" />
			    <input type="hidden" name="operation" value="<?=$i; ?>" />
			    <input type="submit" value="&#10003;" style="width: 100%; height: 200px;" />
			</form>
		    </td>
		</tr>
	    <?php } ?>
	</table>
    </div>
</div>

