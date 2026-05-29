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
