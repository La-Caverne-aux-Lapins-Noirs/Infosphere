let currently_open_billing_action_div = null;
let currently_selected_billing_delete_event = null;
let currently_open_pending_invoice_menu = null;

function open_billing_action_div(div)
{
    let menu = div.querySelector(".billing_action_menu");

    if (menu)
        menu.classList.remove("hidden");

    div.classList.add("open");
    div.style.maxHeight = div.scrollHeight + "px";
}

function close_billing_action_div(div)
{
    let menu = div.querySelector(".billing_action_menu");

    if (menu)
        menu.classList.add("hidden");

    div.classList.remove("open");
    div.style.maxHeight = "44px";
}

function toggle_billing_action_menu(btn)
{
    let action_div = btn.closest(".billing_action_div");

    if (!action_div)
        return;

    billing_close_pending_invoice_menu();
    billing_clear_selected_delete_event();

    if (currently_open_billing_action_div && currently_open_billing_action_div !== action_div)
    {
        close_billing_action_div(currently_open_billing_action_div);
        currently_open_billing_action_div = null;
    }

    if (action_div === currently_open_billing_action_div)
    {
        close_billing_action_div(action_div);
        currently_open_billing_action_div = null;
        return;
    }

    open_billing_action_div(action_div);
    currently_open_billing_action_div = action_div;
}

function billing_clear_selected_delete_event()
{
    if (currently_selected_billing_delete_event)
        currently_selected_billing_delete_event.classList.remove("billing_event_delete_selected");
    currently_selected_billing_delete_event = null;
}

function billing_close_pending_invoice_menu()
{
    if (currently_open_pending_invoice_menu)
    {
        let action_div = currently_open_pending_invoice_menu.closest(".billing_action_div");
        currently_open_pending_invoice_menu.classList.add("hidden");
        if (action_div)
            action_div.classList.remove("billing_pending_open");
    }
    currently_open_pending_invoice_menu = null;
}

document.addEventListener("click", function(e)
{
    if (currently_open_pending_invoice_menu && !currently_open_pending_invoice_menu.closest("form").contains(e.target))
        billing_close_pending_invoice_menu();

    if (currently_selected_billing_delete_event && !currently_selected_billing_delete_event.contains(e.target))
        billing_clear_selected_delete_event();

    if (!currently_open_billing_action_div)
        return;

    if (currently_open_billing_action_div.contains(e.target))
        return;

    close_billing_action_div(currently_open_billing_action_div);
    currently_open_billing_action_div = null;
});

document.addEventListener("contextmenu", function(e)
{
    if (!currently_open_pending_invoice_menu)
        return;

    e.preventDefault();
    billing_close_pending_invoice_menu();
});

document.addEventListener("keydown", function(e)
{
    if (e.key !== "Escape")
        return;

    billing_close_pending_invoice_menu();
    billing_clear_selected_delete_event();
    if (currently_open_billing_action_div)
    {
        close_billing_action_div(currently_open_billing_action_div);
        currently_open_billing_action_div = null;
    }
});

function billing_open_pending_invoice_menu(button, event)
{
    if (event)
        event.stopPropagation();
    billing_clear_selected_delete_event();

    let form = button.closest("form");
    if (!form)
        return (false);
    let menu = form.querySelector(".billing_pending_invoice_menu");
    if (!menu)
        return (false);

    if (currently_open_pending_invoice_menu && currently_open_pending_invoice_menu !== menu)
        billing_close_pending_invoice_menu();

    if (currently_open_pending_invoice_menu === menu)
        billing_close_pending_invoice_menu();
    else
    {
        let action_div = menu.closest(".billing_action_div");
        menu.classList.remove("hidden");
        if (action_div)
            action_div.classList.add("billing_pending_open");
        currently_open_pending_invoice_menu = menu;
    }
    return (false);
}

function billing_prepare_invoice_send(button)
{
    let form = button.closest("form");
    if (!form)
        return (false);

    let text = button.getAttribute("data-confirm") || "";
    if (!window.confirm(text))
        return (false);
    form.setAttribute("method", "put");
    if (form.getAttribute("data-send-action"))
        form.setAttribute("action", form.getAttribute("data-send-action"));
    billing_close_pending_invoice_menu();
    return (silent_submitf(form, {after_success: function(){billing_refresh_student_from_source(form);}}));
}

function billing_pending_invoice_send(button, event)
{
    if (event)
        event.stopPropagation();
    let form = button.closest("form");
    if (!form)
        return (false);
    let invoice = form.querySelector(".billing_invoice_pending");
    if (!invoice)
        return (false);
    return (billing_prepare_invoice_send(invoice));
}

function billing_pending_invoice_delete(button, event)
{
    if (event)
        event.stopPropagation();
    let form = button.closest("form");
    if (!form)
        return (false);
    let msg = form.getAttribute("data-delete-confirm") || "Supprimer ?";

    billing_close_pending_invoice_menu();
    if (!window.confirm(msg))
        return (false);
    form.setAttribute("method", "delete");
    if (form.getAttribute("data-delete-action"))
        form.setAttribute("action", form.getAttribute("data-delete-action"));
    return (silent_submitf(form, {after_success: function(){billing_refresh_student_from_source(form);}}));
}

function billing_select_or_confirm_delete_event(button, event)
{
    if (event)
        event.stopPropagation();
    billing_close_pending_invoice_menu();

    if (currently_selected_billing_delete_event !== button)
    {
        billing_clear_selected_delete_event();
        currently_selected_billing_delete_event = button;
        button.classList.add("billing_event_delete_selected");
        billing_show_tooltip(button, event ? event.clientX : 0, event ? event.clientY : 0, button.getAttribute("data-delete-hint"));
        return (false);
    }

    let form = button.closest("form");
    if (!form)
        return (false);
    let msg = button.getAttribute("data-delete-confirm") || "Supprimer ?";

    billing_clear_selected_delete_event();
    if (!window.confirm(msg))
        return (false);
    return (silent_submitf(form, {after_success: function(){billing_refresh_student_from_source(form);}}));
}

function billing_student_id_from_source(source)
{
    if (!source || !source.closest)
        return (null);

    let action_div = source.closest(".billing_action_div");

    if (!action_div)
        return (null);
    return (action_div.getAttribute("data-user-id"));
}

function billing_refresh_student_by_id(id_user)
{
    if (!id_user)
        return;

    fetch(document.location.href, {credentials: "same-origin", cache: "no-store"})
        .then(function(response)
        {
            if (!response.ok)
                throw new Error("Cannot refresh billing row");
            return (response.text());
        })
        .then(function(html)
        {
            let parser = new DOMParser();
            let doc = parser.parseFromString(html, "text/html");
            let old_row = document.getElementById("billing_table" + id_user);
            let new_row = doc.getElementById("billing_table" + id_user);

            if (!old_row || !new_row)
                throw new Error("Cannot find billing row " + id_user);

            billing_hide_tooltip();
            billing_close_pending_invoice_menu();
            billing_clear_selected_delete_event();
            if (currently_open_billing_action_div)
            {
                close_billing_action_div(currently_open_billing_action_div);
                currently_open_billing_action_div = null;
            }
            old_row.replaceWith(document.importNode(new_row, true));
        })
        .catch(function(error)
        {
            console.error(error);
        });
}

function billing_refresh_student_from_source(source)
{
    billing_refresh_student_by_id(billing_student_id_from_source(source));
}

function billing_submit_and_refresh(source)
{
    return (silent_submitf(source, {after_success: function(){billing_refresh_student_from_source(source);}}));
}

function billing_toggle_history(button)
{
    let history = button.nextElementSibling;

    if (!history || !history.classList)
        return (false);

    let is_open = history.classList.contains("visible");
    if (is_open)
    {
        history.classList.remove("visible");
        history.classList.add("hidden");
        button.textContent = button.getAttribute("data-closed-label") || button.textContent;
    }
    else
    {
        history.classList.remove("hidden");
        history.classList.add("visible");
        button.textContent = button.getAttribute("data-open-label") || button.textContent;
    }
    return (false);
}

let billing_tooltip_div = null;
let billing_tooltip_target = null;

function billing_show_tooltip(target, x, y, override_content)
{
    let content = override_content || target.getAttribute("data-tooltip");

    if (!content)
        return;
    if (!billing_tooltip_div)
    {
        billing_tooltip_div = document.createElement("div");
        billing_tooltip_div.className = "billing_global_tooltip";
        document.body.appendChild(billing_tooltip_div);
    }
    billing_tooltip_target = target;
    billing_tooltip_div.textContent = content;
    billing_tooltip_div.classList.add("visible");
    billing_move_tooltip(x, y);
}

function billing_move_tooltip(x, y)
{
    if (!billing_tooltip_div || !billing_tooltip_div.classList.contains("visible"))
        return;

    let left = x + 14;
    let top = y + 14;
    billing_tooltip_div.style.left = left + "px";
    billing_tooltip_div.style.top = top + "px";

    let rect = billing_tooltip_div.getBoundingClientRect();
    if (rect.right > window.innerWidth - 10)
        billing_tooltip_div.style.left = Math.max(10, window.innerWidth - rect.width - 10) + "px";
    if (rect.bottom > window.innerHeight - 10)
        billing_tooltip_div.style.top = Math.max(10, y - rect.height - 14) + "px";
}

function billing_hide_tooltip()
{
    if (billing_tooltip_div)
        billing_tooltip_div.classList.remove("visible");
    billing_tooltip_target = null;
}

document.addEventListener("mouseover", function(e)
{
    let target = e.target.closest("#billing_panel [data-tooltip]");

    if (!target)
        return;
    billing_show_tooltip(target, e.clientX, e.clientY);
});

document.addEventListener("mousemove", function(e)
{
    if (billing_tooltip_target)
        billing_move_tooltip(e.clientX, e.clientY);
});

document.addEventListener("mouseout", function(e)
{
    if (!billing_tooltip_target)
        return;
    if (billing_tooltip_target.contains(e.relatedTarget))
        return;
    billing_hide_tooltip();
});
