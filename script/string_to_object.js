
function string_to_object(data)
{
    if (typeof data == "string")
	return (document.getElementById(data));
    return (data);
}

