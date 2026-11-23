# Explicit legacy import

Nothing imports on normal startup. Do not run database/setup.php, database/seed_demo.php or PHP tests against the working PHP database as part of this migration.

The importer is an explicit one-shot, non-web Spring profile. It only writes to a guarded `mobile_user_springboot_*` target. All data changes and identifier mappings commit atomically; a missing reference, unsupported password hash, duplicate email, conflicting product, missing order snapshot/tracking record, or uniqueness violation rolls back the import. It never overwrites an existing account or order. It emits no notification emails.

1. Make a database backup through your normal administration process. Stop writes to the source while preparing the final cutover export. Rehearse against a new `mobile_user_springboot_test_<suffix>` schema before importing into the separate development/migration target. Keep the PHP application and database running independently until review is complete.
2. Provision a SELECT-only account on the source schema. Set `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_NAME`, `LEGACY_DB_USER`, and `LEGACY_DB_PASSWORD` through the environment. These are read by the PHP exporter, not copied into the new app. Enable PDO MySQL in PHP. Do not obtain credentials from project notes.
3. Set `LEGACY_TIMEZONE` to the timezone used when the old application wrote `deleted_at` DATETIME values (for example `America/New_York` only if that was its configured timezone). The exporter reads TIMESTAMP columns in UTC and converts DATETIME values using this explicit zone. Validate dates in the rehearsal. Set a stable nonpersonal `IMPORT_SOURCE_KEY`, such as `mobile-user-v18`. Use the same key on every rerun for this source.
4. Choose a new protected export file outside the source tree and version control. The exporter refuses to overwrite an existing export:

```powershell
$env:LEGACY_DB_HOST='127.0.0.1'
$env:LEGACY_DB_PORT='3306'
$env:LEGACY_DB_NAME='Mobile_User_db'
$env:LEGACY_DB_USER='your_select_only_export_account'
# Set LEGACY_DB_PASSWORD securely, without putting it in command history.
$env:LEGACY_TIMEZONE='America/New_York' # Verify this against the original runtime.
$env:IMPORT_SOURCE_KEY='mobile-user-v18'
$env:EXPORT_FILE='C:\protected-migration\mobile-user-v18.json'
php tools/export-legacy.php
```

This exporter uses a read-only transaction and an explicit table/column allowlist. It includes necessary account names/emails/password hashes, products, related delivery addresses, carts, orders, price snapshots and tracking. The export is sensitive account data: restrict filesystem access and retain/delete it according to your migration process. It excludes active password reset tokens, email whitelist records, all saved card numbers, project notes and database credentials.

5. Configure DB_* for the new target, never for the source. Build the JAR, then run:

```powershell
$env:IMPORT_FILE='C:\protected-migration\mobile-user-v18.json'
java -jar target/mobile-user-1.0.0.jar --spring.profiles.active=legacy-import --spring.main.web-application-type=none
```

Flyway first initializes or validates the target schema. The import then commits atomically and exits. `legacy_imports` records the source key, legacy table/id, and new identifier. IDs need not match the PHP IDs. Products are matched by exact name/description/price or inserted if no name conflict exists. Orders keep original item names, unit prices, quantities, totals, timestamps, chosen delivery address and tracking. Cancellations retain soft-delete flags. Source card numbers are never imported; historical card_last_four is null. Imported accounts receive idempotent sample cards/addresses for demo use.

Rerunning an unchanged export with the same source key skips mapped rows. It never synchronizes edits to already imported data or overwrites new app changes. A different source key with duplicate emails fails for review rather than merging identities. If the source changed after an earlier import, use a fresh target and a fresh complete export for final cutover, or implement and review an explicit reconciliation migration. Do not change source keys merely to bypass a conflict.

6. Review counts and joins in `legacy_imports`, compare historical totals and price snapshots, log in with a deliberately created legacy test account, and inspect cancellation/tracking/address behavior. Only proceed with real cutover after reviewing the rehearsal. The delivered tests rehearse identifier remapping, snapshots, bcrypt login, repeat import, conflicts and rollback using synthetic data. No real legacy account/order import was run.

Password compatibility: the inspected source uses `$2y$` bcrypt (`password_hash(..., PASSWORD_DEFAULT)` in its current PHP runtime). ASCII and UTF-8 hashes produced by PHP are checked through Spring authentication. The importer accepts bcrypt `$2a$`, `$2b$`, `$2y$` syntax and refuses unsupported algorithms; do not assume Argon2 compatibility. Active reset links are deliberately invalidated by exclusion from export.
