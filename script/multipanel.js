function media_type_from_path(asset, family)
{
    let clean = String(asset).split('?')[0].toLowerCase();

    if (family == "video")
    {
	if (clean.endsWith(".m3u8"))
	    return ("application/vnd.apple.mpegurl");
	if (clean.endsWith(".webm"))
	    return ("video/webm");
	if (clean.endsWith(".ogv") || clean.endsWith(".ogg"))
	    return ("video/ogg");
	return ("video/mp4");
    }
    if (family == "audio")
    {
	if (clean.endsWith(".ogg"))
	    return ("audio/ogg");
	return ("audio/mpeg");
    }
    return ("");
}

var support_progress_last_sent = {};

function support_progress_decrement_parent_marker(id_support)
{
    if (!id_support)
	return ;
    let markers = document.querySelectorAll('[data-support-unseen-parent="' + id_support + '"]');

    for (let marker of markers)
    {
	let count = parseInt(marker.getAttribute("data-support-unseen-count") || "1", 10);
	count -= 1;
	if (count <= 0)
	    marker.remove();
	else
	{
	    marker.setAttribute("data-support-unseen-count", count);
	    marker.setAttribute("title", count == 1 ? "Support non consulté" : count + " supports non consultés");
	}
    }
}

function support_progress_update_display(asset_id, progress)
{
    if (!asset_id)
	return ;
    let links = document.querySelectorAll('[data-support-asset-id="' + asset_id + '"]');

    for (let link of links)
    {
	let was_unseen = link.classList.contains("support_unseen_asset_link");
	link.setAttribute("data-support-asset-progress", progress);
	if (progress > 0)
	{
	    link.classList.remove("support_unseen_asset_link");
	    for (let marker of link.getElementsByClassName("support_unseen_asset_marker"))
		marker.remove();
	    if (was_unseen)
		support_progress_decrement_parent_marker(link.getAttribute("data-support-id"));
	}
    }
}

function support_find_next_asset_link(link)
{
    if (!link || !link.getAttribute)
	return (null);

    let support_id = link.getAttribute("data-support-id");
    let asset_id = link.getAttribute("data-support-asset-id");
    let links;
    let found = false;

    if (!support_id || !asset_id)
	return (null);
    links = document.querySelectorAll(
	'.asset_link[data-support-id="' + support_id + '"][data-support-asset-id]'
    );
    for (let next of links)
    {
	if (found)
	    return (next);
	if (next == link || next.getAttribute("data-support-asset-id") == asset_id)
	    found = true;
    }
    return (null);
}

function support_next_asset_name(link)
{
    if (!link)
	return ("");

    let label = link.querySelector(".support_compact_pretty");
    let text = label ? label.textContent : link.textContent;

    text = String(text || "").replace(/\s+/g, " ").trim();
    return (text);
}

function support_hide_next_asset_prompt(screen)
{
    if (typeof screen == "string")
	screen = document.getElementById(screen);
    if (!screen)
	return ;
    for (let prompt of screen.getElementsByClassName("support_next_asset_prompt"))
	prompt.remove();
}

function support_show_next_asset_prompt(screen_id, current_link)
{
    let screen = document.getElementById(screen_id);
    let next = support_find_next_asset_link(current_link);
    let prompt;
    let title;
    let button;

    if (!screen || !next)
	return ;
    support_hide_next_asset_prompt(screen);
    prompt = document.createElement("div");
    prompt.className = "support_next_asset_prompt";
    title = document.createElement("span");
    title.className = "support_next_asset_prompt_title";
    title.textContent = support_next_asset_name(next);
    button = document.createElement("input");
    button.type = "button";
    button.value = next.getAttribute("data-support-asset-type") == "video"
	? "Enchaîner la vidéo suivante"
	: "Enchaîner la ressource suivante";
    button.onclick = function(event) {
	if (event)
	{
	    event.preventDefault();
	    event.stopPropagation();
	}
	support_hide_next_asset_prompt(screen);
	next.click();
	return (false);
    };
    prompt.appendChild(title);
    prompt.appendChild(button);
    screen.appendChild(prompt);
}

function support_record_asset_progress(asset_id, progress)
{
    asset_id = parseInt(asset_id, 10);
    progress = Math.max(0, Math.min(100, Math.round(Number(progress) || 0)));
    if (!asset_id || asset_id <= 0 || progress <= 0)
	return ;

    let now = Date.now();
    let last = support_progress_last_sent[asset_id];
    if (last && progress < 100)
    {
	if (progress <= last.progress)
	    return ;
	if (progress < last.progress + 5 && now - last.time < 10000)
	    return ;
    }
    support_progress_last_sent[asset_id] = {progress: progress, time: now};
    support_progress_update_display(asset_id, progress);
    send_ajax(
	"POST",
	"/api/support_progress/" + asset_id,
	JSON.stringify({progress: progress}),
	null,
	null,
	null,
	null,
	function(success) {
	    if (!success)
		return ;
	    support_progress_update_display(asset_id, progress);
	}
    );
}

function support_video_reset_player(video)
{
    support_hide_next_asset_prompt(video.parentElement);
    if (video._infosphere_hls != null)
    {
	video._infosphere_hls.destroy();
	video._infosphere_hls = null;
    }
    video.ontimeupdate = null;
    video.onended = null;
    video.onloadedmetadata = null;
    video._infosphere_progress_asset_id = null;
    video.pause();
    video.removeAttribute("src");
    for (let src of video.getElementsByTagName("source"))
    {
	src.removeAttribute("src");
	src.removeAttribute("type");
    }
    video.load();
}

function support_media_reset_player(media)
{
    if (!media || !media.tagName)
	return ;

    let tag = media.tagName.toLowerCase();

    if (tag == "video")
    {
	support_video_reset_player(media);
	return ;
    }
    if (tag == "audio")
    {
	media.pause();
	media.removeAttribute("src");
	media.removeAttribute("type");
	media.load();
	return ;
    }
    if (tag == "iframe")
    {
	media.removeAttribute("src");
	return ;
    }
}

function support_stop_all_media()
{
    for (let media of document.getElementsByTagName("video"))
	support_media_reset_player(media);
    for (let media of document.getElementsByTagName("audio"))
	support_media_reset_player(media);
    for (let media of document.getElementsByClassName("screen_media"))
	if (media.tagName && media.tagName.toLowerCase() == "iframe")
	    support_media_reset_player(media);
}

function support_video_play(video, asset, hls_asset, asset_id = null, link = null, screen_id = null)
{
    support_video_reset_player(video);
    support_hide_next_asset_prompt(screen_id);
    video._infosphere_progress_asset_id = asset_id;
    if (asset_id)
    {
	support_record_asset_progress(asset_id, 1);
	video.ontimeupdate = function() {
	    if (!video.duration || !isFinite(video.duration) || video.duration <= 0)
		return ;
	    support_record_asset_progress(asset_id, (video.currentTime / video.duration) * 100);
	};
	video.onended = function() {
	    support_record_asset_progress(asset_id, 100);
	    support_show_next_asset_prompt(screen_id, link);
	};
    }

    if (hls_asset && video.canPlayType("application/vnd.apple.mpegurl"))
    {
	video.src = hls_asset;
	video.load();
	video.play();
	return ;
    }

    if (hls_asset && window.Hls && Hls.isSupported())
    {
	video._infosphere_hls = new Hls({
	    maxBufferLength: 20,
	    maxMaxBufferLength: 60,
	    backBufferLength: 30
	});
	video._infosphere_hls.loadSource(hls_asset);
	video._infosphere_hls.attachMedia(video);
	video._infosphere_hls.on(Hls.Events.MANIFEST_PARSED, function() {
	    video.play();
	});
	return ;
    }

    for (let src of video.getElementsByTagName("source"))
    {
	src.src = asset;
	src.type = media_type_from_path(asset, "video");
    }
    video.load();
    video.play();
}

function switch_asset(link, screen, type, asset, hls_asset = "")
{
    var links = document.getElementsByClassName("asset_link");
    var screens = document.getElementsByClassName("screen_media");
    var screen_id = screen;

    if ((screen = document.getElementById(screen)) == null)
	return ;
    let asset_id = link && link.getAttribute ? link.getAttribute("data-support-asset-id") : null;
    support_hide_next_asset_prompt(screen_id);
    support_stop_all_media();
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
	support_video_play(screen, asset, hls_asset, asset_id, link, screen_id);
    }
    else if (type == "audio")
    {
	screen = screen.getElementsByTagName("audio")[0];
	screen.src = asset;
	screen.type = media_type_from_path(asset, "audio");
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
    if (asset_id && type != "video")
	support_record_asset_progress(asset_id, 100);
    screen.style.display = "block";
    if (typeof support_asset_switched == "function")
	support_asset_switched(link, screen_id, type, asset_id);
}

function display_panel(page, panel, to_admin = false, to_user = false)
{
    var panels = document.getElementsByClassName("module_panel");
    var links = document.getElementsByClassName("module_link");

    support_stop_all_media();
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
