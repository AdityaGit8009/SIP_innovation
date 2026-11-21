# SIP Innovation

SIP Innovation is a PHP storefront for products and engineering projects. Customers can compare items, check delivery, place orders, and track purchases. The same application hosts training and services pages and an admin dashboard for the catalog, inventory, orders, and refunds.

## Find the code

| Task | Start here |
| --- | --- |
| Change a public page | [Storefront home](index.php), [shared header and navigation](header.php), [training](training.php), and [services](services.php) |
| Work on the catalog | [Product listing](products.php) and [product detail](product.php), or [mini projects](mini-projects.php), [major projects](major-projects.php), and [project detail](project-details.php) |
| Trace an order | [Add to cart](AJAX/add_to_cart.php) → [cart](cart.php) → [delivery and checkout](checkout.php) → [payment selection](place_order.php) → [order submission](place_order_action.php). The online payment path also calls [Razorpay order creation](razorpay_create_order.php). |
| Change order handling | [Customer order history](orders.php), [stock and order helpers](order_helpers.php), [pricing](ecom_pricing.php), and [transaction helpers](includes/transactions.php) |
| Work on the admin area | [Dashboard](admin/dashboard.php), [access checks](admin/check_admin.php), [product management](admin/products.php), and [order management](admin/orders.php) |
| Follow a browser request | [Storefront AJAX handlers](AJAX/), [browser scripts](assets/js/), and [shared PHP helpers](includes/) |

## Run locally

1. Provision a local MySQL or MariaDB database with the application schema and synthetic data as needed. Use PHP with MySQLi and enable cURL for Razorpay. The [migration](tools/migrate.php) upgrades an existing schema and expects the application tables to exist.
2. Obtain the site's PHP bootstrap files from the maintainer. The [ignore rules](.gitignore) exclude `config/runtime.php`, `config/db.php`, `config/razorpay.php`, `config/mail.php`, and SQL exports. For file-based database settings, copy the [database settings example](config/config.local.example.php) to `config/config.local.php` and point it at the local database.
3. Supply worker environment variables using the [environment example](.env.example). The worker reads its process environment and can use the ignored `config/runtime.local.php` for local settings. Loading a `.env` file requires separate configuration. Use Razorpay test keys or an SMTP test account when exercising those integrations.
4. From the repository root, initialize the local signing key and start the development server.

   ```sh
   php tools/setup-local.php
   php -S 127.0.0.1:8080 -t .
   ```

   Open `http://127.0.0.1:8080/`. Use [the deployment guide](DEPLOYMENT.md) for migration, PHP worker settings, and web-server access rules before serving traffic.

## Run the tests

A fresh checkout can run the pricing, routing, invoice rendering, URL, and browser CSRF checks with Bash, PHP CLI, and Node.js.

```sh
bash tests/standalone.sh
```

The [full suite](tests/run.sh) also exercises authentication, stock handling, HTTP guards, and database invariants, then checks PHP and JavaScript syntax. It requires the runtime bootstrap, PHP CGI, Python 3, MariaDB command-line tools, and a schema-only export at `database/spiinnovation_current.sql`.

```sh
bash tests/run.sh
```

The [October 2026 project audit](PROJECT_AUDIT.txt) records the original findings. The [deployment guide](DEPLOYMENT.md) documents subsequent changes and their verification steps.
