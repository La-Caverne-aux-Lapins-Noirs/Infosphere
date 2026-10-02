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

function path_browser_reset_entry_menu_position(menu)
{
    let popup = menu ? menu.querySelector('.path_browser_entry_menu_popup') : null;
    if (!popup)
        return ;
    popup.style.position = '';
    popup.style.left = '';
    popup.style.top = '';
    popup.style.right = '';
    popup.style.zIndex = '';
}

function path_browser_close_entry_menus(except)
{
    for (let menu of document.querySelectorAll('.path_browser_entry_menu.path_browser_menu_open'))
    {
        if (menu === except)
            continue ;
        menu.classList.remove('path_browser_menu_open');
        path_browser_reset_entry_menu_position(menu);
        let icon = menu.closest('.file_browser > .icon');
        if (icon)
            icon.classList.remove('path_browser_menu_host_open');
        let button = menu.querySelector('.path_browser_entry_menu_button');
        if (button)
            button.setAttribute('aria-expanded', 'false');
    }
}

function path_browser_toggle_entry_menu(event, button)
{
    if (event)
    {
        event.preventDefault();
        event.stopPropagation();
    }
    let menu = button && button.closest ? button.closest('.path_browser_entry_menu') : null;
    if (!menu)
        return (false);
    let open = !menu.classList.contains('path_browser_menu_open');
    path_browser_close_entry_menus(open ? menu : null);
    path_browser_reset_entry_menu_position(menu);
    menu.classList.toggle('path_browser_menu_open', open);
    let icon = menu.closest('.file_browser > .icon');
    if (icon)
        icon.classList.toggle('path_browser_menu_host_open', open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    return (false);
}

function path_browser_rename_entry(event, button)
{
    if (event)
    {
        event.preventDefault();
        event.stopPropagation();
    }
    let icon = button && button.closest ? button.closest('.file_browser > .icon') : null;
    let browser = path_browser_get_browser(icon);
    if (!icon || !browser || icon.classList.contains('path_browser_navigation'))
        return (false);

    let old_name = icon.getAttribute('data-path-browser-name') || '';
    let new_name = window.prompt(browser.getAttribute('data-path-browser-rename-prompt') || 'Nouveau nom :', old_name);
    if (new_name === null)
        return (false);
    new_name = new_name.trim();
    if (new_name === '' || new_name === old_name)
        return (false);

    let data = {
        page: browser.getAttribute('data-path-browser-page') || '',
        id: browser.getAttribute('data-path-browser-id') || '-1',
        type: browser.getAttribute('data-path-browser-type') || '',
        language: browser.getAttribute('data-path-browser-language') || '',
        entry: icon.getAttribute('data-path-browser-relative') || '',
        name: new_name
    };
    path_browser_close_entry_menus(null);
    send_ajax(
        'POST',
        browser.getAttribute('data-path-browser-rename-url') || '/api/filebrowser/0/rename',
        JSON.stringify(data),
        null, null, null, null,
        function(success) {
            if (!success)
                return ;
            let input = path_browser_get_path_input(browser);
            if (input)
                silent_submit(input, browser.id);
        }
    );
    return (false);
}

function path_browser_close_background_menu()
{
    let menu = document.querySelector('.path_browser_background_menu');
    if (menu)
        menu.remove();
}

function path_browser_refresh(browser)
{
    let input = path_browser_get_path_input(browser);
    if (input)
        silent_submit(input, browser.id);
}

function path_browser_create_entry(browser, kind)
{
    if (!browser || (kind !== 'file' && kind !== 'directory'))
        return (false);

    let file = kind === 'file';
    let prompt_text = browser.getAttribute(
        file ? 'data-path-browser-create-file-prompt' : 'data-path-browser-create-directory-prompt'
    ) || (file ? 'Nom du nouveau fichier :' : 'Nom du nouveau dossier :');
    let default_name = file && browser.getAttribute('data-path-browser-dabsic-editor') === '1'
        ? 'nouveau.dab'
        : '';
    let name = window.prompt(prompt_text, default_name);
    if (name === null)
        return (false);
    name = name.trim();
    if (name === '')
        return (false);

    let input = path_browser_get_path_input(browser);
    let data = {
        page: browser.getAttribute('data-path-browser-page') || '',
        id: browser.getAttribute('data-path-browser-id') || '-1',
        type: browser.getAttribute('data-path-browser-type') || '',
        language: browser.getAttribute('data-path-browser-language') || '',
        directory: input ? input.value : '',
        name: name,
        kind: kind
    };

    path_browser_close_background_menu();
    send_ajax(
        'POST',
        browser.getAttribute('data-path-browser-create-url') || '/api/filebrowser/0/create',
        JSON.stringify(data),
        null, null, null, null,
        function(success) {
            if (success)
                path_browser_refresh(browser);
        }
    );
    return (false);
}

function path_browser_style_context_menu(menu)
{
    menu.style.position = 'fixed';
    menu.style.zIndex = '10000';
    menu.style.minWidth = '170px';
    menu.style.boxSizing = 'border-box';
    menu.style.color = 'white';
    menu.style.background = 'rgba(20, 20, 20, 0.98)';
    menu.style.border = '1px solid #54D76F';
    menu.style.borderRadius = '4px';
    menu.style.boxShadow = '0 3px 10px rgba(0, 0, 0, 0.85)';
    menu.style.overflow = 'hidden';
}

function path_browser_style_context_item(item)
{
    item.style.position = 'relative';
    item.style.display = 'block';
    item.style.boxSizing = 'border-box';
    item.style.width = '100%';
    item.style.height = '32px';
    item.style.padding = '5px 12px';
    item.style.border = '0';
    item.style.textAlign = 'left';
    item.style.whiteSpace = 'nowrap';
    item.style.cursor = 'pointer';
    item.style.color = 'white';
    item.style.background = 'transparent';
}

function path_browser_place_context_menu(menu, event)
{
    let left = Number.isFinite(event.clientX) ? event.clientX : 4;
    let top = Number.isFinite(event.clientY) ? event.clientY : 4;
    let rect = menu.getBoundingClientRect();
    if (left + rect.width > window.innerWidth - 4)
        left = Math.max(4, window.innerWidth - rect.width - 4);
    if (top + rect.height > window.innerHeight - 4)
        top = Math.max(4, window.innerHeight - rect.height - 4);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
}

function path_browser_open_background_menu(event, browser)
{
    path_browser_close_entry_menus(null);
    path_browser_close_background_menu();

    let menu = document.createElement('div');
    menu.className = 'path_browser_background_menu';
    menu.setAttribute('role', 'menu');
    path_browser_style_context_menu(menu);

    let file = document.createElement('button');
    file.type = 'button';
    file.className = 'path_browser_background_menu_item';
    file.setAttribute('role', 'menuitem');
    file.textContent = browser.getAttribute('data-path-browser-create-file-label') || 'Nouveau fichier';
    path_browser_style_context_item(file);
    file.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        path_browser_create_entry(browser, 'file');
    });
    menu.appendChild(file);

    let directory = document.createElement('button');
    directory.type = 'button';
    directory.className = 'path_browser_background_menu_item';
    directory.setAttribute('role', 'menuitem');
    directory.textContent = browser.getAttribute('data-path-browser-create-directory-label') || 'Nouveau dossier';
    path_browser_style_context_item(directory);
    directory.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        path_browser_create_entry(browser, 'directory');
    });
    menu.appendChild(directory);

    document.body.appendChild(menu);
    path_browser_place_context_menu(menu, event);
}

function path_browser_open_entry_menu_from_context(event, icon)
{
    let menu = icon ? icon.querySelector('.path_browser_entry_menu') : null;
    let popup = menu ? menu.querySelector('.path_browser_entry_menu_popup') : null;
    let button = menu ? menu.querySelector('.path_browser_entry_menu_button') : null;
    if (!menu || !popup || !button)
        return (false);
    path_browser_close_background_menu();
    path_browser_close_entry_menus(menu);
    menu.classList.add('path_browser_menu_open');
    icon.classList.add('path_browser_menu_host_open');
    button.setAttribute('aria-expanded', 'true');

    popup.style.position = 'fixed';
    popup.style.right = 'auto';
    popup.style.zIndex = '10000';
    path_browser_place_context_menu(popup, event);
    return (true);
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
    let background_menu = event.target && event.target.closest ? event.target.closest('.path_browser_background_menu') : null;
    if (!background_menu)
        path_browser_close_background_menu();

    let entry_menu = event.target && event.target.closest ? event.target.closest('.path_browser_entry_menu') : null;
    if (!entry_menu)
        path_browser_close_entry_menus(null);

    let icon = event.target && event.target.closest ? event.target.closest('.file_browser > .icon') : null;
    if (!icon || icon.classList.contains('path_browser_navigation'))
        return ;
    if (event.target.closest('form, button, input, select, textarea, a, audio, video, .path_browser_entry_menu'))
        return ;
    path_browser_select_icon(icon, event.shiftKey);
});

document.addEventListener('contextmenu', function(event) {
    let browser = event.target && event.target.closest ? event.target.closest('.file_browser') : null;
    if (!browser)
    {
        path_browser_close_background_menu();
        path_browser_close_entry_menus(null);
        return ;
    }

    let icon = event.target.closest('.file_browser > .icon');
    if (icon && !icon.classList.contains('path_browser_navigation'))
    {
        if (event.target.closest('input, textarea, select, audio, video'))
            return ;
        event.preventDefault();
        path_browser_select_icon(icon, event.shiftKey);
        path_browser_open_entry_menu_from_context(event, icon);
        return ;
    }

    if (icon)
        return ;
    event.preventDefault();
    path_browser_open_background_menu(event, browser);
});

document.addEventListener('keydown', function(event) {
    if (event.key !== 'Escape')
        return ;
    path_browser_close_background_menu();
    path_browser_close_entry_menus(null);
});

window.addEventListener('blur', function() {
    path_browser_close_background_menu();
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
