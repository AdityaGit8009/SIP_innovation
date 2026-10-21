"""Offline MariaDB invariants, using schema only and synthetic data (no network)."""
from pathlib import Path
import re, subprocess, tempfile
root=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='sip-sql-test-') as work:
    work=Path(work); data=work/'db'
    init=subprocess.run(['mariadb-install-db',f'--datadir={data}',f'--tmpdir={work}','--auth-root-authentication-method=normal','--skip-test-db'],capture_output=True,text=True)
    if init.returncode: raise RuntimeError('Cannot initialize disposable MariaDB')
    schema=(root/'database/spiinnovation_current.sql').read_text()
    names=['cart','products','projects','orders','order_items','users','login']
    statements=['CREATE DATABASE sip_regression','USE sip_regression']
    for name in names:
        create=re.search(r'CREATE TABLE `'+name+r'`.*?;',schema,re.S)[0].rstrip(';')
        create=create.replace('utf8mb4_0900_ai_ci','utf8mb4_unicode_ci')
        statements += [create,f'ALTER TABLE `{name}` ENGINE=InnoDB']
    statements += [
        'ALTER TABLE cart DROP INDEX user_id, ADD UNIQUE KEY cart_item_identity(user_id,product_id,item_type)',
        "INSERT INTO products(id,title,stock,price,status) VALUES (1,'Synthetic product',1,108.32,1)",
        "INSERT INTO projects(id,title,price,status) VALUES (1,'Synthetic project',500,1)",
        "INSERT INTO cart(user_id,product_id,item_type,qty) VALUES (1,1,'product',1),(1,1,'project',1)",
        "START TRANSACTION",
        "UPDATE products SET stock=stock-1 WHERE id=1 AND stock>=1 AND status=1",
        "SET @first=ROW_COUNT()",
        "UPDATE products SET stock=stock-1 WHERE id=1 AND stock>=1 AND status=1",
        "SET @second=ROW_COUNT()",
        'ROLLBACK',
        "INSERT INTO orders(id,user_id,address_id,order_status,stock_restored) VALUES(1,1,1,'Cancelled',0)",
        "INSERT INTO order_items(order_id,product_id,item_type,qty) VALUES(1,1,'product',1),(1,1,'project',10)",
        'START TRANSACTION',
        "SELECT stock_restored FROM orders WHERE id=1 FOR UPDATE",
        "UPDATE products p JOIN order_items oi ON oi.product_id=p.id SET p.stock=p.stock+oi.qty WHERE oi.order_id=1 AND oi.item_type='product'",
        "UPDATE orders SET stock_restored=1 WHERE id=1 AND stock_restored=0",
        'COMMIT',
        "UPDATE orders SET stock_restored=1 WHERE id=1 AND stock_restored=0",
        'SET @repeat=ROW_COUNT()',
        'START TRANSACTION',
        "INSERT INTO users(name,email,mobile) VALUES('Test','synthetic@example.invalid','0000000000')",
        "INSERT INTO login(login_id,password,mobile,role,status) VALUES('synthetic@example.invalid','not-a-real-credential','0000000000','user',1)",
        'ROLLBACK',
        f"SELECT @first=1,@second=0,(SELECT stock FROM products WHERE id=1)=2,@repeat=0,(SELECT COUNT(*) FROM cart)=2,(SELECT COUNT(*) FROM users)=0,(SELECT COUNT(*) FROM login)=0 INTO OUTFILE '{work}/checks.tsv'",
    ]
    sql=';\n'.join(statements)+';\n'
    result=subprocess.run(['mariadbd','--bootstrap',f'--datadir={data}',f'--tmpdir={work}','--skip-networking',f'--log-error={work}/server.log'],input=sql,text=True,capture_output=True)
    out=work/'checks.tsv'
    if result.returncode or not out.exists():
        print((work/'server.log').read_text()[-2500:]);raise RuntimeError('Offline SQL tests failed')
    checks=out.read_text().strip().split('\t')
    assert all(c=='1' for c in checks),checks
    print(f'PASS: {len(checks)} offline MariaDB stock, cart identity and rollback checks')
