let currently_open_billing_action_div = null;
let currently_selected_billing_delete_event = null;
let currently_open_pending_invoice_menu = null;

let billing_application_body = document.getElementById("body");
if (billing_application_body)
    billing_application_body.classList.add("billing_page_active");

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

    form.querySelector('[name="context_bindings"]').value = JSON.stringify({
        Student: form.querySelector('[data-payment-reminder-student]').value,
        School: form.querySelector('[data-payment-reminder-school]').value
    });
    billing_payment_reminder_set_field(form, 'InvoiceReferences', references.join(', '));
    billing_payment_reminder_set_field(form, 'InvoiceLines', lines.join(' ; '));
    billing_payment_reminder_set_field(form, 'TotalOutstanding', billing_payment_reminder_euros(total));
    billing_payment_reminder_set_field(form, 'OldestDueDate', oldestLabel);
    billing_payment_reminder_set_field(form, 'ResponseDeadline', form.querySelector('[data-payment-reminder-deadline]').value.trim());
    billing_payment_reminder_set_field(form, 'Observations', form.querySelector('[data-payment-reminder-observations]').value.trim());
    return (true);
}
