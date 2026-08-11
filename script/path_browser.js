function path_browser_get_browser(element)
{
    if (!element)
        return (null);
    if (element.classList && element.classList.contains("file_browser"))
        return (element);
    if (element.closest)
        return (element.closest(".file_browser"));
    return (null);
}

function path_browser_get_path_input(element)
{
    let browser = path_browser_get_browser(element);
    let input = null;

    if (browser && browser.id)
        input = document.getElementById("path" + browser.id);
    if (input)
        return (input);

    if (browser)
    {
        let previous = browser.previousElementSibling;

        while (previous)
        {
            if (previous.tagName && previous.tagName.toLowerCase() == "form")
            {
                input = previous.querySelector("input.path_browser[name='path']");
                if (input)
                    return (input);
            }
            previous = previous.previousElementSibling;
        }
    }

    let container = element ? element.parentElement : null;
    while (container)
    {
        input = container.querySelector("input.path_browser[name='path']");
        if (input)
            return (input);
        container = container.parentElement;
    }

    return (null);
}

function path_browser_normalize_path(path)
{
    if (!path)
        return ("/");
    return (path);
}

function path_browser_cd(element, path)
{
    let browser = path_browser_get_browser(element);
    let input = path_browser_get_path_input(element);

    if (!browser || !input)
    {
        console.log("Invalid path browser.");
        console.trace();
        return (false);
    }

    input.value = path_browser_normalize_path(path);
    return (silent_submit(input, browser.id));
}

function path_browser_fill_form_path(element)
{
    let input = path_browser_get_path_input(element);
    let form = element && element.closest ? element.closest("form") : null;
    let target = null;

    if (!input || !form)
        return (false);
    target = form.querySelector("input[name='path']");
    if (!target)
        return (false);
    target.value = input.value;
    return (true);
}

function path_browser_sync_upload_path(element)
{
    return (path_browser_fill_form_path(element));
}

function path_browser_file_accepts(form, file)
{
    let input = form.querySelector('input[type="file"][name="file"]');
    let accept;

    if (!input)
        return (false);
    accept = (input.getAttribute('accept') || '').trim();
    if (accept == '')
        return (true);
    for (let rule of accept.split(','))
    {
        rule = rule.trim().toLowerCase();
        if (rule == '')
            continue ;
        if (rule[0] == '.' && file.name.toLowerCase().endsWith(rule))
            return (true);
        if (rule.endsWith('/*') && file.type.toLowerCase().startsWith(rule.substring(0, rule.length - 1)))
            return (true);
        if (file.type.toLowerCase() == rule)
            return (true);
    }
    return (false);
}

function path_browser_upload_forms(browser)
{
    if (!browser || !browser.id)
        return ([]);
    let marker = browser.id;
    let container = browser.parentElement;

    while (container && container !== document.body)
    {
        let found = [];
        for (let form of container.querySelectorAll('form'))
        {
            let file = form.querySelector('input[type="file"][name="file"]');
            if (!file)
                continue ;
            let source = (form.getAttribute('onsubmit') || '') + ' ' + (file.getAttribute('onchange') || '');
            if (source.indexOf(marker) != -1 || form.getAttribute('data-path-browser-target') == marker)
                found.push(form);
        }
        if (found.length)
            return (found);
        container = container.parentElement;
    }
    return ([]);
}

function path_browser_form_plain_data(form)
{
    let data = {};

    for (let field of form.elements)
    {
        if (!field.name || field.type == 'file' || field.disabled)
            continue ;
        if (field.type == 'checkbox')
            data[field.name] = field.checked ? 1 : 0;
        else if (field.type == 'radio')
        {
            if (field.checked)
                data[field.name] = field.value;
        }
        else
            data[field.name] = field.value;
    }
    return (data);
}

function path_browser_file_base64(file)
{
    return (new Promise(function(resolve, reject) {
        let reader = new FileReader();
        reader.onload = function() {
            let value = String(reader.result || '');
            let comma = value.indexOf(',');
            resolve({name: file.name, content: comma == -1 ? value : value.substring(comma + 1)});
        };
        reader.onerror = function() { reject(reader.error); };
        reader.readAsDataURL(file);
    }));
}

function path_browser_send_dropped_files(form, files, browser, target_path)
{
    return (Promise.all(files.map(path_browser_file_base64)).then(function(encoded) {
        return (new Promise(function(resolve) {
            let data = path_browser_form_plain_data(form);
            let method = (form.getAttribute('method') || 'POST').toUpperCase();
            data.file = encoded;
            if (Object.prototype.hasOwnProperty.call(data, 'path') || target_path !== null)
                data.path = target_path || '/';
            form.style.backgroundColor = 'yellow';
            send_ajax(
                method,
                form.getAttribute('action'),
                JSON.stringify(data),
                browser.id,
                null,
                null,
                null,
                function(success) {
                    form.style.backgroundColor = success ? 'green' : 'red';
                    window.setTimeout(function() { form.style.backgroundColor = ''; }, 900);
                    resolve(success);
                },
                function() {
                    form.style.backgroundColor = 'red';
                    window.setTimeout(function() { form.style.backgroundColor = ''; }, 900);
                    resolve(false);
                }
            );
        }));
    }));
}

async function path_browser_upload_drop(browser, files, target_path)
{
    let forms = path_browser_upload_forms(browser);
    if (!forms.length)
    {
        if (typeof set_error_div == 'function')
            set_error_div("L'envoi de fichiers n'est pas disponible dans ce navigateur.", false);
        return (false);
    }

    let groups = new Map;
    let refused = [];
    for (let file of files)
    {
        let form = forms.find(function(candidate) { return path_browser_file_accepts(candidate, file); });
        if (!form)
        {
            refused.push(file.name);
            continue ;
        }
        if (!groups.has(form))
            groups.set(form, []);
        groups.get(form).push(file);
    }
    if (refused.length && typeof set_error_div == 'function')
        set_error_div('Format non accepté ici : ' + refused.join(', '), false);

    for (let pair of groups)
    {
        let form = pair[0];
        let group = pair[1];
        let input = form.querySelector('input[type="file"][name="file"]');
        if (input && !input.multiple && group.length > 1)
        {
            for (let file of group)
                await path_browser_send_dropped_files(form, [file], browser, target_path);
        }
        else
            await path_browser_send_dropped_files(form, group, browser, target_path);
    }
    return (groups.size > 0);
}

function path_browser_selection(browser)
{
    if (!browser)
        return ([]);
    return (Array.from(browser.querySelectorAll(':scope > .icon.path_browser_selected')));
}

function path_browser_select_icon(icon, additive)
{
    let browser = path_browser_get_browser(icon);
    if (!browser)
        return ;
    if (!additive)
    {
        for (let other of path_browser_selection(browser))
            if (other !== icon)
                other.classList.remove('path_browser_selected');
        icon.classList.add('path_browser_selected');
    }
    else
        icon.classList.toggle('path_browser_selected');
}

function path_browser_base64url(text)
{
    let bytes = new TextEncoder().encode(text);
    let binary = '';
    for (let byte of bytes)
        binary += String.fromCharCode(byte);
    return (btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, ''));
}

function path_browser_export_url(browser, selection)
{
    let params = new URLSearchParams;
    params.set('page', browser.getAttribute('data-path-browser-page') || '');
    params.set('id', browser.getAttribute('data-path-browser-id') || '-1');
    params.set('type', browser.getAttribute('data-path-browser-type') || '');
    params.set('language', browser.getAttribute('data-path-browser-language') || '');
    params.set('selection', path_browser_base64url(JSON.stringify(selection)));
    return (new URL('/api/filebrowser/0/export?' + params.toString(), window.location.href).href);
}

function path_browser_export_filename(icons)
{
    if (icons.length != 1)
        return ('infosphere-selection.zip');
    let name = icons[0].getAttribute('data-path-browser-name') || 'document';
    if (icons[0].getAttribute('data-path-browser-directory') == '1')
        name += '.zip';
    return (name.replace(/:/g, '_'));
}

function path_browser_prepare_external_drag(event, icon)
{
    let browser = path_browser_get_browser(icon);
    if (!browser || !event.dataTransfer)
        return ;
    if (!icon.classList.contains('path_browser_selected'))
        path_browser_select_icon(icon, false);
    let icons = path_browser_selection(browser);
    let selection = icons.map(function(item) {
        return (item.getAttribute('data-path-browser-relative') || '');
    }).filter(function(item) { return item != ''; });
    if (!selection.length)
        return ;

    let url = path_browser_export_url(browser, selection);
    let filename = path_browser_export_filename(icons);
    let mime = icons.length == 1 && icons[0].getAttribute('data-path-browser-directory') != '1'
        ? 'application/octet-stream'
        : 'application/zip';
    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('application/x-infosphere-path-browser', '1');
    event.dataTransfer.setData('text/uri-list', url);
    event.dataTransfer.setData('DownloadURL', mime + ':' + filename + ':' + url);
}

function path_browser_drop_path(browser, target)
{
    let directory = target && target.closest ? target.closest('.file_browser > .icon.directory') : null;
    if (directory && directory.closest('.file_browser') === browser)
    {
        let path = directory.getAttribute('data-path-browser-drop-path');
        if (path)
            return (path);
    }
    let input = path_browser_get_path_input(browser);
    return (input ? input.value : '/');
}


function path_browser_drag_has_files(dataTransfer)
{
    if (!dataTransfer)
        return (false);
    if (dataTransfer.types && Array.from(dataTransfer.types).indexOf('Files') != -1)
        return (true);
    if (dataTransfer.items)
        for (let item of dataTransfer.items)
            if (item.kind == 'file')
                return (true);
    return (!!dataTransfer.files && dataTransfer.files.length > 0);
}

function path_browser_clear_drop_state(browser)
{
    if (!browser)
        return ;
    browser.classList.remove('path_browser_drop_target');
    for (let item of browser.querySelectorAll('.path_browser_directory_drop_target'))
        item.classList.remove('path_browser_directory_drop_target');
}

document.addEventListener('click', function(event) {
    let icon = event.target && event.target.closest ? event.target.closest('.file_browser > .icon') : null;
    if (!icon || icon.classList.contains('path_browser_navigation'))
        return ;
    if (event.target.closest('form, button, input, select, textarea, a, audio, video'))
        return ;
    path_browser_select_icon(icon, event.shiftKey);
});

document.addEventListener('dragstart', function(event) {
    let icon = event.target && event.target.closest ? event.target.closest('.file_browser > .icon') : null;
    if (icon && !icon.classList.contains('path_browser_navigation'))
        path_browser_prepare_external_drag(event, icon);
});

document.addEventListener('dragover', function(event) {
    let browser = event.target && event.target.closest ? event.target.closest('.file_browser') : null;
    if (!browser || !event.dataTransfer)
        return ;
    if (event.dataTransfer.types && Array.from(event.dataTransfer.types).indexOf('application/x-infosphere-path-browser') != -1)
        return ;
    if (!path_browser_drag_has_files(event.dataTransfer))
        return ;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
    path_browser_clear_drop_state(browser);
    let directory = event.target.closest('.file_browser > .icon.directory');
    if (directory && directory.closest('.file_browser') === browser)
        directory.classList.add('path_browser_directory_drop_target');
    else
        browser.classList.add('path_browser_drop_target');
});

document.addEventListener('dragleave', function(event) {
    let browser = event.target && event.target.closest ? event.target.closest('.file_browser') : null;
    if (!browser)
        return ;
    let next = event.relatedTarget;
    if (!next || !browser.contains(next))
        path_browser_clear_drop_state(browser);
});

document.addEventListener('drop', function(event) {
    let browser = event.target && event.target.closest ? event.target.closest('.file_browser') : null;
    if (!browser || !event.dataTransfer)
        return ;
    if (event.dataTransfer.types && Array.from(event.dataTransfer.types).indexOf('application/x-infosphere-path-browser') != -1)
    {
        path_browser_clear_drop_state(browser);
        return ;
    }
    if (!path_browser_drag_has_files(event.dataTransfer) || !event.dataTransfer.files || event.dataTransfer.files.length == 0)
        return ;
    event.preventDefault();
    let files = Array.from(event.dataTransfer.files);
    let path = path_browser_drop_path(browser, event.target);
    path_browser_clear_drop_state(browser);
    path_browser_upload_drop(browser, files, path);
});
