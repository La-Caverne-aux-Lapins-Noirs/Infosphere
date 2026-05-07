<?php

function try_subscribe($login, $mail, $password, $repassword, $fake = false)
{
    if ($password != $repassword && $fake == false)
	return (new ErrorResponse("PasswordDoesNotMatch"));
    return (subscribe($login, $mail, $password, !$fake, $fake));
}

