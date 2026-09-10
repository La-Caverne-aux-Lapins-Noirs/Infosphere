<?php
require_once ("get_docs.php");
require_once (__DIR__."/../../tools/document_workflow.php");
require_once (__DIR__."/../../tools/document_print.php");
require_once (__DIR__."/../../tools/document_context_schema.php");
?>
<div class="documents_page">
<h2 class="alignable_blocks"><?=$Dictionnary["Documents"]; ?></h2>

<script>
var docContextActiveReference = '';
var docContextAutofillTimer = null;
var docContextAutofillSerial = 0;

function doc_toggle(id, elem)
{
    var input = document.getElementById(id);
    var selecting = input.value == '0';
    input.value = selecting ? '1' : '0';
    elem.classList.toggle('selected', selecting);
    var completion = document.querySelector('.documents_generate_panel [name="form_output"]');
    if (completion)
        completion.value = '';
    doc_context_refresh_model();
}

function doc_search_normalize(value)
{
    value = String(value || '').toLowerCase();
    if (typeof value.normalize === 'function')
        value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return value.trim();
}

function doc_filter_documents(value)
{
    var list = document.getElementById('doc_document_list');
    if (!list)
        return;

    var needle = doc_search_normalize(value);
    var visibleSources = {};
    list.querySelectorAll('.doc_document_choice').forEach(function (item) {
        var haystack = doc_search_normalize(
            (item.textContent || '') + ' ' +
            (item.getAttribute('title') || '') + ' ' +
            (item.getAttribute('data-reference') || '')
        );
        var visible = needle === '' || haystack.indexOf(needle) !== -1;
        item.style.display = visible ? '' : 'none';
        if (visible)
            visibleSources[item.getAttribute('data-doc-source') || ''] = true;
    });

    list.querySelectorAll('.doc_source_title').forEach(function (title) {
        var source = title.getAttribute('data-doc-source') || '';
        title.style.display = visibleSources[source] ? '' : 'none';
    });
}

function doc_context_selected_items()
{
    var form = document.querySelector('.documents_generate_panel');
    return form ? Array.prototype.slice.call(form.querySelectorAll('.doc_document_choice.selected')) : [];
}

function doc_context_schema()
{
    var schema = {};
    doc_context_selected_items().forEach(function (item) {
        var current = {};
        try { current = JSON.parse(item.getAttribute('data-contexts') || '{}'); }
        catch (e) { current = {}; }
        Object.keys(current).forEach(function (name) {
            var definition = current[name] || {};
            if (!schema[name])
            {
                schema[name] = Object.assign({}, definition);
                schema[name].infer_from = (definition.infer_from || []).slice();
                return;
            }
            var merged = schema[name];
            merged.required = !!merged.required || !!definition.required;
            merged.infer_from = Array.from(new Set((merged.infer_from || []).concat(definition.infer_from || [])));
            if (!merged.auto && definition.auto) merged.auto = definition.auto;
            if (!merged.placeholder && definition.placeholder) merged.placeholder = definition.placeholder;
            if (!merged.signatory && definition.signatory) merged.signatory = definition.signatory;
            if (!merged.label && definition.label) merged.label = definition.label;
            if (merged.type && definition.type && merged.type !== definition.type)
                merged.conflict = true;
        });
    });
    return schema;
}

function doc_context_bindings(includeAutomatic)
{
    if (typeof includeAutomatic === 'undefined')
        includeAutomatic = true;
    var out = {};
    document.querySelectorAll('#doc_context_list .doc_context_row').forEach(function (row) {
        if (!includeAutomatic && row.getAttribute('data-context-origin') === 'automatic')
            return;
        var input = row.querySelector('[data-context-value]');
        var name = row.getAttribute('data-context-name') || '';
        var value = input ? input.value.trim() : '';
        if (name && value)
            out[name] = value;
    });
    return out;
}

function doc_context_serialize()
{
    var input = document.getElementById('doc_context_bindings');
    if (input)
        input.value = JSON.stringify(doc_context_bindings());
}

function doc_context_schedule_autofill()
{
    if (docContextAutofillTimer !== null)
        window.clearTimeout(docContextAutofillTimer);
    docContextAutofillTimer = window.setTimeout(function () {
        docContextAutofillTimer = null;
        doc_context_autofill();
    }, 180);
}

function doc_context_apply_autofill(payload)
{
    var bindings = payload && payload.bindings ? payload.bindings : {};
    var automatic = new Set(payload && Array.isArray(payload.automatic) ? payload.automatic : []);
    var explicit = doc_context_bindings(false);

    document.querySelectorAll('#doc_context_list .doc_context_row[data-context-origin="automatic"]').forEach(function (row) {
        var name = row.getAttribute('data-context-name') || '';
        if (!automatic.has(name) || !bindings[name] || explicit[name])
            row.remove();
    });

    automatic.forEach(function (name) {
        if (explicit[name] || !bindings[name])
            return;
        var row = doc_context_find_row(name);
        if (!row)
            row = doc_context_add(name, bindings[name], 'automatic', false);
        if (!row)
            return;
        row.setAttribute('data-context-origin', 'automatic');
        row.classList.add('doc_context_row_automatic');
        var definition = doc_context_schema()[name] || {};
        var label = row.querySelector('label');
        if (label)
            label.textContent = (definition.label || name) + (definition.required ? ' *' : '') + ' — automatique';
        var input = row.querySelector('[data-context-value]');
        if (input)
            input.value = bindings[name];
    });
    doc_context_serialize();
    doc_context_refresh_buttons();
}

function doc_context_autofill()
{
    var selected = doc_context_selected_items();
    if (!selected.length)
        return;
    var request = ++docContextAutofillSerial;
    var data = new FormData();
    selected.forEach(function (item) {
        var hash = item.getAttribute('data-form-hash') || '';
        var reference = item.getAttribute('data-reference') || '';
        if (!hash || !reference)
            return;
        data.set('doc_' + hash, '1');
        data.set('docref_' + hash, reference);
    });
    data.set('context_bindings', JSON.stringify(doc_context_bindings(false)));
    fetch('/api/doc/0/context', {method: 'POST', body: data, credentials: 'same-origin'})
        .then(function (response) { return response.json(); })
        .then(function (payload) {
            if (request !== docContextAutofillSerial)
                return;
            if (!payload || payload.result !== 'ok')
            {
                doc_generation_set_error(payload && payload.msg ? payload.msg : 'Impossible de compléter automatiquement le contexte.');
                return;
            }
            doc_context_apply_autofill(payload);
        })
        .catch(function () {
            if (request === docContextAutofillSerial)
                doc_generation_set_error('Impossible de compléter automatiquement le contexte.');
        });
}

function doc_context_placeholder(definition)
{
    if (definition.placeholder)
        return definition.placeholder;
    switch ((definition.type || '').toLowerCase())
    {
        case 'school': return 'id ou nom de code de l’école';
        case 'cycle': return 'id ou nom de code du cycle';
        case 'title_session': return 'id de la session de titre';
        case 'organization': return 'id ou nom de l’organisation';
        case 'tutor': return 'id ou nom de code du tuteur';
        case 'jury': return 'id ou nom de code du juré';
        case 'student': return 'id ou nom de code de l’élève';
        default: return 'id ou nom de code';
    }
}

function doc_context_find_row(name)
{
    var rows = document.querySelectorAll('#doc_context_list [data-context-name]');
    for (var i = 0; i < rows.length; ++i)
        if ((rows[i].getAttribute('data-context-name') || '') === name)
            return rows[i];
    return null;
}

function doc_context_remove(row)
{
    row.remove();
    doc_context_serialize();
    doc_context_refresh_buttons();
    doc_context_schedule_autofill();
}

function doc_context_add(name, value, origin, focus)
{
    var schema = doc_context_schema();
    var definition = schema[name];
    if (!definition)
        return null;
    if (typeof value === 'undefined') value = '';
    if (!origin) origin = 'explicit';
    if (typeof focus === 'undefined') focus = true;
    var list = document.getElementById('doc_context_list');
    var existing = doc_context_find_row(name);
    if (existing)
    {
        if (origin === 'explicit')
        {
            existing.setAttribute('data-context-origin', 'explicit');
            existing.classList.remove('doc_context_row_automatic');
            var existingLabel = existing.querySelector('label');
            if (existingLabel)
                existingLabel.textContent = (definition.label || name) + (definition.required ? ' *' : '');
        }
        var existingInput = existing.querySelector('[data-context-value]');
        if (existingInput && value !== '')
            existingInput.value = value;
        if (existingInput && focus)
            existingInput.focus();
        return existing;
    }

    var row = document.createElement('div');
    row.className = 'doc_context_row' + (origin === 'automatic' ? ' doc_context_row_automatic' : '');
    row.setAttribute('data-context-name', name);
    row.setAttribute('data-context-origin', origin);

    var label = document.createElement('label');
    label.textContent = (definition.label || name) + (definition.required ? ' *' : '') + (origin === 'automatic' ? ' — automatique' : '');
    var input = document.createElement('input');
    input.type = 'text';
    input.setAttribute('data-context-value', '1');
    input.placeholder = doc_context_placeholder(definition);
    input.value = value;
    input.addEventListener('input', function () {
        row.setAttribute('data-context-origin', 'explicit');
        row.classList.remove('doc_context_row_automatic');
        label.textContent = (definition.label || name) + (definition.required ? ' *' : '');
        doc_context_serialize();
        doc_context_refresh_buttons();
        doc_context_schedule_autofill();
    });
    var remove = document.createElement('input');
    remove.type = 'button';
    remove.value = '×';
    remove.addEventListener('click', function () { doc_context_remove(row); });

    row.appendChild(label);
    row.appendChild(input);
    row.appendChild(remove);
    list.appendChild(row);
    doc_context_serialize();
    doc_context_refresh_buttons();
    if (focus) input.focus();
    return row;
}

function doc_context_refresh_buttons()
{
    var buttons = document.getElementById('doc_context_buttons');
    var status = document.getElementById('doc_context_status');
    if (!buttons || !status)
        return;
    buttons.innerHTML = '';

    var selected = doc_context_selected_items();
    if (selected.length == 0)
    {
        status.textContent = 'Sélectionnez un ou plusieurs documents.';
        return;
    }

    var schema = doc_context_schema();
    var names = Object.keys(schema);
    if (!names.length)
    {
        status.textContent = 'Ce modèle ne déclare aucun contexte composable.';
        return;
    }

    var bindings = doc_context_bindings();
    var missing = [];
    names.forEach(function (name) {
        var definition = schema[name];
        var row = doc_context_find_row(name);
        var explicit = !!bindings[name];
        var automatic = explicit && row && row.getAttribute('data-context-origin') === 'automatic';
        if (definition.required && !explicit)
            missing.push(definition.label || name);

        var button = document.createElement('input');
        button.type = 'button';
        if (explicit)
        {
            button.value = '✓ ' + (definition.label || name) + (automatic ? ' (automatique)' : '');
            button.disabled = true;
        }
        else
        {
            button.value = '+ ' + (definition.label || name) + (definition.required ? ' *' : '');
            button.addEventListener('click', function () { doc_context_add(name); });
        }
        buttons.appendChild(button);
    });

    var conflicts = names.filter(function (name) { return !!schema[name].conflict; });
    if (conflicts.length)
        status.textContent = 'Les documents sélectionnés utilisent différemment le contexte : ' + conflicts.join(', ') + '. Sélectionnez-les séparément.';
    else
        status.textContent = missing.length
            ? 'Contexte requis restant : ' + missing.join(', ')
            : 'Le contexte requis est complet. Les valeurs trouvées automatiquement sont affichées ci-dessus.';
}

function doc_context_missing_required()
{
    var schema = doc_context_schema();
    var bindings = doc_context_bindings();
    return Object.keys(schema).filter(function (name) {
        return !!schema[name].conflict || (schema[name].required && !bindings[name]);
    });
}

function doc_context_refresh_model()
{
    var selected = doc_context_selected_items();
    var reference = selected.map(function (item) { return item.getAttribute('data-reference') || ''; }).sort().join('|');
    if (reference !== docContextActiveReference)
    {
        docContextActiveReference = reference;
        var schema = doc_context_schema();
        document.querySelectorAll('#doc_context_list [data-context-name]').forEach(function (row) {
            if (!schema[row.getAttribute('data-context-name') || ''])
                row.remove();
        });
        doc_context_serialize();
    }
    doc_context_refresh_buttons();
    doc_context_schedule_autofill();
}

function doc_generation_clean_error(message)
{
    message = (message || '').replace(/<br\s*\/?\s*>/gi, "\n");
    var tmp = document.createElement('div');
    tmp.innerHTML = message;
    var hidden = tmp.querySelectorAll('[style*="display: none"]');
    for (var i = 0; i < hidden.length; ++i)
        hidden[i].remove();
    return ((tmp.textContent || tmp.innerText || message).trim());
}

function doc_generation_set_error(message)
{
    var box = document.getElementById('doc_generation_error');
    if (!box)
        return ;
    message = doc_generation_clean_error(message);
    box.textContent = message;
    box.style.display = message == '' ? 'none' : 'block';
}

function doc_generation_filename(disposition)
{
    var match;

    if (!disposition)
        return ('generated.pdf');
    match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (match)
        return (decodeURIComponent(match[1].replace(/["']/g, '')));
    match = disposition.match(/filename="?([^";]+)"?/i);
    if (match)
        return (match[1]);
    return ('generated.pdf');
}

function doc_generation_download(blob, filename)
{
    var url = window.URL.createObjectURL(blob);
    var link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    setTimeout(function() {
        window.URL.revokeObjectURL(url);
        link.remove();
    }, 1000);
}

function doc_generation_display_json_error(blob, status_text)
{
    var reader = new FileReader();

    reader.onload = function() {
        var text = reader.result || '';
        var json = null;
        try
        {
            json = JSON.parse(text);
        }
        catch (e)
        {
            doc_generation_set_error(text != '' ? text : status_text);
            return ;
        }
        if (json && json.result == 'DEBUG')
            doc_generation_set_error('La génération du document a échoué: une trace de debug a interrompu la requête.');
        else
            doc_generation_set_error(json && json.msg ? json.msg : status_text);
    };
    reader.readAsText(blob);
}

function doc_completion_nonce()
{
    if (window.crypto && window.crypto.getRandomValues)
    {
        var bytes = new Uint8Array(8);
        window.crypto.getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function(v) {
            return v.toString(16).padStart(2, '0');
        }).join('');
    }
    return Date.now().toString(16).padStart(16, '0').slice(-16);
}

function doc_complete_selected(form)
{
    doc_context_serialize();
    var selected = Array.prototype.slice.call(form.querySelectorAll('.doc_document_choice.selected'));
    if (selected.length != 1)
    {
        doc_generation_set_error(selected.length == 0
            ? 'Sélectionnez un document à compléter.'
            : 'La complétion champ par champ nécessite de sélectionner un seul document.');
        return false;
    }

    var missing = doc_context_missing_required();
    if (missing.length)
    {
        var schema = doc_context_schema();
        doc_generation_set_error('Définissez d’abord le contexte requis : ' + missing.map(function (name) { return schema[name].label || name; }).join(', '));
        return false;
    }

    var item = selected[0];
    var reference = item.getAttribute('data-reference') || '';
    var formFile = item.getAttribute('data-form-file') || '';
    var formHash = item.getAttribute('data-form-hash') || '';
    if (!formFile || !formHash)
    {
        doc_generation_set_error('Ce document ne peut pas être ouvert dans le formulaire de complétion.');
        return false;
    }

    var output = 'document-page:' + formHash + ':' + doc_completion_nonce();
    form.querySelector('[name="form_output"]').value = output;
    var url = 'index.php?p=DabsicFormMenu' +
        '&file=' + encodeURIComponent(formFile) +
        '&output=' + encodeURIComponent(output) +
        '&mode=docbuilder' +
        '&form_role=Etablissement' +
        '&context_bindings=' + encodeURIComponent(document.getElementById('doc_context_bindings').value || '{}');
    window.open(url, '_blank', 'noopener');
    doc_generation_set_error('');
    return false;
}

function doc_generate_submit(form, blank, trigger)
{
    var button = trigger || form.querySelector('.documents_generate_button');
    var old_value = button ? button.value : '';
    var xhr = new XMLHttpRequest();

    doc_context_serialize();
    var selected = doc_context_selected_items();
    if (selected.length == 0)
    {
        doc_generation_set_error('Sélectionnez au moins un document.');
        return false;
    }
    if (!blank)
    {
        var missing = doc_context_missing_required();
        if (missing.length)
        {
            var schema = doc_context_schema();
            doc_generation_set_error('Définissez d’abord le contexte requis : ' + missing.map(function (name) { return schema[name].label || name; }).join(', '));
            return false;
        }
    }
    var completion_input = form.querySelector('[name="form_output"]');
    var saved_completion = completion_input ? completion_input.value : '';
    if (blank && completion_input)
        completion_input.value = '';
    doc_generation_set_error('');
    if (button)
    {
        button.disabled = true;
        button.value = 'Génération...';
    }
    xhr.open((form.getAttribute('method') || 'POST').toUpperCase(), form.getAttribute('action'), true);
    xhr.responseType = 'blob';
    xhr.onload = function() {
        var content_type = xhr.getResponseHeader('Content-Type') || '';
        var disposition = xhr.getResponseHeader('Content-Disposition') || '';
        var is_download = disposition.indexOf('attachment') != -1 ||
            content_type.indexOf('application/octet-stream') != -1 ||
            content_type.indexOf('application/pdf') != -1;

        if (button)
        {
            button.disabled = false;
            button.value = old_value;
        }
        if (xhr.status >= 200 && xhr.status < 300 && is_download)
        {
            if (!xhr.response || xhr.response.size == 0)
            {
                doc_generation_set_error('Le document généré est vide.');
                return ;
            }
            doc_generation_download(xhr.response, doc_generation_filename(disposition));
            return ;
        }
        doc_generation_display_json_error(xhr.response, xhr.statusText || 'La génération du document a échoué.');
    };
    xhr.onerror = function() {
        if (button)
        {
            button.disabled = false;
            button.value = old_value;
        }
        doc_generation_set_error('La génération du document a échoué: impossible de contacter le serveur.');
    };
    var form_data = new FormData(form);
    if (blank)
        form_data.set('blank_document', '1');
    xhr.send(form_data);
    if (blank && completion_input)
        completion_input.value = saved_completion;
    return (false);
}

function document_workflow_remind(form)
{
    return silent_submitf(form, {
        after_success: function () { window.location.reload(); }
    });
}

function document_workflow_expire(form)
{
    if (!window.confirm(<?=json_encode($Dictionnary["DocumentExpireConfirm"] ?? "Faire périmer cette demande de document ?", JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>))
        return (false);
    return (silent_submit(form, null, null, null, null, null, "", false, false, function () {
        var row = form.closest ? form.closest('tr') : null;
        if (row)
            row.remove();
    }));
}

function document_print_complete(form)
{
    if (!window.confirm('Marquer ce document comme imprimé / traité ?'))
        return false;
    return silent_submit(form, null, null, null, null, null, '', false, false, function () {
        var row = form.closest ? form.closest('tr') : null;
        if (row)
            row.remove();
        var table = form.closest ? form.closest('table') : null;
        if (table && table.querySelectorAll('tbody tr').length == 0)
            window.location.reload();
    });
}

window.addEventListener('input', function(ev) {
    if (ev.target.closest && ev.target.closest('#doc_context_list'))
        doc_context_serialize();
});
</script>

<?php
$documents_panel = [
    "Génération" => __DIR__."/generation_state.phtml",
];
$documents_panel_data = [[]];
$print_tasks = document_print_visible_tasks();
$print_tab_label = "À imprimer (".count($print_tasks).")";
if (document_print_can_access_page())
{
    $documents_panel[$print_tab_label] = __DIR__."/print_state.phtml";
    $documents_panel_data[] = ["print_tasks" => $print_tasks];
}
if (document_workflow_can_monitor())
{
    $workflow_instances = document_workflow_visible_instances();
    $workflow_pending_forms = document_workflow_visible_pending_document_forms();
    $workflow_finalize_forms = document_workflow_visible_completed_document_forms();
    $workflow_expired_forms = document_workflow_visible_expired_document_forms();
    $documents_panel += [
        "En attente de complétion" => __DIR__."/completion_state.phtml",
        "À compléter / finaliser" => __DIR__."/finalize_state.phtml",
        "En attente de signature" => __DIR__."/workflow_state.phtml",
        "Signés" => __DIR__."/workflow_state.phtml",
        "Scellés" => __DIR__."/workflow_state.phtml",
        "Erreurs" => __DIR__."/workflow_state.phtml",
        "Terminés" => __DIR__."/workflow_state.phtml",
        "Périmés" => __DIR__."/expired_state.phtml",
    ];
    $documents_panel_data = array_merge($documents_panel_data, [
        ["forms" => $workflow_pending_forms],
        ["forms" => $workflow_finalize_forms],
        ["status" => "AwaitingSignature", "instances" => $workflow_instances],
        ["status" => "Signed", "instances" => $workflow_instances],
        ["status" => "Sealed", "instances" => $workflow_instances],
        ["status" => "Error", "instances" => $workflow_instances],
        ["status" => "Completed", "instances" => $workflow_instances],
        ["forms" => $workflow_expired_forms, "instances" => $workflow_instances],
    ]);
}
?>
<div class="documents_workflow_panel">
    <?php
    $documents_default_tab = (am_i_accountant() || am_i_librarian())
        ? $print_tab_label : "Génération";
    tabpanel($documents_panel, "documents-main", $documents_default_tab, "", "", $documents_panel_data);
    ?>
</div>
</div>
