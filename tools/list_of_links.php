<?php

function single_link($params)
{
    // ($hook_name, $hook_id, $linked_name, $linked_elem, $method = "put", $link = NULL, $display_link = true, $admin_func = "only_admin")
    $method = "put";
    $link = NULL;
    $display_link = true;
    $admin_func = "only_admin";
    $extra_form_id = "";
    extract($params);

    $elm = $linked_elem;
    if (is_array($linked_name))
    {
	if (isset($linked_name["#"]) && substr($elm["codename"], 0, 1) == "#")
	    $this_node_name = $linked_name["#"];
	else if (isset($linked_name["$"]) && substr($elm["codename"], 0, 1) == "$")
	    $this_node_name = $linked_name["$"];
	else if (isset($linked_name["@"]) && substr($elm["codename"], 0, 1) == "@")
	    $this_node_name = $linked_name["@"];
	else
	    $this_node_name = $linked_name[""];
	if (is_array($this_node_name)) // Pour supporter le shift table/champ en BDD
	    $this_node_name = $this_node_name[0];
    }
    else
	$this_node_name = $linked_name;

    $form_id = $this_node_name."-".$hook_id."-";
    if (isset($elm["id_".$this_node_name]))
	$form_id .= $elm["id_".$this_node_name];
    else
	$form_id .= $elm[$this_node_name];
    $form_id .= $extra_form_id;
    $submit = "silent_submit(this, null, null, null, '$form_id')";
    $linked_id = isset($elm["id_{$this_node_name}"]) ? $elm["id_{$this_node_name}"] : $elm["id"];
?>
    <form
	method="delete"
	action="api/<?=$hook_name; ?>/<?=$hook_id; ?>/<?=$this_node_name; ?>/<?=$linked_id; ?>"
	id="<?=$form_id; ?>"
	class="sublist_of_link"
	onsubmit="return <?=$submit; ?>;"
    >
	<input type="hidden" name="extra_form_id" value="<?=$extra_form_id; ?>" />
	<div>
	    <?php if (@$elm["inherit"]) { ?>&nwarr;<?php } ?>
	    <?php if ($display_link) { ?>
		<a href="<?=inside_link($link ?: $this_node_name, $linked_id); ?>">
		    <?=isset($elm["codename"]) ? $elm["codename"] : $elm[$this_node_name]; ?>
		</a>
	    <?php } else { ?>
		<?php if ($link != NULL) { ?>
	    	    <a href="<?=$link; ?>">
		<?php } ?>
		<?=isset($elm["codename"]) ? $elm["codename"] : $elm[$this_node_name]; ?>
		<?php if ($link != NULL) { ?>
		    </a>
		<?php } ?>
	    <?php } ?>
	    <?php if (@$elm["inherit"] == false && @strlen($admin_func) && $admin_func($hook_id)) { ?>
		<input
		    type="button"
		    onclick="<?=$submit; ?>;"
		    value="&#10007;"
		    style="color: red;"
		/>
	    <?php } ?>
	</div>
    </form>
    <?php if (isset($extra_properties)) { ?>
	<form
	    method="put"
	    action="api/<?=$hook_name; ?>/<?=$hook_id; ?>/<?=$this_node_name; ?>/<?=$linked_id; ?>"
	    onsubmit="return <?=$submit; ?>;"
	>
	    <?php foreach ($extra_properties as $epv) { ?>
		<input
		    type="text"
		    name="<?=$epv["codename"]; ?>"
		    placeholder="<?=$epv["name"]; ?>"
		    value="<?=isset($elm[$epv["codename"]]) ? $elm[$epv["codename"]] : ""; ?>"
		/>
	    <?php } ?>
	    <input
		type="button"
		value="&#10003;"
		onclick="<?=$submit; ?>"
	    />
	</form>
	<br />
	<br />
    <?php } ?>
<?php
}

function single_linkb($params)
{
    ob_start();
    single_link($params);
    return (ob_get_clean());
}

function list_of_links($params)
{
    global $Dictionnary;
    global $Background;
    global $BackgroundColor;

    // ($hook_name, $hook_id, $linked_name, $linked_elems, $method = "put", $link = NULL, $display_link = true, $admin_func = "only_admin")
    $method = "put";
    $link = NULL;
    $display_link = true;
    $full_formular = true;
    $additional_html = "";
    $admin_func = "only_admin";
    $extra_form_id = "";
    extract($params);
    
    if (isset($linked_name["table"]))
	$name = $linked_name["table"];
    else
	$name = $linked_name;
    if (isset($linked_name["name"]))
	$name = $linked_name["name"];
    if (isset($linked_name["placeholder"]))
	$placeholder = $linked_name["placeholder"];
    else
	$placeholder = $name;

    $form_id = $name."-".$hook_id;
    $form_id .= $extra_form_id;
    $submit = "silent_submit(this, '$form_id', null, '$form_id', null)";
    if ($full_formular) {
    ?>
    <div
	class="list_of_link <?=$name; ?>"
	id="<?=$form_id; ?>"
	<?=isset($Background) && $Background ? $BackgroundColor : ""; ?>
    >
    <?php } ?>
    <h5><?=$Dictionnary[ucfirst($name)]; ?></h5>
	<?php if (@strlen($admin_func) && $admin_func($hook_id)) { ?>
	    <form
		method="<?=$method; ?>"
		id="<?=$form_id; ?>_form"
		action="api/<?=$hook_name; ?>/<?=$hook_id; ?>/<?=$name; ?>"
		onsubmit="return <?=$submit; ?>, false);"
	    >
		<input type="hidden" name="extra_form_id" value="<?=$extra_form_id; ?>" />
		<input
		    type="text"
		    name="<?=$name; ?>"
		    id="<?=$name.$hook_id; ?>_name"
		    placeholder="<?=$Dictionnary[ucfirst($placeholder)]; ?>"
		    onkeypress="return event.keyCode != 13 ? true : <?=$submit; ?>;"
		/>
		<input
		    type="button"
		    onclick="<?=$submit; ?>;"
		    value="&#10003;"
		    style="color: green;"
		/>
		<?=$additional_html; ?>
	    </form>
	<?php } ?>

	<?php if (count($linked_elems)) { ?>
	    <?php foreach ($linked_elems as $elm) { ?>
		<?php single_link(array_merge($params, [
		    "linked_elem" => $elm
		])); ?>
	    <?php } ?>
	<?php } else { ?>
	<?php } ?>
    <?php if ($full_formular) { ?>
    </div>
    <?php
    }
}

function list_of_linksb($params)
{
    ob_start();
    list_of_links($params);
    return (ob_get_clean());
}
