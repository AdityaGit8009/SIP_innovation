<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

ob_start();
include "column-right.php";
$output = ob_get_clean();
file_put_contents('output_test.html', $output);
