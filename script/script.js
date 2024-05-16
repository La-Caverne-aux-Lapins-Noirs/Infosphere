var clock_content = "";

function scroll_to_bottom(elm)
{
    var box = document.getElementById(elm);

    box.scrollTop = box.scrollHeight;
}

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

function blinkall()
{
    var elems = document.getElementsByClassName("blink");

    for (let i = 0; i < elems.length; ++i)
    {
	if (elems[i].style.opacity == 1)
	    elems.item(i).style.opacity = "0.3";
	else
	    elems.item(i).style.opacity = "1.0";
    }
    setTimeout(blinkall, 50);
}

function roll_unroll(id)
{
    var idx = document.getElementById(id);
    var button = document.getElementById(id + "_button");
    
    localStorage.setItem(id, button.value);
    if (button.value == '+')
    {
	idx.style.display = "block";
	button.value = "−";
    }
    else
    {
	idx.style.display = "none";
	button.value = "+";
    }
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

var CurrentMousePosition = {x: 0, y: 0};
var CurrentLeftClickStatus = false;
var last_active = 0;
window.addEventListener('mousemove', e =>
    {
	var cnt = Date.now();

	CurrentMousePosition = {x: e.clientX, y: e.clientY};
	if (cnt - last_active > 60000)
	{
	    var xhr = new XMLHttpRequest();

	    xhr.open("get", "index.php?silent=1", true);
	    xhr.send();
	    last_active = cnt;
	}
    }
);

window.addEventListener('click', e =>
    {
	CurrentLeftClickStatus = e.button == 1;
	CurrentMousePosition = {x: e.clientX, y: e.clientY};
    }
);


blinkall();
