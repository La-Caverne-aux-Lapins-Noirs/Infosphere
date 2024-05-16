var icomscroll = new Array();

function restore_scroll(icomdiv_list)
{
    var box = document.getElementById(icomdiv_list);

    if (box)
	box.scrollTop = icomscroll[icomdiv_list];
}

function restore_scroll_delay(a, b, c, icomdiv_list)
{
    setTimeout(restore_scroll, 100, icomdiv_list);
}

function go_down(icomdiv_list)
{
    var box = document.getElementById(icomdiv_list);

    if (box)
	box.scrollTop = box.scrollHeight;
}

function check_update(icomdiv)
{
    var subjectbox = document.getElementById(icomdiv + "_subject_list");
    var messagebox = document.getElementById(icomdiv + "_message_list");
    var updater = document.getElementById("update_request");
    var box = messagebox ? messagebox : subjectbox;
    
    setTimeout(check_update, 5000, icomdiv);
    if (updater == null || box == null)
	return ;
    icomscroll[box.id] = box.scrollTop;
    silent_submitf(updater, {
	tofill: box.id,
	after_success: restore_scroll_delay,
	after_success_parameter: box.id
    });
}

