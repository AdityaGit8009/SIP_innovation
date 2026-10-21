#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php tests/security_urls.php
php tests/regressions.php
php -n tests/auth_stock.php
node tests/urls.js
node tests/security.js
python3 tests/http_guards.py
python3 tests/db_invariants.py
find . -type f -name '*.php' -not -path './.git/*' -not -path './.codex/*' -not -path './.agents/*' -not -path './admin/assets/*' -print0 | xargs -0 -n1 php -l > /tmp/sip-php-lint.log
node --check assets/js/script.js
node --check assets/js/script2.js
node --check assets/js/sip-url.js
node --check assets/js/security.js
printf 'PASS: PHP and application JavaScript syntax checks\n'
