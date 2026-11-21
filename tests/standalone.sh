#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

php tests/regressions.php
node tests/urls.js
node tests/security.js
