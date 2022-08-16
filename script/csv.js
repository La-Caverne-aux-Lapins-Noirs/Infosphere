
function load_csv(str, rootname = "", rootmap = true)
{
    var tmp = csv_parse(str);

    if (tmp == null || tmp.length < 2)
	return (null);
    var root = new Map;
    var obj;

    if (rootname != "")
    {
	if (rootmap)
	    obj = root[rootname] = new Map;
	else
	    obj = root[rootname] = new Array;
    }
    else
	obj = root;

    for (let i = 0; i < tmp.length - 1 && tmp[i + 1].length != 0; ++i)
    {
	if (tmp[i + 1][0] == "")
	{
	    obj.length = i;
	    break ;
	}
	obj[i] = new Map();
	for (let j = 0; j < tmp[0].length; ++j)
	{
	    if (j < tmp[0].length)
		obj[i][tmp[0][j]] = tmp[i + 1][j];
	    else
		obj[i][tmp[0][j]] = "";
	}
    }
    return (root);
}

