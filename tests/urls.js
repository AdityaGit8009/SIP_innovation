'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/js/sip-url.js', 'utf8'), context);
let count = 0;
for (const origin of ['http://localhost:8080', 'https://example.com']) {
    for (const base of ['', '/SIP_innovation', '/nested/shop']) {
        context.window.SIP_BASE_URL = base;
        for (const path of ['AJAX/add_to_cart.php', '/AJAX/add_to_wishlist.php', 'AJAX/quick_view_product.php?id=42', 'AJAX/search_suggestions.php?q=a%26b', 'admin/dashboard.php']) {
            const url = context.window.sipUrl(path);
            assert.equal(new URL(url, origin + '/unrelated/page.php').href, origin + base + '/' + path.replace(/^\//, ''));
            count++;
        }
    }
}
for (const bad of ['https://evil.example', '//evil.example', '../login.php', '%2e%2e/login.php', 'a\\b', '%2f%2fevil', 'bad%']) {
    assert.throws(() => context.window.sipUrl(bad)); count++;
}
console.log(`PASS: ${count} JavaScript URL checks`);
