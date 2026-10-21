<?php
session_start();
include "config/db.php";
require_once "secure_helpers.php";

$user_id = ecom_user_id();
$compare_id = (int)($_POST['compare_id'] ?? 0);

if ($user_id > 0 && $compare_id > 0) {

  mysqli_query(
    $conn,
    "DELETE FROM compare 
    WHERE id='$compare_id' AND user_id='$user_id'"
  );

  echo "success";
} else {
  echo "error";
}
