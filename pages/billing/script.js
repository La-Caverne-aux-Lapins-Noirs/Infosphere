let currently_open_billing_action_div = null;
let currently_selected_billing_delete_event = null;
let currently_open_pending_invoice_menu = null;

let billing_application_body = document.getElementById("body");
if (billing_application_body)
    billing_application_body.classList.add("billing_page_active");

function billing_send_payment_schedule(button)
{
    let form = button && button.closest ? button.closest("form") : null;
    if (!form)
        return (false);

    let confirm_text = button.getAttribute("data-confirm") || "";
    if (confirm_text && !window.confirm(confirm_text))
        return (false);

    let data = new FormData(form);
    data.set("mail_payment_schedule", "1");
    button.disabled = true;
    send_ajax(
        "POST",
        form.getAttribute("action"),
        data,
        null, null, null, null,
        function () { button.disabled = false; },
        function () { button.disabled = false; }
    );
    return (false);
}

function billing_submit_remaining_schedule(source)
{
    let form = source && source.tagName === "FORM"
        ? source
        : (source && source.closest ? source.closest("form") : null);
    if (!form)
        return (false);

    if (!form.querySelector("input[data-billing-deduct-entry]:checked"))
    {
        alert(form.getAttribute("data-select-error") || "Select an issued invoice to deduct.");
        return (false);
    }
    return (billing_submit_and_refresh(form));
}

function billing_select_action(select)
{
    let menu = select && select.closest ? select.closest(".billing_action_menu") : null;

    if (!menu)
        return (false);

    let selected = select.value || "";
    menu.querySelectorAll(".billing_action_choice").forEach(function(choice)
    {
        if (selected !== "" && choice.getAttribute("data-billing-action") === selected)
            choice.classList.remove("hidden");
        else
            choice.classList.add("hidden");
    });

    let action_div = menu.closest(".billing_action_div");
    if (action_div && action_div.classList.contains("open"))
        action_div.style.maxHeight = action_div.scrollHeight + "px";
    return (false);
}

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
    billing_hide_tooltip();
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

    let reference_input = form.querySelector('input[name="invoice_reference"]');
    let reference = reference_input ? reference_input.value.trim() : "";
    if (!reference)
    {
        let message = reference_input ? reference_input.getAttribute("data-required-message") : "";
        if (message)
            window.alert(message);
        if (reference_input)
            reference_input.focus();
        return (false);
    }
    reference_input.value = reference;

    let text = button.getAttribute("data-confirm") || "";
    text += "\n" + (button.getAttribute("data-reference-label") || "Référence") + " : " + reference;
    if (!window.confirm(text))
        return (false);
    form.setAttribute("method", "put");
    if (form.getAttribute("data-send-action"))
        form.setAttribute("action", form.getAttribute("data-send-action"));
    billing_close_pending_invoice_menu();
    return (silent_submitf(form, {after_success: function(){billing_refresh_student_from_source(form);}}));
}

function billing_draft_month_short_label(date)
{
    let match = typeof date === "string" ? date.match(/^\d{4}-(\d{2})-\d{2}$/) : null;
    let month = match ? parseInt(match[1], 10) : 0;
    let lang = (document.documentElement.lang || "fr").toLowerCase();
    let labels = lang.startsWith("fr")
        ? ["", "Jan", "Fév", "Mar", "Avr", "Mai", "Jun", "Jul", "Aoû", "Sep", "Oct", "Nov", "Déc"]
        : ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

    return (labels[month] || "---");
}

function billing_apply_saved_draft_invoice(form, state)
{
    if (!form || !state || typeof state !== "object")
        return;

    let invoice = form.querySelector(".billing_invoice_pending");
    let label = form.querySelector('[name="label"]');
    let amount = form.querySelector('[name="amount"]');
    let vat = form.querySelector('[name="vat_rate"]');
    let due = form.querySelector('[name="due_date"]');
    let type = form.querySelector('[name="invoice_type"]');
    let buyer_organization = form.querySelector('[name="id_organization"]');
    let buyer_routing = form.querySelector('[name="buyer_routing_code"]');

    if (label)
        label.value = state.label || "";
    if (amount && typeof state.amount_ht_value !== "undefined")
        amount.value = state.amount_ht_value;
    if (vat && typeof state.vat_rate !== "undefined")
        vat.value = String(state.vat_rate);
    if (due && state.due_date)
        due.value = state.due_date;
    if (type && state.invoice_type)
        type.value = state.invoice_type;
    if (buyer_organization && typeof state.id_organization !== "undefined")
        buyer_organization.value = String(state.id_organization || 0);
    if (buyer_routing && typeof state.buyer_routing_code !== "undefined")
        buyer_routing.value = state.buyer_routing_code || "";

    if (invoice)
    {
        invoice.textContent = billing_draft_month_short_label(state.due_date);
        if (state.tooltip)
            invoice.setAttribute("data-tooltip", state.tooltip);
        if (state.confirm)
            invoice.setAttribute("data-confirm", state.confirm);
    }
    if (state.delete_confirm)
        form.setAttribute("data-delete-confirm", state.delete_confirm);

    let row = document.getElementById("billing_table" + state.id_user);
    if (!row)
        return;

    let to_invoice = row.querySelector(".billing_account_to_invoice");
    if (to_invoice && state.to_invoice_text)
        to_invoice.textContent = state.to_invoice_text;

    let pending = row.querySelector('[data-billing-draft-entry-id="' + state.id + '"]');
    if (!pending)
        return;
    let cell = pending.querySelector('[data-billing-draft-field="due_date"]');
    if (cell)
        cell.textContent = state.due_date_label || "";
    cell = pending.querySelector('[data-billing-draft-field="label"]');
    if (cell)
        cell.textContent = state.label || "";
    cell = pending.querySelector('[data-billing-draft-field="invoice_type"]');
    if (cell)
        cell.textContent = state.invoice_type_label || "";
    cell = pending.querySelector('[data-billing-draft-field="amount"]');
    if (cell)
        cell.textContent = state.amount_display || "";
}

function billing_pending_invoice_save(button, event)
{
    if (event)
        event.stopPropagation();
    let form = button && button.closest ? button.closest("form") : null;
    if (!form)
        return (false);
    let action = form.getAttribute("data-edit-action") || "";
    if (!action)
        return (false);
    if (form.reportValidity && !form.reportValidity())
        return (false);

    button.disabled = true;
    form.setAttribute("method", "put");
    form.setAttribute("action", action);
    return (silent_submitf(form, {
        after_success: function(result, msg, content)
        {
            billing_apply_saved_draft_invoice(form, content);
        },
        after_complete: function()
        {
            button.disabled = false;
        }
    }));
}


function billing_pending_invoice_preview(button, event)
{
    if (event)
        event.stopPropagation();
    let form = button && button.closest ? button.closest("form") : null;
    if (!form)
        return (false);

    let reference_input = form.querySelector('input[name="invoice_reference"]');
    let reference = reference_input ? reference_input.value.trim() : "";
    if (!reference)
    {
        let message = reference_input ? reference_input.getAttribute("data-required-message") : "";
        if (message)
            window.alert(message);
        if (reference_input)
            reference_input.focus();
        return (false);
    }
    reference_input.value = reference;
    if (form.reportValidity && !form.reportValidity())
        return (false);

    let action = form.getAttribute("data-preview-action");
    if (!action)
        return (false);

    // Submit natively in a new tab so the API can answer with an inline PDF.
    // form.submit() intentionally bypasses this form's onsubmit="return false".
    let old_method = form.getAttribute("method");
    let old_action = form.getAttribute("action");
    let old_target = form.getAttribute("target");
    form.setAttribute("method", "post");
    form.setAttribute("action", action);
    form.setAttribute("target", "_blank");
    form.submit();

    if (old_method === null)
        form.removeAttribute("method");
    else
        form.setAttribute("method", old_method);
    if (old_action === null)
        form.removeAttribute("action");
    else
        form.setAttribute("action", old_action);
    if (old_target === null)
        form.removeAttribute("target");
    else
        form.setAttribute("target", old_target);
    return (false);
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

function billing_pending_invoice_mark_sent(button, event)
{
    if (event)
        event.stopPropagation();
    let form = button.closest("form");
    if (!form)
        return (false);
    let invoice = form.querySelector(".billing_invoice_pending");
    if (!invoice)
        return (false);

    let reference_input = form.querySelector('input[name="invoice_reference"]');
    let reference = reference_input ? reference_input.value.trim() : "";
    if (!reference)
    {
        let message = reference_input ? reference_input.getAttribute("data-required-message") : "";
        if (message)
            window.alert(message);
        if (reference_input)
            reference_input.focus();
        return (false);
    }
    reference_input.value = reference;

    let text = invoice.getAttribute("data-mark-sent-confirm") || "";
    text += "\n" + (invoice.getAttribute("data-reference-label") || "Référence") + " : " + reference;
    if (!window.confirm(text))
        return (false);

    form.setAttribute("method", "put");
    if (form.getAttribute("data-mark-sent-action"))
        form.setAttribute("action", form.getAttribute("data-mark-sent-action"));
    billing_close_pending_invoice_menu();
    return (silent_submitf(form, {after_success: function(){billing_refresh_student_from_source(form);}}));
}

function billing_update_payment_reference(button, event)
{
    if (event)
        event.stopPropagation();
    let form = button && button.closest ? button.closest("form") : null;
    if (!form)
        return (false);

    let reference = form.querySelector("[name='transfer_reference']");
    if (reference)
        reference.value = reference.value.trim();

    form.setAttribute("method", "put");
    billing_close_pending_invoice_menu();
    return (silent_submitf(form, {
        after_success: function(){billing_refresh_student_from_source(form);}
    }));
}

function billing_prepare_credit_note_from_invoice(button, event)
{
    if (event)
        event.stopPropagation();
    let form = button.closest("form");
    if (!form)
        return (false);
    if (form.reportValidity && !form.reportValidity())
        return (false);

    form.setAttribute("method", "post");
    if (form.getAttribute("data-credit-action"))
        form.setAttribute("action", form.getAttribute("data-credit-action"));
    billing_close_pending_invoice_menu();
    return (silent_submitf(form, {after_success: function(){billing_refresh_student_from_source(form);}}));
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

function billing_refresh_student_by_id(id_user, after_refresh)
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
            if (after_refresh)
                after_refresh();
        })
        .catch(function(error)
        {
            console.error(error);
        });
}

function billing_open_account_dialog(id_user)
{
    let dialog = document.getElementById("billing_account_dialog_" + id_user);

    if (!dialog)
        return (false);
    if (typeof dialog.showModal === "function")
        dialog.showModal();
    else
        dialog.setAttribute("open", "open");
    return (false);
}

function billing_close_account_dialog(source)
{
    let dialog = source && source.closest ? source.closest("dialog") : null;

    if (!dialog)
        return (false);
    if (typeof dialog.close === "function")
        dialog.close();
    else
        dialog.removeAttribute("open");
    return (false);
}

function billing_submit_account_payment(source)
{
    let dialog = source && source.closest ? source.closest("[data-billing-user-id]") : null;
    let id_user = dialog ? dialog.getAttribute("data-billing-user-id") : null;
    let form = source && source.tagName === "FORM" ? source : (source && source.closest ? source.closest("form") : null);

    if (!form || !id_user)
        return (false);

    return (silent_submitf(form, {
        after_success: function()
        {
            billing_refresh_student_by_id(id_user, function()
            {
                billing_open_account_dialog(id_user);
            });
        }
    }));
}

function billing_toggle_external_invoice_payment(source)
{
    let form = source && source.closest ? source.closest("form") : null;
    let fields = form ? form.querySelector("[data-external-invoice-payment-fields]") : null;

    if (!fields)
        return (false);
    if (source.checked)
        fields.classList.remove("hidden");
    else
        fields.classList.add("hidden");
    return (false);
}

function billing_submit_external_invoice(source)
{
    let form = source && source.tagName === "FORM" ? source : (source && source.closest ? source.closest("form") : null);
    let id_field = form ? form.querySelector("[name='id_user']") : null;
    let id_user = id_field ? id_field.value : billing_student_id_from_source(source);

    if (!form || !id_user)
        return (false);
    if (form.reportValidity && !form.reportValidity())
        return (false);

    return (silent_submitf(form, {
        after_success: function()
        {
            billing_refresh_student_by_id(id_user);
        }
    }));
}

function billing_hidden_users_checkbox()
{
    return (document.querySelector(".billing_show_hidden_users input[type='checkbox']"));
}

function billing_hidden_users_url(include_hidden)
{
    let url = new URL(window.location.href);

    if (include_hidden)
        url.searchParams.set("show_hidden", "1");
    else
        url.searchParams.delete("show_hidden");
    return (url);
}

function billing_reload_table(include_hidden)
{
    let old_table = document.getElementById("billing_table");
    let panel = document.getElementById("billing_panel");
    let url = billing_hidden_users_url(include_hidden);

    if (!old_table)
        return (Promise.reject(new Error("Cannot find billing table")));
    if (panel)
        panel.classList.add("billing_table_loading");

    return (fetch(url.toString(), {credentials: "same-origin", cache: "no-store"})
        .then(function(response)
        {
            if (!response.ok)
                throw new Error("Cannot refresh billing table");
            return (response.text());
        })
        .then(function(html)
        {
            let parser = new DOMParser();
            let doc = parser.parseFromString(html, "text/html");
            let new_table = doc.getElementById("billing_table");

            if (!new_table)
                throw new Error("Cannot find refreshed billing table");

            billing_hide_tooltip();
            billing_close_pending_invoice_menu();
            billing_clear_selected_delete_event();
            if (currently_open_billing_action_div)
            {
                close_billing_action_div(currently_open_billing_action_div);
                currently_open_billing_action_div = null;
            }

            old_table.replaceWith(document.importNode(new_table, true));
            window.history.replaceState(null, "", url.toString());
        })
        .finally(function()
        {
            if (panel)
                panel.classList.remove("billing_table_loading");
        }));
}

function billing_toggle_hidden_users(source)
{
    let requested_state = !!source.checked;

    source.disabled = true;
    billing_reload_table(requested_state)
        .catch(function(error)
        {
            console.error(error);
            source.checked = !requested_state;
        })
        .finally(function()
        {
            source.disabled = false;
        });
    return (false);
}

function billing_apply_user_visibility(source, hidden, include_hidden)
{
    let id_user = parseInt(source.dataset.userId || "0", 10);
    let row = document.getElementById("billing_table" + id_user);
    let student_cell = row ? row.querySelector("td.billing_student_name") : null;
    let title = hidden ? (source.dataset.showTitle || source.title) : (source.dataset.hideTitle || source.title);

    source.dataset.hidden = hidden ? "1" : "0";
    source.classList.toggle("billing_visibility_hidden", !!hidden);
    source.classList.toggle("billing_visibility_visible", !hidden);
    source.title = title;
    source.setAttribute("aria-label", title);

    if (student_cell)
    {
        student_cell.classList.toggle("billing_hidden_user", !!hidden);
        let badge = student_cell.querySelector(".billing_hidden_badge");
        if (hidden && !badge)
        {
            badge = document.createElement("span");
            badge.className = "billing_hidden_badge";
            badge.textContent = source.dataset.hiddenLabel || "Masqué";
            student_cell.appendChild(badge);
        }
        else if (!hidden && badge)
            badge.remove();
    }

    // Quand les utilisateurs masqués ne sont pas affichés, on retire uniquement
    // la ligne concernée. Aucun rechargement du tableau ni de la page n'est utile.
    if (hidden && !include_hidden && row)
        row.remove();
}

function billing_set_user_visibility(source)
{
    if (!source || !source.dataset)
        return (false);

    let id_user = parseInt(source.dataset.userId || "0", 10);
    let currently_hidden = source.dataset.hidden === "1";
    let hidden = currently_hidden ? 0 : 1;
    let checkbox = billing_hidden_users_checkbox();
    let include_hidden = checkbox ? !!checkbox.checked : false;

    if (id_user <= 0 || source.disabled)
        return (false);

    source.disabled = true;
    send_ajax(
        "PUT",
        "/api/billing/" + id_user + "/visibility",
        JSON.stringify({hidden: hidden}),
        null,
        null,
        null,
        null,
        function(success)
        {
            if (!success)
                return;
            billing_apply_user_visibility(source, !!hidden, include_hidden);
        },
        function()
        {
            source.disabled = false;
        }
    );
    return (false);
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
    if (currently_open_pending_invoice_menu)
    {
        let form = currently_open_pending_invoice_menu.closest("form");
        if (form && form.contains(target))
            return;
    }
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

document.addEventListener("click", function(e)
{
    if (e.target.closest("#billing_panel [data-tooltip]"))
        billing_hide_tooltip();
});

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

function billing_payment_reminder_set_field(form, name, value)
{
    let input = form.querySelector('[data-payment-reminder-field="' + name + '"]');
    if (input)
        input.value = 'PaymentReminder.' + name + '=' + value;
}

function billing_payment_payer_changed(select)
{
    let form = select && select.closest ? select.closest('form') : null;
    if (!form)
        return;
    let user = form.querySelector('[data-payment-user]');
    let organization = form.querySelector('[data-payment-organization]');
    let hint = form.querySelector('[data-billing-payment-payer-hint]');
    if (!user || !organization)
        return;

    let value = select.value || 'user';
    if (value.indexOf('organization:') === 0)
    {
        user.value = '0';
        organization.value = value.substring('organization:'.length);
        if (hint)
            hint.classList.add('visible');
    }
    else
    {
        let timeline = form.closest('[data-user-id]');
        user.value = timeline ? (timeline.getAttribute('data-user-id') || '') : '';
        organization.value = '0';
        if (hint)
            hint.classList.remove('visible');
    }
}

function billing_payment_reminder_euros(cents)
{
    return (new Intl.NumberFormat('fr-FR', {style: 'currency', currency: 'EUR'}).format(cents / 100));
}

function billing_prepare_payment_reminder(form)
{
    let selected = Array.from(form.querySelectorAll('[data-payment-reminder-invoice]:checked'));
    if (!selected.length)
    {
        window.alert('Sélectionnez au moins une facture en retard.');
        return (false);
    }

    let references = [];
    let lines = [];
    let total = 0;
    let oldestTs = null;
    let oldestLabel = '';
    selected.forEach(function(input)
    {
        let reference = input.getAttribute('data-reference') || '';
        let label = input.getAttribute('data-label') || '';
        let outstanding = parseInt(input.getAttribute('data-outstanding') || '0', 10);
        let dueTs = parseInt(input.getAttribute('data-due-ts') || '0', 10);
        let dueLabel = input.getAttribute('data-due-label') || '';
        references.push(reference);
        lines.push(reference + ' — ' + label + ' — ' + billing_payment_reminder_euros(outstanding) + ' restant dû — échéance ' + dueLabel);
        total += outstanding;
        if (oldestTs === null || dueTs < oldestTs)
        {
            oldestTs = dueTs;
            oldestLabel = dueLabel;
        }
    });

    let bindings = {
        Student: form.querySelector('[data-payment-reminder-student]').value,
        School: form.querySelector('[data-payment-reminder-school]').value
    };
    let organization = form.querySelector('[data-payment-reminder-organization]');
    if (organization && organization.value)
        bindings.Debtor = organization.value;
    form.querySelector('[name="context_bindings"]').value = JSON.stringify(bindings);
    billing_payment_reminder_set_field(form, 'InvoiceReferences', references.join(', '));
    billing_payment_reminder_set_field(form, 'InvoiceLines', lines.join(' ; '));
    billing_payment_reminder_set_field(form, 'TotalOutstanding', billing_payment_reminder_euros(total));
    billing_payment_reminder_set_field(form, 'OldestDueDate', oldestLabel);
    billing_payment_reminder_set_field(form, 'ResponseDeadline', form.querySelector('[data-payment-reminder-deadline]').value.trim());
    billing_payment_reminder_set_field(form, 'Observations', form.querySelector('[data-payment-reminder-observations]').value.trim());
    return (true);
}

function billing_submit_organization_entry(form)
{
    return (silent_submitf(form, {
        after_success: function() { document.location.reload(); }
    }));
}

function billing_delete_organization_entry(form)
{
    if (!form)
        return (false);
    let text = form.getAttribute("data-confirm") || "";
    if (text && !window.confirm(text))
        return (false);
    return (silent_submitf(form, {
        after_success: function() { document.location.reload(); }
    }));
}

function billing_open_organization_entry_dialog(button)
{
    if (!button)
        return (false);
    let dialog = document.getElementById(button.getAttribute("data-dialog-id") || "");
    if (!dialog)
        return (false);
    let form = dialog.querySelector("form");
    if (!form)
        return (false);

    let id = button.getAttribute("data-entry-id") || "";
    form.setAttribute("action", "/api/billing/" + id + "/organization_entry_update");
    [
        ["id_school", "school-id"],
        ["id_organization", "organization-id"],
        ["movement_type", "movement-type"],
        ["movement_date", "movement-date"],
        ["label", "label"],
        ["amount", "amount"],
        ["reference", "reference"],
        ["comment", "comment"]
    ].forEach(function(mapping)
    {
        let field = form.querySelector('[name="' + mapping[0] + '"]');
        if (field)
            field.value = button.getAttribute("data-" + mapping[1]) || "";
    });

    let upload = form.querySelector('[name="document"]');
    if (upload)
        upload.value = "";
    let remove = form.querySelector('[name="delete_document"]');
    if (remove)
        remove.checked = false;

    let name = button.getAttribute("data-document-name") || "";
    let current = form.querySelector("[data-current-document]");
    if (current)
        current.textContent = name ? current.getAttribute("data-prefix") + name : current.getAttribute("data-none");
    let delete_row = form.querySelector("[data-delete-document-row]");
    if (delete_row)
        delete_row.classList.toggle("hidden", !name);

    if (typeof dialog.showModal === "function")
        dialog.showModal();
    else
        dialog.setAttribute("open", "open");
    return (false);
}

function billing_update_organization_entry(form)
{
    if (!form)
        return (false);
    if (form.reportValidity && !form.reportValidity())
        return (false);
    return (silent_submitf(form, {
        after_success: function() { document.location.reload(); }
    }));
}

function billing_filter_organization_entries(container)
{
    if (!container)
        return (false);
    let table = document.getElementById(container.getAttribute("data-table-id") || "");
    if (!table)
        return (false);
    let search = (container.querySelector("[data-filter-search]")?.value || "").trim().toLowerCase();
    let organization = container.querySelector("[data-filter-organization]")?.value || "";
    let school = container.querySelector("[data-filter-school]")?.value || "";
    let start = container.querySelector("[data-filter-start]")?.value || "";
    let end = container.querySelector("[data-filter-end]")?.value || "";
    let count = 0;
    let total = 0;

    table.querySelectorAll("tbody tr[data-billing-organization-entry]").forEach(function(row)
    {
        let date = row.getAttribute("data-movement-date") || "";
        let visible = true;
        if (search && !row.textContent.toLowerCase().includes(search))
            visible = false;
        if (organization && row.getAttribute("data-organization-id") !== organization)
            visible = false;
        if (school && row.getAttribute("data-school-id") !== school)
            visible = false;
        if (start && date && date < start)
            visible = false;
        if (end && date && date > end)
            visible = false;
        row.style.display = visible ? "" : "none";
        if (visible)
        {
            ++count;
            total += parseInt(row.getAttribute("data-amount") || "0", 10) || 0;
        }
    });

    let summary = document.getElementById(container.getAttribute("data-summary-id") || "");
    if (summary)
    {
        let countNode = summary.querySelector("[data-visible-count]");
        if (countNode)
            countNode.textContent = count + " " + (countNode.getAttribute("data-count-label") || "");
        let totalNode = summary.querySelector("[data-visible-total]");
        if (totalNode)
        {
            let label = totalNode.getAttribute("data-total-label") || "Total";
            totalNode.textContent = label + " : " + billing_payment_reminder_euros(total);
        }
    }
    return (false);
}

function billing_reset_organization_filters(container)
{
    if (!container)
        return (false);
    container.querySelectorAll("[data-filter-search], [data-filter-start], [data-filter-end]").forEach(function(input) { input.value = ""; });
    container.querySelectorAll("[data-filter-organization], [data-filter-school]").forEach(function(select) { select.value = ""; });
    return (billing_filter_organization_entries(container));
}

function billing_open_dialog_by_id(id)
{
    let dialog = document.getElementById(id || "");
    if (!dialog)
        return (false);
    if (typeof dialog.showModal === "function")
        dialog.showModal();
    else
        dialog.setAttribute("open", "open");
    return (false);
}

function billing_open_organization_statement_from_picker(button, movementType)
{
    if (!button)
        return (false);
    let container = button.closest("[data-table-id]");
    let picker = container ? container.querySelector("[data-statement-picker]") : null;
    if (!picker || !picker.value)
        return (false);
    return (billing_open_dialog_by_id("billing_organization_statement_" + movementType + "_" + picker.value));
}

function billing_set_export_period(form, period)
{
    if (!form)
        return (false);
    let year = parseInt(form.querySelector('[name="preset_year"]')?.value || "0", 10);
    if (!year || year < 2000 || year > 2200)
        return (false);
    let ranges = {
        q1: [1, 3], q2: [4, 6], q3: [7, 9], q4: [10, 12],
        s1: [1, 6], s2: [7, 12], year: [1, 12]
    };
    let range = ranges[period];
    if (!range)
        return (false);
    let pad = function(value) { return String(value).padStart(2, "0"); };
    let lastDay = new Date(year, range[1], 0).getDate();
    form.querySelector('[name="start_date"]').value = year + "-" + pad(range[0]) + "-01";
    form.querySelector('[name="end_date"]').value = year + "-" + pad(range[1]) + "-" + pad(lastDay);
    return (false);
}

function billing_bank_import(form)
{
    return (silent_submitf(form, {
        after_success: function() { document.location.reload(); }
    }));
}

function billing_bank_reconcile(form)
{
    let kind = form.querySelector('[name="association_kind"]');
    let id = form.querySelector('[name="association_id"]');
    if (!kind || !id || !kind.value || parseInt(id.value || "0", 10) <= 0)
    {
        alert("Sélectionnez une association proposée dans la liste.");
        return (false);
    }
    return (silent_submitf(form, {
        after_success: function() { document.location.reload(); }
    }));
}

function billing_bank_simple_action(form)
{
    return (silent_submitf(form, {
        after_success: function() { document.location.reload(); }
    }));
}

function billing_bank_supplier_submit(form)
{
    return (silent_submitf(form, {
        after_success: function() { document.location.reload(); }
    }));
}

function billing_bank_preview_csv(input)
{
    let form = input && input.closest ? input.closest("form") : null;
    let root = form && form.closest ? form.closest(".billing_bank_card") : null;
    let preview = root ? root.querySelector("[data-bank-csv-preview]") : null;
    let file = input && input.files ? input.files[0] : null;
    if (!preview)
        return;
    if (!file)
    {
        preview.classList.add("hidden");
        return;
    }
    let reader = new FileReader();
    reader.onload = function(event)
    {
        let rows = null;
        try
        {
            rows = csv_parse(String(event.target.result || "").replace(/^\uFEFF/, ""));
        }
        catch (e)
        {
            rows = null;
        }
        let thead = preview.querySelector("thead");
        let tbody = preview.querySelector("tbody");
        thead.innerHTML = "";
        tbody.innerHTML = "";
        if (!rows || !rows.length)
        {
            let tr = document.createElement("tr");
            let th = document.createElement("th");
            th.textContent = "Impossible de prévisualiser ce CSV. Vérifiez qu'il utilise ';' comme séparateur.";
            tr.appendChild(th);
            thead.appendChild(tr);
            preview.classList.remove("hidden");
            return;
        }
        let header = document.createElement("tr");
        rows[0].forEach(function(value)
        {
            let th = document.createElement("th");
            th.textContent = value;
            header.appendChild(th);
        });
        thead.appendChild(header);
        rows.slice(1, 41).forEach(function(row)
        {
            if (!row.length || row.every(function(v) { return String(v || "").trim() === ""; }))
                return;
            let tr = document.createElement("tr");
            rows[0].forEach(function(_, index)
            {
                let td = document.createElement("td");
                td.textContent = row[index] === undefined ? "" : row[index];
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        preview.classList.remove("hidden");
    };
    reader.readAsText(file);
}

function billing_bank_resolve_association(input)
{
    let form = input && input.closest ? input.closest("form") : null;
    if (!form)
        return;
    let kind = form.querySelector('[name="association_kind"]');
    let id = form.querySelector('[name="association_id"]');
    let option = null;
    let list = input.list;
    if (list)
    {
        Array.from(list.options).some(function(candidate)
        {
            if (candidate.value === input.value)
            {
                option = candidate;
                return true;
            }
            return false;
        });
    }
    if (kind)
        kind.value = option ? (option.dataset.kind || "") : "";
    if (id)
        id.value = option ? (option.dataset.id || "") : "";
    let row = form.closest("[data-bank-row]");
    let documentInput = row ? row.querySelector("[data-bank-document]") : null;
    let allowDocument = option && ["organization", "organization_entry"].indexOf(option.dataset.kind || "") !== -1;
    if (documentInput)
    {
        documentInput.disabled = !allowDocument;
        if (!allowDocument)
            documentInput.value = "";
    }
}

function billing_bank_add_association_option(kind, id, label)
{
    let list = document.getElementById("billing_bank_associations");
    if (!list)
        return;
    let existing = Array.from(list.options).find(function(option) { return option.value === label; });
    if (existing)
        return existing;
    let option = document.createElement("option");
    option.value = label;
    option.dataset.kind = kind;
    option.dataset.id = id;
    list.appendChild(option);
    return option;
}

function billing_bank_create_organization_request(name)
{
    let body = new URLSearchParams();
    body.set("name", name);
    return fetch("/api/billing/-1/bank_organization", {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"},
        body: body.toString()
    }).then(function(response) { return response.json(); }).then(function(packet)
    {
        if (!packet || packet.result !== "ok" || !packet.organization)
            throw new Error(packet && packet.msg ? packet.msg : "Impossible de créer l'organisation.");
        return packet.organization;
    });
}

function billing_bank_create_organization(button)
{
    let row = button && button.closest ? button.closest("[data-bank-row]") : null;
    let form = button && button.closest ? button.closest("form") : null;
    let input = form ? form.querySelector("[data-bank-association]") : null;
    if (!row || !form || !input)
        return false;
    let suggested = row.getAttribute("data-bank-label") || "";
    let name = window.prompt("Nom de la nouvelle organisation :", suggested);
    if (!name || !name.trim())
        return false;
    button.disabled = true;
    billing_bank_create_organization_request(name.trim()).then(function(organization)
    {
        let label = "Entreprise — " + organization.name + " [" + organization.codename + "]";
        billing_bank_add_association_option("organization", organization.id, label);
        input.value = label;
        billing_bank_resolve_association(input);
        let supplierSelect = document.querySelector('[data-bank-supplier-form] select[name="id_organization"]');
        if (supplierSelect && !supplierSelect.querySelector('option[value="' + organization.id + '"]'))
        {
            let option = document.createElement("option");
            option.value = organization.id;
            option.textContent = organization.name + " — " + organization.codename;
            supplierSelect.appendChild(option);
        }
    }).catch(function(error)
    {
        alert(error.message || String(error));
    }).finally(function()
    {
        button.disabled = false;
    });
    return false;
}

function billing_bank_create_organization_for_supplier(button)
{
    let form = button && button.closest ? button.closest("form") : null;
    if (!form)
        return false;
    let name = window.prompt("Nom de la nouvelle organisation :", "");
    if (!name || !name.trim())
        return false;
    button.disabled = true;
    billing_bank_create_organization_request(name.trim()).then(function(organization)
    {
        let select = form.querySelector('select[name="id_organization"]');
        if (select)
        {
            let option = document.createElement("option");
            option.value = organization.id;
            option.textContent = organization.name + " — " + organization.codename;
            option.selected = true;
            select.appendChild(option);
        }
        billing_bank_add_association_option("organization", organization.id, "Entreprise — " + organization.name + " [" + organization.codename + "]");
        billing_bank_find_supplier_matches(form);
    }).catch(function(error)
    {
        alert(error.message || String(error));
    }).finally(function()
    {
        button.disabled = false;
    });
    return false;
}

function billing_bank_filter_rows()
{
    let root = document.querySelector(".billing_bank_reconciliation");
    if (!root)
        return;
    let queryInput = root.querySelector("[data-bank-search]");
    let statusInput = root.querySelector("[data-bank-status]");
    let query = (queryInput ? queryInput.value : "").trim().toLowerCase();
    if (query.normalize)
        query = query.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    query = query.replace(/[^a-z0-9]+/g, " ").trim();
    let status = statusInput ? statusInput.value : "all";
    root.querySelectorAll("[data-bank-row]").forEach(function(row)
    {
        let rowStatus = row.getAttribute("data-status") || "pending";
        let search = (row.getAttribute("data-search") || "").toLowerCase();
        let visible = (status === "all" || status === rowStatus) && (!query || search.indexOf(query) !== -1);
        row.classList.toggle("hidden", !visible);
    });
}

function billing_bank_amount_to_cents_client(value)
{
    value = String(value || "").replace(/[€\s\u00a0]/g, "").replace(",", ".");
    if (!/^\d+(?:\.\d{1,2})?$/.test(value))
        return 0;
    return Math.round(parseFloat(value) * 100);
}

function billing_bank_select_supplier_match(button)
{
    let form = button && button.closest ? button.closest("form") : null;
    if (!form)
        return false;
    let hidden = form.querySelector('[name="id_bank_transaction"]');
    if (!hidden)
        return false;
    let selected = parseInt(button.dataset.bankId || "0", 10);
    let wasSelected = hidden.value === String(selected);
    hidden.value = wasSelected ? "0" : String(selected);
    form.querySelectorAll("[data-supplier-bank-candidate]").forEach(function(candidate)
    {
        candidate.classList.toggle("selected", !wasSelected && candidate === button);
    });
    return false;
}

function billing_bank_find_supplier_matches(form)
{
    if (!form)
        return;
    let container = form.querySelector("[data-supplier-matches]");
    let amountInput = form.querySelector('[name="amount"]');
    let dateInput = form.querySelector('[name="invoice_date"]');
    let schoolInput = form.querySelector('[name="id_school"]');
    let organizationInput = form.querySelector('[name="id_organization"]');
    let hidden = form.querySelector('[name="id_bank_transaction"]');
    if (!container || !amountInput || !dateInput || !schoolInput)
        return;
    let amount = billing_bank_amount_to_cents_client(amountInput.value);
    let invoiceDate = dateInput.value ? new Date(dateInput.value + "T00:00:00") : null;
    let school = parseInt(schoolInput.value || "0", 10);
    if (!amount || !invoiceDate || isNaN(invoiceDate.getTime()) || !school)
    {
        container.innerHTML = "<small>Saisissez le montant et la date pour rechercher un débit bancaire compatible.</small>";
        if (hidden) hidden.value = "0";
        return;
    }
    let orgText = "";
    if (organizationInput && organizationInput.options && organizationInput.selectedIndex >= 0)
        orgText = organizationInput.options[organizationInput.selectedIndex].textContent.toLowerCase();
    let candidates = [];
    document.querySelectorAll('[data-bank-row][data-status="pending"]').forEach(function(row)
    {
        let rowAmount = parseInt(row.getAttribute("data-bank-amount") || "0", 10);
        let rowSchool = parseInt(row.getAttribute("data-bank-school") || "0", 10);
        if (rowSchool !== school || rowAmount >= 0 || Math.abs(rowAmount) !== amount)
            return;
        let bankDate = new Date((row.getAttribute("data-bank-date") || "") + "T00:00:00");
        if (isNaN(bankDate.getTime()))
            return;
        let diff = Math.round((bankDate.getTime() - invoiceDate.getTime()) / 86400000);
        if (diff < -7 || diff > 120)
            return;
        let label = row.getAttribute("data-bank-label") || "";
        let score = Math.abs(diff);
        if (orgText && orgText.split(/\s+/).some(function(token) { return token.length >= 4 && label.toLowerCase().indexOf(token) !== -1; }))
            score -= 30;
        candidates.push({row: row, diff: diff, score: score, label: label});
    });
    candidates.sort(function(a, b) { return a.score - b.score; });
    container.innerHTML = "";
    if (!candidates.length)
    {
        let small = document.createElement("small");
        small.textContent = "Aucun débit bancaire du même montant entre J-7 et J+120.";
        container.appendChild(small);
        if (hidden) hidden.value = "0";
        return;
    }
    let title = document.createElement("small");
    title.textContent = "Mouvements bancaires compatibles — cliquez pour rapprocher :";
    container.appendChild(title);
    candidates.slice(0, 8).forEach(function(candidate)
    {
        let button = document.createElement("button");
        button.type = "button";
        button.dataset.supplierBankCandidate = "1";
        button.dataset.bankId = candidate.row.getAttribute("data-bank-id") || "0";
        let date = candidate.row.getAttribute("data-bank-date") || "";
        button.textContent = date + " · " + candidate.label + " · " + (candidate.diff >= 0 ? "+" : "") + candidate.diff + " j";
        button.onclick = function() { return billing_bank_select_supplier_match(button); };
        container.appendChild(button);
    });
}

document.addEventListener("DOMContentLoaded", function()
{
    billing_bank_filter_rows();
    document.querySelectorAll("[data-bank-supplier-form]").forEach(function(form) { billing_bank_find_supplier_matches(form); });
});

function billing_einvoice_refresh_view()
{
    let current = document.querySelector(".billing_einvoice_tab");
    let scroll_container = current ? current.closest(".billing_tab_content") : null;
    let scroll_top = scroll_container ? scroll_container.scrollTop : 0;
    let scroll_left = scroll_container ? scroll_container.scrollLeft : 0;

    if (!current)
        return Promise.reject(new Error("Cannot find electronic invoicing panel"));

    return fetch(window.location.href, {credentials: "same-origin", cache: "no-store"})
        .then(function(response)
        {
            if (!response.ok)
                throw new Error("Cannot refresh electronic invoicing panel");
            return response.text();
        })
        .then(function(html)
        {
            let parser = new DOMParser();
            let doc = parser.parseFromString(html, "text/html");
            let fresh = doc.querySelector(".billing_einvoice_tab");

            if (!fresh)
                throw new Error("Cannot find refreshed electronic invoicing panel");

            current.replaceWith(document.importNode(fresh, true));
            if (scroll_container)
            {
                scroll_container.scrollTop = scroll_top;
                scroll_container.scrollLeft = scroll_left;
            }
            document.querySelectorAll(".billing_einvoice_connector_form").forEach(function(form)
            {
                if (typeof billing_einvoice_connector_changed === "function")
                    billing_einvoice_connector_changed(form);
            });
        })
        .catch(function(error)
        {
            console.error(error);
            throw error;
        });
}

function billing_einvoice_sync(form)
{
    return (silent_submitf(form, {
        after_success: function() { billing_einvoice_refresh_view(); }
    }));
}
