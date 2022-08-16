
function to_object_array(data)
{
    data = single_to_array(data);
    for (let i in data)
	data[i] = string_to_object(data[i]);
    return (data);
}
