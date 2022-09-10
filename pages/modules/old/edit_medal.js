function edit_medal(activity, user, medal)
{
    var xhr = new XMLHttpRequest();
    var div = document.getElementById(user + "_" + medal);
    var dest = "index.php?p=<?=$Position; ?>&silent=1";
    var method = "post";
    var data = [];
    var serial = "";
    var name;
    var value;
    var visible = div.style.visibility == "visible";

    data.push(encodeURIComponent("action") + "=" + encodeURIComponent("add_medal"));
    data.push(encodeURIComponent("activity") + "=" + encodeURIComponent(activity));
    data.push(encodeURIComponent("user") + "=" + encodeURIComponent(user));
    if (visible == false)
	data.push(encodeURIComponent("medal") + "=" + encodeURIComponent(medal));
    else
	data.push(encodeURIComponent("medal") + "=" + encodeURIComponent("-" + medal));
    serial = data.join('&').replace('/%20/g', '+');

    xhr.addEventListener("load", function(event) {
	div.style.backgroundColor = "green";
	if (visible)
	    div.style.visibility = "hidden";
	else
	    div.style.visibility = "visible";
	console.log(xhr.responseText);
	setTimeout(reset_form, 500, div);
    });
    xhr.addEventListener("error", function(event) {
	div.style.backgroundColor = "red";
	console.log(xhr.responseText);
	setTimeout(reset_form, 500, div);
    });
    xhr.open(method, dest, false);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    div.style.backgroundColor = "yellow";
    xhr.send(serial);
}
