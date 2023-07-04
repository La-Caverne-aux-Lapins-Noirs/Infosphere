<h2 style="text-align: left;">
    <?=$Dictionnary["LastAcquiredMedals"]; ?>:
</h2>
<br />
<?php
$medals = db_select_all("
    medal.*,
    medal.{$Language}_name as name,
    medal.{$Language}_description as description
    FROM user_medal
    LEFT JOIN medal ON user_medal.id_medal = medal.id
    WHERE user_medal.id_user = {$User["id"]}
      AND user_medal.insert_date >= '".db_form_date(time() - 60 * 60 * 24 * 2)."'
      ");
$medals = [];

foreach ($medals as $med)
{
    $med["icon"] = $Configuration->MedalsDir($med["codename"])."icon.png";
    if (strlen($med["icon"]) == 0)
	continue ;
?>
    <a href="index.php?p=MedalsMenu&amp;a=<?=$med["id"]; ?>" style="text-decoration: none;">
	<img
	    src="<?=$med["icon"]; ?>"
	    style="
		 width: 75px;
		 height: 75px;
		 <?php // border-radius: 50px; ?>
		 "
	    alt="<?=medal_tooltip($med); ?>"
	    title="<?=medal_tooltip($med); ?>"
	/>
    </a>
<?php
}
