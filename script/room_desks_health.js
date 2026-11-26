async function room_desks_health_toggle(button)
{
    const row = document.getElementById(button.getAttribute("aria-controls"));
    if (!row)
        return false;
    row.hidden = !row.hidden;
    button.setAttribute("aria-expanded", String(!row.hidden));
    const placeholder = row.querySelector("[data-health-placeholder]");
    if (row.hidden || !placeholder || placeholder.dataset.loading)
        return false;
    placeholder.dataset.loading = "1";
    placeholder.textContent = "Chargement des graphes…";
    try
    {
        const response = await fetch(row.dataset.healthUrl, {credentials: "same-origin", cache: "no-store"});
        const packet = await response.json();
        if (!response.ok || packet.result !== "ok" || typeof packet.content !== "string")
            throw new Error("Invalid machine history");
        row.querySelector("td").innerHTML = packet.content;
    }
    catch (error)
    {
        placeholder.textContent = "Graphes indisponibles. Replier puis rouvrir la ligne pour réessayer.";
        delete placeholder.dataset.loading;
    }
    return false;
}
