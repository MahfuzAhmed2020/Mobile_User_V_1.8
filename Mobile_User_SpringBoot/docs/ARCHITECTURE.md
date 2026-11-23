# Implementation notes

`Domain` contains persistence models and `Repositories` the Spring Data JPA repositories. `StoreService` owns transactions and account-scoped business rules; request IDs never supply the authenticated account. API routes use validated request records and explicit error statuses. `PageController` renders Thymeleaf pages; original store CSS, DOM identifiers, account interactions and SVG illustration paths are retained under `static/assets`.

Cart writes, checkout, order cancellation and address changes acquire a pessimistic account lock. Checkout also acquires product read locks so the reviewed comparison and charged snapshots use consistent prices. A unique `(user_id,submission_key)` and request fingerprint protect retries. The order, items, tracking and cart deletion share one transaction. Order item names/prices are stored independently of the current catalog; delivery text is snapshotted too.

Password resets use 32 cryptographically random bytes encoded as 64 hex characters. Only SHA-256(token) and a one-hour expiry are stored. Token lookup takes a write lock, checks expiry, changes the password, and clears the token in one transaction. Profile password changes require the current password and clear pending reset tokens. Passwords use bcrypt cost 12; input is limited to 72 UTF-8 bytes to avoid silent bcrypt truncation.

Spring Security persists authentication in an HttpOnly session, changes the session ID at login and clears the session/cookies at POST logout. CSRF protects anonymous and authenticated mutations. Cookies are SameSite=Lax, secure when configured for HTTPS. Email links and canonical URLs use validated APP_URL, independently of request Host. Referrer-Policy is no-referrer to reduce reset-link leakage. Sharing constructs URLs using only product IDs.

Spring transaction-bound events deliver email after commit and catch SMTP failures. Responses expose best-effort email_sent status; there is no durable outbox. Address changes compare saved choices and formatted addresses to suppress unchanged notifications. Cancellation soft-deletes the order and tracking as in the source.

`DatabaseGuard` rejects any datasource whose database name does not start with mobile_user_springboot_. The generated local database account is restricted to its new dev/test schemas. Flyway owns DDL and validates checksums; Hibernate only validates mappings. The allowlisted legacy import runs separately and has no HTTP endpoint.
