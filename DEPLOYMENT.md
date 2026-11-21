# Required deployment steps

The [project README](README.md) lists the files needed for a local checkout and the tests you can run before configuring a database.

Apply the schema migration before serving the updated application. Several old tables use MyISAM and cannot roll back orders. The updated application refuses transactional writes on those tables rather than silently partially saving an order.

## 1. Back up and migrate

Put the site into maintenance mode / stop application writes. Use a backup directory outside the web root, with restrictive permissions. Replace the database user/name if different. These commands were **not** executed against your live database:

```bash
umask 077
mkdir -p "$HOME/sip-backups"
mysqldump -u YOUR_DB_USER -p --lock-all-tables spiinnovation > "$HOME/sip-backups/before-fixes.sql"
cd /path/to/SIP_innovation
php tools/migrate.php --apply
```

The migration converts transactional tables to InnoDB, fixes the cart identity index to include item type, adds full street addresses and immutable order snapshots, adds a manual-refund reference, and adds unique payment/order identifiers to stop replayed checkout. It is designed to be rerun after interruption. DDL is not transactional; keep the backup. Existing duplicate payment IDs cause the migration to stop for review instead of deleting data. Old orders retain their original records; missing historical snapshots cannot be reconstructed reliably.

## 2. Configure the PHP worker

Process environment takes precedence over ignored `config/runtime.local.php`. `.env.example` lists the names; `.env` is not automatically loaded.

- `JWT_SECRET`: one stable, randomly generated secret shared by every PHP worker/instance. Existing configured production secrets must be preserved. Use at least 32 random bytes (for example, 64 hexadecimal characters).
- `SIP_BASE_PATH`: omit to auto-detect; empty or `/` for root; `/SIP_innovation` for that exact subdirectory. Use the public prefix when behind a rewriting reverse proxy.
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional `DB_SOCKET`: override existing database configuration when supplied.
- `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM_EMAIL`, `MAIL_ADMIN_EMAIL`, optional SMTP host/port/name: mail configuration.
- `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`: configure the intended test/live environment. Never expose the secret; the key ID is intentionally public for the provider checkout.
- `PRIVATE_INVOICE_DIR`: optional absolute directory outside the document root, writable by PHP. Otherwise an application-specific private temporary directory is used.

A random LOCAL JWT secret was generated in this workspace's ignored `config/runtime.local.php`, with mode 0600, without printing the value. Existing embedded SMTP/payment values were preserved there and removed from application source. Do not copy local/test credentials over production secrets. Rotate previously committed/exposed SMTP/payment credentials at their providers; removing literals cannot erase Git history.

For another local checkout, configure a missing local key using:

```bash
php tools/setup-local.php
php -S 127.0.0.1:8080 -t .
```

The setup command preserves an existing valid key and refuses to replace an invalid existing one automatically. It is never run by a web request.

For Amazon Linux PHP-FPM, assuming service `php-fpm` and pool `www`, provision `/etc/sip/environment` (root-owned, mode 0600) through your existing secret manager. Preserve the existing JWT secret. Add a systemd drop-in:

```ini
# /etc/systemd/system/php-fpm.service.d/sip-env.conf
[Service]
EnvironmentFile=/etc/sip/environment
```

Pass only required variables through the PHP-FPM pool:

```ini
# /etc/php-fpm.d/zz-sip-env.conf
[www]
env[JWT_SECRET] = $JWT_SECRET
env[SIP_BASE_PATH] = $SIP_BASE_PATH
env[DB_HOST] = $DB_HOST
env[DB_NAME] = $DB_NAME
env[DB_USER] = $DB_USER
env[DB_PASS] = $DB_PASS
env[SMTP_USER] = $SMTP_USER
env[SMTP_PASS] = $SMTP_PASS
env[MAIL_FROM_EMAIL] = $MAIL_FROM_EMAIL
env[MAIL_ADMIN_EMAIL] = $MAIL_ADMIN_EMAIL
env[RAZORPAY_KEY_ID] = $RAZORPAY_KEY_ID
env[RAZORPAY_KEY_SECRET] = $RAZORPAY_KEY_SECRET
```

Set only variables you actually configure. In particular, an empty `SIP_BASE_PATH` forces root hosting and an empty `JWT_SECRET` intentionally disables signing. Keep `display_errors=Off` in production; configure a trusted CA bundle instead of disabling TLS verification.

```bash
sudo php-fpm -t
sudo systemctl daemon-reload
sudo systemctl restart php-fpm
sudo systemctl is-active php-fpm
```

Users must sign in again after this upgrade: new authentication includes a password-bound token version, so password changes revoke old tokens and sessions. Legacy plaintext admin passwords must be reset using a proper password hash; the unsafe plaintext/hash fallback was removed.

## 3. Protect private paths and uploads

Apache `.htaccess` files now deny private config, SQL dumps, test/tool directories, generated invoices and executable uploads. Ensure Apache allows these rules and do not deploy public directory listings.

Nginx ignores `.htaccess`. Add equivalent restrictions **before** your general PHP handler, adjusting the optional subdirectory to your actual deployment:

```nginx
location ~ /\.(?!well-known/) { deny all; }
location ~ ^/(?:SIP_innovation/)?(?:config|database|tests|tools)/ { deny all; }
location ~ ^/(?:SIP_innovation/)?uploads/invoices/ { deny all; }
location ~ ^/(?:SIP_innovation/)?uploads/.*\.(?:php[0-9]?|phtml|phar|cgi|pl|svg|html?)$ { deny all; }
location ~ ^/(?:SIP_innovation/)?(?:output_test\.html|PROJECT_AUDIT\.txt)$ { deny all; }
```

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Restrict forwarded-protocol headers to your trusted load balancer. Apply login rate limiting at your proxy/WAF. Disable public access to backup files and keep the SQL dump outside the deployed web root where possible.

## 4. Verify before reopening traffic

Deploy all changed PHP files **and** `assets/js/security.js`, `assets/js/sip-url.js`, and the image fallback. Clear application/CDN caches of these assets.

```bash
bash tests/run.sh
```

The tests require PHP CLI/CGI, Node.js, Python, and MariaDB tooling. MariaDB tests create a temporary schema from DDL only with synthetic records; they do not import customer data or connect to production.

On the actual site verify: registration/login, password change and sign-out, disabled accounts, product/project cart, Buy now, quantity and delivery totals, wishlist removal ownership, compare limit, quick view, full addresses, COD checkout, stock contention/rollback, online payment capture in the provider sandbox, order management, cancellation, and refunds. Online refunds are recorded only after a completed provider/manual refund reference; changing status does not itself transfer funds.

External API clients making state-changing requests must retain their session cookie and submit the page's CSRF token through `X-CSRF-Token` or `_csrf`. Legacy destructive admin action links now submit POST through the shared browser script; direct destructive GET requests are rejected.

Live browser/database, actual payment capture/refunds and email delivery were not verified in this workspace. TCP and Unix server sockets are denied here. PHP CGI, failure-injection tests and offline MariaDB checks were used instead.


Automatic deployment test completed on 2026-10-21.
