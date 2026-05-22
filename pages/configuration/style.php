<style>
.configuration-admin {
    color: #f7fff7;
    width: 100%;
}
.configuration-admin * {
    box-sizing: border-box;
}
.configuration-admin .tabpanel {
    width: 100%;
}
.configuration-admin .tablist {
    display: flex;
    gap: 8px;
    align-items: stretch;
    margin: 0 0 14px 0;
    padding: 0;
}
.configuration-admin .tablist a {
    flex: 1 1 0;
    min-width: 0;
    text-decoration: none;
}
.configuration-tab-button {
    width: 100% !important;
    min-height: 38px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(0, 220, 0, 0.32);
    background: rgba(0, 0, 0, 0.78);
    color: #f7fff7;
    text-align: center;
    cursor: pointer;
}
.configuration-tab-button.selected {
    background: rgba(0, 120, 0, 0.82);
    border-color: rgba(0, 255, 0, 0.70);
    font-weight: bold;
}
.configuration-tab-content {
    width: 100%;
}
.configuration-card,
.configuration-subcard {
    background: rgba(0, 0, 0, 0.82);
    color: #f7fff7;
    border: 1px solid rgba(0, 220, 0, 0.30);
    border-radius: 12px;
    padding: 14px;
    box-shadow: 0 2px 14px rgba(0, 0, 0, 0.34);
    overflow: visible;
}
.configuration-subcard {
    background: rgba(0, 0, 0, 0.74);
}
.configuration-card h2,
.configuration-card h3,
.configuration-subcard h2,
.configuration-subcard h3 {
    color: #ffffff !important;
    margin-top: 0;
    margin-bottom: 8px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.9);
}
.configuration-card a,
.configuration-card code,
.configuration-card strong,
.configuration-card span,
.configuration-card p,
.configuration-card label,
.configuration-card th,
.configuration-card td,
.configuration-card summary,
.configuration-subcard a,
.configuration-subcard code,
.configuration-subcard strong,
.configuration-subcard span,
.configuration-subcard p,
.configuration-subcard label,
.configuration-subcard th,
.configuration-subcard td,
.configuration-subcard summary {
    color: inherit;
}
.configuration-card-header {
    display: flex;
    gap: 12px;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}
.configuration-field-description {
    opacity: 0.96;
    font-size: 0.92em;
    margin: 4px 0 10px 0;
}
.configuration-muted {
    opacity: 0.9;
    font-size: 0.86em;
}
.configuration-card input,
.configuration-card select,
.configuration-card textarea,
.configuration-card button,
.configuration-subcard input,
.configuration-subcard select,
.configuration-subcard textarea,
.configuration-subcard button {
    text-shadow: none;
}
.configuration-toolbar input,
.configuration-toolbar select,
.configuration-toolbar button,
.configuration-toolbar a,
.configuration-log-filters input,
.configuration-log-filters select,
.configuration-log-filters button,
.configuration-field textarea,
.configuration-field input[type="text"],
.configuration-field input[type="password"] {
    border: 1px solid rgba(0, 0, 0, 0.36);
    border-radius: 8px;
    padding: 7px;
    max-width: 100%;
    background: #fff;
    color: #111;
}
.configuration-button,
.configuration-toolbar a,
.configuration-log-filters a,
.configuration-log-pager button,
.configuration-log-pager a,
.configuration-operation-actions button {
    display: inline-block;
    text-decoration: none;
    background: rgba(0, 80, 0, 0.82);
    color: #f7fff7;
    border-radius: 8px;
    padding: 7px 10px;
    border: 1px solid rgba(0, 220, 0, 0.36);
    cursor: pointer;
}
.configuration-grid,
.configuration-stats-grid,
.configuration-stat-strip {
    display: grid;
    gap: 10px;
}
.configuration-grid {
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}
.configuration-stats-grid {
    grid-template-columns: repeat(2, minmax(280px, 1fr));
    align-items: start;
}
.configuration-wide-subcard {
    grid-column: 1 / -1;
}
.configuration-stat-strip {
    grid-template-columns: repeat(auto-fit, minmax(125px, 1fr));
}
.configuration-stat {
    border-radius: 10px;
    padding: 10px;
    background: rgba(0, 0, 0, 0.62);
    border: 1px solid rgba(0, 220, 0, 0.18);
}
.configuration-stat strong {
    color: #ffffff;
    font-size: 1.15em;
}
.configuration-progress {
    width: 100%;
    height: 16px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.26);
    overflow: hidden;
}
.configuration-storage-card .configuration-progress {
    display: block;
    clear: both;
    margin: 8px 0 12px 0;
}
.configuration-storage-details {
    margin-top: 0;
}
.configuration-progress > div {
    height: 16px;
    background: #2e7d32;
}
.configuration-storage-warning .configuration-progress > div {
    background: #b8860b;
}
.configuration-storage-critical .configuration-progress > div {
    background: #b00020;
}
.configuration-storage-alert,
.configuration-alert {
    margin: 0 0 10px 0;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(176, 0, 32, 0.88);
    color: white !important;
    font-weight: bold;
}
.configuration-alert-list {
    display: grid;
    gap: 8px;
    margin-bottom: 12px;
}
.configuration-alert strong {
    display: block;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.configuration-ok-box {
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(0, 90, 0, 0.72);
    border: 1px solid rgba(0, 220, 0, 0.32);
}
.configuration-definition-list {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 8px 14px;
    margin: 0 0 10px 0;
}
.configuration-definition-list dt {
    font-weight: bold;
}
.configuration-definition-list dd {
    margin: 0;
}
.configuration-log-count {
    white-space: nowrap;
    border-radius: 999px;
    background: rgba(0, 140, 0, 0.78);
    padding: 7px 11px;
    font-weight: bold;
}
.configuration-log-filters {
    display: grid;
    grid-template-columns: minmax(300px, 2.4fr) minmax(130px, 0.8fr) minmax(150px, 0.9fr) minmax(110px, 0.65fr) minmax(140px, 0.9fr) minmax(160px, 1fr) minmax(160px, 1fr) auto auto;
    gap: 8px;
    align-items: end;
    margin-bottom: 10px;
}
.configuration-log-filters label {
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-size: 0.86em;
    min-width: 0;
}
.configuration-log-filters input,
.configuration-log-filters select {
    width: 100%;
}
.configuration-checkbox-label {
    flex-direction: row !important;
    align-items: center;
    padding-bottom: 7px;
    white-space: nowrap;
}
.configuration-filter-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    padding-bottom: 1px;
}
.configuration-log-pager {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 4px 0 10px 0;
}
.configuration-log-pager form {
    margin: 0;
}
.configuration-log-table-wrap {
    border: 1px solid rgba(0, 220, 0, 0.26);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.64);
    overflow: visible;
}
.configuration-log-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
    font-size: 0.86em;
}
.configuration-log-col-id { width: 1%; }
.configuration-log-col-date { width: 130px; }
.configuration-log-col-user { width: 110px; }
.configuration-log-col-type { width: 118px; }
.configuration-log-col-message { width: auto; }
.configuration-log-col-ip { width: 90px; }
.configuration-log-col-url { width: 120px; }
.configuration-log-table th,
.configuration-log-table td {
    padding: 5px 7px;
    border-bottom: 1px solid rgba(0, 220, 0, 0.16);
    vertical-align: top;
}
.configuration-log-table tr {
    background: rgba(0, 0, 0, 0.56);
}
.configuration-log-table tr:nth-child(even) {
    background: rgba(0, 34, 0, 0.62);
}
.configuration-log-table th {
    background: rgba(0, 110, 0, 0.82);
    font-weight: bold;
}
.configuration-log-id,
.configuration-log-ip,
.configuration-log-date,
.configuration-log-user,
.configuration-log-type {
    white-space: nowrap;
}
.configuration-log-id {
    width: 1%;
    text-align: right;
}
.configuration-log-user code,
.configuration-log-type code {
    margin-left: 4px;
}
.configuration-log-message {
    min-width: 360px;
    white-space: pre-wrap;
    word-break: break-word;
}
.configuration-log-url {
    max-width: 160px;
    word-break: break-word;
}
.configuration-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
    gap: 8px;
}
.configuration-field {
    border: 1px solid rgba(0, 220, 0, 0.22);
    border-radius: 10px;
    padding: 0;
    background: rgba(0, 0, 0, 0.62);
    min-width: 0;
}
.configuration-field summary {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    padding: 8px 9px;
}
.configuration-field summary > span:first-child {
    min-width: 0;
}
.configuration-field summary strong,
.configuration-field summary code {
    display: block;
    overflow-wrap: anywhere;
}
.configuration-field summary::-webkit-details-marker,
.configuration-operation-source summary::-webkit-details-marker {
    display: none;
}
.configuration-field summary code {
    opacity: 0.84;
    font-size: 0.82em;
}
.configuration-field[open] {
    padding-bottom: 9px;
}
.configuration-field[open] > :not(summary) {
    margin-left: 9px;
    margin-right: 9px;
}
.configuration-field textarea {
    width: 100%;
    min-height: 72px;
    box-sizing: border-box;
    resize: vertical;
}
.configuration-field input[type="text"],
.configuration-field input[type="password"] {
    width: 100%;
}
.configuration-field form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 6px;
    margin: 0;
}
.configuration-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: end;
}
.configuration-badge {
    border-radius: 999px;
    padding: 2px 6px;
    font-size: 0.73em;
    background: rgba(0, 150, 0, 0.62);
    white-space: nowrap;
}
.configuration-operation-output {
    margin-bottom: 12px;
    min-height: 150px;
}
.configuration-operation-buttons {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 10px;
}
.configuration-operation-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    border: 1px solid rgba(0, 220, 0, 0.22);
    border-radius: 10px;
    padding: 8px;
    background: rgba(0, 0, 0, 0.62);
    min-width: 0;
}
.configuration-operation-name {
    min-width: 0;
}
.configuration-operation-name strong,
.configuration-operation-name code {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.configuration-operation-name code {
    opacity: 0.76;
    font-size: 0.82em;
}
.configuration-operation-actions {
    display: flex;
    gap: 6px;
    align-items: center;
}
.configuration-operation-actions form {
    margin: 0;
}
.configuration-operation-actions button {
    white-space: nowrap;
}
.configuration-danger-button {
    background: rgba(90, 0, 0, 0.84) !important;
    border-color: rgba(255, 120, 120, 0.42) !important;
}
.configuration-source,
.configuration-result pre {
    background: rgba(0, 0, 0, 0.72);
    border: 1px solid rgba(0, 220, 0, 0.28);
    border-radius: 8px;
    padding: 10px;
    text-align: left;
    white-space: pre-wrap;
    word-break: break-word;
    overflow: visible;
}
.configuration-source {
    max-width: 100%;
}
.configuration-source code,
.configuration-source span {
    white-space: pre-wrap !important;
}
.configuration-result {
    margin-bottom: 0;
}
.configuration-compact-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
}
@media (max-width: 1320px) {
    .configuration-log-filters {
        grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    }
    .configuration-filter-search {
        grid-column: span 2;
    }
}
@media (max-width: 900px) {
    .configuration-stats-grid,
    .configuration-operation-buttons,
    .configuration-fields {
        grid-template-columns: 1fr;
    }
    .configuration-card-header,
    .configuration-field summary,
    .configuration-field form {
        grid-template-columns: 1fr;
        display: grid;
    }
    .configuration-admin .tablist {
        flex-wrap: wrap;
    }
    .configuration-admin .tablist a {
        flex: 1 1 calc(50% - 8px);
    }
}
@media (max-width: 720px) {
    .configuration-log-filters {
        grid-template-columns: 1fr;
    }
    .configuration-filter-search {
        grid-column: auto;
    }
    .configuration-admin .tablist a {
        flex-basis: 100%;
    }
}
</style>
<script>
function configurationResetLogs(form)
{
    if (!form)
        return (false);
    ["log_search", "log_user", "log_type", "log_ip", "log_url", "log_from", "log_to"].forEach(function(name) {
        if (form.elements[name])
            form.elements[name].value = "";
    });
    if (form.elements["log_system"])
        form.elements["log_system"].checked = false;
    if (form.elements["log_page"])
        form.elements["log_page"].value = "0";
    return (silent_submit(form, "configuration-logs-panel"));
}

function configurationShowOperationSource(operationId)
{
    var template = document.getElementById("configuration-operation-source-" + operationId);
    var target = document.getElementById("configuration-operation-output");

    if (!template || !target)
        return (false);
    target.innerHTML = "";
    target.appendChild(template.content.cloneNode(true));
    return (false);
}

function configurationRefreshStats()
{
    var target = document.getElementById("configuration-stats-panel");

    if (!target)
        return (false);

    var form = document.createElement("form");
    form.method = "post";
    form.action = "api/configuration/0/stats";
    form.style.display = "none";
    document.body.appendChild(form);
    return (silent_submit(
        form,
        "configuration-stats-panel",
        null,
        null,
        null,
        {},
        "",
        false,
        false,
        function () { form.remove(); }
    ));
}

function configurationRunOperation(form, message)
{
    if (!confirm(message))
        return (false);
    return (silent_submit(
        form,
        "configuration-operation-output",
        null,
        null,
        null,
        null,
        "",
        false,
        false,
        configurationRefreshStats
    ));
}
</script>
