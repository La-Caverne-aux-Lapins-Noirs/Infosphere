<?php
require_once ("get_docs.php");
require_once (__DIR__."/../../tools/document_workflow.php");
?>
<div class="documents_page">
<h2 class="alignable_blocks"><?=$Dictionnary["Documents"]; ?></h2>

<script>
function doc_toggle(id, elem)
{
    var input = document.getElementById(id);
    input.value = input.value == '0' ? '1' : '0';
    elem.classList.toggle('selected');
    var completion = document.querySelector('.documents_generate_panel [name="form_output"]');
    if (completion)
        completion.value = '';
}

function doc_chain_add(type, prefix_value, signatory_value)
{
    var list = document.getElementById('doc_chain_list');
    var row = document.createElement('div');
    row.className = 'doc_chain_row';
    row.innerHTML =
        '<select class="doc_chain_type" onchange="doc_chain_refresh(this.parentNode);">' +
        '<option value="student">Élève</option>' +
        '<option value="teacher">Enseignant</option>' +
        '<option value="director">Directeur</option>' +
        '<option value="commercial">Commercial</option>' +
        '<option value="librarian">Bibliothécaire</option>' +
        '<option value="secretariat">Secrétariat</option>' +
        '<option value="school">École</option>' +
        '<option value="organization">Entreprise / organisation</option>' +
        '<option value="parent">Parent</option>' +
        '<option value="tutor">Tuteur entreprise</option>' +
        '<option value="jury">Jury</option>' +
        '<option value="title_session">Session de titre</option>' +
        '<option value="user">Utilisateur exact</option>' +
        '<option value="field">Champ libre</option>' +
        '</select>' +
        '<input class="doc_chain_prefix" type="text" placeholder="Préfixe Dabsic" />' +
        '<input class="doc_chain_id" type="text" placeholder="id ou codename" />' +
        '<input class="doc_chain_key" type="text" placeholder="Champ" style="display:none;" />' +
        '<input class="doc_chain_value" type="text" placeholder="Valeur" style="display:none;" />' +
        '<input class="doc_chain_signatory" type="text" placeholder="Rôle de signature (optionnel)" />' +
        '<input type="button" value="↑" onclick="doc_chain_move(this.parentNode, -1);" />' +
        '<input type="button" value="↓" onclick="doc_chain_move(this.parentNode, 1);" />' +
        '<input type="button" value="×" onclick="this.parentNode.remove(); doc_chain_serialize();" />';
    list.appendChild(row);
    row.querySelector('.doc_chain_type').value = type || 'user';
    row.querySelector('.doc_chain_prefix').value = prefix_value || '';
    row.querySelector('.doc_chain_signatory').value = signatory_value || '';
    doc_chain_refresh(row);
}

function doc_chain_refresh(row)
{
    var type = row.querySelector('.doc_chain_type').value;
    var prefix = row.querySelector('.doc_chain_prefix');
    var id = row.querySelector('.doc_chain_id');
    var key = row.querySelector('.doc_chain_key');
    var value = row.querySelector('.doc_chain_value');
    var signatory = row.querySelector('.doc_chain_signatory');
    key.style.display = type == 'field' ? '' : 'none';
    value.style.display = type == 'field' ? '' : 'none';
    prefix.style.display = type == 'field' ? 'none' : '';
    id.style.display = type == 'field' ? 'none' : '';
    signatory.style.display = type == 'field' ? 'none' : '';
    if (['teacher', 'director', 'commercial', 'librarian', 'secretariat'].indexOf(type) != -1)
        id.placeholder = 'école, vide = école précédente';
    else if (type == 'parent')
        id.placeholder = 'enfant, vide = élève/utilisateur précédent';
    else if (type == 'tutor')
        id.placeholder = 'entreprise, vide = entreprise précédente';
    else if (type == 'school')
        id.placeholder = 'école, vide = école précédente';
    else if (type == 'organization')
        id.placeholder = 'entreprise / organisation';
    else if (type == 'jury')
        id.placeholder = 'jury: id ou codename';
    else if (type == 'title_session')
        id.placeholder = 'id de la session de titre';
    else
        id.placeholder = 'id ou codename';
    doc_chain_serialize();
}

function doc_chain_move(row, delta)
{
    var parent = row.parentNode;
    if (delta < 0 && row.previousElementSibling)
        parent.insertBefore(row, row.previousElementSibling);
    else if (delta > 0 && row.nextElementSibling)
        parent.insertBefore(row.nextElementSibling, row);
    doc_chain_serialize();
}

function doc_chain_serialize()
{
    var rows = document.getElementsByClassName('doc_chain_row');
    var chain = [];
    for (var i = 0; i < rows.length; ++i)
    {
        var row = rows[i];
        var type = row.querySelector('.doc_chain_type').value;
        if (type == 'field')
        {
            var key = row.querySelector('.doc_chain_key').value.trim();
            if (key != '')
                chain.push({type: type, key: key, value: row.querySelector('.doc_chain_value').value});
        }
        else
        {
            var entry = {
                type: type,
                prefix: row.querySelector('.doc_chain_prefix').value.trim(),
                id: row.querySelector('.doc_chain_id').value.trim()
            };
            var signatory = row.querySelector('.doc_chain_signatory').value.trim();
            if (signatory != '')
                entry.signatory = signatory;
            chain.push(entry);
        }
    }
    document.getElementById('doc_chain').value = JSON.stringify(chain);
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
    doc_chain_serialize();
    var selected = Array.prototype.slice.call(form.querySelectorAll('.doc_document_choice.selected'));
    if (selected.length != 1)
    {
        doc_generation_set_error(selected.length == 0
            ? 'Sélectionnez un document à compléter.'
            : 'La complétion champ par champ nécessite de sélectionner un seul document.');
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
        '&chain=' + encodeURIComponent(document.getElementById('doc_chain').value || '[]');
    window.open(url, '_blank', 'noopener');
    doc_generation_set_error('');
    return false;
}

function doc_generate_submit(form, blank, trigger)
{
    var button = trigger || form.querySelector('.documents_generate_button');
    var old_value = button ? button.value : '';
    var xhr = new XMLHttpRequest();

    doc_chain_serialize();
    var chain_input = document.getElementById('doc_chain');
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

window.addEventListener('input', function(ev) {
    if (ev.target.closest && ev.target.closest('#doc_chain_list'))
        doc_chain_serialize();
});
</script>

<?php
$documents_panel = [
    "Génération" => __DIR__."/generation_state.phtml",
];
$documents_panel_data = [[]];
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
    <?php tabpanel($documents_panel, "documents-main", "Génération", "", "", $documents_panel_data); ?>
</div>
</div>
