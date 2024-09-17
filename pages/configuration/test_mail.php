<?php

// New Version :
//
// send_mail("example1@mail.fr", "Example Title", "A content", "efrits.fr", [["filename" => "file content"]]);
//
// send_mail(["example1@mail.fr", "example2@mail.fr"], "Example Title", "A content", NULL, [["filename1" => "file1 content"], ["filename2" => "file2 content"]], false);
//
// More informations in tools/send_mail.php

print_r(send_mail(["keryan.h@outlook.fr", "damdoshi@hotmail.com"], "Envoie de mail test utf-8", "Efrits est une super école à Ivry"));
