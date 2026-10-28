# Mobile User

PHP shopping demo with registration, login, a cart, orders, and tracking.

Use PHP 8.5 with PDO MySQL and a local MySQL server. Connection settings live in
`config/database_settings.php`. Environment variables `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, and `DB_PASSWORD` override the local defaults. An explicitly
empty `DB_PASSWORD` is supported. Configure `APP_URL` to the public application
base URL when sending password-reset and order links.

Initialize the database from the project directory:

```powershell
php database/setup.php
php database/seed_demo.php
```

The setup creates missing tables without replacing existing ones. It does not
migrate an older schema. The schema starts empty. The optional demo seed adds five sample products and
test cards for existing accounts; registration also adds test cards to new accounts.
Running the seed again does not duplicate cards or replace an existing catalog.
Historical users,
products, orders, saved cards, and addresses need a real database backup.
The mixed SQL snippets in `Mobile_user_notepad.txt` are not a complete backup.
Checkout requires an address and test card belonging to the signed-in user.
The demo seed and registration supply five sample addresses and five test cards
per account. Run the seed again to add these to older accounts without duplicates.
The current app has no form to manage saved cards or addresses.

Start the local application and a free temporary tunnel in separate terminals:

```powershell
php -S 127.0.0.1:8085 -t . tools/router.php
cloudflared tunnel --url http://127.0.0.1:8085
```

Open the printed HTTPS URL. Keep both processes running. If an existing
Cloudflare configuration contains ingress rules, pass `--config` with a separate
YAML file containing only `url: http://127.0.0.1:8085`.
The router serves the application routes and denies configuration, notes,
database scripts, tests, and diagnostic pages.

Registration is at `/public/index.php`; login is at `/public/login.php`.
The storefront at `/public/profile.php` includes product search, sorting, and
cart counts. `/public/cart.php` provides quantity controls and an order summary.
Each product has a Share action and a public detail page at
`/public/product.php?id=<id>`. Recipients can view product information without an
account; cart actions still require sign-in. Native sharing is used when
available, with clipboard and a manual-copy dialog as fallbacks. Shared links
contain only a product ID and include page title, description, and canonical
metadata. Quick Tunnel links require the current tunnel to remain running.
`/public/checkout.php` is a dedicated checkout with address and card previews,
item review, final total, duplicate-submit prevention, and order confirmation.
If the reviewed cart total changes, checkout returns 409 and the customer must
review the new total before retrying. Shared account navigation and responsive
styles live in `config/layout.php` and `public/assets/store.css`.

Account settings at `/public/update_profile.php` separate personal details from
password changes. Saved details are read-only by default; Edit personal details
and Change password explicitly open their respective forms. Cancel restores saved
values, and unsaved changes require a keep-editing or discard decision before
closing or switching forms. Successful saves return to the account summary.
The current password and matching new-password confirmation
are required for password changes. Duplicate emails and invalid profile fields
return explicit errors. Sign-out asks for confirmation, uses POST, clears the
session cookie, and ends at `/public/signed_out.php`. Cart and order data remain
in the account for the next sign-in.
The shared header stays visible while scrolling. Its measured height controls
sticky summaries, notices, and scroll offsets on desktop and mobile.

My Orders includes a delivery-address editor with a full address preview,
explicit Save changes and Cancel actions, and inline feedback. The API allows
changes only for an account's active orders with status `Processing`.
An address change is committed to `orders.whitelist_address_id` before sending
a confirmation email through MailHog. The page shows a saved confirmation and
the email includes previous/new addresses and tracking. Re-saving the same
address does not send a duplicate notification. Email failure never rolls back
the saved delivery address.

## Swagger UI

Open `/public/swagger.php` (or `/docs` through the local router).
The OpenAPI 3.0.3 document is at `/public/openapi.json` and covers all 15 API
files and 21 operations. Swagger UI 5.34.0 assets are bundled locally under
`public/assets/swagger-ui`; the original license is included there.

For Try it out, execute `POST /login_api.php` under Authentication first.
The browser automatically reuses the HTTP-only session cookie on same-origin
requests. No token or manually entered cookie is needed. API requests use the
same host as the documentation, so the docs work through the Cloudflare link.
Cart quantities and totals update after adding, removing, or changing an item.
Both cart pages use the API's calculated line subtotals and grand total.

## MailHog email inbox

Start the installed local inbox:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools/start-mailhog.ps1
```

Open http://127.0.0.1:8025 on this PC. MailHog captures registration, order
confirmation with tracking, cancellation, and password-reset messages. These
messages stay in the local inbox and are not delivered to real email accounts.
The SMTP listener is `127.0.0.1:1025`. Both SMTP and the inbox bind to localhost;
the application tunnel does not expose the inbox. Captures are stored under
`.runtime/mailhog` and survive MailHog restarts.

All application emails use `config/mailer.php` and the bundled PHPMailer source,
so no global php.ini changes or Composer install are needed. `MAIL_HOST` and
`MAIL_PORT` override the local SMTP defaults. `MAIL_ENABLED=0` disables sending
for automated tests. A welcome or order email failure does not undo the account
or order; password reset reports an email delivery failure.

Links use the current request's origin, including Cloudflare HTTPS, or `APP_URL`
when configured. If the Quick Tunnel URL changes, request a new reset email or
order email to receive the current link. Reset tokens expire after one hour and
are cleared after use.

`tests/smoke.php` checks login, cart, checkout rollback, price snapshots, and
account ownership. Run a second local server on port 8086 with a unique
`DB_NAME=mobile_user_smoke_<hex>` and `MAIL_ENABLED=0`, then run the test with
the same environment. It creates and removes only its own temporary database.
`tests/email_smoke.php` also verifies local MailHog captures and password-reset
token use. Run it with `MAIL_ENABLED=1` against the same isolated server setup.
`tests/store-ui.cjs` exercises storefront, cart, checkout, account forms, and
sign-out. `tests/orders-ui.cjs` validates the OpenAPI document and address editor.
These DOM tests use the development tools in `.runtime/validation` (jsdom 22.1.0
and @apidevtools/swagger-parser 10.1.0); they do not modify live customer records.
