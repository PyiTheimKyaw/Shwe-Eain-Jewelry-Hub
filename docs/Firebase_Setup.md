# Firebase setup and testing

This project uses Firebase for **Firestore** (inquiry chat) and **Firebase Auth** (custom tokens minted by Laravel). This document lists **everything** to configure and **how to verify** each part.

---

## Architecture (short)

| Layer | Role |
|-------|------|
| **Firestore** | Stores messages under `inquiry_threads/{threadId}/messages`. |
| **Firebase Auth** | Users sign in with a **custom token** from Laravel (`laravel_{userId}` UIDs). |
| **Laravel** | `kreait/firebase-php` mints tokens; optional Firestore thread metadata (lazy; needs extra PHP deps for full Firestore client). |
| **Flutter / Filament** | Initialize Firebase with your app configs, then use Auth + Firestore after custom-token sign-in. |

---

## Complete setup checklist (order matters)

1. **Firebase Console** — Create/select project; enable **Firestore**; register **Web**, **Android**, and **iOS** apps (package/bundle IDs must match the Flutter app).
2. **Service account** — Project settings → Service accounts → generate JSON key; save securely (e.g. `backend/storage/app/private/…json`).
3. **Backend `.env`** — Set `FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT_ID`, `FIREBASE_WEB_API_KEY` (see table below).
4. **Flutter native configs** — Place `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) in the paths below.
5. **`frontend/lib/firebase_options.dart`** — Fill Web / Android / iOS `FirebaseOptions` (or run `flutterfire configure` in `frontend/`).
6. **iOS CocoaPods** — After adding Firebase Flutter packages: `cd frontend && flutter pub get && cd ios && pod install` so `Podfile.lock` includes `firebase_core`, `Firebase`, etc.
7. **Firestore rules** — Repo rules in `firebase/firestore.rules`; CLI project in `firebase/.firebaserc` (default project id); deploy with Firebase CLI.
8. **`firebase login`** — One-time CLI authentication on your machine.

---

## Summary table

| What | Purpose | Where / how |
|------|---------|-------------|
| Firebase project | Hosts Firestore, Auth, app registrations | [Firebase Console](https://console.firebase.google.com/) |
| **Firestore** | Chat data | Console → Firestore Database → create database |
| **Firestore rules** | Access control | `firebase/firestore.rules` → deploy (below) |
| **Service account JSON** | Laravel Admin SDK (tokens + optional Firestore) | Console → Service accounts → new key → `FIREBASE_CREDENTIALS` path |
| **`FIREBASE_PROJECT_ID`** | Filament JS + alignment | Project settings → **Project ID** |
| **`FIREBASE_WEB_API_KEY`** | Filament embedded Firebase JS | Web app → SDK snippet `apiKey` (same as Flutter `web` in `firebase_options.dart`) |
| **`google-services.json`** | Flutter Android | `frontend/android/app/google-services.json` |
| **`GoogleService-Info.plist`** | Flutter iOS | `frontend/ios/Runner/GoogleService-Info.plist` |
| **`firebase_options.dart`** | `Firebase.initializeApp()` | `frontend/lib/firebase_options.dart` |
| **`firebase/.firebaserc`** | CLI default project | `default` → your project id (e.g. `shwe-eain-jewelry-hub`) |
| Firebase CLI | Deploy rules | `firebase login`; deploy from `firebase/` |

---

## 1. Firebase Console

1. Open the [Firebase Console](https://console.firebase.google.com/).
2. **Firestore**: Build → Firestore Database → create (deploy this repo’s rules before relying on production traffic).
3. **Apps** (Project settings → Your apps):
   - **Android** — package name matches `frontend/android/app/build.gradle.kts` (e.g. `com.shweeain.hub.jewelry_hub`).
   - **iOS** — bundle ID matches Xcode / `firebase_options.dart` `iosBundleId` (e.g. `com.shweeain.hub.jewelryHub`).
   - **Web** — note `apiKey`, `appId`, `authDomain`, `projectId`, `storageBucket`, `messagingSenderId`, `measurementId` if used.

---

## 2. Backend (Laravel)

**File:** `backend/.env` (see `backend/.env.example`).

| Variable | Purpose |
|----------|---------|
| `FIREBASE_CREDENTIALS` | Path to service account JSON (relative to backend root or absolute), readable by PHP. |
| `FIREBASE_PROJECT_ID` | Firebase project id (must match Firestore project). |
| `FIREBASE_WEB_API_KEY` | Web app API key (Filament chat blade). |

**Note:** Custom token minting uses **Firebase Auth** only. **Firestore PHP client** (for optional server-side thread document sync) is optional and may require `google/cloud-firestore` and PHP `ext-grpc`; the app lazy-loads Firestore so **admin chat and API tokens work without** the Firestore client package.

---

## 3. Flutter (`frontend/`)

| Item | Path / action |
|------|----------------|
| Android config | `android/app/google-services.json` |
| iOS config | `ios/Runner/GoogleService-Info.plist` |
| Dart options | `lib/firebase_options.dart` |
| iOS pods | After dependency changes: `flutter pub get` then `cd ios && pod install` |

**API URL:** `lib/core/config/api_config.dart` — default `http://127.0.0.1:8000`; iOS Simulator uses host loopback; Android emulator often needs `--dart-define=API_BASE_URL=http://10.0.2.2:8000`.

---

## 4. Firestore rules deploy

**Prerequisites:** [Firebase CLI](https://firebase.google.com/docs/cli) installed, `firebase login` completed.

**Project:** `firebase/.firebaserc` should map `default` to your Firebase project id.

**Commands:**

```bash
cd firebase
firebase deploy --only firestore:rules
```

If you skip `.firebaserc`:

```bash
firebase deploy --only firestore:rules --project YOUR_PROJECT_ID
```

Target name is **`firestore:rules`** (plural), not `firestore:rule`.

---

## 5. Security notes

- Do **not** commit service account JSON or production `.env` to public repos.
- **Web API keys** are client-side by design; restrict keys in Google Cloud Console if needed.
- **Custom token** claims (`role`, `thread_id`, `inquiry_id`) are enforced together with Firestore rules.

---

## How to test everything

Work through these in order; later steps assume earlier ones pass.

### A. Backend (no Firebase calls)

```bash
cd backend
php artisan test tests/Feature/InquiryApiTest.php
```

Creates an inquiry and asserts `firebase_thread_id` is set (MySQL only).

### B. Laravel + Firebase token (API)

1. Run API: `php artisan serve` (or your `composer run dev` stack).
2. Log in as a **customer** via your existing auth API (Sanctum token).
3. Create an inquiry with `POST /api/v1/inquiries` (if not already).
4. Call **`POST /api/v1/conversations/{inquiry}/firebase-token`** with `Authorization: Bearer <token>`.

**Expect:** JSON with `firebase_token`, `firebase_thread_id`, `auth_uid`. If you see “Firebase is not configured”, check `FIREBASE_CREDENTIALS` path and file readability.

### C. Firestore rules deployed

After `firebase deploy --only firestore:rules`, open **Firebase Console → Firestore → Rules** and confirm the revision timestamp updated.

### D. Flutter app (customer chat)

1. **Simulator/device:** `flutter run` with API pointing at your Laravel host (see `API_BASE_URL`).
2. **Console:** No `Firebase.initializeApp failed` / channel-error on startup (if you see channel-error on iOS, run `pod install` and do a **full** restart, not only hot restart).
3. Open an inquiry → **Chat**: should call the token endpoint, then `signInWithCustomToken`, then show the Firestore stream.

**If chat fails:** Check rules, token response, and that `firebase_thread_id` on the inquiry matches the Firestore path.

### E. Filament admin (dashboard chat)

1. Set `FIREBASE_PROJECT_ID` and `FIREBASE_WEB_API_KEY` in `.env`.
2. Open **`/admin/inquiries/{id}/chat`** as an admin.

**Expect:** Chat UI loads (token minted server-side). If the page shows the “configure Firebase” message, verify env vars and `FIREBASE_CREDENTIALS`.

### F. End-to-end chat

1. Customer sends a message in Flutter.
2. Admin sees it in Filament (and vice versa) on the same `inquiry_threads/{threadId}/messages` path.

---

## Troubleshooting: `firebase_auth/unknown` on mobile chat

This usually appears at **`signInWithCustomToken`** (after Laravel returns a token). It is **not** the same as Firestore rules deploy—Auth uses Google’s token service.

1. **`firebase_options.dart`** — Ensure **Android / iOS** include **`authDomain`** (`<projectId>.firebaseapp.com`) and match **`GoogleService-Info.plist`** / **`google-services.json`** (`projectId`, `GOOGLE_APP_ID` / `mobilesdk_app_id`).
2. **Service account** — The JSON in **`FIREBASE_CREDENTIALS`** must be for the **same** Firebase/GCP project as the mobile apps (`project_id` in that JSON = `shwe-eain-jewelry-hub`).
3. **Firebase Console → App Check** — If **Authentication** is **enforced** without the iOS/Android app registered (or a debug token), clients can fail with generic **`unknown`**. For local dev, leave Auth **unenforced** or add a **debug provider** / App Attest as documented by Firebase.
4. **Network** — The phone must reach **Google** (not only your LAN API). VPNs, captive portals, or DNS blocks can cause **`unknown`**.
5. **Rebuild** — After changing `firebase_options.dart` or native config: **`flutter clean`**, **`cd ios && pod install`**, full **`flutter run`** (not only hot reload).

---

## Quick reference commands

```bash
# Rules only
cd firebase && firebase deploy --only firestore:rules

# Backend test
cd backend && php artisan test tests/Feature/InquiryApiTest.php

# Flutter (example)
cd frontend && flutter run -d ios --dart-define=API_BASE_URL=http://127.0.0.1:8000
```
