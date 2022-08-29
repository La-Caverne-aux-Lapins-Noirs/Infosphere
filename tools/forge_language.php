<?php

function forge_language_fields($field, $attr = false, $merge = false, $table = "")
{
    global $LanguageList;
    global $Language;

    if (!is_array($field))
	$field = [$field];

    $out = [];
    foreach ($field as $f)
    {
	foreach ($LanguageList as $k => $v)
	{
	    $str = "";
	    if ($table != "")
		$str = $table.".";
	    $str .= $k."_".$f;
	    if ($attr)
		$str .= " as {$k}_$f ";
	    $out[] = $str;
	}
	$out[] = $Language."_".$f." as ".$f;
    }
    if ($merge)
	return (implode(",", $out));
    return ($out);
}

function forge_language_insert($field, array $data, $merge = false)
{
    global $Database;
    global $LanguageList;

    if (!is_array($field))
	$field = [$field];
    $lng = [];
    $txt = [];
    foreach ($field as $i => $f)
    {
	if (is_integer($i))
	{
	    $symbol = $f;
	    $mandatory = true;
	}
	else
	{
	    $symbol = $i;
	    $mandatory = $f;
	}

	if (!is_symbol($symbol))
	    return (new ErrorResponse("InvalidParameter", $symbol));
	foreach ($LanguageList as $k => $v)
	{
	    if (!isset($data[$k."_".$symbol]))
	    {
		if ($mandatory)
		    return (new ErrorResponse("MissingField", $v." '".$symbol."'"));
		$data[$k."_$symbol"] = "";
	    }
	    else if ($data[$k."_".$symbol] == "")
	    {
		if ($mandatory)
		    return (new ErrorResponse("MissingField", $v." '".$symbol."'"));
		$data[$k."_$symbol"] = "";
	    }
	    $txt[] = "'".$Database->real_escape_string($data[$k."_$symbol"])."'";
	    $lng[] = $k."_".$symbol;
	}
    }
    if ($merge)
    {
	$lng = implode(",", $lng);
	$txt = implode(",", $txt);
    }
    return (new ValueResponse(["Labels" => $lng, "Texts" => $txt]));
}

function forge_language_update($field, array $data, $merge = false)
{
    global $Database;
    global $LanguageList;

    if (!is_array($field))
	$field = [$field];
    $txt = [];
    foreach ($field as $i => $f)
    {
	if (is_integer($i))
	{
	    $symbol = $f;
	    $mandatory = true;
	}
	else
	{
	    $symbol = $i;
	    $mandatory = $f;
	}

	if (!is_symbol($symbol))
	    return (new ErrorResponse("InvalidParameter", $symbol));
	foreach ($LanguageList as $k => $v)
	{
	    if (!isset($data[$k."_".$symbol]))
		continue ;
	    $txt[] = "`{$k}_{$symbol}` = '".$Database->real_escape_string($data[$k."_$symbol"])."'";
	}
    }
    if ($merge)
	$txt = implode(",", $txt);
    return (new ValueResponse($txt));
}

// @codeCoverageIgnoreStart
function forge_language_formular($fields, $prefil, $class = "language_entry")
{
    global $LanguageList;
    global $Dictionnary;

    foreach ($LanguageList as $k => $v)
    {
?>
    <div class="<?=$class; ?>">
 	<span><?=$v; ?></span><br />
	<?php
	foreach ($fields as $f => $type)
	{
	    if ($type == "textarea")
	    {
	?>
	    <textarea
		name="<?=$k; ?>_<?=$f; ?>"
		style="font-size: 15px; line-height: 15px;"
		placeholder="<?=$Dictionnary[ucfirst(is_integer($f) ? $type : $f)]; ?>"
	    ><?=try_get($prefil, $k."_".$f); ?></textarea>
	<?php
	    }
	    else
	    {
	?>
	    <input
		type="<?=$type; ?>"
		name="<?=$k; ?>_<?=$f; ?>"
		placeholder="<?=$Dictionnary[ucfirst(is_integer($f) ? $type : $f)]; ?>"
		value="<?=try_get($prefil, $k."_".$f); ?>"
	    /><br />
	<?php
	}
	}
       ?> <br /></div> <?php
    }
}
// @codeCoverageIgnoreEnd
