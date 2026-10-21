<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

include "config/db.php";
$q = mysqli_query($conn, "SELECT id, title, stock FROM products WHERE title LIKE '%LCD%'");
while($r = mysqli_fetch_assoc($q)) {
    echo $r['id'] . " - " . $r['title'] . " - " . $r['stock'] . "\n";
}
