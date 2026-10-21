<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Explicit opt-in local setup; never executed automatically on a web request.
$path = __DIR__ . '/../config/runtime.local.php';
$config = is_file($path) ? require $path : [];
if (!is_array($config)) throw new RuntimeException('Invalid local configuration.');
require_once __DIR__ . '/../jwt.php';
try {
    JWT::assertConfigured();
    echo "An existing JWT secret is configured; left unchanged.\n";
    exit;
} catch (RuntimeException $e) {
    if (getenv('JWT_SECRET') !== false || isset($config['JWT_SECRET'])) {
        throw new RuntimeException('An invalid existing secret must be replaced explicitly.');
    }
}
$config['JWT_SECRET'] = bin2hex(random_bytes(32));
$oldMask = umask(0077);
try {
    if (file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not write local configuration.');
    }
    chmod($path, 0600);
} finally { umask($oldMask); }
echo "Local JWT secret configured in ignored config/runtime.local.php. No secret was printed.\n";
