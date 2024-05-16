
const characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

function hash(str)
{
    var hash = 5381;
    var i;

    // DJB2
    for (i = 0; i < str.length; i++)
	hash = (((hash << 5) + hash) + str.charCodeAt(i)) | 0;
    return hash;
}

function wide_hash(str, size, skip)
{
    var hsh = hash(str + "&:r5h_;gs");
    var res = "";
    var i;

    for (i = 0; i < size || skip > 0; ++i)
    {
	hsh = characters[Math.abs(hash(hsh + res) % characters.length)];
	res = res + hsh;
	if (skip > 0 && (skip -= 1) == 0)
	{
	    res = "";
	    i = 0;
	}
    }
    return (res);
}

