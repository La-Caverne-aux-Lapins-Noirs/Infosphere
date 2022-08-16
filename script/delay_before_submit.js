
function delay_before_submit(delay, obj, field)
{
    if (obj.old_value == undefined)
	obj.old_value = obj.value;
    else if (obj.old_value != obj.value)
	obj.old_value = obj.value;
    else
    {
	silent_submit(obj);
	return ;
    }
    setTimeout(delay_before_submit, delay, delay, obj, field);
}
