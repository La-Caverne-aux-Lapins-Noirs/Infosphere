function use_search(action, obj, list)
{
    var target = document.getElementById(list);
    var searchbar = document.getElementById("searchbar");

    if (searchbar.value != "")
	obj.action = action + "/" + searchbar.value;
    else
	obj.action = action;
    silent_submit(obj, target, null, null, null);
    return (false);
}
