# Ultimate Admin Reference Manual (Filament Backend)

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

