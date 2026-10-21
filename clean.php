<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require 'config/db.php';
mysqli_query($conn, "DELETE FROM cart WHERE user_id='' OR user_id=0");
mysqli_query($conn, "DELETE FROM wishlist WHERE user_id='' OR user_id=0");
mysqli_query($conn, "DELETE FROM compare WHERE user_id='' OR user_id=0");
echo 'Cleaned up';
