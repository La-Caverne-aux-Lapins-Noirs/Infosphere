function famous_quote_vote_submit(form)
{
    var data = gather_form(form, "", false);
    var buttons = form.getElementsByTagName("button");

    for (let i = 0; i < buttons.length; ++i)
        buttons[i].disabled = true;
    send_ajax(
        form.getAttribute("method").toUpperCase(),
        form.getAttribute("action"),
        JSON.stringify(data),
        "quote",
        null,
        null,
        null,
        function () {
            for (let i = 0; i < buttons.length; ++i)
                buttons[i].disabled = false;
        },
        function () {
            for (let i = 0; i < buttons.length; ++i)
                buttons[i].disabled = false;
        }
    );
    return (false);
}
