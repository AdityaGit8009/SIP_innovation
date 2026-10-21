<?php
require_once __DIR__ . '/transactions.php';
function sip_save_address($conn, int $userId, array $data): int
{
    $id=(int)($data['id'] ?? 0);
    $fields=[];
    foreach (['name','phone','city','state','pincode','type','address_line1','address_line2','landmark'] as $key) {
        $fields[$key]=trim((string)($data[$key] ?? ($key === 'city' ? ($data['district'] ?? '') : ($key === 'type' ? 'Home' : ''))));
        if (strlen($fields[$key]) > 255) throw new InvalidArgumentException('Address field is too long.');
    }
    foreach (['name','city','state','address_line1'] as $key) {
        if ($fields[$key] === '') throw new InvalidArgumentException('Name, street address, city and state are required.');
    }
    if (!preg_match('/^\d{10}$/', $fields['phone']) || !preg_match('/^\d{6}$/', $fields['pincode'])) {
        throw new InvalidArgumentException('Enter a valid phone number and pincode.');
    }
    if (!in_array($fields['type'], ['Home','Work','Other'], true)) $fields['type']='Home';
    sip_require_transactional($conn, ['users','customer_addresses']);
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "SELECT id FROM users WHERE id=$userId FOR UPDATE");
        if ($id > 0 && mysqli_num_rows(mysqli_query($conn, "SELECT id FROM customer_addresses WHERE id=$id AND customer_id=$userId FOR UPDATE")) !== 1) {
            throw new InvalidArgumentException('Address not found.');
        }
        $default=!empty($data['is_default']) ? 1 : 0;
        if ($default) mysqli_query($conn, "UPDATE customer_addresses SET is_default=0 WHERE customer_id=$userId");
        $assign=[];
        foreach ($fields as $key=>$value) $assign[]="`$key`='" . mysqli_real_escape_string($conn,$value) . "'";
        $assign[]= "is_default=$default";
        $sql=implode(',', $assign);
        if ($id > 0) mysqli_query($conn, "UPDATE customer_addresses SET $sql WHERE id=$id AND customer_id=$userId");
        else { mysqli_query($conn,"INSERT INTO customer_addresses SET customer_id=$userId,$sql"); $id=mysqli_insert_id($conn); }
        mysqli_commit($conn);
        return $id;
    } catch (Throwable $e) { mysqli_rollback($conn); throw $e; }
}
