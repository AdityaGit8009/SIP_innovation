<?php
require_once __DIR__ . '/../config/runtime.php';
function sip_invoice_directory(): string
{
    $dir = sip_env('PRIVATE_INVOICE_DIR', sys_get_temp_dir() . '/sip-invoices-' . substr(hash('sha256', dirname(__DIR__)), 0, 16));
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Private invoice directory unavailable.');
    $real = realpath($dir);
    $root = realpath(dirname(__DIR__));
    if ($real === false || $real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
        throw new RuntimeException('Invoices must be stored outside the document root.');
    }
    return $real;
}
