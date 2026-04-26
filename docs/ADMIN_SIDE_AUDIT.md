# Ultimate Admin Reference Manual (Filament Backend)

## Customer-Side Application Audit Addendum (Flutter Mobile)

Date: 2026-04-26  
Scope: Current customer mobile experience audit for production readiness (catalog, inquiries, orders, notifications, account and UX quality).

---

### 1) Completed Features Audit (Recently Implemented Flows)

#### 1.1 Authentication, Session and Role Gating

- Secure token persistence and startup session restoration are implemented.
- Login/register/logout flows are implemented and route users into role-based shells.
- Customer account settings now include password update and account close flow integration.

Primary references:

- `frontend/lib/main.dart`
- `frontend/lib/core/providers/auth_provider.dart`
- `frontend/lib/core/auth/auth_token_storage.dart`
- `frontend/lib/features/auth/data/auth_api.dart`
- `frontend/lib/features/auth/ui/login_screen.dart`
- `frontend/lib/features/auth/ui/register_screen.dart`
- `frontend/lib/features/profile/ui/customer_profile_screen.dart`

#### 1.2 Catalog and Product Discovery

- Catalog list renders categories, arrivals and products with loading/empty/error handling.
- Pull-to-refresh has been standardized and reused on customer surfaces.
- Product detail includes image carousel/zoom, pricing/weight display and inquiry CTA.

Primary references:

- `frontend/lib/features/catalog/ui/product_catalog_screen.dart`
- `frontend/lib/features/catalog/ui/product_detail_screen.dart`
- `frontend/lib/features/catalog/data/catalog_api.dart`
- `frontend/lib/core/ui/widgets/app_refresh.dart`

#### 1.3 Favorites / Saved Products

- Favorites are persisted locally (SharedPreferences) and surfaced in saved products.
- Heart toggle behavior is integrated in both catalog and product detail flows.
- Saved list handles stale/missing IDs gracefully.

Primary references:

- `frontend/lib/core/providers/saved_favorites_provider.dart`
- `frontend/lib/features/profile/ui/saved_products_screen.dart`

#### 1.4 Inquiries, Realtime Chat and Quote/Deposit Pipeline

- New inquiry flow supports product-linked initiation and media attachments.
- Firebase-backed realtime chat is integrated with Laravel bootstrap/token flow.
- Customer quote actions (accept/decline) and deposit-proof upload are implemented.
- Pipeline fallback behavior exists: when Firestore cards are absent, REST pipeline payloads are rendered.

Primary references:

- `frontend/lib/features/inquiries/ui/new_inquiry_screen.dart`
- `frontend/lib/features/inquiries/ui/inquiry_list_screen.dart`
- `frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart`
- `frontend/lib/features/inquiries/data/inquiry_api.dart`
- `frontend/lib/features/inquiries/models/inquiry_pipeline.dart`

#### 1.5 Orders, Timeline and Receipts

- Customer orders list, detail and timeline views are implemented.
- Timeline merges order lifecycle and artisan/progress context for customer visibility.
- Receipt list/detail and document download/open flow are implemented.

Primary references:

- `frontend/lib/features/orders/ui/customer_orders_screen.dart`
- `frontend/lib/features/orders/ui/customer_order_detail_screen.dart`
- `frontend/lib/features/orders/ui/customer_order_timeline_screen.dart`
- `frontend/lib/features/orders/ui/receipt_detail_screen.dart`
- `frontend/lib/features/orders/data/order_api.dart`

#### 1.6 Notifications and Unread UX

- In-app notifications list is implemented with mark-read behavior.
- Unread badge indicator is integrated into the customer bottom navigation.
- Push token registration and lifecycle handling are wired.

Primary references:

- `frontend/lib/core/notifications/ui/notifications_screen.dart`
- `frontend/lib/core/notifications/customer_notification_badge.dart`
- `frontend/lib/core/notifications/push_notification_manager.dart`
- `frontend/lib/core/notifications/data/notification_api.dart`

#### 1.7 Profile, Preferences and Shell UX Polish

- Profile screen now centralizes customer actions: saved pieces, display settings, password, account close.
- Display preferences are persisted and used by shared display widgets.
- Unified customer tab header and premium floating nav patterns are applied.
- Recent UX refinement: slimmer floating dock, tighter spacing and optional border style.

Primary references:

- `frontend/lib/features/profile/ui/customer_profile_screen.dart`
- `frontend/lib/features/profile/ui/display_preferences_screen.dart`
- `frontend/lib/core/providers/display_preferences_provider.dart`
- `frontend/lib/core/ui/widgets/customer_tab_header.dart`
- `frontend/lib/core/ui/widgets/premium_floating_nav_bar.dart`
- `frontend/lib/features/shell/customer_shell.dart`

---

### 2) Current Gaps for Production-Ready Jewelry E-commerce

#### 2.1 Functional Product Gaps

- No direct cart/checkout lane exists for ready-made purchases (current model is inquiry-first).
- Catalog search/sort/filter UI is limited compared to production expectations.
- No customer address book, profile edit (name/phone/avatar), or delivery preference workflow.

#### 2.2 Reliability and Performance Gaps

- Pagination/infinite scrolling is not consistently implemented for large datasets (catalog, orders, notifications).
- Network retry/backoff is not centralized; transient failures can surface abruptly.
- Some screens rely on per-screen fetch orchestration rather than shared data synchronization strategy.

#### 2.3 UX and Accessibility Gaps

- Error messaging still exposes raw exception text in some paths.
- Offline or poor-connectivity behavior (cache + stale indicators + recovery UX) is limited.
- Accessibility hardening is incomplete (dynamic text scaling QA, full semantics labeling audit, contrast regression checks).

#### 2.4 Security and Compliance Gaps

- Production hardening still needs strict HTTPS-only environment control and endpoint governance.
- No explicit certificate pinning strategy is documented for the mobile app.
- Privacy/legal UX coverage is incomplete (consent, retention notice for uploaded chat/inquiry media, account data export/deletion transparency).

#### 2.5 Observability and Quality Gaps

- Crash/error telemetry integration (e.g., Crashlytics/Sentry) is not formalized in customer app flows.
- Product analytics instrumentation is limited (no complete conversion funnel tracking).
- Automated test coverage remains low for critical customer journeys (integration/e2e).

---

### 3) New Feature Suggestions (Prioritized)

Priority key:

- P1: High business impact / launch critical
- P2: High value / near-term
- P3: Strategic enhancement

1. **P1 - Global error-handling layer + retry UX**
   - Map backend/network errors to customer-friendly copy and action buttons.
   - Add standard retry policies and fallback surfaces.

2. **P1 - Catalog search, sort and filtering**
   - Add query search, price/weight filters, ready-made toggle, and category facets.
   - Preserve filter state across tab switches.

3. **P1 - Pagination/infinite loading for large lists**
   - Apply to catalog, orders, inquiries and notifications.
   - Prevent frame drops and over-fetching on low-end devices.

4. **P1 - Observability baseline**
   - Add crash reporting and structured client logs with context IDs (inquiry/order).
   - Track top failure hotspots by screen and API endpoint.

5. **P1 - Customer journey analytics**
   - Track product view -> favorite -> inquiry -> quote accept -> deposit upload -> receipt open funnel.
   - Use event data to optimize conversion and concierge operations.

6. **P2 - Offline-first cache strategy**
   - Cache latest catalog/orders/inquiries for read mode during network loss.
   - Add "last synced" indicators and explicit refresh controls.

7. **P2 - Customer profile expansion**
   - Add editable profile fields, address book, and preferred contact channel.
   - Improve post-order and delivery/pickup communications quality.

8. **P2 - Advanced order tracking**
   - Add richer timeline cards (milestone ETA, artisan note, QC evidence thumbnails when available).
   - Improve confidence and reduce support chats.

9. **P2 - Security hardening**
   - Introduce stricter environment config validation and optional TLS pinning.
   - Add secure logging policy to avoid sensitive payload exposure.

10. **P3 - Loyalty and retention features**
    - Saved searches, restock alerts, and personalized recommendations.
    - Tiered membership/perk cues aligned with premium brand positioning.

---

### 4) Recommended Next Step (Execution Plan Snapshot)

For a production-readiness sprint, prioritize:

1. P1 foundation: error handling, pagination, observability, analytics.
2. Conversion uplift: catalog search/filter + optimized product discovery.
3. Trust and compliance: security hardening and privacy UX completion.

This sequence gives the best balance of launch risk reduction, conversion gains, and maintainability.

---

## Artisan App Documentation

The artisan mobile audit is now maintained separately in:

- `docs/ARTISAN_APP_AUDIT.md`

---

Date: 2026-04-25  
Scope: Complete enterprise-grade reference for the admin panel at `/admin`, including Command Center, inventory, bespoke production flow, settlement, reporting, governance, and system controls.

---

## Platform Overview

The admin panel is organized into fixed navigation groups:

- `Inventory`
- `Operations`
- `Finance`
- `Reports`
- `System Settings`

Core architecture principles:

- **Single-source business logic** in Laravel services (`OrderLifecycleService`, `PaymentVerificationService`, `OrderSettlementService`, etc.).
- **MySQL as source of truth** for formal states (`inquiries`, `quotes`, `orders`, `payments`, `receipts`).
- **Firebase as real-time delivery layer** for chat/timeline/push (with graceful fallback behavior).
- **Audit-first governance**: all high-impact admin actions are intended to be traceable.

---

## 1. The Command Center (Operational Intelligence)

### 1.1 Real-time KPI Row (Gold / FX / SLA Risk)

Primary widget: `CommandCenterKpisWidget`

KPIs:

- **Spot Gold**: latest active gold snapshot used in pricing calculations.
- **FX (MMK per USD)**: latest active exchange-rate snapshot used in USD conversions.
- **Active Inquiries**: live workload signal for concierge/admin response queue.
- **SLA Risk**: count of records breaching configured risk windows.

Operational notes:

- KPI refresh interval is controlled by business configuration (currency refresh interval and polling behavior).
- KPI anomalies should be validated against `Inventory > Gold prices` and `Inventory > Exchange rates`.

### 1.2 Operations Pulse (Pending Verifications + Active Production)

Primary widget: `OperationsPulseWidget`

- **Pending Verifications**: payments awaiting admin/finance decision.
- **Active Production**: current artisan assignment load.

Recommended response protocol:

1. When pending verifications rise, prioritize `Finance > Payments` by aging severity.
2. When active production rises, prioritize `Operations > Orders` with workflow-age triage.
3. Use color signals and badge severity as escalation prompts, not only informational indicators.

### 1.3 Revenue & Conversion Analytics

Dashboard analytics widgets:

- `RevenueBarChartWidget` (MMK/USD trend)
- `OrderStageDoughnutWidget` (status distribution)
- `InquiryConversionLineWidget` (inquiry-to-order conversion trend)

Data layer:

- `DashboardAnalyticsService` computes dashboard series from production data.
- Charts are responsive and aligned with the dark-gold admin theme.

Additional command center accelerators:

- `CommandCenterStripWidget` for quick pricing/audit links.
- `QuickExportWidget` for immediate sales-summary downloads.

---

## 2. Product & Inventory Management

### 2.1 Category Management

Resource: `CategoryResource`

Capabilities:

- Create/list/edit categories.
- Safe lifecycle support with delete/restore operations.
- Sort-order and slug-driven catalog organization.

### 2.2 Product Management (with Multi-Image Support)

Resource: `ProductResource` + `ImagesRelationManager`

Capabilities:

- Create/list/edit products.
- Manage multiple product images per SKU/design.
- Image relation manager supports add/edit/delete ordering flows.

### 2.3 Dynamic Pricing Engine (Gold/FX Snapshot Driven)

Resources:

- `GoldPriceSnapshotResource`
- `ExchangeRateSnapshotResource`

Behavior:

- New active snapshots immediately influence downstream price derivation.
- Catalog pricing signals and quote/order price computations depend on latest snapshots.
- Historical snapshots remain available for traceability and financial context.

---

## 3. The Bespoke Workflow (End-to-End)

### 3.1 Inquiry -> Quote (Live Concierge)

Resources:

- `InquiryResource` (list/view/edit)
- `ChatInquiry` page (real-time concierge workflow)

Capabilities:

- Real-time customer/admin inquiry chat.
- Inline quote builder with multi-line support (weight, labor, gems, product linkage, metal/stone details).
- Quote publish action sends customer-facing update and timeline records.
- Existing commitment checks/warnings are applied during quote publish.
- Inquiry-linked product prefill now auto-populates first quote line defaults (product, title, weight, labor, gems) while preserving explicit concierge edits.
- Concierge quote modal now includes a linked-product starter action and product selector (`SKU - Title`) so staff can start from catalog data instead of blank-slate entry.

### 3.2 Production Stage (Assignment + QC)

Resource: `OrderResource` (`ViewOrder` page actions)

Production controls:

- **Assign artisan** (scope, line-item targeting, instructions).
- **QC approve** guarded transition (`pending_qc -> ready`).

Enforcement:

- Lifecycle guardrails centralized in `OrderLifecycleService`.
- Status transitions are not UI-only; backend validates prerequisites.

### 3.3 Final Settlement (Pickup Closure)

Canonical closure flow uses **Record Final Payment** on order view.

Entry points:

- `Operations > Orders > View order > Record final payment`
- `Finance > Payments > Create` (final-payment create flow)

Dual settlement paths:

- **Bank/Wallet Transfer**
  - Requires `payment_profile_id`.
- **Cash Payment**
  - Explicit option in form.
  - `payment_profile_id` is optional/null.

Remaining balance logic:

- Computed via shared `OrderSettlementService`.
- Defaults final payment amount from outstanding balance.

Zero-Balance Safety Guard (duplicate prevention):

- If remaining balance is already zero:
  - Record final payment action is disabled or blocked.
  - Create flow validation prevents duplicate final settlement.
  - Admin sees clear “already fully settled” guidance.

### 3.4 Order Completion Automation

On successful final settlement:

- Final payment is confirmed through `PaymentVerificationService`.
- When order is `ready` and remaining balance reaches zero:
  - system auto-transitions order to `completed` via lifecycle guard.
- Customer receives completion/thank-you push notification.
- Official final receipt is generated.

This is the canonical close-out behavior for the bespoke journey.

### 3.5 Ready-made Branch Policy (Operational)

When an order is inferred as ready-made (all quote lines linked to ready-made catalog products):

- Full settlement path transitions directly to `ready` for pickup.
- Artisan assignment, in-production, and QC stages are skipped by backend lifecycle guards.
- Deposit-first remains supported (if needed by policy), but flow still bypasses production and moves to `ready` once settlement condition is met.
- Final-payment confirmation for ready-made does **not** auto-mark `completed`; it prioritizes pickup readiness (`ready`) to match store handover flow.

---

## 4. Financial Governance & Reporting

### 4.1 Payment Verification (Approve vs Quick Reject)

Resource: `PaymentResource`

Verification capabilities:

- Approve flow (confirmation)
- Reject flow with reason
- Quick Reject templates:
  - Incomplete amount
  - Blur image
  - Wrong account

Queue triage support:

- Aging badges, status indicators, filters, and grouped row actions.
- Export-selected actions for financial ops batch handling.

### 4.2 Official Documents (Final Receipt PDF)

Final receipt pipeline:

- Receipt generation by `ReceiptService`.
- Dedicated template: `resources/views/receipts/final-receipt.blade.php`.
- Includes:
  - itemized lines,
  - total price,
  - deposit paid,
  - final payment paid,
  - `FULLY PAID` stamp,
  - payment method display (`Cash` supported).

Download surfaces:

- **Admin**: order view action (`Download final receipt`).
- **Customer app**: receipt listing and detail download flow.

### 4.3 Reporting Hub (CSV / XLSX / PDF)

Resources:

- `ReportExportResource`
- `ReportExportHub`
- `QuickExportWidget`

Formats:

- PDF
- CSV
- XLSX

Coverage:

- Sales and payment exports
- Order exports
- Dynamic report ranges and quick monthly/today finance outputs

---

## 5. System Administration

### 5.1 Business Config (No-Code Policy Control)

Resource: `BusinessConfigResource`

Admin-managed runtime controls:

- Default deposit percentage
- Payment SLA risk hours
- Order SLA risk hours
- Currency refresh interval
- Payment accept template
- Payment reject template

Impact:

- Changes apply without code edits/redeploy.
- Affects dashboard behavior, workflow messaging, and risk indicators.

### 5.2 Security, Transparency, and User Control

#### Audit Logs

Resource: `AuditLogResource`

- Central evidence trail for admin/staff actions.
- Action filtering for workflow-critical events.
- Used for incident reconstruction and accountability reviews.

#### User Administration

Resource: `UserResource`

- Role-based user management.
- Account status control (`active` / `suspended`).
- Forced password reset control with token revocation.
- Artisan profile linkage control.

---

## 6. Additional Existing Features (Comprehensive Coverage)

### 6.1 Real-time Communications

- Push foundation is active:
  - token registration/deactivation endpoints,
  - user notification inbox/read APIs,
  - DB notification + FCM delivery attempt pattern.
- Key workflow events already trigger role-targeted notifications.

### 6.2 Table UX Standardization

Implemented polish:

- Consistent badge sizing/alignment.
- Triage-first column ordering in high-traffic tables.
- Action grouping for dense row action sets.
- Toggleable low-priority columns for smaller admin viewports.

### 6.3 Export/Download Stability

- Livewire-safe export approach is in place (route-based download responses).
- Admin export endpoints are centralized for predictable report delivery behavior.

### 6.4 Demo Data and Environment Readiness

- Command center demo seeding support exists (`CommandCenterDemoSeeder`).
- Local setup docs include final-payment/pickup smoke-test steps.

---

## 7. Canonical Order Closure Policy (Feature Freeze Baseline)

For FYP demo and operator consistency, the official closure standard is:

1. Move order through production and QC to `ready`.
2. Record final settlement via:
   - bank/wallet transfer with payment profile, or
   - cash payment without profile.
3. Enforce zero-balance guard (no duplicate settlement).
4. On confirmation:
   - auto-complete order,
   - send customer completion push,
   - issue official final receipt with `FULLY PAID` status.

This workflow is the enterprise-grade canonical closeout path for bespoke orders.

