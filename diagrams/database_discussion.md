# Database Module Discussion (Report Notes)

This file provides a concise technical explanation of the database architecture by **module**, along with the business rationale and key relationships to reference in the report discussion.

---

## Module 1 — Identity & Catalog (`diagrams/database_modules/auth_catalog.puml`)

### Technical purpose
- **Identity & access** is centered on `users` (role-based: `admin`, `artisan`, `customer`) with optional 1:1 profile extensions (`customer_profiles`, `artisan_profiles`).
- **Catalog** is modeled as `categories → products → product_images`, supporting curated ordering (`sort_order`) and marketing curation (`categories.is_featured`).

### Business logic justification
- **Role-based access**: storing `users.role` enables simple authorization gates for API + Filament without separate role tables.
- **Profiles as 1:1 extensions**: keeps `users` minimal while allowing role-specific fields (customer address/phone, artisan specialty/active).
- **Soft deletes for inventory**: `deleted_at` on categories/products supports “remove from listing” without breaking historical references.

### Key relationships (cardinality)
- **User to Profiles**
  - `users (1) — (0..1) customer_profiles`
  - `users (1) — (0..1) artisan_profiles`
- **Catalog**
  - `categories (1) — (0..*) products`
  - `products (1) — (0..*) product_images`

---

## Module 2 — Bespoke Quotation & Dynamic Pricing (`diagrams/database_modules/quotation_pricing.puml`)

### Technical purpose
- `inquiries` captures the customer’s request, optionally linked to a `product_id` (catalog-driven inquiry).
- Pricing is driven by **time-effective snapshots**:
  - `gold_price_snapshots` (MMK per gram)
  - `exchange_rate_snapshots` (MMK per 1 USD)
- `quotes` freeze the chosen snapshot IDs and totals; `quote_line_items` stores per-line breakdowns (metal/labor/gems) and **snapshot-derived USD**.

### Business logic justification
- **Why snapshots?**
  - Gold and FX are volatile; quotes must remain **auditable and historically correct** after rates change.
  - By storing `gold_price_snapshot_id` and `exchange_rate_snapshot_id` on `quotes`, the quote totals are reproducible later.
- **Why decimals / BCMath-friendly scales?**
  - Money and weight calculations require precision; `decimal(18,4)` (and `decimal(12,4)` for grams) supports stable arithmetic and aligns with BCMath usage in services.
  - Avoids floating-point rounding errors that would be unacceptable for pricing/receipts.
- **Why store computed line totals?**
  - Reduces recomputation risk and ensures quote lines remain consistent with the snapshot context used at the time of creation.

### Key relationships (cardinality)
- **Inquiry ownership & content**
  - `users (1) — (0..*) inquiries`
  - `products (1) — (0..*) inquiries` (optional reference)
  - `inquiries (1) — (0..*) inquiry_attachments`
  - `inquiries (1) — (0..*) inquiry_timeline_logs`
- **Inquiry to quote lifecycle**
  - `inquiries (1) — (0..*) quotes`
- **Quote pricing context**
  - `gold_price_snapshots (1) — (0..*) quotes`
  - `exchange_rate_snapshots (1) — (0..*) quotes`
- **Quote composition**
  - `quotes (1) — (1..*) quote_line_items`
  - `products (1) — (0..*) quote_line_items` (optional source)

---

## Module 3 — Order Fulfillment & Artisan Bridge (`diagrams/database_modules/order_artisan.puml`)

### Technical purpose
- `orders` represent the accepted fulfillment unit (often created from `quotes`), with `order_items` as immutable snapshots of what will be delivered.
- `payments` track deposit/final payments with proof and verification metadata; `receipts` are issued (1:0..1) per confirmed payment.
- `order_assignments` routes work to artisans, optionally scoped to a specific `order_item`.
- The **Artisan Bridge** uses:
  - `inquiry_image_candidates` (captures chat/inquiry images as selectable references)
  - `order_assignment_reference_images` (binds selected references to the assignment)
- `order_progress_events` and `order_progress_attachments` track production progress and media.

### Business logic justification
- **Order items as snapshots**
  - Orders must remain stable even if catalog products change later; snapshot fields (`title_snapshot`, pricing totals) preserve the committed spec.
- **Payments separated from orders**
  - Supports multiple payment events (deposit + final), verification workflow, and auditability.
- **Receipts as an immutable artifact**
  - `receipts` store the generated PDF location + checksum, enabling repeatable verification and customer downloads.
- **Artisan bridge tables**
  - Chat images exist in realtime delivery systems; mirroring key references into MySQL enables reliable selection, reporting, and consistent delivery to artisan payloads.

### Key relationships (cardinality)
- **Order creation context**
  - `users (1) — (0..*) orders`
  - `quotes (1) — (0..1) orders`
  - `exchange_rate_snapshots (1) — (0..*) orders`
- **Order structure**
  - `orders (1) — (1..*) order_items`
  - `products (1) — (0..*) order_items` (optional source)
- **Payments & receipts**
  - `orders (1) — (0..*) payments`
  - `payment_profiles (1) — (0..*) payments`
  - `payments (1) — (0..1) receipts`
  - `orders (1) — (0..*) receipts`
  - `users (1) — (0..*) receipts`
- **Assignments & references**
  - `orders (1) — (0..*) order_assignments`
  - `order_items (1) — (0..*) order_assignments` (optional per-line scope)
  - `inquiries (1) — (0..*) inquiry_image_candidates`
  - `order_assignments (1) — (0..*) order_assignment_reference_images`
  - `inquiry_image_candidates (1) — (0..*) order_assignment_reference_images`
- **Production progress**
  - `orders (1) — (0..*) order_progress_events`
  - `order_progress_events (1) — (0..*) order_progress_attachments`

