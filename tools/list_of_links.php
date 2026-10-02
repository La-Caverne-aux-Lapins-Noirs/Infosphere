<?php

function list_of_links_property_control($property, $value = "", $for_create = false)
{
    $codename = (string)($property["codename"] ?? "");
    $label = (string)($property["name"] ?? $codename);
    $type = (string)($property["type"] ?? "text");
    $required = !empty($property["required"]) ? " required" : "";
    $escaped_codename = htmlspecialchars($codename, ENT_QUOTES);
    $escaped_label = htmlspecialchars($label, ENT_QUOTES);

    if ($type === "select")
    {
        $options = is_array($property["options"] ?? NULL) ? $property["options"] : [];
        $all_options = is_array($property["all_options"] ?? NULL) ? $property["all_options"] : $options;
        $empty_label = (string)($property["empty_label"] ?? $label);
        $suffix = (string)($property["unavailable_suffix"] ?? "");
        $selected_value = (string)$value;
        ?>
        <select name="<?=$escaped_codename; ?>" title="<?=$escaped_label; ?>"<?=$required; ?>>
            <option value="" <?=($selected_value === "" ? "selected" : ""); ?>><?=htmlspecialchars($empty_label, ENT_QUOTES); ?></option>
            <?php foreach ($options as $option_value => $option_label) { ?>
                <option value="<?=htmlspecialchars((string)$option_value, ENT_QUOTES); ?>" <?=($selected_value === (string)$option_value ? "selected" : ""); ?>><?=htmlspecialchars((string)$option_label, ENT_QUOTES); ?></option>
            <?php } ?>
            <?php if (!$for_create && $selected_value !== "" && !array_key_exists($selected_value, $options)) { ?>
                <option value="<?=htmlspecialchars($selected_value, ENT_QUOTES); ?>" selected><?=htmlspecialchars((string)($all_options[$selected_value] ?? $selected_value).$suffix, ENT_QUOTES); ?></option>
            <?php } ?>
        </select>
        <?php
        return ;
    }
    ?>
    <input
        type="<?=htmlspecialchars($type, ENT_QUOTES); ?>"
        style="position: relative;"
        name="<?=$escaped_codename; ?>"
        placeholder="<?=$escaped_label; ?>"
        value="<?=htmlspecialchars((string)$value, ENT_QUOTES); ?>"
        <?=$required; ?>
    />
    <?php
}

function list_of_links_extra_class($hook_name, $this_node_name, $elm)
{
    if ($hook_name == "activity"
	&& $this_node_name == "support_asset"
	&& isset($elm["content"])
	&& trim((string)$elm["content"]) == "")
	return (" list_of_link_empty_support_asset");
    return ("");
}

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
    $delete_submit = "silent_submit(this, null, null, null, '$form_id')";
    // Editing relation properties must not reuse the delete submit action.
    // The old code passed $form_id as `toremove`, so a successful PUT removed
    // the form carrying the linked element identity (name/codename) from the DOM.
    $property_submit = "silent_submit(this)";
    $linked_id = isset($elm["id_{$this_node_name}"]) ? $elm["id_{$this_node_name}"] : $elm["id"];
?>
    <form
	method="delete"
	action="api/<?=$hook_name; ?>/<?=$hook_id; ?>/<?=$this_node_name; ?>/<?=$linked_id; ?>"
	id="<?=$form_id; ?>"
	class="sublist_of_link<?=list_of_links_extra_class($hook_name, $this_node_name, $elm); ?>"
	onsubmit="return <?=$delete_submit; ?>;"
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
		    onclick="<?=$delete_submit; ?>;"
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
	    onsubmit="return <?=$property_submit; ?>;"
	    style="position: relative;"
	>
	    <?php $count = 0; ?>
	    <?php foreach ($extra_properties as $epv) { ?>
		<?php if (@strlen($epv["admin_func"]) == 0 || $epv["admin_func"]($hook_id)) { ?>
		    <?php list_of_links_property_control(
			$epv,
			isset($elm[$epv["codename"]]) ? $elm[$epv["codename"]] : "",
			false
		    ); ?>
		    <?php $count += 1; ?>
		<?php } else { ?>
		    <?=$epv["name"]; ?>: <?=@strlen($elm[$epv["codename"]]) ? $elm[$epv["codename"]] : "/"; ?><br />
		<?php } ?>
	    <?php } ?>
	    <?php if ($count) { ?>
		<input
		    type="button"
		    value="&#10003;"
		    style="
			  position: absolute;
			  top: 0px;
			  height: <?=20 * $count; ?>px;
			  "
		    onclick="<?=$property_submit; ?>"
		/>
	    <?php } ?>
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
    if (isset($linked_name["label"]))
        $label = $linked_name["label"];
    else
        $label = $name;

    $dictionary_label = function($value) use ($Dictionnary) {
        $value = (string)$value;
        $key = ucfirst($value);
        if (isset($Dictionnary[$key]))
            return ((string)$Dictionnary[$key]);
        if (isset($Dictionnary[$value]))
            return ((string)$Dictionnary[$value]);
        return ($value);
    };

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
    <h5><?=htmlspecialchars($dictionary_label($label), ENT_QUOTES); ?></h5>
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
		    placeholder="<?=htmlspecialchars($dictionary_label($placeholder), ENT_QUOTES); ?>"
		    onkeypress="return event.keyCode != 13 ? true : <?=$submit; ?>;"
		/>
		<?php if (isset($extra_properties)) { ?>
		    <?php foreach ($extra_properties as $epv) { ?>
			<?php if (!empty($epv["on_create"]) && (@strlen($epv["admin_func"] ?? "") == 0 || $epv["admin_func"]($hook_id))) { ?>
			    <?php list_of_links_property_control($epv, $epv["default"] ?? "", true); ?>
			<?php } ?>
		    <?php } ?>
		<?php } ?>
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
