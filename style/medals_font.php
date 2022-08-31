<?php

chdir(__DIR__."/..");
$Language = "fr";
require_once ("language.php");
require_once ("tools/index.php");

foreach (all_font_files($Configuration->MedalsDir("_ressources")) as $font)
{
    $file = resolve_path($font);
    $font = "fnt".md5($file);
    ?>

@font-face
{
    font-family: <?=$font; ?>;
    src: url('../<?=$file; ?>') format("<?=["ttf" => "truetype", "woff2" => "woff2"][pathinfo($file, PATHINFO_EXTENSION)]; ?>");
}

<?php
}

