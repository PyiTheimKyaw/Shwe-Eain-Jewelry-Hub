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
  - reject flow still routed through existing `PaymentVerificationService` guards.
- **OrdersTable**
  - workflow age badge,
  - custom stage badge colors,
  - quick action to open related inquiry chat.
- **InquiriesTable**
  - unanswered marker,
  - current quote status badge.
- **UsersTable**
  - account status indicators and suspend/activate action,
  - forced password reset action with token revocation.

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

## 3) Surprise Features for Final Demo (Intelligence + Luxury Impact)

1. **Concierge Copilot Sidebar (AI-assisted admin guidance)**
   - “What needs attention now?” panel with plain-language recommendations:
   - e.g., *“3 deposits pending > 24h; prioritize Order #X and #Y first.”*
2. **Smart SLA Heatmap + Priority Ranking**
   - Color-graded risk board that auto-ranks orders/inquiries by urgency and customer value.
3. **Executive ‘One-Tap Story Mode’**
   - Auto-play dashboard walkthrough for judges/stakeholders:
   - KPI summary → revenue trend → conversion trend → actions queue.
4. **Luxury Customer Journey Timeline Replay**
   - Visual replay of one completed bespoke order from inquiry to QC-ready, including quote/payment/assignment milestones.
5. **Predictive Revenue Pulse (next 30 days)**
   - Lightweight forecast widget from existing payment/order momentum (even a simple model creates strong “intelligent system” perception).
6. **VIP Flag + White-Glove Queue**
   - Highlight high-value or repeat customers with distinct gold tagging and SLA tightening.

---

## 4) Remaining UX / Logic Polish Needed in Existing Resource Tables

### Payments Table (`PaymentsTable`)
- Add **column-level sorting/filtering** by computed aging bands.
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

## 5) Senior Architect Verdict

The admin platform is now **well beyond prototype baseline**: core business workflows are service-backed, auditability exists, exports are real, and the Command Center provides strong operational intelligence with luxury presentation quality.

To reach **100% production readiness**, the next decisive layer is not basic functionality—it is **operational rigor**:
- granular permissions,
- escalation automation,
- compliance-grade audit/report controls,
- incident runbook tooling,
- analytics drilldowns/predictive intelligence.

If these are completed, the system can credibly transition from FYP-grade excellence to enterprise-grade operational readiness.

