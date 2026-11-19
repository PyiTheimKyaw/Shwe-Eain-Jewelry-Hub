---
name: Final Payment Pickup Flow
overview: "Implement a full closing-stage payment and pickup workflow: remaining-balance defaults, ready->completed automation on final payment confirmation, official final receipt PDF delivery across admin/mobile, and a Record Final Payment quick action in Order View."
todos:
  - id: domain-final-payment-type
    content: Add final payment type and shared remaining-balance calculator
    status: completed
  - id: lifecycle-completion-hook
    content: Implement ready->completed automation on final payment confirmation with push trigger
    status: completed
  - id: filament-defaults-actions
    content: Add PaymentResource default remaining amount and Order View 'Record Final Payment' action
    status: completed
  - id: final-receipt-pdf
    content: Implement official final receipt template, issuance, and admin/customer download access
    status: completed
  - id: tests-docs
    content: Add/adjust tests and update technical/admin/setup docs
    status: completed
isProject: false
---

# Final Payment & In-Store Pickup Implementation Plan

## Scope and assumptions
- Treat closing payment as a dedicated payment type (`final`) while preserving existing deposit flow.
- Keep verification authority in backend services so API + Filament share one state-machine source.
- Use existing receipt infrastructure (stored PDF + customer download API) as canonical path for official final receipts.

## 1) Data and domain model updates
- Update [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Models/Payment.php`] to add `TYPE_FINAL` constant and any helper methods needed for type checks.
- Add a shared remaining-balance calculator (service-level helper) that computes:
  - `remaining = max(0, order.total_mmk - sum(confirmed payments))`
- Refactor call sites to use the same calculator for consistency:
  - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/InquiryPipelinePresenter.php`]
  - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Orders/Schemas/OrderInfolist.php`] (if present in current branch)
  - new final-payment defaulting logic in Filament payment/order actions.

## 2) Lifecycle + verification changes
- Extend [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/PaymentVerificationService.php`]:
  - keep current deposit confirmation behavior unchanged.
  - add final-payment confirmation branch:
    - mark payment confirmed,
    - recompute remaining balance,
    - if order status is `ready` and remaining is `0`, transition order to `completed`.
  - trigger customer “Thank you / fully paid” push notification via `PushNotificationService` when completion occurs.
- Add explicit lifecycle method in [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/OrderLifecycleService.php`] for safe completion from pickup flow (e.g., `completeAfterFinalSettlement`) so status guards remain centralized.

## 3) Admin payment defaulting and pickup action UX
- Update Payment resource form defaults so when creating payment for `ready` orders, `amount_mmk` defaults to computed remaining balance:
  - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/Schemas/PaymentForm.php`]
  - related create/edit page hooks in [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Payments/Pages/`].
- Add Order View quick action `Record Final Payment` in [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Orders/Pages/ViewOrder.php`]:
  - visible only when order status is `ready` (Ready for Pickup).
  - prefill amount with remaining balance.
  - create final payment record and route through verification flow (or optionally confirm inline using the same service method).
- Keep existing table triage behavior intact (no regressions in current UX polish).

## 4) Official final receipt PDF
- Extend receipt issuance path in [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Services/ReceiptService.php`] to support official final settlement receipts.
- Add dedicated final receipt Blade template:
  - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/resources/views/receipts/final-receipt.blade.php`]
  - Include itemized lines, total, deposit paid, final paid, and a clear `FULLY PAID` stamp.
- Ensure generation trigger runs when final payment is confirmed and settlement reaches zero balance.

## 5) Admin and customer download surfaces
- **Admin panel access**
  - Add download action from Order View (if final receipt exists) in [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Filament/Resources/Orders/Pages/ViewOrder.php`].
  - Wire route/controller endpoint (reuse existing receipt download pattern) in:
    - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/routes/web.php`]
    - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Http/Controllers/Admin/FilamentExportController.php`] or dedicated receipt controller.
- **Customer app access**
  - Reuse current receipt API endpoints in [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/routes/api.php`] and [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/backend/app/Http/Controllers/Api/V1/CustomerReceiptController.php`].
  - Extend payload/model to expose receipt kind (`deposit` vs `final`) and display it in Flutter:
    - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/frontend/lib/features/orders/models/payment_receipt.dart`]
    - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/frontend/lib/features/orders/ui/customer_orders_screen.dart`]
    - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/frontend/lib/features/orders/ui/receipt_detail_screen.dart`]
  - If required for strict “Order details” access, add receipt section in [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/frontend/lib/features/orders/ui/customer_order_detail_screen.dart`].

## 6) Validation and regression checks
- Backend tests:
  - Add/extend feature tests for:
    - remaining-balance default,
    - ready->completed transition on final confirm,
    - push notification dispatch,
    - final receipt issuance and download authorization.
- Frontend checks:
  - Verify receipt labeling/download for final receipt.
  - Run `flutter analyze` after UI updates.
- Backend checks:
  - Run targeted PHPUnit suite for order/payment/receipt flows.

## 7) Documentation updates
- Update:
  - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/docs/FYP_Technical_Log.md`]
  - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/docs/ADMIN_SIDE_AUDIT.md`]
  - [`/Users/pyitheimkyaw/UOG/Project/Shwe-Eain-Jewelry-Hub/docs/Local_Setup.md`]
- Document operator behavior for `Record Final Payment`, final completion automation, and final receipt retrieval paths.