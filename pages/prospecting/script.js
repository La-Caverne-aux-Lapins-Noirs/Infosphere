let currently_open_action_div = null;

let currently_selected_action = null;


let prospecting_tooltip_div = null;
let prospecting_tooltip_target = null;

function prospecting_tooltip_ensure()
{
    if (!prospecting_tooltip_div)
    {
        prospecting_tooltip_div = document.createElement("div");
        prospecting_tooltip_div.className = "prospecting_global_tooltip";
        document.body.appendChild(prospecting_tooltip_div);
    }
    return (prospecting_tooltip_div);
}

function prospecting_tooltip_move(x, y)
{
    if (!prospecting_tooltip_div || !prospecting_tooltip_div.classList.contains("visible"))
        return;

    let left = x + 12;
    let top = y + 12;

    prospecting_tooltip_div.style.left = left + "px";
    prospecting_tooltip_div.style.top = top + "px";

    let rect = prospecting_tooltip_div.getBoundingClientRect();
    if (rect.right > window.innerWidth - 10)
        prospecting_tooltip_div.style.left = Math.max(10, x - rect.width - 12) + "px";
    if (rect.bottom > window.innerHeight - 10)
        prospecting_tooltip_div.style.top = Math.max(10, y - rect.height - 12) + "px";
}

function prospecting_tooltip_show(target, x, y)
{
    let content = target.getAttribute("data-tooltip");

    if (!content)
        return;

    let tooltip = prospecting_tooltip_ensure();
    prospecting_tooltip_target = target;
    tooltip.textContent = content;
    tooltip.classList.add("visible");
    prospecting_tooltip_move(x, y);
}

function prospecting_tooltip_hide()
{
    if (prospecting_tooltip_div)
        prospecting_tooltip_div.classList.remove("visible");
    prospecting_tooltip_target = null;
}

document.addEventListener("mouseover", function(e)
{
    let target = e.target.closest(".action_div [data-tooltip]");

    if (!target)
        return;
    prospecting_tooltip_show(target, e.clientX, e.clientY);
});

document.addEventListener("mousemove", function(e)
{
    if (!prospecting_tooltip_target)
        return;
    prospecting_tooltip_move(e.clientX, e.clientY);
});

document.addEventListener("mouseout", function(e)
{
    if (!prospecting_tooltip_target)
        return;
    if (e.target === prospecting_tooltip_target || prospecting_tooltip_target.contains(e.target))
        prospecting_tooltip_hide();
});

document.addEventListener("scroll", prospecting_tooltip_hide, true);



var prospecting_action_height_observer = null;

function prospecting_sync_action_height(file_browser)
{
    let row = file_browser.closest("tr");
    let action_div;
    let height;

    if (!row)
        return;
    action_div = row.querySelector(".prospect_action_history_cell .action_div");
    if (!action_div)
        return;

    // La hauteur du navigateur de fichiers ne doit être répercutée que
    // lorsque le détail des actions est effectivement déplié. Au repos,
    // on laisse les règles historiques rendre la barre compacte.
    if (!file_browser.classList.contains("open"))
    {
        action_div.style.removeProperty("min-height");
        return;
    }

    height = Math.ceil(file_browser.getBoundingClientRect().height);
    if (height > 0)
        action_div.style.minHeight = height + "px";
}

function prospecting_observe_action_heights(root)
{
    if (!root)
        root = document;

    if (typeof ResizeObserver == "function" && !prospecting_action_height_observer)
    {
        prospecting_action_height_observer = new ResizeObserver(function(entries) {
            entries.forEach(function(entry) {
                prospecting_sync_action_height(entry.target);
            });
        });
    }

    root.querySelectorAll(".prospect_file_browser").forEach(function(file_browser) {
        if (file_browser.dataset.actionHeightObserved == "1")
        {
            prospecting_sync_action_height(file_browser);
            return;
        }
        file_browser.dataset.actionHeightObserved = "1";
        if (prospecting_action_height_observer)
            prospecting_action_height_observer.observe(file_browser);
        prospecting_sync_action_height(file_browser);
    });
}

function prospecting_update_hidden_action_indicator(items)
{
    let actions = items.closest(".actions");

    if (!actions)
        return;

    actions.classList.toggle(
        "has_hidden_actions",
        items.scrollWidth > items.clientWidth + 1
    );
}

function prospecting_align_collapsed_actions(root)
{
    if (!root)
        root = document;

    root.querySelectorAll(".action_items").forEach(function(items) {
        let action_div = items.closest(".action_div");
        let actions = items.closest(".actions");

        if (!action_div || !actions)
            return;
        if (action_div.classList.contains("open"))
        {
            actions.classList.remove("has_hidden_actions");
            return;
        }
        items.scrollLeft = items.scrollWidth;
        prospecting_update_hidden_action_indicator(items);
    });
}

function prospecting_after_action_update(result, msg, content, prospect_id)
{
    setTimeout(function() {
        let root = document.getElementById("actionbar" + prospect_id);
        prospecting_align_collapsed_actions(root);
        prospecting_status_filter_apply();
    }, 0);
}

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
        Math.max(150, action_div.scrollHeight * 2) + "px"
    );
    file_browser.classList.add("open");
    window.requestAnimationFrame(function() {
        prospecting_sync_action_height(file_browser);
    });
}

function close_file_browser_for_action_div(action_div)
{
    let file_browser = get_file_browser_for_action_div(action_div);

    if (!file_browser)
        return;

    file_browser.classList.remove("open");
    file_browser.style.removeProperty("--prospect-file-browser-open-height");
    prospecting_sync_action_height(file_browser);
}

function prospecting_position_action_menu(action_div)
{
    let button;
    let menu;
    let button_rect;
    let menu_rect;
    let margin = 8;
    let gap = 3;
    let left;
    let top;

    if (!action_div)
        return;
    button = action_div.querySelector(".action_edit > button");
    menu = action_div.querySelector(".action_menu");
    if (!button || !menu || menu.classList.contains("hidden"))
        return;

    button_rect = button.getBoundingClientRect();
    menu.classList.add("prospecting_floating_action_menu");
    menu.style.width = Math.max(135, Math.round(button_rect.width)) + "px";
    menu.style.maxHeight = Math.max(80, window.innerHeight - margin * 2) + "px";

    // Le menu est maintenant en position fixe : on peut le mesurer sans que
    // sa hauteur agrandisse la ligne de la table.
    menu_rect = menu.getBoundingClientRect();
    left = button_rect.right - menu_rect.width;
    left = Math.max(margin, Math.min(left, window.innerWidth - menu_rect.width - margin));

    top = button_rect.bottom + gap;
    if (top + menu_rect.height > window.innerHeight - margin)
        top = button_rect.top - menu_rect.height - gap;
    top = Math.max(margin, Math.min(top, window.innerHeight - menu_rect.height - margin));

    menu.style.left = Math.round(left) + "px";
    menu.style.top = Math.round(top) + "px";
}

function prospecting_reset_action_menu_position(menu)
{
    if (!menu)
        return;
    menu.classList.remove("prospecting_floating_action_menu");
    menu.style.removeProperty("left");
    menu.style.removeProperty("top");
    menu.style.removeProperty("width");
    menu.style.removeProperty("max-height");
}

function open_action_div(div)
{
    let menu = div.querySelector(".action_menu");

    if (menu)
        menu.classList.remove("hidden");

    div.classList.add("open");
    div.style.maxHeight = div.scrollHeight + "px";
    expand_file_browser_for_action_div(div);
    if (menu)
        window.requestAnimationFrame(function() {
            prospecting_position_action_menu(div);
            div.style.maxHeight = div.scrollHeight + "px";
        });
}

function close_action_div(div)
{
    let menu = div.querySelector(".action_menu");

    if (menu)
    {
        menu.classList.add("hidden");
        prospecting_reset_action_menu_position(menu);
    }

    div.classList.remove("open");
    div.style.maxHeight = "20px";
    close_file_browser_for_action_div(div);
    setTimeout(function() { prospecting_align_collapsed_actions(div); }, 0);
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

window.addEventListener("resize", function() {
    if (currently_open_action_div)
        prospecting_position_action_menu(currently_open_action_div);
});

document.addEventListener("scroll", function() {
    if (currently_open_action_div)
        prospecting_position_action_menu(currently_open_action_div);
}, true);

document.addEventListener('click', async function (event) {

    const target = event.target.closest('[data-prospect-copy-value]');

    if (!target)
        return;

    const text = target.getAttribute('data-prospect-copy-value') || '';

    if (text === '')
        return;

    event.preventDefault();
    event.stopPropagation();

    try {
        await navigator.clipboard.writeText(text);
        target.classList.add('copied');
        setTimeout(() => target.classList.remove('copied'), 300);
    } catch (e) {

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

function open_generated_document(result, msg, content, parameter)
{
    if (content)
        window.open(content);
}

function open_generated_contract(result, msg, content, parameter)
{
    open_generated_document(result, msg, content, parameter);
}


function prospecting_status_filter_storage_key(status)
{
    return "prospecting_show_" + status;
}

function prospecting_status_filter_read(status)
{
    let stored = localStorage.getItem(prospecting_status_filter_storage_key(status));

    // Aucune préférence enregistrée : on conserve le comportement historique
    // et on affiche toutes les lignes.
    return (stored !== "0");
}

function prospecting_status_filter_init()
{
    let completed = document.getElementById("prospecting_show_completed");
    let lost = document.getElementById("prospecting_show_lost");

    if (completed)
        completed.checked = prospecting_status_filter_read("completed");
    if (lost)
        lost.checked = prospecting_status_filter_read("lost");
    prospecting_status_filter_apply();
}

function prospecting_status_filter_change()
{
    let completed = document.getElementById("prospecting_show_completed");
    let lost = document.getElementById("prospecting_show_lost");

    if (completed)
        localStorage.setItem(
            prospecting_status_filter_storage_key("completed"),
            completed.checked ? "1" : "0"
        );
    if (lost)
        localStorage.setItem(
            prospecting_status_filter_storage_key("lost"),
            lost.checked ? "1" : "0"
        );
    prospecting_status_filter_apply();
}

function prospecting_status_filter_apply(root)
{
    let completed = document.getElementById("prospecting_show_completed");
    let lost = document.getElementById("prospecting_show_lost");
    let show_completed = completed ? completed.checked : true;
    let show_lost = lost ? lost.checked : true;

    if (!root)
        root = document.getElementById("prospecting_campaign_table");
    if (!root)
        return;

    root.querySelectorAll(".dynamic_table tr").forEach(function(row) {
        let is_completed = row.querySelector(".prospect_status_completed") !== null;
        let is_lost = row.querySelector(".prospect_status_lost") !== null;
        let hidden = (is_completed && !show_completed) || (is_lost && !show_lost);

        row.classList.toggle("prospecting_status_hidden", hidden);
    });
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

            if (!success)
                return ;
	    setTimeout(function() {
		
		let table = document.getElementById("prospecting_campaign_table");
		
		prospecting_campaign_execute_scripts(table);
		
		if (typeof init_bigselects == "function")
                    init_bigselects(table);
                prospecting_align_collapsed_actions(table);
                prospecting_observe_action_heights(table);
                prospecting_status_filter_apply(table);
            }, 0);
	});
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

function open_registration_form(result, msg, content, parameter)
{
    if (content)
        window.open(content);
}

document.addEventListener("DOMContentLoaded", function() {
    prospecting_align_collapsed_actions(document);
    prospecting_observe_action_heights(document);
});

window.addEventListener("resize", function() {
    prospecting_align_collapsed_actions(document);
    prospecting_observe_action_heights(document);
});

function prospecting_campaign_tabs_reveal_selected()
{
    let tablist = document.querySelector("#campaign_list .campaign_tabs .tablist");
    let selected;
    let item;
    let left;
    let right;

    if (!tablist)
        return;

    selected = tablist.querySelector("[data-tabpanel-button].selected");
    if (!selected)
        return;

    item = selected.parentElement;
    if (!item)
        item = selected;

    left = item.offsetLeft;
    right = left + item.offsetWidth;

    if (left < tablist.scrollLeft)
        tablist.scrollLeft = left;
    else if (right > tablist.scrollLeft + tablist.clientWidth)
        tablist.scrollLeft = right - tablist.clientWidth;
}

function prospecting_campaign_tabs_sync_storage()
{
    let selected = document.querySelector("#campaign_list .campaign_tabs [data-tabpanel-button].selected");

    if (!selected)
        return;

    localStorage.setItem("prospecting-campaigns", selected.getAttribute("data-tabpanel-tab"));
    prospecting_campaign_tabs_reveal_selected();
}

function prospecting_campaign_tabs_after_update()
{
    // silent_submit appelle after_success juste avant de remplacer tofill.
    // On attend donc le tour de boucle suivant pour travailler sur le nouveau
    // tabpanel renvoyé par /api/campaign.
    setTimeout(prospecting_campaign_tabs_sync_storage, 0);
}

document.addEventListener("DOMContentLoaded", function() {
    setTimeout(prospecting_campaign_tabs_reveal_selected, 0);
});

function prospecting_campaign_registration_close(widget)
{
    if (widget)
        widget.classList.remove("editing");
}

function prospecting_campaign_registration_sync(form)
{
    let select = form.querySelector('select[name="campaign_id"]');
    let date = form.querySelector('input[name="registration_date"]');
    let option;
    let start;
    let end;

    if (!select || !date)
        return;
    option = select.options[select.selectedIndex];
    start = option ? option.getAttribute("data-start") : "";
    end = option ? option.getAttribute("data-end") : "";

    date.min = start || "";
    date.max = end || "";
    if (!start || !end)
        return;
    if (!date.value || date.value < start)
        date.value = start;
    else if (date.value > end)
        date.value = end;
}

function prospecting_campaign_registration_submit(form)
{
    prospecting_campaign_registration_sync(form);
    return (silent_submitf(form, {
        after_success: function() {
            // La campagne est déduite de registration_date à plusieurs endroits
            // (table, statistiques, niveau courant...). Un rafraîchissement
            // immédiat garantit que tous ces affichages restent cohérents.
            refresh();
        }
    }));
}

document.addEventListener("click", function(event) {
    let toggle = event.target.closest(".prospect_registration_toggle");
    let cancel = event.target.closest(".prospect_registration_cancel");
    let widget;

    if (toggle)
    {
        event.preventDefault();
        widget = toggle.closest(".prospect_registration_widget");
        document.querySelectorAll(".prospect_registration_widget.editing").forEach(function(open_widget) {
            if (open_widget !== widget)
                prospecting_campaign_registration_close(open_widget);
        });
        if (widget)
        {
            widget.classList.add("editing");
            prospecting_campaign_registration_sync(widget.querySelector("form"));
        }
        return;
    }

    if (cancel)
    {
        event.preventDefault();
        prospecting_campaign_registration_close(cancel.closest(".prospect_registration_widget"));
    }
});

document.addEventListener("change", function(event) {
    let select = event.target.closest('.prospect_registration_form select[name="campaign_id"]');

    if (select)
        prospecting_campaign_registration_sync(select.form);
});

document.addEventListener("keydown", function(event) {
    if (event.key != "Escape")
        return;
    document.querySelectorAll(".prospect_registration_widget.editing").forEach(function(widget) {
        prospecting_campaign_registration_close(widget);
    });
});


function prospecting_inline_editor_close(widget)
{
    if (widget)
        widget.classList.remove("editing");
}

function prospecting_inline_editor_after_success()
{
    let campaign_select = document.getElementById("prospecting_campaign_select");

    if (campaign_select && campaign_select.value)
    {
        prospecting_campaign_load(campaign_select.value);
        return;
    }
    refresh();
}

function prospecting_inline_editor_submit(form)
{
    return (silent_submitf(form, {
        after_success: prospecting_inline_editor_after_success
    }));
}

document.addEventListener("click", function(event) {
    let toggle = event.target.closest(".prospect_inline_toggle");
    let cancel = event.target.closest(".prospect_inline_cancel");
    let widget;
    let select;

    if (toggle)
    {
        event.preventDefault();
        event.stopPropagation();
        widget = toggle.closest(".prospect_inline_editor");
        document.querySelectorAll(".prospect_inline_editor.editing").forEach(function(open_widget) {
            if (open_widget !== widget)
                prospecting_inline_editor_close(open_widget);
        });
        if (widget)
        {
            widget.classList.add("editing");
            select = widget.querySelector("select");
            if (select)
                select.focus();
        }
        return;
    }

    if (cancel)
    {
        event.preventDefault();
        event.stopPropagation();
        prospecting_inline_editor_close(cancel.closest(".prospect_inline_editor"));
        return;
    }

    if (!event.target.closest(".prospect_inline_editor"))
        document.querySelectorAll(".prospect_inline_editor.editing").forEach(prospecting_inline_editor_close);
});

document.addEventListener("keydown", function(event) {
    if (event.key != "Escape")
        return;
    document.querySelectorAll(".prospect_inline_editor.editing").forEach(prospecting_inline_editor_close);
});
