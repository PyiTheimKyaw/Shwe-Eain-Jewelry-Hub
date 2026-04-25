# Admin Side Audit (Filament Backend)

Date: 2026-04-25  
Scope: Filament admin panel under `backend/app/Filament`, connected Laravel services/controllers, and production-readiness posture after the Command Center rollout.

---

## 1) Currently Implemented & Functional Features

### 1.1 Navigation Structure (Current)

The admin panel at `/admin` is active with explicit group ordering and command-center-first IA.

**Inventory**
- `CategoryResource` (list/create/edit)
- `ProductResource` (list/create/edit) + `ImagesRelationManager`
- `GoldPriceSnapshotResource` (list/create; history-oriented)
- `ExchangeRateSnapshotResource` (list/create; history-oriented)

**Operations**
- `InquiryResource` (list/view/edit) + `ChatInquiry` live concierge page
- `OrderResource` (list/view) with assignment/QC workflow actions

**Finance**
- `PaymentResource` (list/view/edit with verification actions)
- `PaymentProfileResource` (list/create/edit)

**Reports**
- `ReportExportResource` + `ReportExportHub`
- PDF / XLSX / CSV export flows with date-range filters and quick dashboard entry points

**System Settings**
- `UserResource` (list/create/edit; role-aware artisan profile fields)
- `AuditLogResource` (read-only list/view for governance traceability)
- `BusinessConfigResource` (single-record global business variables console)

### 1.2 Command Center Dashboard (Implemented)

- **Dedicated dashboard page** (`App\Filament\Pages\Dashboard`) with curated widget composition.
- **KPI row**:
  - `CommandCenterKpisWidget`: spot gold & FX, active inquiries, SLA-risk orders.
- **Operations Pulse row**:
  - `OperationsPulseWidget`: pending payment verifications + active production assignments.
- **Action strip**:
  - `CommandCenterStripWidget`: global pricing snapshot shortcuts + audit trail access.
- **Analytics charts (real DB data)**:
  - `RevenueBarChartWidget` (12-month MMK/USD revenue trend),
  - `OrderStageDoughnutWidget` (pending/production/QC/ready),
  - `InquiryConversionLineWidget` (6-month inquiry→order signal).
- **Theming**:
  - Chart palette and card accents aligned to luxury navy/gold/cream visual system.

### 1.3 Backend Analytics & Reporting Stack (Implemented)

- `DashboardAnalyticsService` centralizes chart/pulse metrics:
  - `monthlyRevenueSeries(...)`
  - `orderStageDistribution()`
  - `inquiryOrderConversionSeries(...)`
  - `slaBreachCount()`
- Real-data export and reporting pipeline:
  - dynamic report service (`ReportExportService`)
  - PDF views (sales summary, order detail, payment detail)
  - bulk CSV/XLSX exports on key operational tables
  - Livewire-safe export routes/controllers.

### 1.4 Workflow & Governance Logic (Implemented)

- **Payment verification** is centralized and guarded (`PaymentVerificationService`).
- **Order lifecycle** transitions are centralized (`OrderLifecycleService`) and reused across API + Filament.
- **Audit logging** is centralized (`AdminAuditLogService`) and surfaced in admin UI.
- **Quote/deposit supersession behaviors** and payment reparenting logic are enforced in backend services.
- **Realtime touchpoints** are integrated across MySQL + Firestore with fallback-safe behavior.
- **Business policy variables are DB-managed** through `BusinessConfigService` (deposit %, SLA thresholds, refresh interval, notification templates).
- **Push notification backbone is implemented**:
  - device token registration + deactivation,
  - in-app notification inbox/read APIs,
  - FCM dispatch attempt layer with graceful fallback to DB-only notifications.

### 1.5 Data Readiness for Demo/Testing (Implemented)

- Seeder support now includes command-center demo data (`CommandCenterDemoSeeder`) for:
  - inquiries,
  - orders with stage spread,
  - payments with monthly revenue spread,
  - active assignments for pulse widgets.
- `CatalogSeeder` is patched for safer re-runs with soft-deleted categories.

### 1.6 P0/P1 Table UI Polish (Implemented)

- **PaymentsTable**
  - aging badge (`Submitted X ago`) with green/yellow/red thresholds,
  - quick reject action with common reasons,
  - reject flow still routed through existing `PaymentVerificationService` guards,
  - row actions grouped into a clean **Actions** dropdown.
- **OrdersTable**
  - workflow age badge,
  - custom stage badge colors,
  - quick action to open related inquiry chat,
  - triage-first column ordering.
- **InquiriesTable**
  - unanswered marker,
  - current quote status badge,
  - triage-first column ordering.
- **UsersTable**
  - account status indicators and suspend/activate action,
  - forced password reset action with token revocation.
- **Cross-table visual consistency**
  - normalized badge width/alignment in admin theme for professional status readability.
- **Responsive table density**
  - lower-priority columns set as toggleable defaults for tablet/smaller admin screens.

### 1.7 Phase 3 Real-time Communication (Kickoff Implemented)

- **API endpoints (auth required)**
  - `POST /api/v1/push/devices`
  - `DELETE /api/v1/push/devices`
  - `GET /api/v1/notifications`
  - `POST /api/v1/notifications/{notificationId}/read`
- **Role-targeted real-time events currently wired**
  - Admin notified on new inquiries, deposit-proof submissions, customer quote decisions.
  - Customer notified on quote publish and payment verification outcomes.

---

## 2) Fresh Gap Analysis — What Is Still Missing for 100% Production Readiness

### 2.1 Security, Governance, and Compliance Gaps

1. **Fine-grained authorization matrix**
   - Current role-based controls are solid, but production needs capability-level permissions (finance_verifier, ops_manager, catalog_editor, read_only_auditor).
2. **Maker-checker controls for high-risk actions**
   - Payment confirmation, large quote publication, and irreversible workflow transitions should support dual-approval thresholds.
3. **Immutable compliance-grade audit package**
   - Current logs are good operationally, but missing tamper-evident storage/signatures, retention policy controls, and export certifications.
4. **PII/security hardening for report distribution**
   - Role-scoped report visibility and download watermarking/expiry tokens should be formalized for finance/legal use.

### 2.2 Reliability & Operability Gaps

1. **Proactive alerting and escalation engine**
   - SLA breaches are visible, but not automatically escalated to owners with reminders/escalation ladders.
2. **Operations runbook controls**
   - Missing in-panel tools for replaying failed sync events, forcing pipeline reconciliation, and inspecting queue health.
3. **Async job observability**
   - Need deeper queue diagnostics (dead-letter views, retry controls, per-job latency/error dashboards).
4. **Disaster recovery and data integrity workflows**
   - Backup verification routines, restore drills, and post-restore consistency checks are not operationalized in admin.

### 2.3 Product/UX Maturity Gaps

1. **Drill-down analytics**
   - Charts are strong but need click-through drilldowns (e.g., bar click → filtered orders/payments table).
2. **Forecasting and trend intelligence**
   - No predictive layer yet (revenue forecast, conversion probability, backlog burn-down ETA).
3. **Scheduled reporting**
   - Exports are manual; production needs scheduled report delivery with recipient policies.
4. **Cross-surface config propagation**
   - Business-config variables are now UI-managed; remaining gap is formal propagation visibility/versioning across all surfaces and clients.

---

## 3. Detailed Admin Operations & Workflow Manual

This section is a practical, click-by-click operations guide for day-to-day admin usage in `/admin`.

### 3.1 Command Center Operations

#### A) Spot Gold & FX KPIs (What they mean + refresh behavior)

**Where to look**
- Open `/admin` dashboard.
- Top KPI row (`CommandCenterKpisWidget`) shows pricing snapshots and operational risk.

**What each KPI means**
- **Spot Gold**: latest active gold-price snapshot used for MMK pricing logic in catalog/quote/order calculations.
- **FX (`MMK per USD`)**: latest active exchange-rate snapshot used for USD conversions and frozen USD snapshots in transactions.
- **Active Inquiries**: currently open inquiry load requiring concierge/admin attention.
- **SLA Risk**: count of items breaching configured SLA thresholds.

**Refresh behavior**
- KPIs auto-refresh using the polling interval derived from `BusinessConfigService` (currency refresh interval in settings).
- Manual refresh path:
  1. Use dashboard refresh (browser refresh or widget-level refresh actions where available).
  2. Verify values against Pricing resources if numbers look stale.

**When KPI values drift unexpectedly**
1. Go to `Inventory > Gold prices` and `Inventory > Exchange rates`.
2. Confirm a new snapshot exists and is active.
3. Return to dashboard; KPIs should reflect latest snapshot on next poll cycle.

#### B) Operations Pulse (Pending Verifications + Active Production)

**Where to look**
- Dashboard row under KPIs (`OperationsPulseWidget`).

**Meaning**
- **Pending Verifications**: payment proofs waiting for finance/admin decision.
- **Active Production**: work currently assigned/in-progress with artisans.

**How to respond when numbers rise**
- If **Pending Verifications rises**:
  1. Open `Finance > Payments`.
  2. Sort/filter to oldest pending.
  3. Clear red/yellow aging badges first (oldest risk first).
- If **Active Production rises**:
  1. Open `Operations > Orders`.
  2. Filter status to `in_progress` / `pending_qc`.
  3. Check workflow age badges; prioritize oldest items.
  4. Ensure assignees/QC transitions are not blocked.

**Operational rule of thumb**
- Growing pulse counts over multiple refresh cycles indicate queue congestion; shift admin effort from catalog/reporting tasks to queue-clearing actions.

### 3.2 Financial & Order Workflows

#### A) Verify a Payment Proof (Step-by-step)

**Primary screen**
- `Finance > Payments` table.

**Triage signals**
- **Aging badge** (`Submitted X ago`):
  - Green: fresh
  - Yellow: mid-risk
  - Red: urgent/stale
- **Status badge**: pending/confirmed/rejected state.

**Approve flow**
1. Click row `Actions`.
2. Click `Confirm` / `Approve` verification action.
3. System behavior on click:
   - `PaymentVerificationService::confirm()` runs.
   - Payment status changes to confirmed.
   - Order deposit milestone/state-machine checks are applied.
   - Timeline/chat update is emitted using configured accept template.
   - Audit log record is written.
   - Push notification is sent to customer (DB inbox + FCM attempt).

**Quick Reject flow**
1. Click row `Actions`.
2. Click `Quick reject`.
3. Select reason template:
   - Incomplete amount
   - Blur image
   - Wrong account
4. Confirm reject.
5. System behavior on click:
   - `PaymentVerificationService::reject()` runs.
   - Payment status becomes rejected.
   - Timeline/chat update uses reject template (from business config).
   - Audit log record is written.
   - Push notification is sent to customer (DB inbox + FCM attempt).

**State-machine safety**
- Verification actions are guarded server-side; invalid transitions are blocked by service logic even if attempted from UI.

#### B) Handle Inquiry -> Publish Quote -> Assign Artisan

**Step 1: Open and assess inquiry**
1. Go to `Operations > Inquiries`.
2. Use left-priority columns:
   - Unanswered marker
   - Inquiry status
   - Quote status shortcut
3. Open inquiry chat (`ChatInquiry`) from row action/view.

**Step 2: Prepare and publish quote**
1. Inside inquiry workflow, create/update quote lines.
2. Set metal/weight/labor/gems as required.
3. Click `Publish quote`.
4. System behavior on click:
   - Quote status moves to sent/published state.
   - Commitment/deposit checks run.
   - Warning appears (not hard stop) if deposit already exists and recalculation context applies.
   - Chat/timeline card is emitted.
   - Customer push notification is triggered.
   - Audit entry is created.

**Step 3: Customer accepts -> order emerges**
- On customer accept, backend creates/supersedes order according to quote/order rules (including deposit-credit carryover where applicable).

**Step 4: Assign artisan**
1. Go to `Operations > Orders`.
2. Open target order (view page).
3. Click `Assign artisan`.
4. Choose artisan + instructions and confirm.
5. System behavior on click:
   - `OrderLifecycleService` transition guard executes.
   - Assignment row is created/updated.
   - Order moves into production-eligible flow.
   - Artisan notification is emitted.
   - Audit log entry is recorded.

#### C) Business Config Console (No-code policy changes)

**Where**
- `System Settings > Business Config`.

**What admin can change**
- Default Deposit Percentage
- Payment/Order SLA thresholds (hours)
- Currency refresh interval
- Default notification templates (accept/reject)

**How to update**
1. Open `Business Config` list.
2. Click `Edit`.
3. Change values in form fields.
4. Click `Save`.

**What happens when Save is clicked**
- Values persist to `business_configs` table.
- `BusinessConfigService` starts serving updated values to runtime logic.
- Dashboard polling/SLA logic and template-driven messages reflect new values without code changes or redeploy.
- Audit log record is written for config update.

### 3.3 Real-time Interactions

#### A) Auto-triggered push notifications (Admin-side origin)

Push notifications are emitted automatically from admin actions and workflow events, including:
- Quote publish/status changes,
- Payment verification outcomes,
- New inquiry/deposit-proof events routed to admins where configured.

**What actually happens**
1. Admin clicks action (e.g., publish quote, confirm payment).
2. Service layer performs state transition.
3. `PushNotificationService` writes a DB notification record.
4. Service attempts FCM delivery to active tokens.
5. If FCM fails/unavailable, DB inbox remains as reliable fallback.

#### B) Broadcast Tool (manual announcements)

If your panel build exposes a **Broadcast** action/tool, use this sequence:
1. Open broadcast UI (from command center strip or notifications utility area).
2. Select audience scope (all users / role-specific segment).
3. Enter title + body + optional deep-link metadata (`inquiry_id`, `order_id`, etc.).
4. Click `Send broadcast`.

**Expected behavior on send**
- Notification payload is stored to recipients’ inbox records.
- FCM push attempt is sent to active devices.
- Action appears in audit trail with actor + target scope.

If broadcast UI is not visible in current build, keep using event-driven auto notifications and schedule broadcast tool enablement as a follow-up enhancement.

### 3.4 Governance & Traceability

#### Audit Logs usage (staff/admin transparency)

**Where**
- `System Settings > Audit Logs`.

**How to investigate actions**
1. Open logs list.
2. Apply action filters (payment confirmed/rejected, QC approved, artisan assignment, etc.).
3. Open a row in view page for full context.

**What each record gives you**
- **Who**: acting admin/staff user.
- **What**: action code/type.
- **When**: timestamp.
- **Where/Target**: model/entity affected.
- **Context**: additional payload details for incident review.

**Recommended governance routine**
- Daily: review payment verification and assignment actions.
- Weekly: sample-check business-config changes and high-impact transitions.
- Incident: filter by time window + action type, then reconstruct sequence from logs.

---

## 4) Surprise Features for Final Demo (Intelligence + Luxury Impact)

1. **Concierge Copilot Sidebar (AI-assisted admin guidance)**
   - “What needs attention now?” panel with plain-language recommendations:
   - e.g., *“3 deposits pending > 24h; prioritize Order #X and #Y first.”*
2. **Smart SLA Heatmap + Priority Ranking**
   - Color-graded risk board that auto-ranks orders/inquiries by urgency and customer value.
3. **Executive ‘One-Tap Story Mode’**
   - Auto-play dashboard walkthrough for judges/stakeholders:
   - KPI summary -> revenue trend -> conversion trend -> actions queue.
4. **Luxury Customer Journey Timeline Replay**
   - Visual replay of one completed bespoke order from inquiry to QC-ready, including quote/payment/assignment milestones.
5. **Predictive Revenue Pulse (next 30 days)**
   - Lightweight forecast widget from existing payment/order momentum (even a simple model creates strong “intelligent system” perception).
6. **VIP Flag + White-Glove Queue**
   - Highlight high-value or repeat customers with distinct gold tagging and SLA tightening.

---

## 5) Remaining UX / Logic Polish Needed in Existing Resource Tables

### Payments Table (`PaymentsTable`)
- Add **column-level filtering** by computed aging bands.
- Add sticky **queue tabs** for “Needs action now” and “Stale > X hours”.

### Orders Table (`OrdersTable`)
- Add **current assignee** column for faster triage.
- Add optional **kanban-style quick view** toggle for stage-oriented operations.

### Inquiries Table (`InquiriesTable`)
- Add **last message time** and **unread/admin-unanswered marker**.
- Add stronger default sorting by recency + priority risk.

### Audit Logs Table (`AuditLogsTable`)
- Add **target summary rendering** (human-friendly target labels, not only class/id).
- Add saved filter presets:
  - Payment approvals
  - Workflow transitions
  - Pricing updates
- Add JSON context formatter with key highlighting for faster incident review.

### Users Table (`UsersTable`)
- Add **last login** and session/device visibility.
- Add role-scoped list presets for operations handover.

### Report Export Hub (`ReportExportHub`)
- Add **saved export presets** (e.g., “Monthly Finance Pack”).
- Add **async export progress + retry** handling for large date ranges.
- Add export history with initiator, filters, file size, and expiry.

---

## 6) Senior Architect Verdict

The admin platform is now **well beyond prototype baseline**: core business workflows are service-backed, auditability exists, exports are real, and the Command Center provides strong operational intelligence with luxury presentation quality.

To reach **100% production readiness**, the next decisive layer is not basic functionality - it is **operational rigor**:
- granular permissions,
- escalation automation,
- compliance-grade audit/report controls,
- incident runbook tooling,
- analytics drilldowns/predictive intelligence.

If these are completed, the system can credibly transition from FYP-grade excellence to enterprise-grade operational readiness.

---

## 7) Complete Admin Function Reference (Current Panel Build)

This is the complete function inventory for currently available admin-panel capabilities in `/admin`, organized by navigation and page.

### 7.1 Global Panel Functions

- **Authentication**
  - Admin login at `/admin/login`.
  - Session-authenticated access middleware is active.
- **Navigation groups (fixed order)**
  - `Inventory` -> `Operations` -> `Finance` -> `Reports` -> `System Settings`.
- **Theme and identity**
  - Primary Filament color is configured to luxury gold (`#D4AF37`).
- **Top-right account utilities**
  - Filament `AccountWidget` available on dashboard.

### 7.2 Dashboard (`Command center`) Functions

#### Header action
- **Download Current Month Sales Summary (PDF)**
  - Button opens `admin.reports.sales-summary.current-month` in new tab.

#### Widgets and available actions
- **CommandCenterKpisWidget**
  - Spot Gold KPI.
  - FX KPI (MMK per USD).
  - Active inquiries KPI.
  - SLA-risk KPI.
- **OperationsPulseWidget**
  - Pending verifications counter.
  - Active production counter.
- **CommandCenterStripWidget**
  - `New gold snapshot`.
  - `New FX snapshot`.
  - `Gold history`.
  - `FX history`.
  - `Audit trail`.
- **QuickExportWidget**
  - `Today's Sales Summary`.
  - `Current Month PDF`.
- **RevenueBarChartWidget**
  - 12-month revenue chart (MMK/USD).
- **OrderStageDoughnutWidget**
  - Stage distribution (pending/production/QC/ready).
- **InquiryConversionLineWidget**
  - Inquiry->order trend chart.

### 7.3 Inventory Group Functions

#### A) Categories (`CategoryResource`)
- **Pages**
  - List categories.
  - Create category.
  - Edit category.
- **Row actions**
  - Edit.
- **Bulk actions**
  - Delete.
  - Force delete.
  - Restore.

#### B) Products (`ProductResource`)
- **Pages**
  - List products.
  - Create product.
  - Edit product.
- **Row actions**
  - Edit.
- **Edit page header actions**
  - Delete.
  - Force delete.
  - Restore.
- **Bulk actions**
  - Delete.
  - Force delete.
  - Restore.
- **Relation manager: product images**
  - Create image.
  - Edit image.
  - Delete image.
  - Bulk delete images.

#### C) Gold Prices (`GoldPriceSnapshotResource`)
- **Pages**
  - List snapshots.
  - Create snapshot.
- **Operational effect**
  - New snapshot updates pricing inputs used by catalog/quote/order computations.

#### D) Exchange Rates (`ExchangeRateSnapshotResource`)
- **Pages**
  - List snapshots.
  - Create snapshot.
- **Operational effect**
  - New snapshot updates MMK/USD conversion inputs and dashboard FX display.

### 7.4 Operations Group Functions

#### A) Inquiries (`InquiryResource`)

- **Pages**
  - List inquiries.
  - View inquiry.
  - Edit inquiry.
  - `ChatInquiry` concierge console page.

- **List table functions**
  - Live polling list.
  - Response-state badge (`Unanswered` / `Responded`).
  - Inquiry status badge.
  - Quote-status badge (`Draft`, `Sent`, `Accepted`, etc.).
  - Filters:
    - Status.
    - Created window.
  - Row actions:
    - View.
    - Edit.

- **View page functions**
  - `Open chat` header action.
  - `Edit` header action.
  - Auto-refresh polling for current inquiry state.

- **ChatInquiry page functions**
  - Live admin/customer chat thread (when Firebase web config is available).
  - Quote line builder:
    - Add line.
    - Remove line.
    - Set line fields (`weight`, `labor`, `gem`, `product`, `custom title`, `metal type`, `stone details`).
  - Quote publication:
    - `publishQuoteFromConsole()`.
    - Validation and pricing snapshot guard.
    - Sends quote card and timeline entry.
  - Payment instructions workflow:
    - Select active payment profile.
    - `sendPaymentInstructionsToChat()`.
    - Creates/updates deposit payment instruction state.
  - Pipeline refresh:
    - Manual and polling sync (`pollChatSync`/`refreshPipeline`).

#### B) Orders (`OrderResource`)

- **Pages**
  - List orders.
  - View order.

- **List table functions**
  - Workflow age badge.
  - Status badge with stage colors.
  - Filters:
    - Status.
    - Created window.
  - Row actions:
    - View.
    - `Open related inquiry chat` (new tab).
  - Bulk actions:
    - `Export selected` (CSV/XLSX).

- **View order header actions**
  - `Download PDF report`.
  - `Record final payment` (visible only in `ready` stage):
    - pre-fills remaining balance (total minus confirmed deposits),
    - creates final payment record and verifies it,
    - auto-completes order when settlement reaches zero balance.
  - `Download final receipt` (visible when a final receipt exists).
  - `Assign artisan`:
    - choose artisan,
    - choose scope (`full_order` or `line_item`),
    - optional line-item id,
    - instructions.
  - `QC approve`:
    - transitions pending QC -> ready when guards pass.
    - publishes customer-facing QC-ready message to inquiry thread.

### 7.5 Finance Group Functions

#### A) Payments (`PaymentResource`)

- **Pages**
  - List payments.
  - View payment.
  - Edit payment.

- **List table functions**
  - Aging badge (`Submitted X ago`) with severity colors.
  - Status badge.
  - Proof thumbnail.
  - Order/customer/amount fields.
  - Filters:
    - Status.
    - Submitted window.
    - Verified window.
    - Order/customer lookup.
  - Row `Actions` group:
    - `Review` (open view page).
    - `Approve payment` (when awaiting verification).
    - `Reject` (free reason + optional quick reason).
    - `Quick reject` (template reasons).
  - Create flow:
    - `Create` action is available from Payments list,
    - selecting a `ready` order auto-defaults amount to remaining balance and sets payment type to `final`.
  - Bulk actions:
    - `Export selected` (CSV/XLSX).

- **View page header actions**
  - `Download PDF report`.
  - `Approve Payment` (guarded by payment status).
  - `Reject Payment` (reason required).
  - `Edit record`.

- **Edit page header actions**
  - `Approve Payment`.
  - `Reject Payment`.
  - `View record`.

#### B) Payment Profiles (`PaymentProfileResource`)

- **Pages**
  - List payment profiles.
  - Create payment profile.
  - Edit payment profile.
- **Table actions**
  - Edit row.
  - Bulk delete.
- **Operational use**
  - Active profile is used for payment instructions sent from inquiry chat workflows.

### 7.6 Reports Group Functions

#### A) Report Exports (`ReportExportResource`)

- **Pages**
  - Report export index.
  - `ReportExportHub` custom page.

- **ReportExportHub action**
  - `Generate Export` form fields:
    - Report type (`sales`, `payments`, `artisan_performance`, `order_lifecycle`).
    - Start date.
    - End date.
    - Format (`PDF`, `XLSX`, `CSV`).
  - Redirects to report-generation route with selected parameters.

#### B) Quick report routes exposed via dashboard
- Current month sales summary PDF.
- Today sales summary download.
- Payment PDF report download (from payment view).
- Order PDF report download (from order view).
- Bulk payments export (CSV/XLSX).
- Bulk orders export (CSV/XLSX).

### 7.7 System Settings Group Functions

#### A) Users (`UserResource`)

- **Pages**
  - List users.
  - Create user.
  - Edit user.

- **List table functions**
  - Account-status badge (`active` / `suspended`).
  - Password-reset-required indicator.
  - Artisan-active indicator.
  - Role filter.
  - Row actions:
    - Edit.
    - `Suspend` / `Activate` (toggle account status).
    - `Forced password reset` (sets reset flag + revokes tokens).
  - Bulk actions:
    - Delete selected.

- **Edit page functions**
  - Delete header action.
  - On save:
    - clears forced-reset when password changed,
    - revokes existing tokens on password update,
    - updates/cleans artisan profile based on role.

#### B) Audit Logs (`AuditLogResource`)

- **Pages**
  - List audit logs.
  - View audit log.
- **List functions**
  - Action filter presets (payment confirms/rejects, QC approvals, artisan assignment, etc.).
  - Row action:
    - View.

#### C) Business Config (`BusinessConfigResource`)

- **Pages**
  - List business config (single-record style).
  - Edit business config.
- **Editable variables**
  - Default deposit percentage.
  - Payment at-risk hours.
  - Order at-risk hours.
  - Currency refresh interval minutes.
  - Payment accept template.
  - Payment reject template.
- **Behavior**
  - Saved values are applied immediately via `BusinessConfigService`.

### 7.8 Real-time & Notification Functions (Admin-facing effect)

- Admin actions in quote/payment workflows trigger push notification pipeline:
  - DB notification record creation.
  - FCM send attempt to active device tokens.
  - Graceful DB-only fallback on provider failure.
- Audit entries are written for key admin workflow actions.

### 7.9 Functions Present in Codebase but Not Active on Dashboard Layout

The following widget classes exist but are not part of the current curated dashboard widget list:
- `OpenInquiriesStats`
- `RevenueStats`
- `ActionQueuesStats`
- `ArtisanOverviewStats`

They remain available as implementation assets but are not currently shown in the active Command Center composition.

