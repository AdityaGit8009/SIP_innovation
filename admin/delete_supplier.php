<?php
require_once __DIR__ . '/check_admin.php';

$id = (int)($_GET['id'] ?? 0);
mysqli_query($conn, "DELETE FROM suppliers WHERE id=$id");
header("Location: suppliers.php?msg=deleted");
exit;
