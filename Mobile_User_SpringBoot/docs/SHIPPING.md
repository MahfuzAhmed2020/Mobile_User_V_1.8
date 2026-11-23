# Delivery and tracking

Checkout now requests `POST /api/shipping/rates` with an owned `address_id`, then submits the selected `delivery_quote_id` and the combined merchandise + delivery `expected_total` to `/api/checkout`. Quotes expire after 15 minutes and are bound to the account, saved address, item quantities, and prices. Checkout rechecks all of these and keeps existing submission-key replay protection. Flyway V3 adds quotes and shipping snapshots without changing old orders.

With no API key, the UI explicitly offers demo Standard ($0) and Express ($12) services. Legacy API clients may omit a quote only in this mode. No carrier movement is simulated.

To use Shippo, set `SHIPPO_API_KEY` to your own test key and `SHIPPO_ORIGIN_JSON` to a JSON sender address containing `name`, `street1`, `city`, `state`, `zip`, `country` (US), `phone`, and `email`, then restart the application. Use real deliverable saved addresses for carrier testing; seeded addresses are examples. No credentials are copied from the separate shippo_api project, and its database is never used.

The current packing model is a 12 x 10 x 8 inch package with 2 lb per unit and a 20-unit limit. This is a demo estimate, not production warehouse data. Only US domestic shipping and USD rates are supported. Delivery days are carrier transit estimates excluding handling time.

On My orders, **Create test shipping label** calls `POST /api/orders/{id}/test-label`. It requires an owned active order, an unexpired carrier quote, and a `shippo_test_` key. It does not run automatically at checkout. Repeated successful requests return the saved label. The label URL is available in the owned order API response. If Shippo times out, inspect its dashboard before retrying: the provider and local database do not share a transaction.

**Refresh tracking** calls `POST /api/orders/{id}/tracking/refresh`, fetching normalized carrier status with a 60-second cache. The local order reference remains stable, and the carrier tracking number is displayed separately. Order access remains authenticated and owner-scoped with CSRF on mutations. Rated orders cannot change address without a new checkout; cancellation is restricted to Processing orders. Labels mark an order as Label created, not Shipped.

Production fulfillment still requires actual catalog weights/dimensions, warehouse packing rules, real payment capture, a staff-authorized fulfillment workflow, durable label-job reconciliation and retries, and authenticated webhook/event processing. This application intentionally rejects live label purchases because its cards and payments are demos. Quote retention cleanup should be scheduled before high-volume deployment, excluding quotes referenced by orders.

The reference project's shipment/rate/label flow was adapted to the store's existing ownership, Flyway, CSRF, and checkout protections. The integration uses bounded-timeout REST calls instead of its legacy SDK.

Provider reference: https://docs.goshippo.com/docs/guides_general/authentication/

## Checkout confirmation

After checkout the confirmation displays the order tracking reference, a locally generated Code 128 barcode, and a printable delivery preview. The preview is clearly marked as demo and is not valid postage. When the order has an eligible Shippo test rate, the browser separately requests a test label after the order is saved, then displays the carrier tracking barcode and embedded PDF with an open/download link. Label failure keeps the successful order confirmation and preview visible, and never resubmits checkout. No tracking data is sent to an external barcode service.

## PDF order emails

A newly committed order sends a multipart email with `mobile-user-order-{id}.pdf` attached. The PDF contains the customer name, delivery address, snapshotted items and prices, merchandise and shipping totals, order tracking reference, Code 128 barcode, and the authenticated tracking URL. Generation and SMTP run after commit and never undo a saved order. Identical checkout retries do not send another email. `email_sent` reports email acceptance by the configured SMTP server and `pdf_attached` reports attachment success for the initial checkout response. Neither flag guarantees inbox delivery. No full card numbers are placed in the PDF.

Local development uses MailHog at http://127.0.0.1:8025 (SMTP 1025). Open an order confirmation there and download its attachment. PDF generation is in-process using PDFBox, so Python or a PDF service is not required by the deployed application. The PDF is a demo order confirmation, not paid postage or a tax invoice. There is no durable mail retry queue.
