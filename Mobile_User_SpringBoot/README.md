# Mobile User â€” Spring Boot

=====================run first======================
$config = Get-Content .\.runtime\local-env.json -Raw | ConvertFrom-Json
$config.PSObject.Properties | ForEach-Object {
    [Environment]::SetEnvironmentVariable($_.Name, [string]$_.Value, 'Process')
}

mvn spring-boot:run
=================================================
A separate implementation of the PHP Mobile_User V1.8 shopping demo. Uses Java 21, Spring Boot 3.5.16, Maven, MVC, Security, Data JPA, Bean Validation, Thymeleaf, Spring Mail, MySQL, Flyway and springdoc 2.10.0. No real payment processing.

The PHP project and `Mobile_User_db` remain independent. No existing accounts or orders are imported automatically. The 15 names, descriptions, prices and illustrations match the inspected source and live catalog. Source notes, credentials and MailHog archives are not copied.

## Run on this machine

The delivered application may already be running at the links below. Stop its running Java instance before rebuilding on Windows to release the JAR file lock. The initially launched process ID is recorded in `.runtime/app.pid`; confirm that it still identifies this application before stopping it.

Development and test databases were provisioned separately with a newly generated, restricted database account. Their environment values are in ignored `.runtime/local-env.json`; `tools/run-local.ps1` loads these into its process. This file contains only the new application's local configuration. Never commit it.

```powershell
cd C:\Users\mahfu\Desktop\back_UP_xampp_files\Mobile_User_V_1.8\Mobile_User_SpringBoot
mvn -B -ntp -DskipTests package
.\tools\run-local.ps1
```

Open [Shop](http://localhost:8087/shop), [Register](http://localhost:8087/register), or [Swagger UI](http://localhost:8087/docs). Register a synthetic `@example.invalid` account to get five sample checkout addresses, five alternative delivery addresses and five public demo cards. The application creates no default account or password. It keeps carts and orders after POST sign-out.

## Configure a fresh installation

Requires JDK 21, Maven 3.6.3+, MySQL 8+, and local MailHog. Frontend tests need Node 20+ (Node 24 was used for the browser suite) and Microsoft Edge or a Playwright Chromium installation.

1. Create two empty databases and a dedicated account using `tools/create-databases.sql.example` as a template. Choose your own password and grant permissions only on these databases.
2. Set the environment variables shown in `.env.example`. Spring does not automatically read `.env`; use your shell or a local secret manager. Never point the new app at the PHP database. A datasource guard requires database names beginning with `mobile_user_springboot_`. Tests additionally require `TEST_DB_NAME=mobile_user_springboot_test_<suffix>`.
3. Build and run:

```powershell
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_NAME='mobile_user_springboot_dev'
$env:DB_USER='mobile_user_springboot'
# Set DB_PASSWORD to your newly chosen local database password.
$env:APP_URL='http://localhost:8087'
$env:MAIL_HOST='127.0.0.1'
$env:MAIL_PORT='1025'
mvn -B -ntp -DskipTests package
java -jar target/mobile-user-1.0.0.jar
```

Flyway runs V1 (explicit schema) and V2 (15 catalog records). Repeated startup validates migration checksums and retains data. Hibernate uses `ddl-auto=validate`; Flyway clean and automatic baselining are disabled. Do not edit an applied migration; add a new numbered migration. Account demo seeding checks for existing rows and is safe to repeat. Development runs on port 8087 and binds to loopback, independently of the PHP server on 8085.

Configuration: `PORT`, `SERVER_ADDRESS`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `APP_URL`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENABLED`, `MAIL_FROM`, `SESSION_COOKIE_SECURE`, and `FORWARD_HEADERS_STRATEGY`. Defaults are in `src/main/resources/application.yml`.

## MailHog

Use the existing local MailHog process: SMTP `127.0.0.1:1025`, inbox [http://127.0.0.1:8025](http://127.0.0.1:8025). If stopped, start the existing PHP project's `tools/start-mailhog.ps1` in a separate terminal. Do not expose the inbox through a tunnel or configure a real SMTP relay for testing.

Registration, checkout/tracking, cancellation, delivery-address changes, and password resets all use Spring Mail. Notifications are sent after transaction commit. SMTP failure returns `email_sent:false` and never rolls back a saved account, order or address. Re-saving an unchanged delivery address and replaying a checkout key send no additional email. Email delivery is best-effort; there is no durable retry queue. Password reset returns a generic message to avoid exposing account existence; check the local inbox or `email_sent` for delivery.

## Cloudflare Tunnel

Keep MySQL and MailHog local. In a separate terminal:

```powershell
cloudflared tunnel --url http://127.0.0.1:8087
```

Set these variables before starting/restarting Spring Boot, using the printed HTTPS origin:

```powershell
$env:APP_URL='https://your-current-tunnel.trycloudflare.com'
$env:FORWARD_HEADERS_STRATEGY='framework'
$env:SESSION_COOKIE_SECURE='true'
java -jar target/mobile-user-1.0.0.jar
```

`APP_URL` is the authoritative email/canonical origin and must contain no credentials, query or fragment. Forwarded headers let the loopback-only application recognize HTTPS from the trusted tunnel; enable them only behind your trusted proxy. Secure, HttpOnly, SameSite=Lax session cookies work over the HTTPS tunnel. For direct local HTTP, set `SESSION_COOKIE_SECURE=false`. Quick Tunnel URLs expire when the tunnel stops; update APP_URL and request new emails when it changes. Existing shared product links contain only a product ID, with no session or account data. An actual external tunnel was not created as part of migration verification.

## API and Swagger

[Route mapping](docs/ROUTE_MAPPING.md) inventories all 15 PHP files and all 21 original operations. The new API exposes those 21 operations plus GET `/api/csrf` and GET `/api/products/{id}`. Product pages at `/products/{id}` and `/products/detail?id={id}` are public. Shop/search/sort are public; cart actions require authentication.

Swagger UI uses the reviewed document at `/openapi.json`. It documents bodies, response envelopes, authentication, ownership restrictions and error statuses. For Try it out, execute GET `/api/csrf`, then POST `/api/auth/login`; fetch a new CSRF token after login. Swagger UI automatically sends the XSRF cookie header. Scripts should retain cookies and send the returned token in `X-XSRF-TOKEN` on every POST/PATCH/DELETE, including registration, login and reset. Never disable CSRF to make a client work.

All amounts use BigDecimal and decimal database columns. Checkout requires an owned address, owned demo card, `expected_total`, and a fresh `submission_key` (16â€“100 alphanumeric/underscore/hyphen characters; a UUID works). Keep the key for network retries. Identical retries return the existing order; changed details with a used key return 409. The retained frontend supplies these fields and retains the key during retries. New reviewed totals need a new key. Checkout serializes changes per account, locks product prices, snapshots item names/prices, creates tracking, and clears the cart in one transaction.

Errors consistently use `success:false` and a message: 401 unauthenticated, 403 CSRF, 404 unavailable/foreign resources, 409 duplicate email or conflicting checkout/order state, and 422 invalid fields or selected address/card. These explicit errors replace the PHP endpoints' occasional HTTP 200 validation errors. Fractional quantities are rejected. See the mapping for the two profile-update methods.

## Tests

On this machine, with MySQL and MailHog running:

```powershell
.\tools\run-local.ps1 -Test
npm ci --ignore-scripts
npm test
# Start the app in another terminal first:
npm run test:browser
```

Elsewhere set DB_HOST/DB_PORT/DB_USER/DB_PASSWORD, plus a separate `TEST_DB_NAME=mobile_user_springboot_test_<suffix>`, then run `mvn test`. The test runner refuses a missing or incorrectly named test database and never uses DB_NAME for integration data. The account must be permitted on that test schema. All accounts are randomly named `@example.invalid`. Tests leave synthetic data in the isolated test database for inspection; use a new empty test schema for a clean run. No test drops a database, calls Flyway clean, or alters the PHP database.

The MailHog integration test uses real local SMTP and queries the inbox for a unique synthetic recipient; it expects all five notification types. It does not clear other inbox messages. Other email tests mock delivery and simulate failure. PHP bcrypt fixtures were generated with `tools/generate-php-fixtures.php`; both ASCII and UTF-8 `$2y$` hashes are tested through actual Spring login. Do not regenerate fixtures using Java when testing PHP compatibility.

`npm test` runs the retained interaction checks against migrated DOM and JavaScript with mocked API responses. `npm run test:browser` uses a real Edge browser against the isolated local development app, creates a synthetic account, and checks registration, login, sharing fallback, cart/checkout, address persistence, account forms, password change, POST sign-out, persistence, cancellation, responsive layout, and rendered Swagger. Screenshots/results are saved in `.runtime/`. Use `UI_BASE_URL` for a different localhost port and `BROWSER_EXECUTABLE` for a custom Chromium binary. If using Playwright's bundled Chromium, install it and adjust the launch channel in the test.

See [verification report](docs/VERIFICATION.md) for the actual runs and remaining limitations.

## Existing accounts and orders

Follow [the explicit import procedure](docs/IMPORT.md). The allowlisted read-only exporter and transactional importer remap identifiers, preserve bcrypt hashes and historical charged prices, and refuse conflicts without overwriting records. They do not import active reset tokens, card numbers, email whitelist entries, project notes or database credentials. Historical card display is intentionally left empty; new demo cards are seeded for imported accounts. No real legacy accounts were copied during implementation.

## Version and configuration references

Verified against official documentation on 2026-10-28:

- [Spring Boot 3.5.16 requirements](https://docs.spring.io/spring-boot/3.5/system-requirements.html): Java 17â€“25 and Maven 3.6.3+; Java 21 is compatible.
- [Java support roadmap](https://www.oracle.com/java/technologies/java-se-support-roadmap.html): Java 21 is an LTS release.
- [springdoc 2.10.0 and compatibility matrix](https://springdoc.org/v2/): compatible with Spring Boot 3.5.x.
- [Spring Boot database initialization](https://docs.spring.io/spring-boot/3.5/how-to/data-initialization.html): Flyway as schema owner, Hibernate validation only.
- [Spring Security CSRF](https://docs.spring.io/spring-security/reference/6.5/servlet/exploits/csrf.html): CSRF token repository and authentication/logout token refresh.
- [Spring transaction-bound events](https://docs.spring.io/spring-framework/reference/data-access/transaction/event.html): notifications after successful commit.
- [Spring Boot proxy/forwarded headers](https://docs.spring.io/spring-boot/3.5/how-to/webserver.html): forwarded-header strategy behind a trusted proxy.

## Delivery and tracking

Checkout supports expiring delivery quotes, shipping-inclusive totals, Shippo test labels, and owner-scoped carrier tracking refresh. With no Shippo key it uses clearly marked demo delivery options. See [shipping setup and limitations](docs/SHIPPING.md) before enabling provider rates.

Order confirmation emails now include a PDF receipt with order items, delivery totals, address, and a tracking barcode. See the PDF order emails section in [shipping documentation](docs/SHIPPING.md).
