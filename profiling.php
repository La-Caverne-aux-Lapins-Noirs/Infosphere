<?php
if (!isset($OriginalUser) || $OriginalUser["id"] > 2)
    return ;
?>
<?php ob_start(); ?>
<?php
function sort_by_delay($a, $b)
{
    return ($b["delay"] * 1000000 - $a["delay"] * 1000000);
}
?>
<html>
    <head>
	<title>Infosphere: Profilage des performences</title>
	<meta charset="utf8" />
	<style>
	 table tr td
	 {
	     text-align: center;
	     vertical-align: middle;
	     border: 1px solid black;
	 }
	</style>
    </head>
    <body>
	<h1>Profilage</h1>
	<h2>Résumé</h2>
	<ul>
	    <li><b>Durée totale du chargement:</b> <?=($now = microtime(true)) - $PHPPerf; ?></li>
	    <li><b>Nombre de requêtes SQL:</b> <?=$DBCount; ?></li>
	    <li><b>Cumul des requêtes SQL:</b> <?=$DBPerf; ?></li>
	    <?php if (count($DBHistory) != 0) { ?>
		<li><b>Durée moyenne d'une requête:</b> <?=$DBPerf / count($DBHistory); ?></li>
	    <?php } ?>
	    <li><b>Durée hors requêtes SQL:</b> <?=$now - $PHPPerf - $DBPerf; ?></li>
	</ul>

	<h2>Temps par type de requete:</h2>
	<?php usort($DBMerge, "sort_by_delay"); ?>
	<table>
	    <tr>
		<th>Temps total</th>
		<th>Nombre de requêtes</th>
		<th>Chemin d'appel</th>
	    </tr>
	    <tr>
		<?php foreach ($DBMerge as $v) { ?>
		    <td><?=sprintf("%.6f", $v["delay"]); ?></td>
		    <td><?=$v["count"]; ?></td>
		    <td><?=$v["back"]; ?></td>
	    </tr>
	    <tr>
		<td colspan="3">
		    <?php if (strstr($v["query"], "SELECT")) { ?>
			<?=$v["query"]; ?>
		    <?php } else { ?>
			Requete non SELECT
		    <?php } ?>
		</td>
	    </tr>
		<?php } ?>
	</table>

	<h2>Historique des requetes:</h2>
	<?php echo "- Désactivé- "; // usort($DBHistory, "sort_by_delay"); ?>
	<?php if (0) foreach ($DBHistory as $v) { ?>
	    <?=sprintf("%.6f", $v["delay"])." ".substr($v["query"], 0, 64)."\n"; ?>
	<?php } ?>
    </body>
</html>
<?php
$out = ob_get_clean();
@file_put_contents("profiling.htm", $out);
?>
