# Project Status Report

## Executive Snapshot

This project is in a strong **advanced prototype / pre-production** state with a mostly complete bespoke jewelry workflow across Laravel backend, Filament admin, and Flutter customer/artisan apps.  
Core business flow (Inquiry -> Quote -> Order -> Deposit -> Assignment -> Production -> QC -> Ready) is implemented and supported by backend feature tests.  

Update as of 2026-04-22:
- Filament Order detail now includes **Assign Artisan** and **QC Approve** actions.
- QC approve now posts a required Firestore chat text message: **`Your order has passed Quality Control and is now Ready for Collection!`**.
- Flutter artisan app now includes notification deep-linking from notification list to job detail by related order assignment.
- Admin **View Order** now includes expanded transparency details (customer profile, itemized specs/pricing, workflow timeline, payment ledger).
- Customer app now includes formal **Payment Receipt** assurance with in-app receipt view + authenticated PDF download for confirmed payments.
- When a receipt is issued, customer chat now receives a Firestore **`receipt_card`** system message with a **View Receipt** CTA that opens the in-app receipt detail view.

The main remaining work is not foundational architecture, but **hardening and productization**:
- reliability under realtime/live UI conditions,
- consistent state transitions and stricter guardrails,
- expanded UX polish and notifications,
- deployment/security/observability readiness for market launch.

---

## 1) Current End-to-End Workflow Status

Legend:
- **Implemented**
- **Partial/Conditional**
- **Pending**
- **Doc Drift**

### 1.1 Inquiry, Chat Bootstrap, and Pipeline

- **Implemented** Customer inquiry creation with generated `firebase_thread_id` and optional attachments in [`backend/app/Http/Controllers/Api/V1/InquiryController.php`](backend/app/Http/Controllers/Api/V1/InquiryController.php).
- **Implemented** Firebase bootstrap/token endpoints in [`backend/app/Http/Controllers/Api/V1/ConversationController.php`](backend/app/Http/Controllers/Api/V1/ConversationController.php) and route wiring in [`backend/routes/api.php`](backend/routes/api.php).
- **Implemented** Derived pipeline presentation in [`backend/app/Services/InquiryPipelinePresenter.php`](backend/app/Services/InquiryPipelinePresenter.php), exposed via inquiry endpoints.
- **Implemented** Customer inquiry list/new/chat UI in:
  - [`frontend/lib/features/inquiries/ui/inquiry_list_screen.dart`](frontend/lib/features/inquiries/ui/inquiry_list_screen.dart)
  - [`frontend/lib/features/inquiries/ui/new_inquiry_screen.dart`](frontend/lib/features/inquiries/ui/new_inquiry_screen.dart)
  - [`frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart`](frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart)

### 1.2 Quote Publishing and Customer Decision

- **Implemented** Admin quote publish from inquiry context in [`backend/app/Http/Controllers/Api/V1/Admin/InquiryQuotePublishController.php`](backend/app/Http/Controllers/Api/V1/Admin/InquiryQuotePublishController.php).
- **Implemented** Publish warning advisor (non-blocking) in [`backend/app/Services/InquiryQuoteCommitmentGate.php`](backend/app/Services/InquiryQuoteCommitmentGate.php).
- **Implemented** Customer accept/decline flow in [`backend/app/Http/Controllers/Api/V1/CustomerQuoteController.php`](backend/app/Http/Controllers/Api/V1/CustomerQuoteController.php).
- **Implemented** Filament concierge quote generation UI/action in:
  - [`backend/app/Filament/Resources/Inquiries/Pages/ChatInquiry.php`](backend/app/Filament/Resources/Inquiries/Pages/ChatInquiry.php)
  - [`backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php`](backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php)
- **Implemented** Flutter quote card rendering + accept/decline actions:
  - [`frontend/lib/features/inquiries/widgets/inquiry_quote_card.dart`](frontend/lib/features/inquiries/widgets/inquiry_quote_card.dart)
  - [`frontend/lib/features/inquiries/data/inquiry_api.dart`](frontend/lib/features/inquiries/data/inquiry_api.dart)

### 1.3 Order Creation, Supersession, and Deposit Credit Math

- **Implemented** Core order build + supersession in [`backend/app/Services/OrderBuilderService.php`](backend/app/Services/OrderBuilderService.php):
  - supersede non-cancelled prior inquiry orders,
  - reparent deposit payments,
  - apply confirmed-deposit credit to new due amount,
  - cancel prior orders, migrate assignments, notify artisans.
- **Implemented** Deposit/payment schema and order status extension in [`backend/database/migrations/2026_04_10_120000_bespoke_order_workflow_payments_and_order_status.php`](backend/database/migrations/2026_04_10_120000_bespoke_order_workflow_payments_and_order_status.php).
- **Implemented** Payment profiles in [`backend/database/migrations/2026_04_15_100000_create_payment_profiles_table.php`](backend/database/migrations/2026_04_15_100000_create_payment_profiles_table.php).

### 1.4 Deposit Instructions, Proof Upload, Verification

- **Implemented** Admin sends payment instructions via [`backend/app/Http/Controllers/Api/V1/Admin/OrderPaymentInstructionController.php`](backend/app/Http/Controllers/Api/V1/Admin/OrderPaymentInstructionController.php).
- **Implemented** Customer proof upload via [`backend/app/Http/Controllers/Api/V1/CustomerOrderDepositController.php`](backend/app/Http/Controllers/Api/V1/CustomerOrderDepositController.php).
- **Implemented** Admin confirm/reject via:
  - [`backend/app/Http/Controllers/Api/V1/Admin/PaymentVerificationController.php`](backend/app/Http/Controllers/Api/V1/Admin/PaymentVerificationController.php)
  - [`backend/app/Services/PaymentVerificationService.php`](backend/app/Services/PaymentVerificationService.php)
- **Implemented** Filament payment cards and actions in [`backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php`](backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php).
- **Implemented** Flutter payment card + proof upload UX:
  - [`frontend/lib/features/inquiries/widgets/inquiry_payment_info_card.dart`](frontend/lib/features/inquiries/widgets/inquiry_payment_info_card.dart)
  - [`frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart`](frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart)

### 1.5 Artisan Production and QC

- **Implemented** Assignment creation and gating in [`backend/app/Http/Controllers/Api/V1/Admin/OrderController.php`](backend/app/Http/Controllers/Api/V1/Admin/OrderController.php).
- **Implemented** Artisan start/ready-for-review in [`backend/app/Http/Controllers/Api/V1/Artisan/OrderWorkflowController.php`](backend/app/Http/Controllers/Api/V1/Artisan/OrderWorkflowController.php).
- **Implemented** Progress uploads in [`backend/app/Http/Controllers/Api/V1/OrderProgressController.php`](backend/app/Http/Controllers/Api/V1/OrderProgressController.php).
- **Implemented** QC approve in [`backend/app/Http/Controllers/Api/V1/Admin/OrderQcController.php`](backend/app/Http/Controllers/Api/V1/Admin/OrderQcController.php).
- **Implemented** Flutter artisan app flow:
  - [`frontend/lib/features/artisan/ui/artisan_jobs_screen.dart`](frontend/lib/features/artisan/ui/artisan_jobs_screen.dart)
  - [`frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart`](frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart)

### 1.6 Notifications

- **Implemented** Artisan database notifications for superseded quote acceptance via:
  - [`backend/app/Notifications/QuoteRevisedAcceptedForArtisan.php`](backend/app/Notifications/QuoteRevisedAcceptedForArtisan.php)
  - [`backend/database/migrations/2026_04_16_100000_create_notifications_table.php`](backend/database/migrations/2026_04_16_100000_create_notifications_table.php)
  - [`backend/app/Http/Controllers/Api/V1/Artisan/ArtisanNotificationController.php`](backend/app/Http/Controllers/Api/V1/Artisan/ArtisanNotificationController.php)
- **Pending** Flutter artisan notifications UI/consumer for this endpoint.

### 1.7 Realtime Firestore Projection

- **Partial/Conditional** Server-side Firestore writes (`quote_card`, `payment_info_card`, thread sync, progress image) are implemented in [`backend/app/Services/FirebaseInquiryService.php`](backend/app/Services/FirebaseInquiryService.php), but explicitly degrade when Firestore PHP client/runtime deps are unavailable.
- **Implemented** Fallback pattern from REST pipeline/cards keeps UX functional even when Firestore server writes are skipped.

---

## 2) Gaps / Pending Features vs Initial Goals and Plans

Based on `.cursor/plans`, docs, and `.cursorrules`, most milestone todos are marked complete. Remaining items are mainly hardening or deferred goals.

### 2.1 Pending or Deferred

- **Pending** Typing indicators in inquiry chat (not fully implemented despite being documented as desired in [`docs/FYP_Technical_Log.md`](docs/FYP_Technical_Log.md) and referenced in [`.cursorrules`](.cursorrules)).
- **Pending** Flutter artisan notifications UI despite backend endpoint availability.
- **Pending** Rich offline/retry UX for customer chat interactions.
- **Pending** Market-ready payment UX refinements (copy actions, richer rejection/retry visibility, clearer reconciliation states).
- **Pending (Phase-2)** Push notifications strategy (FCM or equivalent) for assignment/payment/order events.

### 2.2 Partial/Conditional (Operational)

- **Partial/Conditional** Firestore server projection is environment-dependent (`ext-grpc` / `google/cloud-firestore` availability), with graceful log-and-skip behavior in [`backend/app/Services/FirebaseInquiryService.php`](backend/app/Services/FirebaseInquiryService.php).

### 2.3 Documentation Drift

- **Doc Drift** Early technical log + older plan sections still reference USD-base storage, while current implementation is MMK-base:
  - current truth in [`.cursorrules`](.cursorrules) and current migrations,
  - older statements in [`docs/FYP_Technical_Log.md`](docs/FYP_Technical_Log.md) and [`/.cursor/plans/jewelry_hub_schema_api_e7449548.plan.md`](.cursor/plans/jewelry_hub_schema_api_e7449548.plan.md).
- **Doc Drift** Some “future steps” in early log entries are now already implemented.

---

## 3) Detected UI/UX Bugs and Logic Inconsistencies

## 3.1 Filament Admin (Chat / Workflow UI)

- **Issue** Quote/Payment cards in chat are JS-rendered and sensitive to Livewire morph timing in [`backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php`](backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php).  
  **Impact:** perceived disappearance/inconsistent redraw after modal actions.
- **Issue** Native `<dialog>`-based modals required explicit centering/refresh handling in the same Blade file.  
  **Impact:** misaligned dialogs and inconsistent UX without explicit style hooks.
- **Issue** Validation/runtime errors in quote publish action were not always obvious to operators before explicit notification handling in [`backend/app/Filament/Resources/Inquiries/Pages/ChatInquiry.php`](backend/app/Filament/Resources/Inquiries/Pages/ChatInquiry.php).  
  **Impact:** “silent failure” perception during admin operations.

## 3.2 Flutter Customer App

- **Issue** Potential thread mismatch risk in chat connect flow: API returns thread/token, but UI path can still rely on inquiry’s local thread id in [`frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart`](frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart).  
  **Impact:** possible stale-thread attachment if IDs ever rotate/migrate.
- **Issue** Chat screen signs out Firebase auth in `dispose()` in the same file.  
  **Impact:** can disrupt adjacent realtime features and create avoidable reconnect churn.
- **Issue** Action availability differences between Firestore quote cards vs REST fallback banner in:
  - [`frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart`](frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart)
  - [`frontend/lib/features/inquiries/widgets/inquiry_api_quote_banner.dart`](frontend/lib/features/inquiries/widgets/inquiry_api_quote_banner.dart)  
  **Impact:** inconsistent “Accept/Decline” visibility in edge states.
- **Issue** Inquiry list rows without valid thread context are less actionable in [`frontend/lib/features/inquiries/ui/inquiry_list_screen.dart`](frontend/lib/features/inquiries/ui/inquiry_list_screen.dart).  
  **Impact:** user confusion when tap does not proceed as expected.

## 3.3 Flutter Artisan App

- **Issue** No artisan notifications UI despite backend endpoint availability.  
  **Impact:** reduced awareness for revised orders and operational lag.
- **Issue** Limited progress metadata controls in job detail upload flow in [`frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart`](frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart).  
  **Impact:** weaker audit clarity for workshop progress stages.

## 3.4 Backend Logic / Data Integrity Risks

- **Issue** Assignment status can drift from order lifecycle because assignment patch path is broad in [`backend/app/Http/Controllers/Api/V1/Artisan/AssignmentController.php`](backend/app/Http/Controllers/Api/V1/Artisan/AssignmentController.php).  
  **Impact:** inconsistent states between assignment and order.
- **Issue** `firebase_event_id` idempotency in progress flow is app-level check without DB uniqueness guarantee:
  - logic in [`backend/app/Http/Controllers/Api/V1/OrderProgressController.php`](backend/app/Http/Controllers/Api/V1/OrderProgressController.php),
  - non-unique index in [`backend/database/migrations/2026_04_03_100140_create_order_progress_events_table.php`](backend/database/migrations/2026_04_03_100140_create_order_progress_events_table.php).  
  **Impact:** race-condition duplicate inserts possible.
- **Issue** Order status moved to plain string in migration [`backend/database/migrations/2026_04_10_120000_bespoke_order_workflow_payments_and_order_status.php`](backend/database/migrations/2026_04_10_120000_bespoke_order_workflow_payments_and_order_status.php).  
  **Impact:** reduced DB-level enforcement of valid lifecycle values.

---

## 4) Professional Recommendations for Next Phase (Market-Ready Track)

## P0 (Launch blockers / platform trust)

- **State-machine hardening**
  - Centralize allowed transitions for `orders`, `payments`, and `assignments`.
  - Enforce transition guards in services + policies, not only UI.
- **Data integrity guarantees**
  - Add DB uniqueness where idempotency relies on identifiers (e.g., progress dedupe key strategy).
  - Add invariants for payment/order state coupling.
- **Realtime reliability contract**
  - Treat Firestore projection as first-class operational feature:
    - ensure required PHP deps in deploy images,
    - add health checks + alerting when projection skips occur.
- **Admin chat stability**
  - Reduce mixed JS/Livewire fragility by explicitly defining ownership of message/cards region and refresh events.

## P1 (Product quality / usability)

- **Flutter customer chat resilience**
  - unify thread source of truth (`firebaseToken` response thread id),
  - remove global Firebase sign-out side effects,
  - add reconnect/offline banners and retry semantics.
- **Artisan operational UX**
  - add artisan notifications screen and read-state handling,
  - enrich progress metadata (stage templates, note quality, attachment preview).
- **Payment UX clarity**
  - clearer customer guidance for rejection/retry flows,
  - richer payment history/reconciliation presentation.

## P2 (Commercial readiness / scale)

- **Security and compliance uplift**
  - key management review, strict env segregation, audit logging policy,
  - media upload hardening (size/type scanning, signed URLs, retention rules).
- **Observability**
  - structured business-event logs and dashboards for inquiry-to-ready funnel.
- **Performance and release hygiene**
  - broader automated test matrix (API integration, critical UI flows),
  - smoke tests for Filament chat + Flutter quote/deposit lifecycle.

---

## 5) Suggested 6-Week Execution Outline

- **Week 1-2:** lifecycle/state integrity hardening + DB constraints + missing backend tests.
- **Week 3:** realtime reliability hardening and admin chat stabilization.
- **Week 4:** Flutter chat/artisan UX fixes (thread source, notifications, retry UX).
- **Week 5:** payment journey polish + rejection/retry clarity.
- **Week 6:** observability/security checklist + release candidate testing.

---

## 6) Confidence and Final Assessment

- **Business workflow completeness:** High.
- **Production readiness:** Medium.
- **Primary risks now:** realtime UI consistency, lifecycle integrity edge cases, and operational hardening.

With focused P0/P1 execution, this can transition from FYP-grade advanced prototype to a credible market-ready boutique jewelry platform.
