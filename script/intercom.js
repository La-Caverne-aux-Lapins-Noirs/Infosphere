var icomscroll = new Array();
var icom_update_timer = new Array();

function restore_scroll(icomdiv_list)
{
    var box = document.getElementById(icomdiv_list);

    if (box)
	box.scrollTop = icomscroll[icomdiv_list];
}

function restore_scroll_delay(a, b, c, icomdiv_list)
{
    setTimeout(restore_scroll, 100, icomdiv_list);
}

function go_down(icomdiv_list)
{
    var box = document.getElementById(icomdiv_list);

    if (box)
	box.scrollTop = box.scrollHeight;
}

function intercom_after_manual_message_update(a, b, c, icomdiv_list)
{
    setTimeout(go_down, 100, icomdiv_list);
}

function intercom_after_full_message_update(a, b, c, icomdiv)
{
    setTimeout(function () {
	intercom_apply_message_layout(icomdiv);
	go_down(icomdiv + "_msg");
	check_update(icomdiv);
    }, 100);
}

function intercom_after_full_subject_update(a, b, c, icomdiv)
{
    setTimeout(check_update, 100, icomdiv);
}

function intercom_is_at_bottom(box)
{
    if (!box)
	return (false);
    return (box.scrollTop + box.clientHeight >= box.scrollHeight - 35);
}

function intercom_set_parameter(url, key, value)
{
    var separator = url.indexOf("?") == -1 ? "?" : "&";

    return (url + separator + encodeURIComponent(key) + "=" + encodeURIComponent(value));
}

function intercom_live_url(updater)
{
    var url = updater.getAttribute("action");

    url = intercom_set_parameter(url, "known_hash", updater.dataset.hash || "");
    url = intercom_set_parameter(url, "known_page", updater.dataset.page || 0);
    if (updater.dataset.mode == "message")
    {
	url = intercom_set_parameter(url, "known_count", updater.dataset.count || 0);
	url = intercom_set_parameter(url, "known_on_last_page", updater.dataset.onLastPage || 0);
	url = intercom_set_parameter(url, "since", updater.dataset.lastId || 0);
    }
    return (url);
}

function intercom_store_payload(updater, payload)
{
    if (!updater || !payload)
	return ;
    if (typeof payload.hash != "undefined")
	updater.dataset.hash = payload.hash;
    if (typeof payload.page != "undefined")
	updater.dataset.page = payload.page;
    if (typeof payload.page_size != "undefined")
	updater.dataset.pageSize = payload.page_size;
    if (typeof payload.count != "undefined")
	updater.dataset.count = payload.count;
    if (typeof payload.last_id != "undefined")
	updater.dataset.lastId = payload.last_id;
    if (typeof payload.on_last_page != "undefined")
	updater.dataset.onLastPage = payload.on_last_page;
}

function intercom_apply_message_layout(icomdiv)
{
    var msg = document.getElementById(icomdiv + "_msg");
    var composer = document.getElementById(icomdiv + "_message_composer");

    if (!msg || !composer)
	return ;
    if (composer.innerHTML.trim() == "")
	msg.style.bottom = "10px";
    else
	msg.style.bottom = "170px";
}

function intercom_apply_subject_update(icomdiv, payload)
{
    var header = document.getElementById(icomdiv + "_subject_header");
    var list = document.getElementById(icomdiv + "_subject_list");
    var old_scroll = list ? list.scrollTop : 0;

    if (header && typeof payload.header != "undefined")
	header.innerHTML = payload.header;
    if (list && typeof payload.list != "undefined")
    {
	list.innerHTML = payload.list;
	list.scrollTop = old_scroll;
    }
}

function intercom_apply_message_update(icomdiv, payload)
{
    var updater = document.getElementById(icomdiv + "_update_request");
    var msg = document.getElementById(icomdiv + "_msg");
    var controls = document.getElementById(icomdiv + "_message_controls");
    var composer = document.getElementById(icomdiv + "_message_composer");
    var at_bottom = intercom_is_at_bottom(msg);
    var old_scroll = msg ? msg.scrollTop : 0;

    if (controls && typeof payload.controls != "undefined")
	controls.innerHTML = payload.controls;
    if (composer && typeof payload.composer != "undefined")
	composer.innerHTML = payload.composer;

    if (msg && payload.mode == "append" && typeof payload.messages != "undefined")
	msg.insertAdjacentHTML("beforeend", payload.messages);
    else if (msg && payload.mode == "replace_messages" && typeof payload.messages != "undefined")
	msg.innerHTML = payload.messages;

    intercom_apply_message_layout(icomdiv);
    if (msg)
    {
	if (at_bottom)
	    msg.scrollTop = msg.scrollHeight;
	else
	    msg.scrollTop = old_scroll;
    }
    intercom_store_payload(updater, payload);
}

function intercom_apply_update(icomdiv, payload)
{
    var updater = document.getElementById(icomdiv + "_update_request");

    if (!payload)
	return ;
    if (payload.changed)
    {
	if (payload.mode == "subject")
	    intercom_apply_subject_update(icomdiv, payload);
	else
	    intercom_apply_message_update(icomdiv, payload);
    }
    intercom_store_payload(updater, payload);
}

function intercom_poll(icomdiv)
{
    var updater = document.getElementById(icomdiv + "_update_request");

    icom_update_timer[icomdiv] = null;
    if (updater == null)
    {
	intercom_schedule_update(icomdiv, 5000);
	return ;
    }

    var xhr = new XMLHttpRequest();
    xhr.addEventListener("load", function() {
	try
	{
	    var json = JSON.parse(xhr.responseText);
	    if (json.result == "ok" && typeof json.intercom != "undefined")
		intercom_apply_update(icomdiv, json.intercom);
	}
	catch (e)
	{
	    console.log(e);
	    console.log(xhr.responseText);
	}
	intercom_schedule_update(icomdiv, 5000);
    });
    xhr.addEventListener("error", function() {
	intercom_schedule_update(icomdiv, 8000);
    });
    xhr.open("GET", intercom_live_url(updater), true);
    xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    xhr.send(null);
}

function intercom_schedule_update(icomdiv, delay)
{
    if (icom_update_timer[icomdiv])
	clearTimeout(icom_update_timer[icomdiv]);
    icom_update_timer[icomdiv] = setTimeout(intercom_poll, delay, icomdiv);
}

function check_update(icomdiv)
{
    intercom_schedule_update(icomdiv, 5000);
}
