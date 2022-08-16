
function select_entry(name, id)
{
    var modules = document.getElementsByClassName("select_" + name);
    var target = document.getElementById(name + "_" + id);

    if (!modules || !target)
	return ;
    for (let mod of modules)
	mod.style.backgroundColor = null;
    target.style.backgroundColor = "rgba(0, 0, 0, 0.4)";
}

function remove_down_formular()
{
    eraseCookie("<?=$page; ?>-module");
    eraseCookie("<?=$page; ?>-activity");
    eraseCookie("<?=$page; ?>-session");
    document.getElementById("activity_list").innerText = "";
    document.getElementById("session_list").innerText = "";

    document.getElementById("edit_formular_title").innerHTML = "";
    document.getElementById("edit_formular").innerHTML = "";
}

function list_modules()
{
    send_ajax(
	"get",
	"/api/<?=$page; ?>",
	null,
	"module_list"
    );
    return (false);
}

function list_activities(module = "")
{
    if (module != "")
    {
	if (document.getElementById("module_list").innerText == "")
	{
	    setTimeout(list_activities, 100, module);
	    return (false);
	}
	setCookie("<?=$page; ?>-module", module);
	eraseCookie("<?=$page; ?>-activity");
	eraseCookie("<?=$page; ?>-session");
	document.getElementById("session_list").innerText = "";
	select_entry("module", module);
	document.getElementById("activity_add_form").style.display = "block";
	document.getElementById("session_add_form").style.display = "none";
	document.getElementById("selected_module").value = module;
	send_ajax(
	    "get",
	    "/api/<?=$page; ?>/" + module + "?sub=1",
	    null,
	    "activity_list"
	);
	document.getElementById("edit_formular_title").innerText =
	    <?php if ($page != "template") { ?>
	    "<?=$Dictionnary["EditModule"]; ?> #" + module
            <?php } else { ?>
	    "<?=$Dictionnary["EditTemplate"]; ?> #" + module
	    <?php } ?>
;
	send_ajax(
	    "get",
	    "/api/<?=$page; ?>/" + module,
	    null,
	    "edit_formular",
	    null, null, null, function() { setTimeout(select_formular, 1); }
	);
    }
    return (false);
}

function list_sessions(activity = "")
{
    if (activity != "")
    {
	if (document.getElementById("activity_list").innerText == "")
	{
	    setTimeout(list_sessions, 100, activity);
	    return (false);
	}
	setCookie("<?=$page; ?>-activity", activity);
	eraseCookie("<?=$page; ?>-session");
	select_entry("activity", activity);
	document.getElementById("session_add_form").style.display = "block";
	document.getElementById("selected_activity").value = activity;
	send_ajax(
	    "get",
	    "/api/activity/" + activity + "?sub=1",
	    null,
	    "session_list",
	);
	document.getElementById("edit_formular_title").innerText = "<?=$Dictionnary["EditActivity"]; ?> #" + activity;
	send_ajax(
	    "get",
	    "/api/activity/" + activity,
	    null,
	    "edit_formular",
	    null, null, null, function() { setTimeout(select_formular, 1); }
	);
    }
    return (false);
}

function display_session(session = "")
{
    if (session != "")
    {
	if (document.getElementById("session_list").innerText == "")
	{
	    setTimeout(display_session, 100, session);
	    return (false);
	}
	setCookie("<?=$page; ?>-session", session);
	select_entry("session", session);
	document.getElementById("edit_formular_title").innerText = "<?=$Dictionnary["EditSession"]; ?> #" + session;
	send_ajax(
	    "get",
	    "/api/session/" + session,
	    null,
	    "edit_formular"
	);
    }
}

var current_language = getCookie("<?=$page; ?>-language");
var current_category = getCookie("<?=$page; ?>-category");
var current_ressource = getCookie("<?=$page; ?>-ressource");

function select_formular()
{
    var blocks = document.getElementsByClassName("activity_configuration_block");

    if (document.getElementById("edit_formular").innerText == "")
    {
	setTimeout(select_formular, 100);
	return ;
    }

    for (let mod of blocks)
    {
	if (mod.classList.contains(current_category + "_" + current_language))
	    mod.style.display = "block";
	else if (mod.classList.contains(current_ressource + "_" + current_language))
	    mod.style.display = "block";
	else
	    mod.style.display = "none";
    }
}

function select_language(lng, thx)
{
    var doc = document.getElementsByClassName("languages_buttons");

    for (let d of doc)
    {
	d.style.color = "lightgray";
	d.style.fontWeight = "initial";
    }
    thx.style.color = "white";
    thx.style.fontWeight = "bold";
    setCookie("<?=$page; ?>-language", current_language = lng);
    select_formular();
}

function select_category(cat, thx)
{
    var doc = document.getElementsByClassName("categories_buttons");

    for (let d of doc)
    {
	d.style.color = "lightgray";
	d.style.fontWeight = "initial";
    }
    thx.style.color = "white";
    thx.style.fontWeight = "bold";
    setCookie("<?=$page; ?>-category", current_category = cat);
    select_formular();
}

function select_ressource(res, thx)
{
    var doc = document.getElementsByClassName("ressources_buttons");

    for (let d of doc)
    {
	d.style.color = "lightgray";
	d.style.fontWeight = "initial";
    }
    thx.style.color = "white";
    thx.style.fontWeight = "bold";
    setCookie("<?=$page; ?>-ressource", current_ressource = res);
    select_formular();
}
