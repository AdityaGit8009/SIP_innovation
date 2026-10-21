<?php
require __DIR__ . '/../ecom_pricing.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/InvoicePdf.php';
require __DIR__ . '/../includes/InvoiceTemplate.php';
$count=0;
function verify($value,$label) { global $count; if (!$value) throw new RuntimeException('FAIL: '.$label); $count++; }
$p=ecom_product_price(['base_price'=>100,'default_tax_percentage'=>2,'tax_percentage'=>18,'price'=>108.32,'discount'=>10]);
verify(abs($p['sale']-108.32)<0.001,'discounted sale price');
verify(abs($p['base']+$p['tax']-$p['sale'])<0.001,'included tax does not increase total');
$totals=ecom_cart_totals([['price'=>108.32,'tax_percentage'=>18,'qty'=>3,'delivery_charge'=>20],['item_type'=>'project','price'=>500,'qty'=>2,'delivery_charge'=>0]]);
verify(abs($totals['items_total']-1324.96)<0.001,'mixed cart sale totals');
verify($totals['delivery_total']==60,'delivery charged per unit');
verify(function_exists('sip_render_invoice_pdf_html') && function_exists('sip_render_invoice_html'),'invoice renderers coexist');
foreach ([['/shop/admin/products.php',['delete'=>3],true],['/shop/admin/products.php',[],false],['/shop/products.php',['status'=>1],false],['/shop/remove_address.php',['id'=>3],true],['/shop/cart.php',['add'=>3],true]] as [$path,$query,$expected]) {
    verify(sip_mutating_get($path,$query)===$expected,'mutation detection');
}
// The former admin migration fallback accepted this password hash as a password.
$hash=password_hash('correct-password',PASSWORD_DEFAULT);
verify(password_verify('correct-password',$hash),'valid password');
verify(!password_verify($hash,$hash),'stored hash is not a password');
echo "PASS: $count pricing, routing and renderer regressions\n";
