<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--apply') {
    exit("Back up the database, stop writes, then run: php tools/migrate.php --apply\n");
}
require __DIR__ . '/../config/db.php';
// DDL commits implicitly. Each step is idempotent; stop and repair on failure.
$tables = ['cart','products','projects','orders','order_items','order_status_history','transactions','users','login','customer_addresses','compare','wishlist'];
foreach ($tables as $table) {
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'"));
    if (!$row) throw new RuntimeException('Required table missing: ' . $table);
    if (strtoupper($row['ENGINE']) !== 'INNODB') mysqli_query($conn, "ALTER TABLE `$table` ENGINE=InnoDB");
}
$columns = [
    'customer_addresses' => ['address_line1'=>'VARCHAR(255) NOT NULL DEFAULT \'\'', 'address_line2'=>'VARCHAR(255) NOT NULL DEFAULT \'\'', 'landmark'=>'VARCHAR(255) NOT NULL DEFAULT \'\''],
    'orders' => ['shipping_address'=>'LONGTEXT NULL', 'refund_reference'=>'VARCHAR(190) NULL', 'stock_restored'=>'TINYINT(1) NOT NULL DEFAULT 0'],
    'order_items' => ['title_snapshot'=>'VARCHAR(255) NULL'],
];
foreach ($columns as $table=>$fields) {
    foreach ($fields as $column=>$definition) {
        if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'")) === 0) {
            mysqli_query($conn, "ALTER TABLE `$table` ADD `$column` $definition");
        }
    }
}
$indexes = [];
$q = mysqli_query($conn, 'SHOW INDEX FROM cart');
while ($row = mysqli_fetch_assoc($q)) {
    if ((int)$row['Non_unique'] === 0 && $row['Key_name'] !== 'PRIMARY') $indexes[$row['Key_name']][(int)$row['Seq_in_index']] = $row['Column_name'];
}
$hasIdentity = false;
foreach ($indexes as $name=>$fields) {
    ksort($fields); $fields=array_values($fields);
    if ($fields === ['user_id','product_id']) {
        $escaped = str_replace('`','``',$name);
        mysqli_query($conn, "ALTER TABLE cart DROP INDEX `$escaped`");
    }
    if ($fields === ['user_id','product_id','item_type']) $hasIdentity=true;
}
if (!$hasIdentity) mysqli_query($conn, 'ALTER TABLE cart ADD UNIQUE KEY cart_item_identity (user_id,product_id,item_type)');
// Reject reusing a payment or checkout order after a retry. Empty COD IDs become NULL.
foreach (['razorpay_payment_id','razorpay_order_id'] as $column) {
    mysqli_query($conn, "UPDATE orders SET `$column`=NULL WHERE `$column`=''");
    $index='unique_'.$column;
    if (mysqli_num_rows(mysqli_query($conn, "SHOW INDEX FROM orders WHERE Key_name='$index'")) === 0) {
        mysqli_query($conn, "ALTER TABLE orders ADD UNIQUE KEY `$index` (`$column`)");
    }
}
echo "Migration complete. Existing duplicate payment IDs, if present, must be reviewed rather than silently deleted.\n";
