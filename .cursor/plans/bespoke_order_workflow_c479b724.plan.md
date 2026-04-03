---
name: Bespoke Order Workflow
overview: Introduce a gated deposit and QC-aware order lifecycle in MySQL, extend the derived concierge pipeline and Firestore thread/messages for new server-written cards and progress images, then implement Customer, Filament (admin), and Artisan surfaces in the existing Flutter monorepo—reusing patterns already used for `quote_card` and REST fallbacks where Firestore is unavailable.
todos:
  - id: mysql-states-payments
    content: Add payments table; extend orders status enum + migrations; map accept-quote to awaiting_deposit; data migration for existing orders
    status: completed
  - id: pipeline-presenter
    content: Refactor InquiryPipelinePresenter + Firestore thread fields for deposit/assignment/QC substeps
    status: completed
  - id: api-filament
    content: Customer deposit-proof API; admin payment-instructions, confirm/reject, gated assignment, QC; Filament Payment/Order UI + ChatInquiry action
    status: completed
  - id: firebase-messages
    content: payment_info_card + progress image writers in FirebaseInquiryService; update firestore.rules; logging when Firestore unavailable
    status: completed
  - id: flutter-customer
    content: Expand ConciergePipelineStepper; chat tiles for payment card + progress images; upload proof CTA
    status: completed
  - id: flutter-artisan
    content: Role-based navigation; job list/detail; start job, progress upload, ready-for-review using existing artisan endpoints
    status: completed
  - id: tests-docs
    content: Feature tests for full workflow; FYP technical log entry
    status: completed
isProject: false
---

# Bespoke order and artisan workflow (architectural plan)

## Current baseline (what you have today)

- **Accept quote** ([`CustomerQuoteController::accept`](backend/app/Http/Controllers/Api/V1/CustomerQuoteController.php)) sets quote to `accepted`, creates an [`Order`](backend/app/Models/Order.php) with status **`pending`** via [`OrderBuilderService`](backend/app/Services/OrderBuilderService.php), then [`FirebaseInquiryService::syncInquiryThreadDocument`](backend/app/Services/FirebaseInquiryService.php).
- **Pipeline / stepper** ([`InquiryPipelinePresenter`](backend/app/Services/InquiryPipelinePresenter.php)) maps **any** order whose status is not `ready|shipped|completed` to **`in_production`**. That is why the UI jumps straight to “In production” after accept.
- **Artisan backend** already exists: [`OrderAssignment`](backend/database/migrations/2026_04_03_100130_create_order_assignments_table.php), [`OrderProgressController`](backend/app/Http/Controllers/Api/V1/OrderProgressController.php) (photos to storage + MySQL [`order_progress_events`](backend/database/migrations/2026_04_03_100140_create_order_progress_events_table.php)), [`Artisan\AssignmentController`](backend/app/Http/Controllers/Api/V1/Artisan/AssignmentController.php). **There is no dedicated artisan job UI** in the Flutter tree yet (only [`AuthUser`](frontend/lib/features/auth/models/auth_user.dart) role awareness).
- **Firestore** ([`firebase/firestore.rules`](firebase/firestore.rules)): only **admin** and **customer** (matching `thread_id`) may read/write messages; **artisans are not thread participants**. Any “artisan photo in customer chat” should therefore be **written by Laravel (Admin SDK)** after an authenticated API upload—mirroring **`quote_card`**—unless you choose to redesign tokens/rules for artisan direct Firestore access (higher risk).

```mermaid
stateDiagram-v2
  direction LR
  [*] --> Consulting
  Consulting --> Quoted: publish_quote
  Quoted --> AwaitingDeposit: accept_quote
  AwaitingDeposit --> DepositConfirmed: admin_confirm_deposit
  DepositConfirmed --> Assigned: admin_assign_artisan
  Assigned --> InProduction: artisan_start_job
  InProduction --> PendingQC: artisan_ready_for_review
  PendingQC --> ReadyForCollection: admin_qc_pass
  ReadyForCollection --> Ready: mark_ready_or_pickup
```

---

## 1. MySQL as source of truth: schema and state machine

### 1.1 New / extended tables

- **`payments`** (new): `id`, `order_id` (FK), `type` (`deposit` first; extensible later), `amount_mmk`, `currency` (default MMK), `status` (`pending_proof`, `awaiting_verification`, `confirmed`, `rejected`), `proof_storage_path` / `proof_mime`, `submitted_at`, `verified_at`, `verified_by_user_id` (nullable), `rejection_reason` (nullable), `notes_internal` (nullable), timestamps. Optionally `expected_amount_mmk` if you separate “amount due” from “amount paid”.
- **`orders`** (extend): `deposit_amount_mmk` (or derive as % of `total_mmk` from config), `deposit_percentage` optional; ensure **one clear rule** for “how much to pay” shown on the payment card (store computed value at order creation or when admin sends instructions).
- **Optional `business_payment_profiles`** or reuse **Laravel config / Filament site settings** for KPay/Bank text fields (account name, number, QR asset path) so the “Payment Info Card” is data-driven.

### 1.2 Order status vocabulary (align with pipeline)

Keep the existing enum column but **extend values** (migration) so the names match the business:

| Proposed `orders.status` | Meaning |
|-------------------------|---------|
| `awaiting_deposit` | Quote accepted; deposit not confirmed |
| `deposit_confirmed` | Admin verified payment; assignment allowed |
| `in_production` | Artisan started work |
| `pending_qc` | Artisan marked “ready for review” |
| `ready` | Admin QC done; ready for collection |
| (retain `shipped`, `completed`, `cancelled` if you still need them) |

**Migration strategy:** map existing `pending` → `awaiting_deposit` in a data migration; map `confirmed` if you used it today → decide (e.g. merge into `deposit_confirmed` or drop `confirmed`).

### 1.3 Gates (enforce in services / policies, not only UI)

- **Assign artisan:** only if `orders.status === deposit_confirmed` **and** there is a **confirmed deposit `payments` row** (or a single canonical deposit payment in `confirmed` state).
- **Start job (move to in_production):** only if an **active assignment** exists for that artisan and current status is `deposit_confirmed` (or stricter: assignment `assigned` → transition to `in_production` + assignment `in_progress`).
- **Progress uploads:** only while `in_production` (or `pending_qc` if you allow final photos—product decision).
- **Admin QC → ready:** only from `pending_qc` → `ready`.

[`OrderPolicy`](backend/app/Policies/OrderPolicy.php) and [`AssignmentPolicy`](backend/app/Policies) (if any) should centralize these checks; [`Admin\OrderController::storeAssignment`](backend/app/Http/Controllers/Api/V1/Admin/OrderController.php) currently only checks `isArtisan()`—add **order status + deposit** gate.

---

## 2. Pipeline presenter and Firestore thread document

### 2.1 Extend [`InquiryPipelinePresenter`](backend/app/Services/InquiryPipelinePresenter.php)

Replace the coarse `orderStatusToStep()` with a mapping that includes **awaiting deposit**, **pre-production** (deposit ok, not yet started), **in production**, **QC**, **ready**. You may need **sub-signals** beyond `orders.status`:

- **Assignment exists but artisan not started:** derive from `order_assignments` where `status === 'assigned'` while order is `deposit_confirmed`.
- **Deposit proof submitted but not verified:** derive from latest `payments` row.

Expose a **structured pipeline payload** in API `meta.pipeline` (and mirror into Firestore):

- `step` (string enum for clients)
- `order_id`, `order_number`, `order_status`
- `deposit_state` (`none` | `instructions_sent` | `proof_submitted` | `confirmed` | `rejected`)
- `assignment_state` (`none` | `assigned` | `in_progress` | …)
- `active_quote_id` (unchanged where relevant)

### 2.2 [`FirebaseInquiryService::syncInquiryThreadDocument`](backend/app/Services/FirebaseInquiryService.php)

Add fields (merge) such as `deposit_state`, `assignment_id`, `artisan_user_id` (optional), so **real-time listeners** can animate the stepper without polling—**MySQL remains authoritative**; Firestore is a **projection** refreshed after each write.

---

## 3. Firebase: message types and rules

### 3.1 New server-only message types (recommended)

Following **`quote_card`**:

- **`payment_info_card`**: `{ type, order_id, amount_mmk, instructions_html_or_fields, payment_profile_id?, created_at }` — written only by Laravel when admin clicks “Send payment instructions” in Filament.
- **`system` or `status`** (optional): short line items (“Deposit confirmed”, “Assigned to artisan”) — keeps chat readable; **or** rely on payment card + pipeline only.

**Artisan progress in customer chat:** after API stores files and creates `OrderProgressEvent`, Laravel writes **`type: image`** messages with `image_url` pointing to **public/signed URL** and `sender_role: 'admin'` or a dedicated `sender_role: 'system'` if you extend rules—**simplest** is **`admin`** + label in UI “Workshop” so existing [`participantOk`](firebase/firestore.rules) patterns remain consistent.

### 3.2 Client-created messages

- **Customer payment proof:** prefer **POST / API** that saves to `payments` + **server** posts a chat line (“Proof received”) with thumbnail URL—avoids trusting client-only Firestore writes for audit. If you allow direct **`image`** messages from customer, keep them **supplemental** to the MySQL record.

### 3.3 Rules

Update [`firebase/firestore.rules`](firebase/firestore.rules): **deny client create** for `payment_info_card` (server-only). Keep **`quote_card` / `payment_info_card`** out of client `allow create` paths (only Admin SDK).

---

## 4. Backend API surface (REST)

**Customer**

- `POST /api/v1/orders/{order}/deposit-proof` — multipart; creates/updates `payments` row, transitions `awaiting_deposit` → internal `awaiting_verification` state; triggers `syncInquiryThreadDocument` + optional chat message.
- `GET /api/v1/inquiries/{id}` — already returns pipeline in `meta`; extend schema.

**Admin**

- `POST /api/v1/admin/orders/{order}/payment-instructions` — body: optional override amount/notes; calls `publishPaymentInfoCard` + sets `deposit_state` / flag `instructions_sent`.
- `POST /api/v1/admin/payments/{payment}/confirm` — sets payment `confirmed`, order `deposit_confirmed`, sync Firebase.
- `POST /api/v1/admin/payments/{payment}/reject` — optional with reason.
- **Reuse** `storeAssignment` with **new gates** (deposit confirmed).
- `POST /api/v1/admin/orders/{order}/qc/approve` — `pending_qc` → `ready` (or `ready_for_collection` if you split naming).

**Artisan** (extend existing)

- `POST /api/v1/artisan/orders/{order}/start` — validates assignment + order status; sets order `in_production`, assignment `in_progress`; sync Firebase pipeline.
- `POST /api/v1/artisan/orders/{order}/progress` — **existing** [`OrderProgressController::store`](backend/app/Http/Controllers/Api/V1/OrderProgressController.php): **add** post-commit hook: `publishProgressImageMessages($inquiry, $event)` (N photos → N or one combined message).
- `POST /api/v1/artisan/orders/{order}/ready-for-review` — order `pending_qc`, assignment `done` or new status.

All Firebase writes wrapped with the same **graceful no-op + logging** pattern as [`publishQuoteCardMessage`](backend/app/Services/FirebaseInquiryService.php) when Firestore is unavailable; Flutter can **fallback** to API lists (you already did for quotes).

---

## 5. Filament (Admin / “Father”)

- **New resource** (recommended): `PaymentResource` or `OrderResource` with relation manager for **Payments** — table: customer, order, amount, status, proof thumbnail, actions **Confirm / Reject**.
- **ChatInquiry** ([`ChatInquiry`](backend/app/Filament/Resources/Inquiries/Pages/ChatInquiry.php) / [`chat-inquiry.blade.php`](backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php)): action **“Send payment info to chat”** calling the same service as the admin API.
- **Assign artisan** UI: either on **Order** page (select user with `UserRole::Artisan`) calling existing assignment endpoint with server-side gate, or link from inquiry.

Use existing **Dark Gold** styling patterns already applied to the chat console.

---

## 6. Flutter: Customer app

- **Stepper** ([`ConciergePipelineStepper`](frontend/lib/features/inquiries/widgets/concierge_pipeline_stepper.dart)): expand labels to match new `pipeline.step` values (e.g. 6 steps: Consulting → Quoted → Awaiting deposit → In production → Quality review → Ready—or group “Deposit” + “Assignment” into one visual step if you want a compact bar).
- **Inquiry chat** ([`InquiryChatScreen`](frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart)): render **`payment_info_card`** (new widget, same glass/gold language as [`InquiryQuoteCard`](frontend/lib/features/inquiries/widgets/inquiry_quote_card.dart)); ensure **`image`** tiles show progress photos with caption (stage/artisan label).
- **Upload payment proof** button: visible when `deposit_state` indicates instructions sent and proof not confirmed—opens image picker → `POST deposit-proof`.

---

## 7. Flutter: Artisan experience (same codebase)

- **Role-gated home / shell**: after login, if `role == artisan`, route to **Job list** (use `GET /api/v1/artisan/assignments`).
- **Job detail**: show order summary, **Start job**, **Upload progress** (reuse progress API), **Mark ready for review**—all **Dark Gold** components consistent with [`AppGlassCard`](frontend/lib/core/ui/widgets/app_glass_card.dart) / [`AppColors.gold`](frontend/lib/core/ui/design_system.dart).
- **Push notifications** (optional phase 2): FCM topic or per-user device tokens when assignment created—omit from MVP if timeboxed.

---

## 8. Testing and documentation

- **Feature tests** (extend [`InquiryQuoteFlowTest`](backend/tests/Feature/InquiryQuoteFlowTest.php) pattern): accept → `awaiting_deposit` → upload proof → admin confirm → assign → start → progress → QC → ready; assert MySQL and **mocked** Firebase service calls.
- **`docs/FYP_Technical_Log.md`**: document state machine, new endpoints, and Firestore message types.

---

## 9. Implementation ordering (suggested)

1. **Migrations + Order state machine** + update **accept quote** to create order with `awaiting_deposit` and adjust **presenter** so the customer stepper is correct (fixes the immediate “jump to production” bug).
2. **`payments` table + APIs** + Filament verification queue.
3. **Firebase: `payment_info_card` writer + rules** + Filament / API trigger.
4. **Gate assignment + artisan start** endpoints + presenter sub-states.
5. **Progress → chat images** from Laravel after existing progress upload.
6. **QC endpoints + pipeline mapping** to final Ready.
7. **Flutter**: customer stepper + cards + proof upload; **artisan** screens.

---

## Key design choice to lock early

**Artisan ↔ Firestore:** recommend **server-mediated chat images** only (no artisan Firestore client) so [`firebase/firestore.rules`](firebase/firestore.rules) stay small and audit trails stay in Laravel. If you later want **live artisan typing** in the same thread, revisit **custom token claims** for `role === artisan` + `thread_id`.
