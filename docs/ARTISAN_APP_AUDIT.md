# Artisan App Audit (Flutter)

Date: 2026-04-26  
Scope: Current artisan mobile experience audit and production-hardening updates for workshop operations (jobs, progress updates, notifications, and navigation integrity).

---

## 1) Completed Artisan Flows (Current State)

- Role-based routing for artisan users is implemented at app gate level.
- Artisan shell supports core workshop tabs: Jobs and Alerts.
- Jobs list and detail flows are implemented with pull-to-refresh, loading, empty and error handling.
- Job lifecycle actions are implemented: start production, upload progress/photos, and ready-for-review handoff.
- Artisan notifications endpoint and notification model are implemented and linked to job detail navigation.

Primary references:

- `frontend/lib/main.dart`
- `frontend/lib/features/shell/artisan_shell.dart`
- `frontend/lib/features/artisan/ui/artisan_jobs_screen.dart`
- `frontend/lib/features/artisan/ui/artisan_job_detail_screen.dart`
- `frontend/lib/features/artisan/ui/artisan_notifications_screen.dart`
- `frontend/lib/features/artisan/data/artisan_api.dart`
- `frontend/lib/features/artisan/models/artisan_notification.dart`

---

## 2) Production Gaps Identified During Audit

Before this update cycle, the main gaps were:

- Alerts tab in artisan shell was using generic customer notifications UI.
- Push deep-link fallback defaulted to customer role, causing potential artisan routing mismatches.
- Artisan alert unread/read state was not persisted on tap, risking stale unread indicators.
- No artisan unread badge parity in bottom navigation.
- Artisan API lacked request timeout hardening for poor-network scenarios.

---

## 3) Implemented Hardening Updates (This Iteration)

### 3.1 Correct Artisan Alerts Surface

- Artisan shell now uses `ArtisanNotificationsScreen` directly for the Alerts tab.

Updated file:

- `frontend/lib/features/shell/artisan_shell.dart`

### 3.2 Role-Aware Push Deep Link Navigation

- Push manager now stores session role on registration and uses payload role when available.
- Message-open navigation now routes by resolved role instead of hardcoded customer fallback.

Updated file:

- `frontend/lib/core/notifications/push_notification_manager.dart`

### 3.3 Artisan Unread Badge in Bottom Navigation

- Added artisan unread badge provider and wired unread dot overlay into artisan nav.
- Aligns artisan UX with customer notification badge behavior.

Updated files:

- `frontend/lib/features/artisan/providers/artisan_notification_badge.dart`
- `frontend/lib/main.dart`
- `frontend/lib/features/shell/artisan_shell.dart`

### 3.4 Mark-As-Read on Artisan Notification Open

- Opening an unread artisan notification now attempts server-side `mark read`.
- Local list is optimistically updated to read state and badge count is synced.

Updated files:

- `frontend/lib/features/artisan/ui/artisan_notifications_screen.dart`
- `frontend/lib/features/artisan/data/artisan_api.dart`

### 3.5 API Reliability Hardening

- Added request timeout handling to artisan API calls (assignments, notifications, assignment detail, lifecycle actions, progress upload, mark-read).
- Introduced friendlier timeout/network failure messaging via `ArtisanApiException`.

Updated file:

- `frontend/lib/features/artisan/data/artisan_api.dart`

---

## 4) Remaining Recommended Work (Next Sprint)

1. Add artisan integration/widget tests for:
   - jobs list states,
   - notification tap-to-job navigation,
   - role-aware push deep-link routing.
2. Add detail-screen refresh affordance on `ArtisanJobDetailScreen` for long-running tasks.
3. Introduce centralized retry/backoff policy shared across feature APIs.
4. Add crash/telemetry instrumentation to track artisan workflow failures in production.

---
