<?php
require_once ("get_docs.php");
require_once (__DIR__."/../../tools/document_workflow.php");
?>
<h2 class="alignable_blocks"><?=$Dictionnary["Documents"]; ?></h2>

<script>
function doc_toggle(id, elem)
{
    var input = document.getElementById(id);
    input.value = input.value == '0' ? '1' : '0';
    elem.classList.toggle('selected');
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

function doc_generate_submit(form)
{
    var button = form.querySelector('.documents_generate_button');
    var old_value = button ? button.value : '';
    var xhr = new XMLHttpRequest();

    doc_chain_serialize();
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
    xhr.send(new FormData(form));
    return (false);
}

window.addEventListener('input', function(ev) {
    if (ev.target.closest && ev.target.closest('#doc_chain_list'))
        doc_chain_serialize();
});
</script>

<div class="documents_page_layout">
    <div class="documents_upload_panel">
	<?php $js = "silent_submit(this, 'file_browser');"; ?>
	<form
	    method="post"
	    onsubmit="return <?=$js; ?>;"
	    action="/api/doc"
	>
	    <label for="file"><?=$Dictionnary["File"]; ?></label><br />
	    <input id="path2" type="hidden" name="path" value="" />
	    <input type="hidden" name="show_hidden_entries" value="1" />
            <input type="hidden" name="path_browser_dabsic_editor" value="<?=is_admin() ? 1 : 0; ?>" />
	    <input
		type="file"
		name="file"
		multiple="true"
		onchange="document.getElementById('path2').value = document.getElementById('pathfile_browser').value; <?=$js; ?>"
	    />
	</form>

	<?php
	$language = "";
	$type = "file";
	$page = "doc";
	$id = 0;
	$path = "";
	$target = $Configuration->DocDir();
	$show_hidden_entries = true;
        $path_browser_dabsic_editor = is_admin();
	require ("./tools/template/path_browser.phtml");
	?>
    </div>

    <form method="post" action="/api/doc/0/generate" class="documents_generate_panel" onsubmit="return doc_generate_submit(this);">
	<div class="documents_generate_toolbar">
	    <input type="submit" value="<?=$Dictionnary["GenerateDocument"]; ?>" class="documents_generate_button" />
	</div>
	<div id="doc_generation_error" class="doc_generation_error" style="display: none;"></div>
	<input type="hidden" id="doc_chain" name="chain" value="[]" />

	<div class="doc_chain_box">
	    <div style="font-weight: bold;">Paramètres Dabsic composés</div>
	    <div id="doc_chain_list"></div>
	    <div class="doc_chain_buttons">
		<input type="button" value="+ Élève" onclick="doc_chain_add('student', 'Student');" />
		<input type="button" value="+ Enseignant" onclick="doc_chain_add('teacher', 'Teacher');" />
		<input type="button" value="+ Directeur" onclick="doc_chain_add('director', 'SignatureSources.Director', 'Director');" />
		<input type="button" value="+ Commercial" onclick="doc_chain_add('commercial', 'Commercial');" />
		<input type="button" value="+ Bibliothécaire" onclick="doc_chain_add('librarian', 'Librarian');" />
		<input type="button" value="+ Secrétariat" onclick="doc_chain_add('secretariat', 'Secretariat');" />
		<input type="button" value="+ École" onclick="doc_chain_add('school', 'School');" />
		<input type="button" value="+ Entreprise" onclick="doc_chain_add('organization', 'Company');" />
		<input type="button" value="+ Parent" onclick="doc_chain_add('parent', 'Parent');" />
		<input type="button" value="+ Tuteur" onclick="doc_chain_add('tutor', 'Company.Tutor');" />
		<input type="button" value="+ Jury" onclick="doc_chain_add('jury', 'Jury');" />
		<input type="button" value="+ Destinataire" onclick="doc_chain_add('user', 'Destination');" />
		<input type="button" value="+ Champ" onclick="doc_chain_add('field');" />
	    </div>
	</div>

	<div class="doclist">
	    <?php foreach (get_doc_sources() as $source => $docs) { ?>
		<div class="doc_source_title"><?=htmlentities($docs["label"]); ?></div>
		<?php foreach ($docs["documents"] as $doc) { ?>
		    <?php
		    $ref = document_reference_from_source_path($source, $doc);
		    $hash = md5($ref);
		    ?>
		    <input type="hidden" id="doc_<?=$hash; ?>" name="doc_<?=$hash; ?>" value="0" />
		    <input type="hidden" name="docref_<?=$hash; ?>" value="<?=htmlentities($ref); ?>" />
		    <div onclick="doc_toggle('doc_<?=$hash; ?>', this);" title="<?=htmlentities($ref); ?>">
			<?=htmlentities($doc); ?>
		    </div>
		<?php } ?>
	    <?php } ?>
	</div>
    </form>
</div>

<?php if (document_workflow_can_monitor()) {
    $workflow_instances = document_workflow_visible_instances();
    $workflow_panel = [
        "En attente de signature" => __DIR__."/workflow_state.phtml",
        "Signés" => __DIR__."/workflow_state.phtml",
        "Scellés" => __DIR__."/workflow_state.phtml",
        "Erreurs" => __DIR__."/workflow_state.phtml",
        "Terminés" => __DIR__."/workflow_state.phtml",
    ];
    $workflow_panel_data = [
        ["status" => "AwaitingSignature", "instances" => $workflow_instances],
        ["status" => "Signed", "instances" => $workflow_instances],
        ["status" => "Sealed", "instances" => $workflow_instances],
        ["status" => "Error", "instances" => $workflow_instances],
        ["status" => "Completed", "instances" => $workflow_instances],
    ];
?>
<div class="documents_workflow_panel">
    <h3>Suivi des documents</h3>
    <?php tabpanel($workflow_panel, "documents-workflow", "En attente de signature", "", "", $workflow_panel_data); ?>
</div>
<?php } ?>
