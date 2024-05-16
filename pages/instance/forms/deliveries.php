<?php if (@strlen($activity->repository_name)) { ?>
    <?php $js = "silent_submit(this)"; ?>
    <div
	style="
	       position: absolute;
	       width: 20%; left: 50%;
	       text-align: center;
	       "
    >
	<?=$Dictionnary["Delivery"]; ?>:&nbsp;
	<form
	    target="_blank"
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input type="hidden" name="alive" value="1" />
	    <input
		type="submit"
		      value="&nearr;"
		      title="<?=$Dictionnary["DownloadCurrentRepository"]; ?>"
		      style="color: red; font-weight: bold; width: 30px; height: 30px;"
	    />
	</form>
	<form
	    target="_blank"
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input
		type="submit"
		      value="&nearr;"
		      title="<?=$Dictionnary["DownloadLastDelivery"]; ?>"
		style="color: white; font-weight: bold; width: 30px; height: 30px;"
	    />
	</form>
	<form
	    target="_blank"
	    method="post"
	    action="/api/activity/<?=$activity->id; ?>/pickup/<?=$cteam["id"]; ?>?_method=put"
	    style="display: inline-block"
	>
	    <input type="hidden" name="official" value="1" />
	    <input
		type="submit"
		      value="&nearr;"
		      style="color: green; font-weight: bold; width: 30px; height: 30px;"
		      title="<?=$Dictionnary["DownloadDelivery"]; ?>"
	    />
	</form>
    </div>
<?php } ?>
