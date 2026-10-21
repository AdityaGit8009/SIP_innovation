<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/secure_helpers.php';
$userId=ecom_user_id();
$id=(int)($_POST['wid'] ?? 0);
if ($userId <= 0) { http_response_code(401); exit('login_required'); }
if ($id <= 0) { http_response_code(400); exit('invalid'); }
mysqli_query($conn, "DELETE FROM wishlist WHERE id=$id AND user_id=$userId");
echo 'success';
