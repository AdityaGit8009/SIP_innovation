<?php
require_once __DIR__ . '/../config/runtime.php';

/**
 * Origin-relative URLs retain HTTPS, ports and the public host behind a proxy.
 * SIP_BASE_PATH overrides detection when a proxy mounts the app at another path.
 */
function sip_base_path(): string
{
    $configured = sip_env('SIP_BASE_PATH', false);
    if ($configured !== false) {
        $path = trim($configured);
    } else {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $path = str_replace('\\', '/', dirname($script));
        // Account for entry points below the application root.
        $filename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        $root = str_replace('\\', '/', dirname(__DIR__));
        if (strpos($filename, $root . '/') === 0) {
            $relativeDir = dirname(substr($filename, strlen($root) + 1));
            if ($relativeDir !== '.' && substr($path, -strlen('/' . $relativeDir)) === '/' . $relativeDir) {
                $path = substr($path, 0, -strlen('/' . $relativeDir));
            }
        }
    }
    if ($path === '' || $path === '/' || $path === '.') {
        return '';
    }
    // Only a path is accepted: never a host, scheme, query or traversal.
    if (!preg_match('#^/(?:[A-Za-z0-9._~-]+/)*[A-Za-z0-9._~-]*/?$#D', $path)
        || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)
        || strpos($path, '//') !== false) {
        throw new RuntimeException('Invalid SIP_BASE_PATH configuration.');
    }
    return rtrim($path, '/');
}
