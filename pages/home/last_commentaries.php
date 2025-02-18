<h2 style="text-align: left;">
    <?=$Dictionnary["LastCommentaries"]; ?>:
</h2>
<br />
<?php
$comments = db_select_all("
   * FROM comment
   WHERE comment_date BETWEEN DATE_SUB(NOW(), INTERVAL 50 DAY) AND NOW()
   AND deleted IS NULL
   GROUP BY misc_type, id_misc
   ORDER BY comment_date DESC
");
$user_teams = db_select_all("
   user_team.*, team.id_activity FROM user_team
   LEFT JOIN team ON user_team.id_team = team.id
   WHERE id_user = ".$User["id"]."
   ", "id");
$teams = db_select_all("
   team.* FROM team
   LEFT JOIN user_team ON team.id = user_team.id_team
   WHERE user_team.id_user = ".$User["id"]."
   ", "id");
$cycles = [];
foreach ($User["cycle"] as $cyc)
    $cycles[$cyc["id"]] = $cyc;
foreach ($comments as $comment)
{
    $act = NULL;
    $cyc = NULL;
    if ($comment["misc_type"] == 0)
    {
	if (!isset($teams[$comment["id_misc"]]))
	    continue ;
	($act = new FullActivity)->build($teams[$comment["id_misc"]]["id_activity"]);
    }
    else if ($comment["misc_type"] == 1)
    {
	if (!isset($user_teams[$comment["id_misc"]]))
	    continue ;
	($act = new FullActivity)->build($user_teams[$comment["id_misc"]]["id_activity"]);
    }
    else if ($comment["misc_type"] == 2)
    {
	if (!isset($cycles[$comment["id_misc"]]))
	    continue ;
	$cyc = $cycles[$comment["id_misc"]];
    }
    else
	continue ;
    ?>
<div style="min-height: 60px; box-shadow: 0px 0px 20px rgba(0, 0, 0, 0.1) inset; border-radius: 10px; margin-top: 3px; margin-bottom: 2px;">
    <?=display_avatar($comment["id_user"], 50, false, "float: left; margin-right: 5px; margin-bottom: 5px;"); ?>
    <i><?php display_nickname($comment["id_user"]); ?>, <?=human_date($comment["comment_date"]); ?></i><br />
    <?php if ($act) { ?>
	<b>
	    <?=$act->name ? str_replace(":", "<br />", $act->name) : $act->codename; ?>
	</b><br />
    <?php } else if ($cyc) { ?>
	<b><?=$cyc["name"]; ?></b><br />
    <?php } ?>
    <p style="text-align: justify; box-sizing: bordere-box; padding-left: 5px; padding-right: 5px;">
	<?=$comment["content"]; ?>
    </p>
</div>
    <?php
}
