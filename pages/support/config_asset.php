<br />
<?php
$js = "
silent_submitf(this, {
  tofill: 'support".$support["id"]."'
});
  ";
if (isset($asset) && $asset != NULL)
{
    $asset_id = "/asset/".$asset["id"];
    $asset_codename = $asset["codename"];
    $prefil = $asset;
}
else
{
    $asset_id = "";
    $asset_codename = "";
    $prefil = [];
}
?>
<form
    method="post"
    action="/api/support_category/<?=$support["id_support_category"]; ?>/support/<?=$support["id"]; ?><?=$asset_id; ?>"
    style="
    	    text-align: center;
	    background-color: lightgray;
	    margin-left: 10px;
	    margin-right: 10px;
	    border-radius: 10px;
	    padding-top: 5px;
	    padding-bottom: 5px;
	    "
    onsubmit="return <?=$js; ?>,"
>
    <?php if ($asset_id != "") { ?>
	<h3><?=$Dictionnary["EditAsset"]; ?></h3>
    <?php } else { ?>
	<h3><?=$Dictionnary["AddAsset"]; ?></h3>
    <?php } ?>
    <?php
    forge_language_formular(
	["name" => "text", "content" => "file"],
	$prefil,
	"_300pxw language_entry"
    );
    ?>
    <div class="_300pxw language_entry" style="vertical-align: top;">
	<input
	    style="margin-top: 35px;"
	    type="text"
	    name="codename"
	    class="_300pxw"
	    <?php if ($asset_id != "" && isset($asset_codename)) { ?>
		value="<?=$asset_codename; ?>"
	    <?php } ?>
	    placeholder="<?=$Dictionnary["CodeName"]; ?>"
	/><br />
	<input
	    type="button"
	    onclick="<?=$js; ?>"
	    style="height: 40px; line-height: 40px;"
	    class="_300pxw"
	    value="+"
	/>
    </div>
</form>

