
function remove_loading_screen()
{
    let top = document.getElementById("loading_screen1");
    let bot = document.getElementById("loading_screen2");
    var log = document.getElementById("loading_logo");
    let now = Date.now();
    let dif = false;
    let opa = 1.0;

    if (now - start < 200)
    {
	top.style.display = "none";
	bot.style.display = "none";
	log.style.display = "none";
	return ;
    }

    if (top.clientHeight > 0)
    {
	let res = top.clientHeight - 10;

	if (res < 0)
	    res = 0;
	top.style.height = res + "px";
	dif = true;
    }
    if (bot.clientHeight > 0)
    {
	let res = bot.clientHeight - 10;

	if (res < 0)
	    res = 0;
	bot.style.height = res + "px";
	dif = true;
    }
    
    if (bot.clientHeight == 0)
	log.style.display = "none";
    else
	opa = bot.clientHeight / (window.innerHeight / 2);

    if (opa <= 0.5)
    {
	opa = bot.clientHeight / (window.innerHeight / 4);
	log.style.opacity = opa;
    }
    else
	opa = 1.0;
    log.style.top = (top.clientHeight - log.clientHeight / 2) + "px";
    if (dif)
	setTimeout(remove_loading_screen, 10);
}
