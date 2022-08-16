<?php

$Background = false;
$BackgroundColor = "";

function single_field_form($page, $obj, $field, $dic, $empty_value = null)
{
    global $Dictionnary;
    global $Background;
    global $BackgroundColor;

    $js = "silent_submit(this)";
?>
    <form
	method="put"
	onsubmit="return false;"
	action="/api/<?=$page; ?>/<?=$obj->id; ?>"
	<?=isset($Background) && $Background ? $BackgroundColor : ""; ?>
    >
	<label for="<?=$field; ?>"><?=$Dictionnary[$dic]; ?></label>
	<input type="hidden" id="previous_value_<?=$field; ?>" value="<?=$obj->$field; ?>" />
	<input
	    type="text"
	    name="<?=$field; ?>"
	    value="<?=$empty_value != $obj->$field ? $obj->$field : ""; ?>"
	    onchange="delay_before_submit(1000, this, '<?=$field; ?>');"
	/>
    </form>
<?php
}

function single_field_formf($f)
{
    return (single_field_form([
	$f["module"],
	$f["object"],
	$f["field"],
	$f["label"],
	$f["empty"]
    ]));
}

function check_form($page, $obj, $field, $dic, $submod = NULL, $subid = NULL)
{
    global $Dictionnary;
    global $Background;
    global $BackgroundColor;

    $js = "silent_submit(this)";
    if ($submod != NULL)
    {
	$submod = "/$submod";
	if ($subid != NULL)
	    $submod .= "/$subid";
    }
?>
    <form
	class="check_formular"
	method="put"
	onsubmit="return <?=$js; ?>;"
	action="/api/<?=$page; ?>/<?=$obj->id; ?><?=$submod; ?>"
	<?=isset($Background) && $Background ? $BackgroundColor : ""; ?>
    >
	<label for="<?=$field; ?>"><?=$Dictionnary[$dic]; ?></label>
	<input
	    type="checkbox"
	    name="<?=$field; ?>"
	    <?=$obj->$field ? "checked" : ""; ?>
	    onchange="<?=$js; ?>"
	/>
    </form>
<?php
}

function single_field_with_ok_form($page, $obj, $field, $dic, $submod = NULL, $subid = NULL, $addhtml = "", $method = "put", $js = "silent_submit(this)")
{
    global $Dictionnary;
    global $Background;
    global $BackgroundColor;

    if ($submod != NULL)
    {
	$submod = "/$submod";
	if ($subid != NULL)
	    $submod .= "/$subid";
    }
    $value = "";
    if (is_object($obj))
    {
	if (isset($obj->id))
	    $id = $obj->id;
	if (isset($obj->$field))
	    $value = $obj->$field;
    }
    else if (is_array($obj))
    {
	if (isset($obj["id"]))
	    $id = $obj["id"];
	if (isset($obj[$field]))
	    $value = $obj[$field];
    }
?>
    <form
	method="<?=$method; ?>"
	onsubmit="return <?=$js; ?>;"
	action="/api/<?=$page; ?>/<?=$id; ?><?=$submod; ?>"
	<?=isset($Background) && $Background ? $BackgroundColor : ""; ?>
    >
	<label for="<?=$field; ?>"><?=$Dictionnary[$dic]; ?></label>
	<input type="text" name="<?=$field; ?>" value="<?=$value; ?>" />
	<input type="button" onclick="<?=$js; ?>" value="&#10003;" style="color: green;" />
	<?=$addhtml; ?>
    </form>
<?php
}

function single_field_with_ok_formf($f)
{
    return (single_field_form([
	$f["module"],
	$f["object"],
	$f["field"],
	$f["label"],
	$f["submodule"],
	$f["subid"],
	$f["html"],
	$f["method"]
    ]));
}


function edit_codename_form($page, $obj)
{
    global $Dictionnary;

    if ($obj->parent_activity == -1)
	$codename = $obj->codename;
    else
    {
	$module = db_select_one("codename FROM activity WHERE id = {$obj->parent_activity}");
	$codename = substr($obj->codename, strlen($module["codename"]));
	if (substr($codename, 0, 1) == "-")
	    $codename = substr($codename, 1);
    }
    single_field_with_ok_form($page, ["id" => $obj->id, "codename" => $codename], "codename", "CodeName");
}

function edit_type_form($page, $obj)
{
    global $Dictionnary;
    global $ActivityType;
    global $Background;
    global $BackgroundColor;

    $js = "silent_submit(this)";
?>
    <form
	method="put"
	onsubmit="return <?=$js; ?>;"
	action="/api/<?=$page; ?>/<?=$obj->id; ?>"
	<?=isset($Background) && $Background ? $BackgroundColor : ""; ?>
    >
	<label for="type"><?=$Dictionnary["Type"]; ?></label>
	<select name="type" onchange="<?=$js; ?>">
	    <?php foreach ($ActivityType as $i => $v) { ?>
		<?php if ($obj->parent_activity == -1) { ?>
		    <?php if ($v["type"] == 0) { ?>
			<option value="<?=$v["id"]; ?>">
			    <?=$Dictionnary[$v["codename"]]; ?>
			</option>
		    <?php } ?>
		<?php } else { ?>
		    <?php if ($v["type"] != 0) { ?>
			<option
			    value="<?=$v["id"]; ?>"
			    <?=$v["id"] == $obj->type ? "selected" : ""; ?>
			>
			    <?=$Dictionnary[$v["codename"]]; ?>
			</option>
		    <?php } ?>
		<?php } ?>
	    <?php } ?>
	</select>
    </form>
<?php
}
?>
