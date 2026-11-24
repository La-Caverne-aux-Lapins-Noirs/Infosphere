
function send_ajax_target(target, role)
{
    if (!target)
	return (null);
    if (typeof target == "string")
    {
	let id = target;

	target = document.getElementById(id);
	if (target == null)
	{
	    console.warn("send_ajax: unable to find " + role + " target #" + id);
	    return (null);
	}
    }
    return (target);
}

function send_ajax_download_filename(disposition)
{
    if (!disposition)
        return ("download.bin");

    let utf8 = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8 && utf8[1])
    {
        try { return (decodeURIComponent(utf8[1])); }
        catch (e) {}
    }
    let quoted = disposition.match(/filename="([^"]+)"/i);
    if (quoted && quoted[1])
        return (quoted[1]);
    let plain = disposition.match(/filename=([^;]+)/i);
    if (plain && plain[1])
        return (plain[1].trim());
    return ("download.bin");
}

function send_ajax_download_blob(blob, disposition)
{
    let url = URL.createObjectURL(blob);
    let anchor = document.createElement("a");

    anchor.href = url;
    anchor.download = send_ajax_download_filename(disposition);
    anchor.style.display = "none";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
}

function send_ajax(method, url, data, tofill = null, toadd = null, toclear = null, toremove = null, gcall = null, bcall = null, verbose = false, download = false)
{
    var xhr = new XMLHttpRequest();

    xhr.addEventListener("load", function(event) {
	let json;

	if (xhr.status == 413)
	{
	    set_error_div("Le fichier ou l'ensemble de l'envoi dépasse la taille maximale autorisée par le serveur.", false);
	    if (gcall)
		gcall(false, null, null, null);
	    if (bcall)
		bcall();
	    return ;
	}

	if (download)
	{
	    let disposition = xhr.getResponseHeader("Content-Disposition") || "";
	    let content_type = xhr.getResponseHeader("Content-Type") || "";
	    let is_attachment = /attachment/i.test(disposition);
	    let is_json = /application\/json/i.test(content_type);

	    if (xhr.status >= 200 && xhr.status < 300 && is_attachment && !is_json)
	    {
		if (!xhr.response || xhr.response.size == 0)
		{
		    set_error_div("Le fichier retourné est vide.", false);
		    if (gcall)
			gcall(false, null, null, null);
		    return ;
		}
		send_ajax_download_blob(xhr.response, disposition);
		if (gcall)
		    gcall(true, "ok", "", xhr.response);
		return ;
	    }

	    if (!xhr.response || typeof xhr.response.text != "function")
	    {
		set_error_div(xhr.statusText || "Réponse serveur invalide.", false);
		if (bcall)
		    bcall();
		return ;
	    }

	    xhr.response.text().then(function(response_text) {
		let response;

		try
		{
		    response = JSON.parse(response_text);
		}
		catch (e)
		{
		    set_error_div(response_text || xhr.statusText || "Réponse serveur invalide.", false);
		    if (bcall)
			bcall();
		    return ;
		}

		let result = response["result"] || "";
		let msg = response["msg"] || "";
		let content = response["content"] || "";

		if (result == "ok")
		{
		    if (gcall)
			gcall(true, result, msg, content);
		}
		else if (result == "DEBUG")
		{
		    let dbox = document.getElementById("debugbox");
		    if (dbox)
		    {
			dbox.style.display = "block";
			dbox.textContent = msg + "\n" + content;
		    }
		}
		else if (gcall)
		    gcall(false, null, null, null);

		if (msg != "")
		    set_error_div(msg, result == "ok");
	    }).catch(function() {
		set_error_div(xhr.statusText || "Réponse serveur invalide.", false);
		if (bcall)
		    bcall();
	    });
	    return ;
	}

	if (verbose)
            console.log(xhr.responseText);

	if (xhr.responseText == "")
	{
	    set_error_div(
		"<?=$Dictionnary["ServerReturnErrorStatus"]; ?>'" +
		    xhr.statusText +
		    "'",
		false
	    );
	    if (bcall)
		bcall();
	    return ("");
	}
	
	try
	{
	    json = JSON.parse(xhr.responseText);
	}
	catch (e)
	{
	    let trace = xhr.responseText; // Plus explicite
	    // Il y a peut etre des appels a debug_response
	    let dbg_tok = '{"result":"DEBUG","msg":"DEBUG","content":"';
	    let debug = trace.indexOf(dbg_tok);
	    
	    if (debug != -1)
	    {
		// Il y a du debug. On le retire de la trace.
		let tmp = trace.substring(0, debug);
		// On peut maintenant lire le JSON
		debug = trace.substring(debug);
		trace = tmp;
		debug = JSON.parse(debug);
		debug = debug["content"];
	    }
	    else
		debug = "";

	    // On neutralise les caractères HTML
	    trace = trace.replaceAll(/<br \/>/g, "\n");
	    trace = trace.replaceAll(/[ ]+/g, " ").replaceAll(/</g, "&lt;").replaceAll(/>/g, "&rt;");

	    // On forge le message final
	    let msg = "";

	    if (trace.length)
		msg = msg + "Error:<br />" + trace + "<br />";
	    if (debug.length)
		msg = msg + "Debug:<br />" + debug + "<br />";
	    console.log(msg);
	    set_debug_div(msg);
	    // console.log(json);
	    if (bcall)
		bcall(json["result"] == "ok");
	    return ("");
	}

	let result = "";
	let msg = "";
	let content = "";

	if (typeof json["result"] !== "undefined")
	    result = json["result"];
	if (typeof json["msg"] !== "undefined")
	    msg = json["msg"];
	if (typeof json["content"] !== "undefined")
	    content = json["content"];

	if (result == "ok")
	{
	    if (gcall)
		gcall(true, result, msg, content);
	    if (toclear)
	    {
		toclear = single_to_array(toclear);
		for (let i in toclear)
		{
		    toclear[i] = send_ajax_target(toclear[i], "clear");
		    if (toclear[i] == null)
			continue ;
		    toclear[i].value = "";
		    toclear[i].innerHTML = "";
		}
	    }
	    if (toremove)
	    {
		toremove = send_ajax_target(toremove, "remove");
		if (toremove != null)
		    toremove.remove();
	    }
	    if (tofill)
	    {
		tofill = send_ajax_target(tofill, "fill");
		if (tofill != null)
		{
		    tofill.innerHTML = ""; // Pour forcer le rafraich des images
		    tofill.innerHTML = content;
		}
	    }
	    if (toadd)
	    {
		toadd = send_ajax_target(toadd, "add");
		if (toadd != null)
		{
		    let ext = new DOMParser().parseFromString(content, "text/html");
		    toadd.appendChild(ext.firstChild);
		}
	    }
	}
	else if (result == "DEBUG")
	{
	    var dbox = document.getElementById("debugbox");
	    
	    dbox.style.display = "block";
	    dbox.innerHTML = new DOMParser()
		.parseFromString("<h1>" + msg + "</h1>\n<br />" + "<p>" + content + "</p>", "text/html")
		.documentElement
		.textContent
	    ;
	}
	else if (gcall)
	    gcall(false, null, null, null);

	if (msg != "")
	    set_error_div(msg, result == "ok");
    });

    xhr.addEventListener("error", function(event) {
	if (verbose)
	    console.log(xhr.statusText); // Debug
	set_error_div(download ? (xhr.statusText || "Erreur de téléchargement.") : xhr.responseText, false);
	if (bcall)
	    bcall();
    });

    xhr.open(method, url, true);
    if (download)
	xhr.responseType = "blob";
    // xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    if (!(typeof FormData != "undefined" && data instanceof FormData))
	xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    xhr.send(data);

}

