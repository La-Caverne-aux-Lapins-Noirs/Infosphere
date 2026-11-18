async function room_desks_upgrade_refresh()
{
    const root = document.querySelector(".room_desks");
    if (!root)
        return;
    try
    {
        const response = await fetch("/api/desk/-1/upgrade_states", {
            credentials: "same-origin",
            cache: "no-store",
        });
        const packet = await response.json();
        if (!response.ok || packet.result !== "ok" || typeof packet.content !== "object" || !packet.content)
            throw new Error("Invalid workstation upgrade response");
        for (const cell of root.querySelectorAll("[data-desk-upgrade]"))
        {
            const id = cell.dataset.deskUpgrade;
            const state = packet.content[id];
            if (!state)
                continue;
            const label = cell.querySelector("[role=status]");
            if (label.textContent !== state.label)
                label.textContent = state.label;
            const button = cell.querySelector("button");
            if (button)
                button.disabled = !state.can_request;
            root.querySelector('[data-desk-huile="' + id + '"]').textContent = state.huile_version;
        }
    }
    catch (error)
    {
        for (const cell of root.querySelectorAll("[data-desk-upgrade]"))
        {
            cell.querySelector("[role=status]").textContent = root.dataset.upgradeUnavailable;
            const button = cell.querySelector("button");
            if (button)
                button.disabled = true;
        }
    }
}

function room_desks_upgrade_submit(form)
{
    form.querySelector("button").disabled = true;
    return silent_submitf(form, {after_complete: room_desks_upgrade_refresh});
}

async function room_desks_upgrade_poll()
{
    if (!document.querySelector(".room_desks"))
        return;
    if (!document.hidden)
        await room_desks_upgrade_refresh();
    window.setTimeout(room_desks_upgrade_poll, 5000);
}

document.addEventListener("DOMContentLoaded", function () {
    if (document.querySelector(".room_desks"))
        window.setTimeout(room_desks_upgrade_poll, 5000);
});
