<?php
// Run with php -n so mysqli is replaced by a deterministic failure-injection adapter.
if (extension_loaded('mysqli')) exit("Run: php -n tests/auth_stock.php\n");
ini_set('session.save_path', sys_get_temp_dir());
session_start();
class Rows { public array $rows; public function __construct(array $rows) { $this->rows=$rows; } }
class TestConnection {
    public array $stock=[1=>3,2=>5];
    public int $restored=0;
    public bool $active=true;
    public string $password='synthetic-hash';
    public bool $fail=false;
    public array $snapshot=[];
    public int $commits=0;
    public int $rollbacks=0;
}
if (!extension_loaded('mysqli')) {
function mysqli_begin_transaction($c) { $c->snapshot=[$c->stock,$c->restored]; }
function mysqli_commit($c) { $c->commits++; }
function mysqli_rollback($c) { [$c->stock,$c->restored]=$c->snapshot; $c->rollbacks++; }
function mysqli_fetch_assoc($r) { return array_shift($r->rows); }
function mysqli_real_escape_string($c,$v) { return addslashes($v); }
function mysqli_num_rows($r) { return count($r->rows); }
function mysqli_query($c,$sql) {
    if (strpos($sql,'information_schema.TABLES')!==false) return new Rows([['ENGINE'=>'InnoDB']]);
    if (strpos($sql,'SELECT stock_restored,order_status')!==false) return new Rows([['stock_restored'=>$c->restored,'order_status'=>'Cancelled']]);
    if (strpos($sql,'SELECT product_id,qty FROM order_items')!==false) {
        $rows=[['product_id'=>1,'qty'=>2]];
        if (strpos($sql,"item_type='product'")===false) $rows[]=['product_id'=>2,'qty'=>20];
        return new Rows($rows);
    }
    if (preg_match('/UPDATE products SET stock=stock\+(\d+) WHERE id=(\d+)/',$sql,$m)) {
        if ($c->fail) throw new RuntimeException('Injected write failure');
        $c->stock[(int)$m[2]]+=(int)$m[1];return true;
    }
    if (strpos($sql,'UPDATE orders SET stock_restored=1')!==false) { $c->restored=1;return true; }
    if (strpos($sql,'SELECT u.id,l.password FROM users u JOIN login')!==false) {
        return new Rows($c->active || strpos($sql,'l.status=1')===false ? [['id'=>7,'password'=>$c->password]] : []);
    }
    throw new RuntimeException('Unexpected query in test adapter');
}
}
require __DIR__.'/../order_helpers.php';
require __DIR__.'/../secure_helpers.php';
$count=0;
function check_case($condition,$label) { global $count; if (!$condition) throw new RuntimeException('FAIL: '.$label);$count++; }
$conn=new TestConnection();
check_case(sip_restore_order_stock_once($conn,1)===true,'first restoration');
check_case($conn->stock===[1=>5,2=>5],'project ID never restores product stock');
check_case(sip_restore_order_stock_once($conn,1)===false,'repeated restoration');
check_case($conn->stock===[1=>5,2=>5],'repeat has no inventory effect');
$conn=new TestConnection();$conn->fail=true;
try { sip_restore_order_stock_once($conn,1); check_case(false,'failure must propagate'); } catch (RuntimeException $e) {}
check_case($conn->stock===[1=>3,2=>5] && $conn->restored===0 && $conn->rollbacks===1,'failure rolls back restoration');
$conn=new TestConnection();mysqli_begin_transaction($conn);sip_restore_order_stock_once($conn,1,true);
check_case($conn->commits===0,'nested restoration does not commit parent transaction');
mysqli_rollback($conn);check_case($conn->stock===[1=>3,2=>5] && $conn->restored===0,'parent rollback restores state');
$_SESSION['user_id']=7;
require_once __DIR__ . '/../jwt.php';
$_SESSION['auth_version']=JWT::accountVersion($conn->password);
check_case(ecom_user_id()===7,'active session');
$conn->active=false;
check_case(ecom_user_id()===0 && !isset($_SESSION['user_id']),'disabled account cannot reuse session');
session_destroy();
echo "PASS: $count PHP authentication and stock failure-injection checks\n";
