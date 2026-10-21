<?php
require __DIR__ . '/../jwt.php';
require __DIR__ . '/../includes/url.php';
$count = 0;
function check($condition, $label) {
    global $count;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $count++;
}
$originalKey = getenv('JWT_SECRET');
$originalPath = getenv('SIP_BASE_PATH');
try {
    foreach (['', 'short', str_repeat('a', 64), 'dev-only-change-JWT_SECRET-on-production'] as $key) {
        putenv($key === false ? 'JWT_SECRET' : 'JWT_SECRET=' . $key);
        check(JWT::decode('bad.token.value') === false, 'unconfigured verification');
        try { JWT::encode(['exp' => time()+60]); check(false, 'unconfigured signing'); }
        catch (RuntimeException $e) { check($e->getMessage() === 'JWT signing is not configured.', 'generic configuration error'); }
    }
    $fixture = sys_get_temp_dir() . '/sip-jwt-test-' . bin2hex(random_bytes(6));
    mkdir($fixture . '/config', 0700, true);
    copy(__DIR__ . '/../jwt.php', $fixture . '/jwt.php');
    copy(__DIR__ . '/../config/runtime.php', $fixture . '/config/runtime.php');
    file_put_contents($fixture . '/test.php', '<?php putenv("JWT_SECRET"); require __DIR__ . "/jwt.php"; try { JWT::assertConfigured(); exit(1); } catch (RuntimeException $e) { exit(JWT::decode("a.b.c") === false ? 0 : 1); }');
    $proc=proc_open([PHP_BINARY, $fixture . '/test.php'], [], $pipes);
    check(proc_close($proc) === 0, 'missing configuration signing and verification');
    unlink($fixture . '/test.php'); unlink($fixture . '/jwt.php'); unlink($fixture . '/config/runtime.php'); rmdir($fixture . '/config'); rmdir($fixture);
    $key = bin2hex(random_bytes(32));
    putenv('JWT_SECRET=' . $key);
    $payload = ['uid'=>1, 'role'=>'user', 'login_id'=>'test@example.invalid', 'exp'=>time()+60];
    $token = JWT::encode($payload);
    check(JWT::decode($token) === $payload, 'round trip');
    $version = JWT::accountVersion('old-password-hash');
    check(JWT::matchesAccount(['ver'=>$version], 'old-password-hash'), 'account version valid');
    check(!JWT::matchesAccount(['ver'=>$version], 'new-password-hash'), 'password change revokes token');
    check((bool)preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token), 'base64url output');
    $parts = explode('.', $token); $parts[1] = rtrim(strtr(base64_encode('{"role":"admin","exp":9999999999}'), '+/', '-_'), '=');
    check(JWT::decode(implode('.', $parts)) === false, 'tampering');
    foreach ([null, [], '', 'a.b', 'a.b.c.d', str_repeat('a', 17000)] as $bad) { check(JWT::decode($bad) === false, 'malformed token'); }
    foreach ([['exp'=>time()-1], ['exp'=>time()], ['exp'=>time()+60,'nbf'=>time()+30], ['exp'=>time()+60,'nbf'=>'wrong']] as $claims) {
        check(JWT::decode(JWT::encode($claims)) === false, 'time constraints');
    }
    // Legacy encoding and malicious signed structures are tested independently.
    foreach ([['alg'=>'HS256'], ['alg'=>'none'], ['alg'=>'HS512']] as $header) {
        $h = base64_encode(json_encode($header)); $b = base64_encode(json_encode($payload));
        $legacy = "$h.$b." . base64_encode(hash_hmac('sha256', "$h.$b", $key, true));
        check((JWT::decode($legacy) !== false) === ($header['alg'] === 'HS256'), 'algorithm/legacy compatibility');
    }
    foreach ([['role'=>'admin'], ['exp'=>'9999999999'], 'not an object'] as $badPayload) {
        $h=base64_encode('{"alg":"HS256"}'); $b=base64_encode(json_encode($badPayload));
        check(JWT::decode("$h.$b." . base64_encode(hash_hmac('sha256', "$h.$b", $key, true))) === false, 'invalid claims');
    }
    putenv('JWT_SECRET=' . bin2hex(random_bytes(32)));
    check(JWT::decode($token) === false, 'wrong signing key');
    foreach ([''=>'', '/'=>'', '/shop'=>'/shop', '/shop/'=>'/shop', '/nested/shop'=>'/nested/shop'] as $input=>$expected) {
        putenv('SIP_BASE_PATH=' . $input); check(sip_base_path() === $expected, 'configured base');
    }
    foreach (['https://example.com', '//example.com', '/a/../b', '/a?b', '/a\\b', '/a//b'] as $invalid) {
        putenv('SIP_BASE_PATH=' . $invalid);
        try { sip_base_path(); check(false, 'invalid base'); } catch (RuntimeException $e) { check(true, 'invalid base rejected'); }
    }
    putenv('SIP_BASE_PATH');
    foreach (['/index.php'=>'', '/shop/products.php'=>'/shop', '/shop/AJAX/add_to_cart.php'=>'/shop', '/shop/admin/index.php'=>'/shop'] as $script=>$expected) {
        $_SERVER['SCRIPT_NAME']=$script;
        $relative = str_replace('/shop/', '', ltrim($script, '/'));
        if (strpos($relative, 'shop/') === 0) { $relative=substr($relative,5); }
        $_SERVER['SCRIPT_FILENAME']=dirname(__DIR__) . '/' . $relative;
        check(sip_base_path() === $expected, 'detected base ' . $script);
    }
    echo "PASS: $count PHP security and URL checks\n";
} finally {
    putenv($originalKey === false ? 'JWT_SECRET' : 'JWT_SECRET=' . $originalKey);
    putenv($originalPath === false ? 'SIP_BASE_PATH' : 'SIP_BASE_PATH=' . $originalPath);
}
