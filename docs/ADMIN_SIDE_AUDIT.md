# Admin Side Audit (Filament Backend)

Date: 2026-04-25  
Scope: Filament admin panel under `backend/app/Filament`, related policies/services/controllers, and current workflow wiring.

---

## 1) Currently Implemented & Functional Features

### 1.1 Active Filament Resources (Navigation Modules)

The admin panel at `/admin` is active and resource auto-discovery is enabled via `AdminPanelProvider`.

**Inventory**
- `CategoryResource` (list/create/edit)
- `ProductResource` (list/create/edit)
- `ImagesRelationManager` for product images
- `GoldPriceSnapshotResource` (list/create; history-oriented, no edit)
- `ExchangeRateSnapshotResource` (list/create; history-oriented, no edit)

**Operations**
- `InquiryResource` (list/view/edit)
- `ChatInquiry` custom page for concierge chat operations
- `OrderResource` (list/view)
- `ViewOrder` header actions for assignment and QC approval
- `PaymentResource` (list/view/edit)

**Finance**
- `PaymentProfileResource` (list/create/edit)

**Reports**
- `ReportExportResource` + `ReportExportHub` (PDF/XLSX/CSV exports with date-range filters)

**System Settings**
- `UserResource` (list/create/edit) with role-aware artisan profile inputs
- `AuditLogResource` (read-only list/view)

### 1.2 Fully Wired Admin Business Logic

The following flows are not just UI forms; they are connected to domain services and persistence rules.

- **Quote publishing from admin chat is fully wired**
  - Admin creates quote lines in `ChatInquiry`, quote is generated via `QuoteBuilderService`, inquiry status is updated, and Firestore/timeline sync is triggered.
  - Confirmed-deposit advisory warning is shown without blocking publish (via `InquiryQuoteCommitmentGate`).

- **Deposit instruction issuance is fully wired**
  - Admin selects active payment profile and sends payment instructions from `ChatInquiry`.
  - Deposit `Payment` record is created/updated, order flags are updated, and chat card/timeline are synced.

- **Payment verification is fully wired**
  - Approve/reject actions are available in `PaymentsTable` and `ViewPayment`.
  - Logic is centralized in `PaymentVerificationService` with transition guards, order status update, receipt issuance, timeline sync, and receipt-card broadcast.

- **Artisan assignment and QC approval are fully wired**
  - `ViewOrder` actions call `OrderLifecycleService`.
  - Assignment enforces deposit-confirmed prerequisites and artisan validation.
  - QC approval enforces `pending_qc -> ready`, then syncs chat with required system message.

- **Pricing snapshot integrity is wired through service layer**
  - Filament create pages call `PricingService::recordGoldPrice` / `recordExchangeRate`, preserving snapshot chronology.

- **Operational visibility is present**
  - Rich order infolist exposes customer profile, item pricing specs, assignment summary, payment ledger, and workflow timeline.
  - Dashboard command center now includes:
    - `CommandCenterKpisWidget` (spot gold/FX, active inquiries, SLA risk),
    - `OperationsPulseWidget` (pending verifications + active production),
    - advanced chart widgets for revenue, order-stage distribution, and conversion trend.
  - Quick access command strip links pricing snapshot updates and audit trail review.

---

## 2) Current Technical Limitations

### 2.1 Areas with Logic Present but Operationally Manual

- **Admin workflow is action-driven, not event-orchestrated**
  - Payment instruction sending, assignment, and QC approval are manual admin actions, with limited automated escalation/retry orchestration.

- **Realtime refresh relies on polling and client-side refresh hooks**
  - Inquiries and chat screens rely on polling and JS refresh timing; robust event bus/state reconciliation is not yet unified.

- **No automation for exception queues**
  - Rejected deposits, stalled inquiries, and aging orders do not auto-generate operational tasks/escalations in Filament.

- **Limited batch operations in operations-heavy modules**
  - Payments/Inquiries/Orders do not currently expose mature bulk workflows (mass verify, mass stage change, reassignment).

- **Analytics is now Filament-first for operational dashboards**
  - Core admin analytics are available directly in Filament chart/KPI widgets; next maturity step is drill-down and scheduled alerting.

### 2.2 RBAC (Current State)

RBAC is **functional but coarse-grained**.

- **Strengths**
  - Panel access is restricted to admin users (`User::canAccessPanel`).
  - Additional route-level admin protection exists for admin chat upload endpoint (`auth + role:admin`).
  - Core policies (`InquiryPolicy`, `OrderPolicy`, `PaymentPolicy`, `QuotePolicy`, `OrderAssignmentPolicy`) are implemented and used in key flows.

- **Limitations**
  - Authorization is mostly role-based (`admin/artisan/customer`) and action-level; there is no granular permission matrix (e.g., per-module capability toggles).
  - No scoped multi-admin roles (e.g., finance-only admin, catalog-only admin, read-only supervisor).
  - No approval segregation controls (e.g., maker-checker for high-risk actions like payment confirmation).

---

## 3) Gap Analysis (What Is Missing)

### 3.1 Essential Admin Management Features Not Yet Built

- **Advanced staff operations**
  - User resource exists, but no full staff lifecycle tooling (lock/suspend/reactivate, forced password reset workflows, admin session control).

- **Global configuration console**
  - Pricing snapshots exist, but broader global business configuration is missing (deposit policy presets, SLA thresholds, workflow timers, notification templates).

- **Operational bulk actions**
  - Bulk export exists for orders/payments; broader bulk workflow actions (batch assignment/reassignment, guarded mass transitions) remain future work.

- **Governance and traceability**
  - Audit trail UI is now implemented; next step is richer diff presentation and actor accountability dashboards.

### 3.2 Remaining Operational Gaps

- **Order Kanban / workflow board**
  - No visual stage board for `awaiting_deposit -> in_production -> pending_qc -> ready`.

- **Filament-native analytics dashboard**
  - KPI and chart dashboard now exists; remaining gap is deeper per-widget drilldowns and historical comparison presets.

- **Notification control center**
  - No admin inbox/escalation center for delayed verifications, reassignment conflicts, or failed realtime projections.

- **Runbook tooling**
  - No in-panel incident controls for replaying failed syncs, checking integration health, or forcing pipeline re-index.

- **Export/compliance utilities**
  - Ad-hoc exports (PDF/XLSX/CSV) are implemented; scheduled exports and stricter role-scoped distribution remain open.

---

## 4) Recommended Future Features (Prioritized Roadmap)

### Priority P0 (Before Final Submission / Demo Risk Reduction)

1. **State-machine enforcement dashboard + guardrails**
   - Central transition monitor with invalid-state alerts and corrective controls.
2. **Order workflow board (Kanban)**
   - Introduce drag/transition UI with policy-aware validation.
3. **Notification control center**
   - Add admin inbox/escalation handling for delayed verifications and stalled production.
4. **Operational runbook tools**
   - Add safe replay/reconcile controls for failed sync and projection incidents.

### Priority P1 (Production-Readiness Uplift)

1. **Granular permission model**
   - Add capability-based permissions beyond role enum (finance operator, production lead, read-only auditor).
2. **Bulk operations with safety rails**
   - Batch approve/reject, bulk assignment/reassignment, bulk messaging with preview/confirm.
3. **Automated escalation engine**
   - Auto-create admin alerts for aging deposits, stalled production, and failed sync events.
4. **Advanced reporting module**
   - Date-range dashboards for order throughput, payment conversion, artisan productivity, and quote win-rate.

### Priority P2 (Scalability and Governance)

1. **Push notification broadcaster**
   - Admin-triggered and rule-triggered push broadcasts for customers/artisans.
2. **Integration health and replay tooling**
   - Visibility into Firebase projection failures with retry/reconcile actions.
3. **Compliance-oriented export/audit package**
   - Signed exports, filterable history, and retention policy controls.

---

## 5) Expert Feedback (Senior Architect Verdict)

The current Filament admin side is **architecturally solid for an advanced FYP prototype**: core workflows are service-backed, business-critical transitions are guarded, and operational visibility is already meaningful. The platform demonstrates good separation between UI actions and domain logic, which is a strong foundation for maintainability.

For true production-readiness, the main gap is **operational maturity**, not feature absence: finer RBAC, first-class observability, automation/escalation tooling, and auditable governance controls are needed. With the P0/P1 roadmap delivered, the admin side can realistically transition from prototype-grade robustness to a scalable operations console suitable for real business usage.

