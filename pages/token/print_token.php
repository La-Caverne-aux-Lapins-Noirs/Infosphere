<?php

function print_token($id_session)
{
    $all = db_select_all("* FROM token WHERE id_session = $id_session AND status = 0");
    if (count($all) == 0)
    {
	generate_token($id_session);
	$all = db_select_all("* FROM token WHERE id_session = $id_session AND status = 0");
    }
    echo '<table style="width: 100%;" class="page">';
    for ($i = 0; isset($all[$i]); )
    {
?>
    <tr>
	<?php for ($j = 0; isset($all[$i]) && $j < 3; ++$j) { ?>
	    <td style="text-align: center; width: 33%;">
		<div>
		    <?php if (strlen(@$all[$i + $j]["codename"])) { ?>
			<?=$all[$i]["codename"]; ?><br />
			<?=human_date($all[$i]["invalidation_date"]); ?><br />
		    <?php } ?>
		</div>
		<br />
	    </td>
	<?php } ?>
	<?php $i += $j; ?>
    </tr>
    <?php
    }
    echo '</table>';
}


