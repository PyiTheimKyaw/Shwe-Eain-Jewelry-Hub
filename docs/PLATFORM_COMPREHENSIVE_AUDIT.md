# Platform Comprehensive Audit (Customer + Artisan + Admin)

Date: 2026-04-26  
Scope: Final implementation-state audit across Laravel backend, Filament admin, customer mobile, and artisan mobile after the Artisan Image Bridge updates.

---

## 1) Final Architecture Snapshot

- System of record: Laravel + MySQL.
- Realtime delivery: Firebase Firestore (chat and timeline cards/messages).
- Business-rule ownership: Laravel services (`OrderBuilderService`, `OrderLifecycleService`, `InquiryPipelinePresenter`).
- Mobile strategy: one Flutter app with role-based shells.
- Admin strategy: Filament web command center.

Core principle: Firestore is delivery/realtime, while Laravel/MySQL remains the ledger and source of truth.

---

## 2) Implemented Features Audit

### 2.1 Customer App

- Auth/session restore with secure token storage and role gate.
- Catalog list/detail, favorites, product inquiry entry.
- Smart Home marketing sections on catalog:
  - New Arrivals
  - Popular Items (inquiry-weighted)
  - Featured Collections (admin-curated categories)
- Realtime inquiry chat with text/image, quote accept/decline, payment card, receipt card.
- REST pipeline fallback when Firestore cards are missing/delayed.
- Orders list/detail/timeline and receipt detail/download.
- Offline-first cache fallback for catalog, inquiries list, and orders list with Last Synced indicator.
- Profile settings including password and account-close flow.
- Shared premium fullscreen image viewer flow introduced.
- Inquiry chat image upload supports multi-image selection.

### 2.2 Artisan App

- Role-gated artisan shell (Jobs + Alerts).
- Assignment list/detail with production lifecycle actions.
- Job detail enriched with deadline, priority, dimensions/ring size, latest admin feedback.
- Progress upload supports multi-image selection.
- Artisan notifications with read sync and deep link behavior.
- Artisan profile/stats page with password and logout controls.
- Premium zoom/swipe image detail for design/inquiry/reference sections.

### 2.3 Admin Side

- Concierge chat console in Filament with quote/payment actions.
- Order lifecycle operations: assign artisan, QC approve, ready-for-pickup, settlement flows.
- Assign modal supports visual reference-image selection and upload-new flow.
- Report/export surfaces (PDF/CSV/XLSX) and analytics foundations.
- Command Center now includes a dual Conversion Funnel report widget:
  - Customer journey funnel (catalog-linked inquiry -> inquiry -> quote accepted -> deposit confirmed)
  - Operations funnel (inquiry -> quote sent -> deposit confirmed -> ready -> completed)
  - Time window selector (7/30/90 days) with per-step drop-off percentages.
- Admin inquiry chat upload supports multi-image selection.

### 2.4 Artisan Image Bridge (Delivered)

- Bridge tables: `inquiry_image_candidates`, `order_assignment_reference_images`.
- Candidate capture from customer/admin chat uploads and assignment modal uploads.
- Optional Firestore backfill command for parity.
- Selected references are included in artisan assignment payload.
- Legacy initial inquiry attachments are now mirrored into candidates for admin visibility and assignment selection.

---

## 3) Small Gaps / Polish Needed

- Optimize customer orders list to avoid per-order receipt fetch overhead.
- Reduce heartbeat/polling cost in realtime chat where event-driven sync can replace polling.
- Add more explicit operational alerting for Firestore write degradation.
- Complete final app-wide pass so every image tap uses shared viewer consistently.
- Replace customer-funnel "catalog-linked inquiry" proxy with true catalog-view event telemetry for exact top-of-funnel measurement.
- Expand Flutter admin shell parity (currently minimal compared with Filament web).

---

## 4) What To Do Next (Market-Ready Roadmap)

1. Performance hardening
   - Consolidate order+receipt summaries into fewer API roundtrips.
   - Extend offline cache from list surfaces to detail surfaces (order detail, inquiry detail/history).

2. Reliability/operations
   - Add health checks and alerting for Firebase bridge failures.
   - Add upload retry/queue strategy for poor-network scenarios.

3. UX/product polish
   - Final consistency pass for image viewer and media cards.
   - Add better upload feedback (multi-file progress states and partial-failure messaging).

4. Commercial readiness
   - Add real catalog-view telemetry events to complete customer funnel accuracy.
   - Improve analytics and funnel reporting for admin decisions.
   - Add role-based notification preference controls.
   - Add release hardening gates (critical flow smoke tests on CI).

---

## 5) Relevant Source References

- Backend: `backend/routes/api.php`, `backend/app/Services/`, `backend/app/Filament/Resources/`
- Customer: `frontend/lib/features/catalog/`, `frontend/lib/features/inquiries/`, `frontend/lib/features/orders/`, `frontend/lib/features/profile/`
- Artisan: `frontend/lib/features/artisan/`, `frontend/lib/features/shell/artisan_shell.dart`
- Shared UI: `frontend/lib/core/ui/widgets/`
