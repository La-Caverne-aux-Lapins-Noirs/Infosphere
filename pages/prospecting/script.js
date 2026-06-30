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


function prospecting_campaign_storage_key()
{
    return "prospecting_current_campaign";
}

function prospecting_campaign_index_by_id(id)
{
    id = parseInt(id, 10);
    if (!Array.isArray(window.prospecting_campaigns))
        return (-1);
    for (let i = 0; i < window.prospecting_campaigns.length; ++i)
        if (parseInt(window.prospecting_campaigns[i].id, 10) == id)
            return (i);
    return (-1);
}

function prospecting_campaign_current_index()
{
    let select = document.getElementById("prospecting_campaign_select");

    if (!select)
        return (-1);
    return (prospecting_campaign_index_by_id(select.value));
}

function prospecting_campaign_summary(campaign)
{
    let summary = document.getElementById("prospecting_campaign_summary");

    if (!summary || !campaign)
        return ;
    summary.innerText = "Campagne " + (prospecting_campaign_current_index() + 1)
        + " / " + window.prospecting_campaigns.length;
}

function prospecting_campaign_execute_scripts(root)
{
    if (!root)
        return ;
    root.querySelectorAll("script").forEach(function(old_script) {
        let script = document.createElement("script");

        Array.prototype.forEach.call(old_script.attributes, function(attribute) {
            script.setAttribute(attribute.name, attribute.value);
        });
        script.text = old_script.textContent;
        old_script.parentNode.replaceChild(script, old_script);
    });
}

function prospecting_campaign_load(id)
{
    let target = document.getElementById("prospecting_campaign_table");
    let index = prospecting_campaign_index_by_id(id);
    let campaign;

    if (!target || index < 0)
        return ;
    campaign = window.prospecting_campaigns[index];
    localStorage.setItem(prospecting_campaign_storage_key(), campaign.id);
    prospecting_campaign_summary(campaign);
    target.innerHTML = "<p>Chargement de la campagne...</p>";
    send_ajax(
        "GET",
        "/api/campaign/" + encodeURIComponent(campaign.id) + "/prospects",
        null,
        "prospecting_campaign_table",
        null,
        null,
        null,
        function(success) {
            let table = document.getElementById("prospecting_campaign_table");

            if (!success)
                return ;
            prospecting_campaign_execute_scripts(table);
            if (typeof init_bigselects == "function")
                init_bigselects(table);
        }
    );
}

function prospecting_campaign_select(id)
{
    let select = document.getElementById("prospecting_campaign_select");
    let index = prospecting_campaign_index_by_id(id);

    if (!select || index < 0)
        return ;
    select.value = window.prospecting_campaigns[index].id;
    prospecting_campaign_load(select.value);
}

function prospecting_campaign_move(delta)
{
    let index = prospecting_campaign_current_index();

    if (index < 0)
        return ;
    index += delta;
    if (index < 0)
        index = 0;
    if (index >= window.prospecting_campaigns.length)
        index = window.prospecting_campaigns.length - 1;
    prospecting_campaign_select(window.prospecting_campaigns[index].id);
}

function prospecting_campaign_init()
{
    let select = document.getElementById("prospecting_campaign_select");
    let stored;

    if (!select || !Array.isArray(window.prospecting_campaigns) || window.prospecting_campaigns.length == 0)
        return ;
    stored = localStorage.getItem(prospecting_campaign_storage_key());
    if (prospecting_campaign_index_by_id(stored) < 0)
        stored = window.prospecting_campaigns[0].id;
    prospecting_campaign_select(stored);
}
