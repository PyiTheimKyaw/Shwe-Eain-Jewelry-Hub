# Section 5.1: Development Environment & Setup

This section documents the practical software environment used to develop, run, and test the **Shwe Eain Jewelry Hub** platform across backend, mobile frontend, and cloud integration layers.

---

## 5.1.1 Backend Environment

The backend is implemented using Laravel as a RESTful application server and Filament for administrative operations. According to the current project dependency manifest, the backend targets **Laravel 13** and **PHP 8.3+**.

### Backend software specifications

| Component | Version / Configuration | Evidence in Project |
|---|---|---|
| Framework | Laravel `^13.0` | `backend/composer.json` |
| Language Runtime | PHP `^8.3` | `backend/composer.json` |
| Admin Panel | Filament `^4.0` | `backend/composer.json` |
| API Authentication | Laravel Sanctum `^4.3` | `backend/composer.json` |
| PDF Engine | barryvdh/laravel-dompdf `^3.1` | `backend/composer.json` |
| Excel/CSV Export | maatwebsite/excel `^3.1` | `backend/composer.json` |
| Firebase Admin SDK | kreait/firebase-php `^8.2` | `backend/composer.json` |
| Optional Firebase Storage Disk | spatie/laravel-google-cloud-storage `^2.4` | `backend/composer.json` |

### Database configuration (MySQL-focused deployment)

The default local environment template uses SQLite for quick bootstrapping, but the project specification and production-style deployment are MySQL-based.

| Setting | Local Default | MySQL Deployment Target |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | `mysql` |
| `DB_HOST` | (commented in `.env.example`) | e.g., `127.0.0.1` / server host |
| `DB_PORT` | (commented in `.env.example`) | `3306` |
| `DB_DATABASE` | sqlite file path | MySQL schema name |
| `DB_USERNAME` | N/A for sqlite | MySQL user |
| `DB_PASSWORD` | N/A for sqlite | MySQL password |

Technical note: monetary fields across catalog, quotation, order, and payment tables are persisted using fixed-precision decimals and processed through business services, which aligns with financial consistency requirements for MMK-based calculations.

### Authentication architecture for API v1

API v1 uses **Laravel Sanctum bearer tokens** for role-scoped authentication (`admin`, `artisan`, `customer`). This design provides:
- Stateful user identity with token revocation capability.
- Clean separation between public catalog/pricing endpoints and protected transactional endpoints.
- Secure interoperability with Flutter clients using token storage and authenticated HTTP calls.

---

## 5.1.2 Mobile Frontend Environment

The mobile client is a Flutter application with role-based shells (customer, artisan, and lightweight admin-mobile alerts flow). The current package constraints indicate a modern Dart and Flutter toolchain.

### Mobile software specifications

| Component | Version / Constraint | Evidence in Project |
|---|---|---|
| SDK Language | Dart `>=3.11.0 <4.0.0` | `frontend/pubspec.yaml`, `frontend/pubspec.lock` |
| Framework | Flutter `>=3.38.4` (resolved toolchain floor) | `frontend/pubspec.lock` |
| State Management | `provider: ^6.1.2` | `frontend/pubspec.yaml` |
| Networking | `http: ^1.3.0` | `frontend/pubspec.yaml` |
| Secure Token Storage | `flutter_secure_storage: ^9.2.4` | `frontend/pubspec.yaml` |
| Realtime + Auth | `firebase_core`, `firebase_auth`, `cloud_firestore` | `frontend/pubspec.yaml` |
| Push Notifications | `firebase_messaging: ^15.2.5` | `frontend/pubspec.yaml` |
| Local Caching | `hive`, `hive_flutter`, `shared_preferences` | `frontend/pubspec.yaml` |
| Media Input | `image_picker: ^1.1.2` | `frontend/pubspec.yaml` |
| File Access / Viewer | `path_provider`, `open_filex` | `frontend/pubspec.yaml` |

### Dark Gold design system implementation

The UI layer uses a centralized premium design system in `frontend/lib/core/ui/design_system.dart`, with:
- `AppTheme.darkGold()` as the global `ThemeData`.
- Branded palette via `AppColors` (e.g., `gold #D4AF37`, `background #0F172A`).
- Shared spacing/radius/gradient tokens (`AppRadii`, `AppGradients`).
- Typography pairing through Google Fonts (Cinzel Decorative, Cormorant Garamond, Outfit).

This centralized theme strategy supports visual consistency and maintainability, ensuring that major screens can reuse standard components instead of ad-hoc styling.

---

## 5.1.3 Infrastructure & Integration (Firebase)

Firebase is integrated as the realtime communication layer while Laravel/MySQL remains the authoritative business ledger.

### Firebase integration scope

| Firebase Service | Role in System | Integration Point |
|---|---|---|
| Firestore | Realtime inquiry chat stream (`inquiry_threads/{threadId}/messages`) | Flutter + Filament listeners |
| Firebase Auth | Secure session for realtime access via Custom Token sign-in | Laravel token minting endpoint |
| FCM | Push notifications for role-targeted workflow events | Backend notification service + mobile handlers |

### Secure cross-platform authentication using Firebase Custom Tokens

For chat access, the platform does not rely on anonymous Firestore clients. Instead, it uses a controlled server-issued token flow:

1. User authenticates to Laravel API via Sanctum.
2. Client requests chat token from backend conversation endpoint.
3. Laravel (via `kreait/firebase-php`) mints a **Firebase Custom Token** with context claims (e.g., role/thread scope).
4. Flutter/Filament performs `signInWithCustomToken`.
5. Client subscribes to Firestore thread/message paths governed by deployed rules.

This architecture provides:
- Centralized trust boundary at backend.
- Consistent identity between REST workflows and realtime chat.
- Better policy enforcement for role-sensitive conversations.

### Required backend Firebase configuration

| Environment Variable | Purpose |
|---|---|
| `FIREBASE_CREDENTIALS` | Service account JSON path for Firebase Admin SDK |
| `FIREBASE_PROJECT_ID` | Firebase project alignment across backend and clients |
| `FIREBASE_WEB_API_KEY` | Required for Filament-side embedded Firebase web usage |

Operational note: backend Firestore writes are designed to be resilient; where optional server-side Firestore write capability is unavailable, formal workflow state still remains correct in MySQL/Laravel and can be surfaced through API fallback payloads.

---

## 5.1.4 Host Development Context (Current Workspace Snapshot)

| Item | Value |
|---|---|
| Operating System | Darwin 25.4.0 (macOS host) |
| Backend Workspace | `backend/` (Laravel + Filament) |
| Frontend Workspace | `frontend/` (Flutter) |
| Documentation & Setup References | `docs/Local_Setup.md`, `docs/Firebase_Setup.md` |

This environment supports full-stack local development, including API, admin panel, mobile testing, and Firebase-backed realtime features.

