<?php
include "config/db.php";
include "jwt.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(["status" => "error", "message" => "Invalid ID"]);
    exit;
}

require_once "secure_helpers.php";

$user_id = ecom_user_id();
if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "User not logged in"]);
    exit;
}


// Delete only if address belongs to user
$res = mysqli_query($conn, "DELETE FROM customer_addresses WHERE id='$id' AND customer_id='$user_id'");
if ($res) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "The request could not be completed."]);
}
