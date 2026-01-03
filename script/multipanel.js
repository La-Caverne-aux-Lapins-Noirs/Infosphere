
function switch_asset(link, screen, type, asset)
{
    var links = document.getElementsByClassName("asset_link");
    var screens = document.getElementsByClassName("screen_media");

    if ((screen = document.getElementById(screen)) == null)
	return ;
    Array.prototype.forEach.call(links, function(el) {
	el.classList.remove("selected");
    });
    Array.prototype.forEach.call(screens, function(el) {
	el.style.display = "none";
    });
    
    link.classList.add("selected");
    
    console.log(type);
    if (type == "video")
    {
	screen = screen.getElementsByTagName("video")[0];
	screen.style.display = "";
	for (src of screen.getElementsByTagName("source"))
	    src.src = asset;
	screen.load();
	screen.play();
    }
    else if (type == "audio")
    {
	screen = screen.getElementsByTagName("audio")[0];
	screen.src = asset;
    }
    else if (type == "frame")
    {
	screen = screen.getElementsByTagName("iframe")[0];
	screen.src = asset;
    }
    else if (type == "img")
    {
	screen = screen.getElementsByTagName("div")[0];
	screen.innerHTML = "";
	screen.style.backgroundImage = "url(" + asset + ")";
    }
    else if (type == "file")
    {
	screen = screen.getElementsByTagName("div")[0];
	screen.style.backgroundImage = "";
	screen.innerHTML =
	    '<table style="height: 100%; width: 100%;"><tr>' +
	    '<td style="text-align: center; vertical-align: middle;">' +
	    '<a style="font-size: xx-large;" href="' + asset + '">' +
	    '<?=$Dictionnary["DownloadFile"]; ?>' +
	    '</a>' +
	    '</td></tr></table>';
	;
    }
    else
    {
	screen = screen.getElementsByTagName("div")[0];
	screen.style.backgroundImage = "";
	var data;

	try {
	    data = atob(asset);
	} catch (e) {
	    data = asset;
	}
	screen.innerHTML = data;
    }
    screen.style.display = "block";
}

function display_panel(page, panel, to_admin = false, to_user = false)
{
    var panels = document.getElementsByClassName("module_panel");
    var links = document.getElementsByClassName("module_link");

    if (to_admin)
	localStorage.setItem("admin_" + page, 1);
    if (to_user)
	localStorage.setItem("admin_" + page, 0);
    
    Array.prototype.forEach.call(panels, function(el) {
	el.style.display = "none";
    });
    Array.prototype.forEach.call(links, function(el) {	
	el.classList.remove("selected");
    });
    if (panel == -1)
	document.getElementById("resume").style.display = "block";
    else
    {
	var form_id = "form_" + page + "_" + panel + "_admin";
	var admin_id = page + "_" + panel + "_admin";
	var tpanel = panel;

	if (localStorage.getItem("admin_module") == 1 && document.getElementById(form_id) != null)
	{
	    load_medal_panel(document.getElementById(form_id), admin_id);
	    // adapt_scroll_size("" + panel);
	    tpanel = tpanel + "_admin";
	}
	var lnk = document.getElementById(page + "_link" + panel);
	var mod = document.getElementById(page + "_" + tpanel);

	if (lnk)
	    lnk.classList.add("selected");
	if (mod)
	    mod.style.display = "block";
    }
    <?php if (isset($User["id"])) { ?>
    localStorage.setItem(page + '<?=$User["id"]; ?>', panel);
    localStorage.setItem("<?=$Position; ?>_last_panel", page);
    <?php } ?>
}
