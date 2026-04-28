# Timebox 3 — Bespoke Fulfillment Workflow & Artisan Bridge

## 1) Functional Overview

### Implemented functions in this timebox
- Implemented quote acceptance to order creation with supersession and deposit-credit handling.
- Implemented deposit payment workflow: payment instructions, proof upload, admin confirm/reject.
- Implemented guarded order lifecycle transitions: assignment, production start, ready-for-review, QC approve.
- Implemented artisan notifications and assignment deep-link behavior.
- Implemented artisan reference-image bridge (`inquiry_image_candidates` + assignment reference mapping).
- Implemented enriched artisan job payload with specs, deadlines, priorities, and latest feedback.

## 2) Testing Suite

### 2.1 Test Plan Table

| Test ID | Scenario | Requirement Link |
|---|---|---|
| TB3-TP-01 | Accepting latest quote supersedes prior inquiry orders and credits confirmed deposits | `backend/app/Services/OrderBuilderService.php` |
| TB3-TP-02 | Deposit proof lifecycle enforces pending->awaiting_verification->confirmed/rejected transitions | `backend/app/Services/PaymentVerificationService.php` |
| TB3-TP-03 | Assignment blocked unless deposit is confirmed | `backend/app/Services/OrderLifecycleService.php` |
| TB3-TP-04 | Artisan can start production and mark ready for review under valid status guards | `backend/app/Services/OrderLifecycleService.php` |
| TB3-TP-05 | QC approve transitions order to ready and updates communication timeline | `backend/app/Http/Controllers/Api/V1/Admin/OrderQcController.php` |
| TB3-TP-06 | Reference image bridge delivers selected images to artisan assignment payload | `backend/database/migrations/2026_04_26_170000_create_inquiry_image_candidates_table.php`, `backend/database/migrations/2026_04_26_170100_create_order_assignment_reference_images_table.php` |

### 2.2 Test Script Table

| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer accepts newer quote on existing inquiry | New order created; previous non-cancelled inquiry orders superseded | Actual Result matches Expected | Pass |
| 2 | Admin sends payment instructions; customer uploads deposit proof | Payment enters awaiting verification state | Actual Result matches Expected | Pass |
| 3 | Admin confirms deposit payment | Order transitions to deposit-confirmed (or ready-made settlement state) | Actual Result matches Expected | Pass |
| 4 | Admin assigns artisan with instructions/reference image IDs | Assignment created with attached reference images | Actual Result matches Expected | Pass |
| 5 | Artisan starts production then marks ready for review | Assignment/order statuses progress according to lifecycle rules | Actual Result matches Expected | Pass |
| 6 | Admin QC approves | Order transitions to ready status and downstream customer visibility updates | Actual Result matches Expected | Pass |

## 3) Visual Proofs (Screenshot Insertion Guide)

- **Admin - Order Detail Page**: current status, assignment controls, QC action visibility.
- **Admin - Send Payment Instructions Modal**: payment profile and amount instructions.
- **Customer - Deposit Proof Upload Screen/Card**: proof submission UI.
- **Admin - Payments Verification Queue**: confirm/reject actions and status badges.
- **Admin - Assign Artisan Modal**: artisan selector, scope, instructions, reference-image picker.
- **Artisan - Jobs List Screen**: assignment cards and status indicators.
- **Artisan - Job Detail Screen**: specs, reference images, deadline/priority, lifecycle actions.
- **Admin - QC Approve Action State**: final review state before handover.

## 4) Technical Diagrams

- Use Case Diagram: `diagrams/chapter5/timebox3_fulfillment_artisan_usecase.puml`
- Class Diagram: `diagrams/chapter5/timebox3_fulfillment_artisan_class.puml`

## 5) Diagram Descriptions

### 5.1 Use Case Diagram Elements

| Element | Description |
|---|---|
| Customer Actor | Submits payment proof and tracks bespoke fulfillment states. |
| Admin Actor | Verifies payments, assigns artisans, and approves QC. |
| Artisan Actor | Executes production tasks and updates progress/review readiness. |
| Verify Deposit | Financial gate before production workflow unlocks. |
| Assign Artisan | Work allocation action with optional scoped task and references. |
| Progress Production | Artisan execution loop from start to ready-for-review. |
| Approve QC | Final quality gate before order readiness. |

### 5.2 Class Diagram Elements

| Element | Description |
|---|---|
| `Order` | Central lifecycle aggregate for bespoke fulfillment. |
| `Payment` | Deposit/final transaction record with verification state machine. |
| `OrderAssignment` | Artisan work allocation entity with status and scope. |
| `InquiryImageCandidate` | Captured chat/inquiry images reusable as workshop references. |
| `OrderAssignmentReferenceImage` | Junction linking selected references to assignment. |
| `OrderBuilderService` | Implements supersession and deposit-credit order generation logic. |
| `OrderLifecycleService` | Enforces assignment/start/review/QC transition guards. |
| `PaymentVerificationService` | Confirms/rejects proofs and synchronizes order financial state. |

