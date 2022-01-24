function edit_lock(activity, user, button)
{
    var xhr = new XMLHttpRequest();
    var dest = "index.php?p=<?=$Position; ?>&silent=1";
    var method = "post";
    var data = [];
    var serial = "";
    var name;
    var value;

    data.push(encodeURIComponent("action") + "=" + encodeURIComponent("edit_lock"));
    data.push(encodeURIComponent("activity") + "=" + encodeURIComponent(activity));
    data.push(encodeURIComponent("user") + "=" + encodeURIComponent(user));
    serial = data.join('&').replace('/%20/g', '+');

    xhr.addEventListener("load", function(event) {
	button.style.backgroundColor = "green";
	if (button.value == "")
	    button.value = "X";
	else
	    button.value = "";
	console.log(xhr.responseText);
	setTimeout(reset_form, 500, button);
    });
    xhr.addEventListener("error", function(event) {
	console.log(xhr.responseText);
	setTimeout(reset_form, 500, button);
    });
    xhr.open(method, dest, false);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    button.style.backgroundColor = "yellow";
    xhr.send(serial);
}
