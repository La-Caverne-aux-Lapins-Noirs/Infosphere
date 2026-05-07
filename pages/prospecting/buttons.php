<?php if ($p["deleted"] === NULL) { ?>
    <?php $js = "silent_submitf(this, {toremove: 'prospects_table".$p["id"]."'});"; ?>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons" onsubmit="return <?=$js; ?>">
	<input type="hidden" name="decision" value="remove" />
	<input type="button" value="X" style="color: red;" onclick="confirm('<?=$Dictionnary["Deleted"]; ?>') && <?=$js; ?>" />
    </form>
<?php } else { ?>
    <?php $js = "silent_submit(this);"; ?>
    <form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons" onsubmit="return <?=$js; ?>">
	<input type="hidden" name="decision" value="restore" />
	<input type="button" value="&#8634;" style="color: green;" onclick="<?=$js; ?>" />
    </form>
<?php } ?>

<?php $js = "silent_submit(this, {toremove: 'prospects_table".$p["id"]."'}); window.open('/dres/user/".$p["codename"]."/admin/contract.pdf');"; ?>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="ecole" />
    <input type="button" value="ECL" style="color: lightgreen;" onclick="<?=$js; ?>" />
</form>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="of" />
    <input type="button" value="OF" style="color: lightblue;" onclick="<?=$js; ?>" />
</form>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="ofa" />
    <input type="button" value="OFA" style="color: yellow;" onclick="<?=$js; ?>" />
</form>
<form method="put" action="/api/prospect/<?=$p["id"]; ?>" style="display: inline-block;" class="decision_buttons">
    <input type="hidden" name="decision" value="cfa" />
    <input type="button" value="CFA" style="color: teal;" onclick="<?=$js; ?>" />
</form>
    
