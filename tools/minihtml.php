<?php
// Rakesh Sankar https://stackoverflow.com/questions/6225351/how-to-minify-php-page-html-output

function minihtml($buffer)
{
    return (preg_replace(
	[
            '/\>[^\S ]+/s',
            '/[^\S ]+\</s',
            '/(\s)+/s',
            '/<!--(.|\s)*?-->/'
	],
	[
	    '>',
	    '<',
	    '\\1',
	    ''
	],
	$buffer
    ));
}

