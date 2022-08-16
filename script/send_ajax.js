
function send_ajax(method, url, data, tofill = null, toadd = null, toclear = null, toremove = null, gcall = null, bcall = null, verbose = false)
{
    var xhr = new XMLHttpRequest();

    xhr.addEventListener("load", function(event) {
	let json;

	if (verbose)
            console.log(xhr.responseText);

	if (xhr.responseText == "")
	{
	    set_error_div("Server returns an empty answer with status '" + xhr.statusText + "'", false);
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
		bcall();
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
		gcall();
	    if (toclear)
	    {
		toclear = to_object_array(toclear);
		for (let i in toclear)
		{
		    toclear[i].value = "";
		    toclear[i].innerHTML = "";
		}
	    }
	    if (toremove)
	    {
		if (typeof toremove == "string")
		    toremove = document.getElementById(toremove);
		toremove.remove();
	    }
	    if (tofill)
	    {
		if (typeof tofill == "string")
		    tofill = document.getElementById(tofill);
		tofill.innerHTML = ""; // Pour forcer le rafraichissement des images
		tofill.innerHTML = content;
	    }
	    if (toadd)
	    {
		if (typeof toadd == "string")
		    toadd = document.getElementById(toadd);
		let ext = new DOMParser().parseFromString(content, "text/html");

		console.log(content);
		// toadd.appendChild(ext.firstChild);
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
	    gcall();

	if (msg != "")
	    set_error_div(msg, result == "ok");
    });

    xhr.addEventListener("error", function(event) {
	if (verbose)
	    console.log(xhr.statusText); // Debug
	set_error_div(xhr.responseText, false);
    });

    xhr.open(method, url, true);
    // xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    xhr.send(data);

}

