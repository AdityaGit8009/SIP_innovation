<?php
function sip_require_transactional($conn, array $tables): void
{
    foreach ($tables as $table) {
        if (!preg_match('/^[a-z_]+$/', $table)) throw new InvalidArgumentException('Invalid table.');
        $q = mysqli_query($conn, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'");
        $row = mysqli_fetch_assoc($q);
        if (!$row || strtoupper($row['ENGINE']) !== 'INNODB') {
            throw new RuntimeException('Database migration required before this operation.');
        }
    }
}
