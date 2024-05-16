
function reset_form(form)
{
    form.style.backgroundColor = null;
}

var file_load_counter = 0;

function gather_form(form, wrapper, ismap)
{
    var filling;
    var name;
    var value;
    var data;

    data = new Object;
    if (wrapper != "")
    {
	if (ismap)
	    filling = data[wrapper] = new Map;
	else
	{
	    data[wrapper] = new Array(1);
	    filling = data[wrapper][0] = new Map;
	}
    }
    else
	filling = data;
 
    var fields = form.elements;
    
    for (name in fields)
    {
	if (fields[name].name == "")
	    continue ;
	if (fields[name].type == "checkbox")
	    filling[fields[name].name] = fields[name].checked ? 1 : 0;
	else if (fields[name].type == "file")
	{
	    file_load_counter += fields[name].files.length;
	    filling[fields[name].name] = new Array;
	    for (let i = 0; i < fields[name].files.length; ++i)
	    {
		// Je deteste ce langage.
		(function(filling, fields, name, i) {
		    var reader = new FileReader();
		    var nam = fields[name].name;

		    filling[fields[name].name][i] = new Object;
		    filling[nam][i]["name"] = fields[nam].files[i].name;
		    reader.onload = function() {
			if (reader.readyState != 2)
			    return ;
			filling[nam][i]["content"] = btoa(reader.result);
			file_load_counter -= 1;
		    }
		    reader.onerror = function() {
			console.log(reader.error);
		    }
		    reader.readAsBinaryString(fields[nam].files[i]);
		})(filling, fields, name, i);
	    }
	}
	else
	    filling[fields[name].name] = fields[name].value;
    }

    return (data);
}

function silent_submitf(form, cnf)
{
    return (silent_submit(
	form,
	cnf["tofill"],
	cnf["toadd"],
	cnf["toclear"],
	cnf["toremove"],
	cnf["body"],
	cnf["wrapper"],
	cnf["ismap"],
	cnf["clear_form"],
	cnf["after_success"],
	cnf["after_success_parameter"]
    ));
}

function refresh()
{
    location.reload();
}

function wait_complete_loading(form, tofill, toadd, toclear, toremove, clear_form, after_success, after_success_parameter, method, data)
{
    if (file_load_counter != 0)
    {
	setTimeout(wait_complete_loading, 300, form, tofill, toadd, toclear, toremove, clear_form, after_success, after_success_parameter, method, data);
	return (false);
    }
    // Ca y est, le paquet est pret !
    data = JSON.stringify(data);
    // console.log(data);
    // On lance l'AJAX
    form.style.backgroundColor = "yellow";
    var ret = send_ajax(
	method,
	form.getAttribute("action"),
	data,
	tofill,
	toadd,
	toclear,
	toremove,
	function (success, result, msg, content)
	{
	    setTimeout(reset_form, 1000, form);
	    form.style.backgroundColor = "green";

	    if (after_success && success)
		after_success(result, msg, content, after_success_parameter);
	    
	    if (!clear_form)
		return ;
	    // On vide le formulaire en fin de requete 100% réussie si c'était un ajout
	    var fields = form.elements;
	    
	    for (name in fields)
	    {
		if (fields[name].type == "checkbox")
		    fields[name].checked = false;
		else if (fields[name].type == "text")
		    fields[name].value = "";
		else if (fields[name].type == "file")
		    fields[name].value = "";
		else if (fields[name].tagName == "TEXTAREA")
		    fields[name].value = "";
	    }
	},
	function ()
	{ // En cas d'échec...
	    form.style.backgroundColor = "red";
	    setTimeout(reset_form, 1000, form);

	}
    ); 
}

function silent_submit(form, tofill = null, toadd = null, toclear = null, toremove = null, body = null, wrapper = "", ismap = false, clear_form = false, after_success = null, after_success_parameter = null)
{
    // On récupère le formulaire
    while (form != null && form.tagName.toLowerCase() != "form")
	form = form.parentNode;
    if (form == null)
    {
	console.log("Invalid form.");
	console.trace();
	return (false);
    }
    var method = form.getAttribute("method");
    var data;

    // On remonte les informations du formulaire
    if (method.toUpperCase() != "GET" && body == null)
	data = gather_form(form, wrapper, ismap);
    else
	data = body;

    if (file_load_counter == 0)
	wait_complete_loading(form, tofill, toadd, toclear, toremove, clear_form, after_success, after_success_parameter, method, data);
    else
	setTimeout(wait_complete_loading, 300, form, tofill, toadd, toclear, toremove, clear_form, after_success, after_success_parameter, method, data);
    
    return (false); // Pour éviter le submit
}
