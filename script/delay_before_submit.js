
function delay_before_submit(delay, obj, field)
{
    if (obj.old_value == undefined)
	obj.old_value = obj.value;
    else if (obj.old_value == obj.value)
	return (silent_submit(obj));
    obj.old_value = obj.value;
	
    if (obj.timeout != undefined && obj.timeout != null)
    {
	clearTimeout(obj.timeout);
	obj.timeout = null;
    }
    obj.timeout = setTimeout(delay_before_submit, delay, delay, obj, field);
}
