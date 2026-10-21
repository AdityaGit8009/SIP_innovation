<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

include "config/db.php";
$q = mysqli_query($conn, "SHOW COLUMNS FROM projects");
while($r = mysqli_fetch_assoc($q)) {
    echo $r['Field'] . "\n";
}
