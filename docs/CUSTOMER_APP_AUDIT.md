# Customer App Audit (Flutter)

Date: 2026-04-25  
Scope: End-to-end audit of the Flutter customer experience, backend API integration status, luxury UX consistency, and production-readiness gaps.

---

## Platform Overview

The customer app is built on a premium dark-gold design system and currently centers on:

- `Auth` (register/login/session restore/logout)
- `Catalog` browsing and product detail
- `Inquiries` and real-time concierge chat
- `Orders`, timeline tracking, and receipts
- `Notifications` (push + inbox + read state)

Core architecture principles observed in implementation:

- **Laravel API as source of truth** for customer business data and workflow states.
- **Firebase used for real-time delivery** (chat + push) with REST fallback paths for resilience.
- **Shared UI system reuse** through `design_system.dart` and premium widgets (`AppGlassCard`, `AppGoldButton`, `PremiumText`, `PriceTag`, `WeightBadge`).

---

## 1. Currently Implemented and Functional Features

### 1.1 Authentication and Session Management

Implemented and functioning:

- Register, login, logout, and token-based bootstrap.
- Role-aware post-login gate routing.
- Secure token persistence in `flutter_secure_storage`.

Primary files:

- `frontend/lib/core/providers/auth_provider.dart`
- `frontend/lib/features/auth/data/auth_api.dart`
- `frontend/lib/core/auth/auth_token_storage.dart`
- `frontend/lib/main.dart`

### 1.2 Catalog and Product Discovery

Implemented and functioning:

- Customer catalog list with refresh and pricing-config aware display.
- Product detail with image carousel, weight/pricing metadata, and inquiry CTA.
- MMK/USD and weight-unit display toggles integrated with app preferences.

Primary files:

- `frontend/lib/features/catalog/ui/product_catalog_screen.dart`
- `frontend/lib/features/catalog/ui/product_detail_screen.dart`
- `frontend/lib/features/catalog/data/catalog_api.dart`
- `frontend/lib/core/providers/display_preferences_provider.dart`
- `frontend/lib/core/providers/catalog_pricing_provider.dart`

### 1.3 Inquiry and Real-Time Concierge Chat

Implemented and functioning:

- Inquiry listing and new inquiry submission with image attachments.
- Firebase-backed real-time messaging (text + image).
- Pipeline stepper and quote/payment card rendering.
- Quote accept/decline from customer side.
- Deposit proof upload and receipt-entry opening from chat context.
- REST pipeline fallback when server cards are absent in Firestore.

Primary files:

- `frontend/lib/features/inquiries/ui/inquiry_list_screen.dart`
- `frontend/lib/features/inquiries/ui/new_inquiry_screen.dart`
- `frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart`
- `frontend/lib/features/inquiries/data/inquiry_api.dart`
- `frontend/lib/features/inquiries/models/inquiry_pipeline.dart`
- `frontend/lib/features/inquiries/widgets/inquiry_quote_card.dart`
- `frontend/lib/features/inquiries/widgets/inquiry_payment_info_card.dart`

### 1.4 Orders, Timeline, and Receipts

Implemented and functioning:

- Orders list (`mine`) and order detail view.
- Progress timeline view.
- Receipt list and receipt detail.
- Official receipt PDF download and local open.

Primary files:

- `frontend/lib/features/orders/ui/customer_orders_screen.dart`
- `frontend/lib/features/orders/ui/customer_order_detail_screen.dart`
- `frontend/lib/features/orders/ui/customer_order_timeline_screen.dart`
- `frontend/lib/features/orders/ui/receipt_detail_screen.dart`
- `frontend/lib/features/orders/data/order_api.dart`
- `frontend/lib/features/orders/models/payment_receipt.dart`

### 1.5 Notifications (Push + Inbox)

Implemented and functioning:

- FCM bootstrap and foreground handling.
- Device token registration/unregistration with backend.
- Notifications inbox with read/unread indicator.
- Mark-as-read endpoint call on notification open.
- Deep-link open handling for notification targets.

Primary files:

- `frontend/lib/core/notifications/push_notification_manager.dart`
- `frontend/lib/core/notifications/ui/notifications_screen.dart`
- `frontend/lib/core/notifications/data/notification_api.dart`

---

## 2. Backend Sync Status

### 2.1 Fully Integrated Endpoints (Backend + Flutter)

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/catalog/products`
- `GET /api/v1/catalog/products/{id}`
- `GET /api/v1/pricing/fx/current`
- `POST /api/v1/inquiries`
- `GET /api/v1/inquiries/mine`
- `GET /api/v1/inquiries/{inquiry}`
- `POST /api/v1/conversations/{inquiry}/firebase-token`
- `GET /api/v1/quotes/{quote}`
- `POST /api/v1/quotes/{quote}/accept`
- `POST /api/v1/quotes/{quote}/decline`
- `GET /api/v1/orders/mine`
- `GET /api/v1/orders/{order}`
- `GET /api/v1/orders/{order}/progress`
- `POST /api/v1/orders/{order}/deposit-proof`
- `GET /api/v1/orders/{order}/receipts`
- `GET /api/v1/orders/{order}/receipts/{receipt}/download`
- `POST /api/v1/uploads`
- `POST /api/v1/push/devices`
- `DELETE /api/v1/push/devices`
- `GET /api/v1/notifications`
- `POST /api/v1/notifications/{id}/read`

### 2.2 Implemented in Backend but Not Currently Used by Flutter Customer App

- `POST /api/v1/conversations/{inquiry}/firebase-bootstrap`
- `GET /api/v1/quotes/mine`
- `GET /api/v1/orders/{order}/receipts/{receipt}`
- `GET /api/v1/catalog/categories`

### 2.3 Sync Gaps Against Latest Backend/Admin Logic

#### A) Final/Cash Payment Logic Visibility (Partial in Customer UI)

Backend already supports:

- Admin-side cash final payment recording.
- Final settlement automation to `completed`.
- Receipt metadata including payment method label and fully-paid state.

Customer app currently:

- Correctly shows final/deposit receipt type and final receipt download.
- Does **not** clearly surface payment method label (Cash vs Transfer) from receipt metadata.

Impact: Functional parity is strong, but customer-facing settlement transparency is incomplete.

#### B) Firebase Thread Bootstrap Recovery (Missing Usage)

Backend already provides `firebase-bootstrap` for thread recovery/bootstrap.  
Flutter chat flow still depends on existing `firebase_thread_id` and fallback pipeline polling.

Impact: Edge-case inquiries with missing thread IDs may fail to open chat despite backend recovery capability.

#### C) Completion Semantics in Concierge Pipeline (Display Gap)

Backend can move order `ready -> completed` after final settlement.  
Customer pipeline presentation can visually flatten this to a “ready-style” terminal representation.

Impact: Journey closure perception is weaker than actual backend state.

### 2.4 Mock Data Status

No major hardcoded mock datasets were found in customer feature flows.  
Most data paths are API/Firebase-backed. Remaining issues are contract/display gaps, not fake data use.

---

## 3. UX and UI Polish Status

### 3.1 Luxury Branding Consistency

Current status: **Mostly consistent** with dark-gold premium direction.

Strengths:

- Reuse of shared design primitives (`AppGlassCard`, `AppGoldButton`, gradients, gold accents).
- Recent official logo integration in auth/splash and selected shell headers.

Polish gaps:

- Logo placement is not fully uniform across all customer surfaces.
- Typography implementation diverges from strict workspace guidance in some places (mixed primitives and occasional style drift).

### 3.2 Responsiveness and Screen-Size Behavior

Current status: **Good baseline, needs tablet/foldable hardening**.

Observed gaps:

- Catalog grid is fixed at 2 columns rather than width-adaptive layout.
- Some fixed-width/fixed-size media and card constraints in chat/payment UI may feel cramped on small split-screen widths and unoptimized on larger tablets.
- A few small text sizes in dense timeline/pipeline areas can reduce readability.

### 3.3 Interaction and Visual Consistency

Current status: **Functional and polished**, with some component-level inconsistency.

- Some screens use shared premium text/components consistently.
- Other screens still mix direct `Text`/`ActionChip` usage patterns, creating subtle tone differences in a luxury UI.

---

## 4. Remaining Gaps and Production Readiness

## 4.1 P0 (Critical Before Production)

1. **Environment safety for API base URL**
   - Ensure production-safe URL configuration and guardrails (avoid localhost fallback misconfiguration during release builds).
2. **Use backend chat bootstrap endpoint**
   - Integrate `POST /api/v1/conversations/{inquiry}/firebase-bootstrap` in thread-missing scenarios.

## 4.2 P1 (High Priority for 100% Experience)

1. **Expose payment method in customer receipt UI**
   - Show `Cash`/`Bank Transfer` label from receipt metadata for settlement transparency.
2. **Explicitly show completed closure state in customer journey UI**
   - Distinguish `completed` from `ready` in pipeline/timeline visual language.
3. **Adaptive layout improvements**
   - Width-based grid columns, flexible card/media constraints, and better tablet/foldable tuning.

## 4.3 P2 (Polish and Confidence Upgrades)

1. **Unify premium component usage**
   - Reduce direct style overrides; standardize text/badge/chip treatments.
2. **Consistency pass for logo/header treatment**
   - Ensure all major customer tabs preserve consistent brand identity.
3. **Improve failure transparency**
   - Replace silent catch blocks for key operations with customer-safe feedback states where appropriate.

---

## 5. Production Readiness Snapshot

Overall status: **~85-90% production-ready for customer scope**.

- **Strong:** Core commerce journey works (browse -> inquire -> quote -> order -> receipt), notifications are integrated, and backend sync is largely complete.
- **Needs closure for 100% readiness:** chat bootstrap recovery usage, settlement method visibility in customer receipts, and adaptive/responsive polish for broader device classes.

---

## 6. Recommended Immediate Execution Plan

1. Implement chat bootstrap fallback usage in `inquiry_chat_screen`.
2. Extend `PaymentReceipt` model + receipt detail UI for payment method/full-paid metadata.
3. Add adaptive breakpoints for catalog/chat/order card layouts.
4. Run end-to-end smoke tests on:
   - quote accept -> order created,
   - deposit proof + payment card updates,
   - final/cash settlement reflection in customer receipt UI,
   - push tap -> deep link -> read-state update.

This sequence delivers the fastest path to a complete, enterprise-grade customer app parity with the current backend/admin architecture.
