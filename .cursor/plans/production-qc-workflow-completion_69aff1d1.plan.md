---
name: production-qc-workflow-completion
overview: Complete the missing Production & QC user story by adding Filament assignment/QC controls, tightening backend transition handling, and refining artisan progress/QC UX while reusing existing API endpoints and Firestore sync behavior.
todos:
  - id: filament-order-actions
    content: Add Filament Order Detail actions for Assign Artisan and QC Approve using existing workflow guards.
    status: in_progress
  - id: backend-lifecycle-unification
    content: Centralize order stage transition rules in a reusable backend service and wire both API + Filament paths to it.
    status: pending
  - id: artisan-progress-ux
    content: Enhance Flutter artisan job detail with stage/note inputs, progress timeline, and QC handoff visibility.
    status: pending
  - id: chat-propagation-hardening
    content: Validate and harden progress-photo-to-chat propagation behavior and fallback handling.
    status: pending
  - id: tests-and-e2e-validation
    content: Extend backend tests, run analyzers, and execute manual E2E workflow validation checklist.
    status: pending
isProject: false
---

# Production & QC Workflow Completion Plan

## Current state (confirmed from code)
- Implemented already:
  - Artisan `Start Job`, `Upload Progress`, `Ready for Review` in Flutter (`frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart`) calling existing APIs in `frontend/lib/features/artisan/data/artisan_api.dart`.
  - Progress photo -> Firestore chat message publish in backend via `OrderProgressController::store` + `FirebaseInquiryService::publishProgressImageMessage`.
  - QC approve API in `backend/app/Http/Controllers/Api/V1/Admin/OrderQcController.php`.
- Missing/partial:
  - No Filament **Order Detail** UI action to assign artisan (backend API exists).
  - No Filament UI action to trigger QC approve (API exists).
  - Artisan progress UX is hardcoded (`stage='other'`, static note) and lacks progress timeline/QC feedback.

## Step-by-step implementation plan

## 1) Add Filament Order Detail control surface for Production/QC
- Create/extend a Filament `OrderResource` detail page under `backend/app/Filament/Resources/Orders/` (or extend an existing order resource if present) with order-centric actions.
- Add **Assign Artisan** action in Order Detail:
  - Inputs: `artisan_user_id`, `task_scope`, optional `order_item_id`, optional `instructions`.
  - Action should call existing assignment logic (`Admin\OrderController::storeAssignment` semantics) either by:
    - direct service/policy reuse in Filament action handler (preferred for in-process consistency), or
    - HTTP route call only if resource architecture forces it.
  - Respect current guards: order must be `deposit_confirmed` and confirmed deposit payment exists.
- Add **QC Approve** action in Order Detail:
  - Show action only when order is `pending_qc`.
  - Execute same transition logic as `OrderQcController::approve` (`pending_qc -> ready` + Firebase thread sync).

## 2) Consolidate backend transition logic (single source of truth)
- Extract order transition checks into a reusable service (e.g. `OrderLifecycleService`) under `backend/app/Services/`.
- Move/centralize transition rules currently spread across:
  - `backend/app/Http/Controllers/Api/V1/Artisan/OrderWorkflowController.php`
  - `backend/app/Http/Controllers/Api/V1/Admin/OrderController.php`
  - `backend/app/Http/Controllers/Api/V1/Admin/OrderQcController.php`
- Ensure both API and Filament actions use same service methods:
  - `assignArtisan(...)`
  - `startProduction(...)`
  - `markReadyForReview(...)`
  - `approveQc(...)`
- Keep existing policy checks intact and add consistent 422 messages for invalid transitions.

## 3) Upgrade Artisan progress UX to match production workflow
- In `frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart`:
  - Replace hardcoded progress payload with user-controlled inputs:
    - stage selector (`casting|filing|polishing|setting|qc|other`)
    - optional note text
  - Keep photo picker flow, but validate client-side constraints before upload (count/type/size guidance aligned with backend).
- Add lightweight progress history in job detail:
  - Fetch `GET /api/v1/orders/{order}/progress` (already available in backend routes).
  - Render chronological cards (stage, note, thumbnails, timestamp).
- Add QC status feedback for artisan:
  - Show when order is `pending_qc` vs `ready` so artisan sees handoff completion.

## 4) Ensure customer chat propagation remains deterministic
- Keep existing publish path in `OrderProgressController::store`.
- Add explicit fallback behavior notes in code comments for cases where Firestore publish is skipped (inquiry thread missing or Firestore client unavailable).
- Optional hardening: publish one summary `text/system` message when photo upload has no attachments (to maintain timeline continuity).

## 5) Filament UX polish for operator clarity
- In Filament order detail/chat context:
  - Show current workflow state badges (`awaiting_deposit`, `deposit_confirmed`, `in_production`, `pending_qc`, `ready`).
  - Disable/hide actions that are invalid in current stage with clear tooltip/reason.
  - After assignment or QC approve, trigger the same static chat/pipeline refresh hook used in inquiry chat (`jh-admin-static-refresh` style event).

## 6) Test and validation plan
- Backend feature tests (extend existing):
  - Add coverage for Filament-equivalent action paths via service/controller tests:
    - assign artisan success/failure by stage,
    - start production denied without confirmed deposit,
    - QC approve only from `pending_qc`.
  - Keep `BespokeOrderWorkflowTest` and `InquiryQuoteFlowTest` green.
- Frontend validation:
  - Run `flutter analyze` on modified artisan files.
  - Manual QA matrix:
    - admin assigns artisan -> artisan sees assignment,
    - artisan starts job -> status changes,
    - artisan uploads progress photo -> appears in customer inquiry chat thread,
    - artisan marks ready for review -> admin QC approve -> order becomes ready.

## 7) Delivery order
1. Filament Order Detail actions (Assign Artisan + QC Approve).
2. Backend lifecycle service consolidation (so UI/API paths stay consistent).
3. Artisan Flutter progress form + timeline + QC visibility.
4. Test pass and manual end-to-end verification.

## Key files expected to change
- Backend/Filament:
  - [backend/app/Filament/Resources/Orders/](backend/app/Filament/Resources/Orders/)
  - [backend/app/Http/Controllers/Api/V1/Admin/OrderController.php](backend/app/Http/Controllers/Api/V1/Admin/OrderController.php)
  - [backend/app/Http/Controllers/Api/V1/Admin/OrderQcController.php](backend/app/Http/Controllers/Api/V1/Admin/OrderQcController.php)
  - [backend/app/Http/Controllers/Api/V1/Artisan/OrderWorkflowController.php](backend/app/Http/Controllers/Api/V1/Artisan/OrderWorkflowController.php)
  - [backend/app/Services/FirebaseInquiryService.php](backend/app/Services/FirebaseInquiryService.php)
  - [backend/app/Services/](backend/app/Services/)
- Flutter:
  - [frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart](frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart)
  - [frontend/lib/features/artisan/data/artisan_api.dart](frontend/lib/features/artisan/data/artisan_api.dart)
  - [frontend/lib/features/artisan/ui/artisan_jobs_screen.dart](frontend/lib/features/artisan/ui/artisan_jobs_screen.dart)
  - (new/updated artisan progress models/widgets as needed)
