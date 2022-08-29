function add_to_listfield(button, codename, input)
{
    input = document.getElementById(input);
    if (input.value.search(codename) != -1)
    {
	input.value = input.value.replace(codename, "");
	input.value = input.value.replace(/^;/, "");
	input.value = input.value.replace(/;$/, "");
	input.value = input.value.replace(/;;/, "");
	button.style.backgroundColor = button.backgroundColor;
	return ;
    }
    else
    {
	button.backgroundColor = button.style.backgroundColor;
	button.style.backgroundColor = "rgba(0, 0, 0, 0.5)";
    }
    if (input.value != "")
	input.value += ";"
    input.value += codename;
}

function removed_checked_listfield(button, codename, input)
{
    // Ca pourrait etre bien de retirer les elements de la clicklist une fois qu'on
    // a submit la list_of_links.
    // mais pour l'instant, c'est vraiment pas essentiel
}
