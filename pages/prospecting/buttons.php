<?php if ($p["password"]) return ; ?>
<?php if ($p["deleted"] === NULL) { ?>
    <?php $js = "silent_submitf(this, {toremove: 'prospects_table".$p["id"]."'});"; ?>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons" onsubmit="return <?=$js; ?>">
	<input type="hidden" name="decision" value="remove" />
	<input type="button" value="X" style="color: red;" onclick="confirm('<?=$Dictionnary["Deleted"]; ?>') && <?=$js; ?>" />
    </form>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>/transform" style="display: inline-block;" class="decision_buttons" onsubmit="return <?=$js; ?>">
	<input type="button" value="USR" style="color: orange; font-weight: bold;" title="<?=$Dictionnary["TransformProspectIntoUser"]; ?>" onclick="confirm('<?=htmlspecialchars($Dictionnary["ConfirmProspectTransformation"], ENT_QUOTES); ?>') && <?=$js; ?>" />
    </form>
<?php } else { ?>
    <?php $js = "silent_submit(this);"; ?>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons" onsubmit="return <?=$js; ?>">
	<input type="hidden" name="decision" value="restore" />
	<input type="button" value="&#8634;" style="color: green;" onclick="<?=$js; ?>" />
    </form>
<?php } ?>

<?php $js = "silent_submitf(this, {after_success: open_generated_contract});"; ?>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="ecole" />
    <input type="button" value="ECL" style="color: lightgreen; font-weight: bold;" onclick="<?=$js; ?>" />
</form>
<br />
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="of" />
    <input type="button" value="OF" style="color: lightblue; font-weight: bold;" onclick="<?=$js; ?>" />
</form>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="ofa" />
    <input type="button" value="OFA" style="color: yellow; font-weight: bold;" onclick="<?=$js; ?>" />
</form>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="cfa" />
    <input type="button" value="CFA" style="color: teal; font-weight: bold;" onclick="<?=$js; ?>" />
</form>


