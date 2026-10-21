<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require 'config/db.php';
$res = mysqli_query($conn, "SELECT id, title, main_image FROM products WHERE title LIKE '%micro%'");
while($r = mysqli_fetch_assoc($res)) {
    echo $r['id'] . ' - ' . $r['title'] . ' - [' . $r['main_image'] . "]\n";
}
