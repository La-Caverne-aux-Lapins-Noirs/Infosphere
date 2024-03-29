function setCookie(name, value, days = 365)
{
    var expires = "";

    if (days)
    {
        var date = new Date();

        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    if (value && typeof value == "string")
	value = value.replaceAll(';', 'XXXSEPARATORXXX');
    var tmp = name + "=" + (value || "") + expires + "; path=/";
    document.cookie = tmp;
    return (tmp);
}

function getCookie(name)
{
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');

    for(var i = 0; i < ca.length; i++)
    {
        var c = ca[i];

        while (c.charAt(0)==' ')
	    c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0)
	    return (c.substring(nameEQ.length, c.length));
    }
    return (null);
}

function eraseCookie(name)
{
    setCookie(name, "", -1);
}
