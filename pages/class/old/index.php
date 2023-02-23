<?php

if ($User == NULL)
    return ;
require ("fetch_support.php");

get_user_promotions($User);
$support = fetch_support($User);

?>

<div class="module_menu" style="height: 97%;">
    <h2><?=$Dictionnary["Elearning"]; ?></h2>
    <a href="index.php?p=<?=$Position; ?>">
	<?=$Dictionnary["SeeAvailableClasses"]; ?>
    </a>
    <ul>
	<?php foreach ($support as $sup) { // LISTE DES MATIERES ?>
	    <li>
		<a href="index.php?p=TopGalleryMenu&amp;a=<?=$sup["data"]["id"]; ?>">
		    <?=$sup["data"]["class_name"]; ?>
		</a>
		<?php if ($sup["data"]["id"] == try_get($_GET, "a", -1)) { ?>
		    <ul style="padding-left: 20px;">
			<?php foreach ($sup["content"] as $cnt) { ?>
			    <li>
				<a href="index.php?p=TopGalleryMenu&amp;a=<?=$sup["data"]["id"]; ?>&amp;b=<?=$cnt["id_class_asset"]; ?>">
				    <?=$cnt["asset_name"]; ?>
				</a>
			    </li>
			<?php } ?>
		    </ul>
		<?php } ?>
	    </li>
	<?php } ?>
    </ul>
</div>
<div class="module_body" style="height: 97%;">
    <?php if (isset($support[try_get($_GET, "a", -1)])) { ?>

	<?php if (!isset($support[try_get($_GET, "a", -1)]["content"][try_get($_GET, "b", -1)])) { // BROWSER ?>

	    <a href="index.php?p=<?=$Position; ?>">
		<div style="width: 100%; height: 5%; font-size: xx-large;">
		    &larr;
		</div>
	    </a>
	    <?php foreach ($support[$_GET["a"]]["content"] as $cnt) { ?>
		<div class="module_block">
		    <h2 style="font-size: large;"><?=$cnt["asset_name"]; ?></h2>
		    <p>
			<a href="index.php?<?=unrollget(["b" => $cnt["id_class_asset"]]); ?>">
			    <?=$Dictionnary["SeeTheClass"]; ?>
			</a>
		    </p>
		</div>
	    <?php } ?>

	<?php } else { // PLAYER ?>
	    <?php
	    // Partie superieure: contenu
	    $brow = &$support[$_GET["a"]]["content"][$_GET["b"]];
	    ?>
	    <a href="index.php?p=<?=$Position; ?>&amp;a=<?=$_GET["a"]; ?>">
		<div style="width: 100%; height: 5%; font-size: xx-large;">
		    &larr;
		</div>
	    </a>
	    <iframe
		width="100%"
		height="75%"
		frameborder="0"
		allowfullscreen
		<?php
		if ($brow["asset_content"] != NULL)
		{
		?>
		src="index.php?ressource=<?=$brow["asset_link"]; ?>"
		<?php
		}
		else if (substr($brow["asset_link"], 0, 4) == "http")
		{
		?>
		src="<?=$brow["asset_link"]; ?>"
		<?php
		}
		else if (strlen($brow["asset_link"]) < 15) // YouTube
		{
		?>
		src="https://www.youtube.com/embed/<?=$brow["asset_link"]; ?>"
		title="YouTube video player"
		allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
		<?php
		}
		else // VideoSphere
		{
		?>
		sandbox="allow-same-origin allow-scripts allow-popups"
		src="https://video.ecole-89.com/videos/embed/<?=$brow["asset_link"]; ?>"
		<?php
		}
		?>
	    ></iframe>

	    <?php // Partie inferieure: playlist ?>
	    <div style="width: 98%; height: 18%; overflow-x: auto; column-count: 3; padding-left: 2%; padding-top: 2%;">
		<ul>
		    <?php $cnt = 0; $b = $_GET["b"]; ?>
		    <?php foreach ($support[$_GET["a"]]["content"] as $sup) { ?>
			<li><a href="index.php?<?=unrollget(["b" => $sup["id_class_asset"]]); ?>">
			    <?php if ($b == $sup["id_class_asset"]) { ?>
				<b>
			    <?php } ?>
			    <?=++$cnt; ?> - <?=$sup["asset_name"]; ?>
			    <?php if ($b == $sup["id_class_asset"]) { ?>
				</b>
			    <?php } ?>
			</a></li>
		    <?php } ?>
		</ul>
	    </div>
	<?php } ?>

    <?php } else { // LISTE DES MATIERES ?>
	<?php foreach ($support as $sup) { ?>
	    <div class="module_block">
		<h2 style="font-size: large;"><?=$sup["data"]["class_name"]; ?></h2>
		<p>
		    <?=$sup["data"]["class_description"]; ?>
		    <br />
		    <a href="index.php?<?=unrollget(["a" => $sup["data"]["id"]]); ?>">
			<?=$Dictionnary["SeeTheClass"]; ?>
		    </a>
		</p>
	    </div>
	<?php } ?>
    <?php } ?>
</div>
