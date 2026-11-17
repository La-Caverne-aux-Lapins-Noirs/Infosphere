<?php if (@strlen($activity->repository_name)) { ?>
    <div
	style="
	       display: inline-block;
	       text-align: center;
	       vertical-align: top;
	       "
    >
	<?php $js = "return silent_submitf(this, {download: true});"; ?>
	<?=$Dictionnary["Delivery"]; ?>:&nbsp;
	<form
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input type="hidden" name="alive" value="1" />
	    <input
		type="button"
		onclick="<?=$js; ?>"
		value="&nearr;"
		title="<?=$Dictionnary["DownloadCurrentRepository"]; ?>"
		style="color: red; font-weight: bold; width: 30px; height: 30px;"
	    />
	</form>
	<form
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input
		type="button"
		onclick="<?=$js; ?>"
		value="&nearr;"
		title="<?=$Dictionnary["DownloadLastDelivery"]; ?>"
		      style="color: white; font-weight: bold; width: 30px; height: 30px;"
	    />
	</form>
	<form
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input type="hidden" name="official" value="1" />
	    <input
		type="button"
		onclick="<?=$js; ?>"
		value="&nearr;"
		style="color: green; font-weight: bold; width: 30px; height: 30px;"
		title="<?=$Dictionnary["DownloadDelivery"]; ?>"
	    />
	</form>
	<form
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input type="hidden" name="correction" value="1"/>
	    <input type="hidden" name="alive" value="1" />
	    <input
		type="button"
		onclick="<?=$js; ?>"
		value="&nearr;"
		style="color: green; font-weight: bold; width: 30px; height: 30px;"
		title="<?=$Dictionnary["LaunchCorrection"]; ?>"
	    />
	</form>
	<form
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input type="hidden" name="correction" value="1"/>
	    <input type="hidden" name="official" value="1" />
	    <input type="hidden" name="alive" value="1" />
	    <input
		type="button"
		onclick="<?=$js; ?>"
		value="&nearr;"
		style="color: green; font-weight: bold; width: 30px; height: 30px;"
		title="<?=$Dictionnary["LaunchOfficialCorrection"]; ?>"
	    />
	</form>
    </div>
<?php } ?>
