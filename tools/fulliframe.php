<?php

function fulliframe($target, $link)
{
    ob_start();
?>
    <html><head><style>body { padding: 0; margin: 0; overflow: hidden; } </style></head>
	<body>
	    <iframe
		title="Inline Frame Example"
		       style="width: 100%; height: 100%"
		       src="<?=$link; ?>">
	    </iframe>
	</body>
    </html>
    <?php
    $out = ob_get_contents();
    ob_end_clean();
    file_put_contents($target, $out);
}
