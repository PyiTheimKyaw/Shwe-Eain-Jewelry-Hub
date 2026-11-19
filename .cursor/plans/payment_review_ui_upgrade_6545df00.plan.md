---
name: payment_review_ui_upgrade
overview: Enhance Filament payment review UX with proof preview, inline approve/reject actions, and thumbnail listing while preserving existing verification and notification flow.
todos:
  - id: add-payment-pages
    content: Add View/Edit pages and register PaymentResource routes.
    status: completed
  - id: build-proof-preview
    content: Implement large payment-proof preview using MediaStorageService-backed URL resolution.
    status: completed
  - id: add-review-actions
    content: Add Approve/Reject actions on review pages; reject collects reason.
    status: completed
  - id: timeline-reason-publish
    content: Update rejection flow to include reason in timeline log message sent to chat.
    status: completed
  - id: table-thumbnail
    content: Add proof thumbnail column in Payments list table with polished presentation.
    status: completed
  - id: validate-flow
    content: Run verification checks for status updates, timeline notifications, and UX behavior.
    status: completed
isProject: false
---

# Payment Review UX Upgrade Plan

## Scope
Implement a proper admin payment-proof review flow in Filament by extending the `PaymentResource` from list-only into list + view/edit review pages, with image previews, approval/rejection actions, and stitched visual polish.

## Current Baseline
- Resource is list-only: [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/PaymentResource.php`](/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/PaymentResource.php)
- Table has status/actions but no proof thumbnail: [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/Tables/PaymentsTable.php`](/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/Tables/PaymentsTable.php)
- Verification logic already central and should remain source-of-truth: [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/PaymentVerificationService.php`](/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/PaymentVerificationService.php)
- Media URL generation already centralized via [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/MediaStorageService.php`](/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/MediaStorageService.php)

## Implementation Approach

### 1) Add dedicated review pages to PaymentResource
- Add `ViewPayment` and `EditPayment` Filament pages under `Payments/Pages`.
- Register routes in `PaymentResource::getPages()` (`index`, `view`, `edit`).
- Keep list page as the entry point, but clicking records opens the review experience.

### 2) Build a payment proof review layout (View/Edit)
- Add a Payment infolist/schema section showing:
  - order number, customer, amount, submitted time, status badge
  - rejection reason (if present)
- Add large proof preview card/lightbox:
  - Resolve image URL using `MediaStorageService::publicUrl($payment->proof_storage_path)`.
  - Use Filament image entry or custom blade component with modal/lightbox behavior for full-size inspect.
- Display fallback state when proof is missing/corrupt.

### 3) Add page-level admin actions
- Add `Approve Payment` and `Reject Payment` actions on review page header.
- Both actions call `PaymentVerificationService` (no duplicated business logic).
- `Reject` action includes required/validated short reason input (textarea).

### 4) Ensure rejection note reaches customer via timeline log
- Keep your selected behavior: rejection reason delivered as timeline update in concierge stream.
- Update `PaymentVerificationService::reject()` to publish timeline with reason, e.g.:
  - `--- Payment Rejected --- | Reason: <note>`
- Continue existing `syncInquiryThreadDocument()` call to keep pipeline state consistent.

### 5) Add thumbnail to Payment list table
- Add small thumbnail/image column in `PaymentsTable`:
  - Uses proof storage path URL via `MediaStorageService` for Firebase/local compatibility.
  - Circular or rounded tiny preview (`32-40px`) with click-to-open full preview if supported.
- Keep status and submitted columns for queue triage.

### 6) Stitch-inspired UI polish
- Apply cleaner spacing/typography/badges in infolist and action grouping.
- Keep neutral surface + concise metadata hierarchy for quick review.
- Preserve Filament accessibility defaults (button contrast/focus states).

### 7) Verification and regression checks
- Manual checks:
  - proof thumbnail visible in list
  - proof opens in large preview on view/edit
  - approve updates status/order state and existing Firestore timeline/receipt behavior
  - reject with reason stores `rejection_reason` and posts timeline message with reason
- Run backend tests for payment workflow features (especially bespoke order workflow/payment verification paths).

## Files Expected To Change
- `/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/PaymentResource.php`
- `/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/Tables/PaymentsTable.php`
- `/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/Pages/ViewPayment.php` (new)
- `/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/Pages/EditPayment.php` (new)
- `/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/PaymentVerificationService.php`
- Optional view partial (if needed for lightbox): `/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/resources/views/filament/resources/payments/*`
