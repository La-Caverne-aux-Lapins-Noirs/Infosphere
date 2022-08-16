<?php
// Les catégories prévisibles: user, activity, class_asset, room, laboratory, cycle
// Les catégories imprévisibles: general
$categories = db_select_all("
    *
    FROM message
    GROUP BY position
    ORDER BY position ASC
", "position");
if (!isset($categories["general"]))
    $categories["general"] = [
	"position" => "general"
    ];
if (!isset($categories["cycle"]))
    $categories["cycle"] = [
	"position" => "cycle"
    ];
if (!isset($categories["activity"]))
    $categories["activity"] = [
	"position" => "activity"
    ];

$tables = db_get_tables();
?>
<?php foreach ($categories as $cat) { ?>

    <div class="prettybox" style="width: calc(100% - 20px); display: inline-block; text-align: center;">
	<h2><?=$Dictionnary[ucfirst($cat["position"])]; ?></h2>
	<br />
	<?php
	$section = $Language."_name";
	if ($cat["position"] == "user" || $cat["position"] == "cycle")
	    $section = "codename";

	if (isset($tables[$cat["position"]]))
	{
	    if ($cat["position"] == "activity")
	    {
		$parts = db_select_all("
                  $section as name, codename as codename
                  FROM {$cat["position"]}
                  WHERE deleted = 0
                    AND is_template = 1
                    AND parent_activity = -1
                  ORDER BY name
		  ");
		foreach ($parts as &$c)
		{
		    $cd = explode("-", $c["codename"]);
		    if (count($cd) <= 2)
			continue ;
		    $year = (int)($cd[2]);
		    $trimester = array_search(substr($cd[2], 2, 1), ["X", "A", "B", "C", "D"]);
		    $c["date"] = $Dictionnary["Year"]." ".$year." ".$Dictionnary["Cycle"]." ".$trimester;
		}
	    }
	    else
	    {
		$parts = db_select_all("
                  $section as name, codename as codename
                  FROM {$cat["position"]}
		  WHERE deleted = 0
		  ");
	    }
	}
	else if ($cat["position"] == "general")
	    $parts = [
		["name" => $Dictionnary["Administrative"], "codename" => "administrative"],
		["name" => $Dictionnary["GeneralConversation"], "codename" => "general_conversation"],
		["name" => $Dictionnary["CriticismsComplaintsAndSuggestions"], "codename" => "tears"],
	    ];
	?>
	<?php foreach ($parts as $ps) { ?>
	    <?php
	    $last_message = NULL;
	    ?>
	    <div class="prettybox" style="width: 31%; display: inline-block; text-align: center; position: relative; height: 50px;">
		<table style="height: 100%; width: 100%;">
		    <tr>
			<td style="vertical-align: middle; text-align: center;">
			    <a href="index.php?<?=unrollget(["table" => $ps["codename"]]); ?>">
				<span style="font-size: large; font-weight: bold;">
				    <?=$ps["name"]; ?>
				</span>
				<?php if (isset($ps["date"])) { ?>
				    <br /><?=$ps["date"]; ?>
				<?php } ?>
			    </a>
			</td>
			<td style="vertical-align: middle; font-style: italic; font-size: small; text-align: center;">
			    <?php if ($last_message != NULL) { ?>
				<?=$Dictionnary["LastMessageIn"]; ?>:<br />
				"<?="X"; ?>"<br />
				<?=$Dictionnary["By"]; ?> <?="Y"; ?>, <?=human_date(""); ?>
			    <?php } else { ?>
				<?=$Dictionnary["NoMessages"]; ?>
			    <?php } ?>
			</td>
		    </tr>
		</table>
	    </div>
	<?php } ?>
    </div>
<?php } ?>
