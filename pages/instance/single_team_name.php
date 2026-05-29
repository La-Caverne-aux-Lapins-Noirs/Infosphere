<?php
if ($activity->teamable)
{
    $label = $cteam["canjoin"] ? "" : "&#128274; ";
    $label .= $cteam["team_name"];
    $label .= $cteam["real_members"] < $activity->min_team_size ? " (".$Dictionnary["TeamIncomplete"].")" : "";
?>
    <input
	style="
	       width: 100%;
	       height: 35px;
	       box-sizing: border-box;
	       font-weight: bold;
	       "
	type="button"
	value="<?=$label; ?>"
    />
<?php } else { ?>
    <div
	style="
	       width: 100%;
	       height: 35px;
	       box-sizing: border-box;
	       font-weight: bold;
	       "
    >
	<?php display_nickname($cteam["user"][array_key_first($cteam["user"])]); ?>
    </div>
<?php } ?>
