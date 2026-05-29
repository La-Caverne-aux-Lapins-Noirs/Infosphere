let currently_open_action_div = null;

let currently_selected_action = null;

function xconfirm(select)
{
    if (currently_selected_action != select)
    {
	if (currently_selected_action != null)
	    document.getElementById(currently_selected_action).style.opacity = 1.0;
	currently_selected_action = select;
	document.getElementById(currently_selected_action).style.opacity = 0.5;
	return (false);
    }
    currently_selected_action = null;
    return (true);
}

function get_file_browser_for_action_div(action_div)
{
    let row = action_div.closest("tr");

    if (!row)
        return (null);

    return (row.querySelector(".prospect_file_browser"));
}

function expand_file_browser_for_action_div(action_div)
{
    let file_browser = get_file_browser_for_action_div(action_div);

    if (!file_browser)
        return;

    file_browser.style.setProperty(
        "--prospect-file-browser-open-height",
        Math.max(75, action_div.scrollHeight) + "px"
    );
    file_browser.classList.add("open");
}

function close_file_browser_for_action_div(action_div)
{
    let file_browser = get_file_browser_for_action_div(action_div);

    if (!file_browser)
        return;

    file_browser.classList.remove("open");
    file_browser.style.removeProperty("--prospect-file-browser-open-height");
}

function open_action_div(div)
{
    let menu = div.querySelector(".action_menu");

    if (menu)
        menu.classList.remove("hidden");

    div.classList.add("open");
    div.style.maxHeight = div.scrollHeight + "px";
    expand_file_browser_for_action_div(div);
}

function close_action_div(div)
{
    let menu = div.querySelector(".action_menu");

    if (menu)
        menu.classList.add("hidden");

    div.classList.remove("open");
    div.style.maxHeight = "20px";
    close_file_browser_for_action_div(div);
}

function toggle_action_menu(btn)
{
    let action_div = btn.closest(".action_div");

    if (!action_div)
        return;

    if (currently_open_action_div && currently_open_action_div !== action_div)
    {
        close_action_div(currently_open_action_div);
        currently_open_action_div = null;
    }

    if (action_div === currently_open_action_div)
    {
        close_action_div(action_div);
        currently_open_action_div = null;
        return;
    }

    open_action_div(action_div);
    currently_open_action_div = action_div;
}

document.addEventListener("click", function(e)
{
    if (!currently_open_action_div)
        return;

    if (currently_open_action_div.contains(e.target))
        return;

    close_action_div(currently_open_action_div);
    currently_open_action_div = null;
});

document.addEventListener("keydown", function(e)
{
    if (e.key === "Escape" && currently_open_action_div)
    {
        close_action_div(currently_open_action_div);
        currently_open_action_div = null;
    }
});

document.addEventListener('click', async function (event) {

    const td = event.target.closest('td.copyable');

    if (!td)
        return;

    const text = td.innerText.trim();

    if (text === '')
        return;

    try {
        await navigator.clipboard.writeText(text);
        td.classList.add('copied');
        setTimeout(() => td.classList.remove('copied'), 300);
    } catch (e) {

    }
});

function open_generated_contract(result, msg, content, parameter)
{
    if (content)
	window.open(content);
}
