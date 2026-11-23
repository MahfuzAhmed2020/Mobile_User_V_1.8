# Verification report

Executed on 2026-10-28 using Temurin Java 21.0.10, Maven 3.9.16, MySQL 8.0.33, Spring Boot 3.5.16, and local MailHog. The browser suite used Node 24.22.1 and headless Microsoft Edge. Development and integration tests used different newly provisioned MySQL databases and a new account restricted to those databases. Every test account is synthetic and uses example.invalid.

| Check actually run | Result |
|---|---|
| Maven package with integration suite | PASS â€” 19 tests, 0 failures/errors/skips |
| Exact decimals, quantities, account-specific carts | PASS |
| Authentication, CSRF, POST logout and persisted cart | PASS |
| PHP-generated ASCII and UTF-8 $2y$ bcrypt hashes through Spring login | PASS |
| Ownership of orders, tracking, addresses and card selection | PASS |
| Checkout rollback, price snapshots and reviewed-total conflicts | PASS |
| Concurrent duplicate checkout and single order/email | PASS |
| Processing-only address changes, saved previews and duplicate-notification suppression | PASS |
| Cancellation and tracking soft-deletes | PASS |
| SMTP failures retain saved accounts/orders/addresses | PASS |
| Reset token hashing, expiry, validation, reuse rejection and old-password failure | PASS |
| Concurrent reset consumption | PASS |
| Synthetic legacy import remapping, snapshots, PHP login, rerun and rollback | PASS |
| Repeatable account demo seeding | PASS |
| Actual local MailHog captures | PASS â€” registration, order/tracking, address change, reset and cancellation |
| Storefront/account DOM interactions | PASS â€” 43 checks |
| Address-editor DOM interactions | PASS â€” preview, Cancel, save, feedback, error recovery, dispatched restriction, escaping |
| OpenAPI parser validation | PASS â€” all 21 migrated operations plus CSRF and public product JSON |
| Real Edge desktop/mobile workflow | PASS â€” 33 checks; no JavaScript errors |
| Browser password reset and MailHog link | PASS â€” old password rejected inline; new password works |
| Browser public sharing | PASS â€” public detail page, clean canonical URL, manual-copy fallback; native and clipboard paths also covered by DOM checks |
| Browser checkout/account/orders/sign-out | PASS â€” previews, confirmations, saved address after reload, unsaved decisions, password change, cart persistence and cancellation |
| Responsive design and sticky header | PASS â€” 1440px desktop and 390px mobile; screenshots saved under .runtime/screenshots |
| Application startup and real rendered Swagger UI | PASS â€” localhost:8087, /docs |
| Packaged non-web import command | PASS â€” empty synthetic export, isolated test schema; exits after commit |
| PHP export/fixture tools syntax | PASS â€” php -l |
| Source file hash audit | PASS â€” all inventoried source files unchanged |

JUnit XML and concise UI/browser/import outputs are in `docs/evidence`. The source inventory is `docs/source-inventory.sha256.json`; the inspected live DDL is `docs/inspected-source-schema.sql`. A separate read-only source table-count/checksum audit is compared again at delivery. No migration command, seed or test writes to the PHP database.

Implemented features include the 15-product storefront and matching illustrations, public sharing, persistent carts, transactional demo checkout, price/address snapshots, account-scoped orders/tracking, cancellation, Processing-only address changes and notifications, session/CSRF authentication, independent account/password forms, secure password reset, all five email types, Flyway migrations, Swagger, explicit legacy tools, environment examples, and Cloudflare instructions.

Remaining limits: no real payments; no durable email retry queue; no saved address/card management UI (same scope as PHP); password migration is verified for the source bcrypt hashes, not Argon2; legacy imports are explicit snapshots rather than continuous synchronization. Historical card numbers and active reset tokens are deliberately excluded from import. No real legacy accounts/orders were imported. The exporter was syntax checked; a real-data export/cutover was not run. Cloudflare configuration and HTTPS links are documented and exercised with a configured HTTPS APP_URL in integration tests, but an external tunnel was not launched. Local browser verification covers Edge at two viewport sizes; other browsers and external HTTPS end-to-end remain untested.

Final delivery check: the executable was launched from the requested sibling `Mobile_User_SpringBoot` folder. GET /shop, /products/1, /docs (including redirect), /swagger-ui/index.html and /openapi.json returned 200; the delivered document contains 23 operations; anonymous GET /api/cart returned 401. These checks used IPv4 loopback. All 57 inventoried source files still match their initial hashes, and all 10 source table counts/checksums match the read-only audit baseline.
