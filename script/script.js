var clock_content = "";

function SetContentClock(str)
{
    clock_content = str;
    if (str != "")
	clock.innerHTML = clock_content;
}

function enable(obj, enable)
{
    document.getElementById(obj).disabled = !enable;
}

function setcookie(id, value)
{
    setCookie(id, value, 365 * 10);
}

function toggle_roll(id, size)
{
    var div = document.getElementById(id);

    if (div.offsetHeight <= size + 5)
    {
	div.style.height = "unset";
	div.style.overflow = null;
    }
    else
    {
	div.style.height = size + "px";
	div.style.overflow = "hidden";
    }
    /* console.log(id); */
    setCookie(id, div.style.height, 365 * 10);
}

function toggle_rollc(cook, cla, size, obj, show, hide)
{
    var div = document.getElementsByClassName(cla);
    var ck = getCookie(cook);

    if (ck == null)
	ck = 0;
    else
	ck = parseInt(ck);
    if (ck == 0)
	obj.innerHTML = hide;
    else
	obj.innerHTML = show;
    for (var i = 0; i < div.length; i++)
    {
	if (ck == 0)
	    div.item(i).style.visibility = "visible";
	else
	    div.item(i).style.visibility = "collapse";
    }
    setCookie(cook, ck > 0 ? "0" : "1", 365 * 10);
}

var last_active = 0;
window.addEventListener('mousemove', e =>
    {
	var cnt = Date.now();

	if (cnt - last_active > 60000)
	{
	    var xhr = new XMLHttpRequest();

	    xhr.open("get", "index.php?silent=1", true);
	    xhr.send();
	    last_active = cnt;
	}
    }
);
