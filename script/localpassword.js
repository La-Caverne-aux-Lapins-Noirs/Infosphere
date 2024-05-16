
function generate_local_password(password)
{
    localStorage.setItem("local_password", wide_hash(password, 128));
    return (get_local_password());
}

function get_local_password(id = -1)
{
    if (id != -1)
	return (localStorage.getItem("local_password" + id.toString()));
    return (localStorage.getItem("local_password"));
}

function xor(msg, mask)
{
    var res = "";
    var i;

    for (i = 0; i < msg.length; ++i)
	res += String.fromCharCode(msg.charCodeAt(i) ^ mask.charCodeAt(i));
    return (res);
}

function cipher_message(msg, skip = 0)
{
    return (
	xor(msg, wide_hash(get_local_password(), msg.length, skip))
    );
}

function uncipher_message(msg, skip = 0, id = -1)
{
    return (
	xor(msg, wide_hash(get_local_password(id), msg.length, skip))
    );
}
