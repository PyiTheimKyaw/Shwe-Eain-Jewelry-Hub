# Local development — backend & mobile

How to run the **Laravel API + Filament admin** (`backend/`) and the **Flutter app** (`frontend/`) on your machine.

## Prerequisites

- **Backend:** PHP **8.3+**, [Composer](https://getcomposer.org/), [Node.js](https://nodejs.org/) (for Vite / Filament assets if you use `composer run dev` or `npm run build`)
- **Database:** The default `.env.example` uses **SQLite** (`DB_CONNECTION=sqlite`). For **MySQL** (as in the FYP spec), set `DB_*` in `.env` and create an empty database before migrating.
- **Mobile:** [Flutter SDK](https://docs.flutter.dev/get-started/install) (stable), Xcode (iOS) and/or Android Studio (Android)
- **Inquiry chat (optional for local dev):** Firebase project + credentials; see [Firebase_Setup.md](./Firebase_Setup.md).

---

## Backend (Laravel)

All commands assume your shell is in the `backend/` directory.

### 1. Install dependencies

```bash
cd backend
composer install
```

### 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database

**SQLite (quickest):** ensure the database file exists (Laravel expects `database/database.sqlite` when using the default sqlite config):

```bash
touch database/database.sqlite
```

**MySQL:** in `.env`, set `DB_CONNECTION=mysql` and fill in `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Comment out or remove the sqlite-only settings if your `config/database.php` requires it.

### 4. Migrate and seed

```bash
php artisan migrate
php artisan db:seed
```

Seeding creates:

- **Filament admin:** `admin@jewelryhub.test` / `password` (change in production)
- **Demo customer:** `customer@example.com` / `password`
- Active **gold + FX** pricing snapshots and **catalog** products so `GET /api/v1/catalog/products` returns `meta.pricing_configured: true` and numeric prices in the app.
- Command-center demo operations data (inquiries, orders, payments, assignments) for dashboard charts and pulse widgets.

### Optional: refresh only Command Center demo data

If you only want to repopulate dashboard analytics/pulse data without touching migrations:

```bash
php artisan db:seed --class=CommandCenterDemoSeeder
```

This is useful after clearing records used by:
- Revenue chart (`RevenueBarChartWidget`)
- Order stage doughnut (`OrderStageDoughnutWidget`)
- Inquiry conversion trend (`InquiryConversionLineWidget`)
- Operations pulse (`OperationsPulseWidget`)

### 5. Public URLs for uploaded catalog images

Product images use the `public` disk. Link storage once:

```bash
php artisan storage:link
```

### 6. Run the API and admin UI

```bash
php artisan serve
```

- **API base:** `http://127.0.0.1:8000/api/v1`
- **Filament admin:** `http://127.0.0.1:8000/admin` (sign in with the admin user above)

### Optional: one-shot setup script

Composer can install PHP deps, create `.env`, generate the key, migrate, and build frontend assets:

```bash
composer run setup
```

That does **not** run `db:seed` or `storage:link`; run those separately if you need demo data and image URLs.

### Optional: full dev stack (server + queue + logs + Vite)

```bash
composer run dev
```

---

## Mobile (Flutter)

All commands assume your shell is in the `frontend/` directory.

### 1. Dependencies

```bash
cd frontend
flutter pub get
```

### 2. Point the app at your API

Default in code is `http://127.0.0.1:8000` (see `lib/core/config/api_config.dart`). Override with **`API_BASE_URL`** when the device cannot reach that host:

| Where you run the app | Typical `API_BASE_URL` |
|-----------------------|-------------------------|
| iOS Simulator (Mac) | `http://127.0.0.1:8000` (default is fine) |
| Android Emulator | `http://10.0.2.2:8000` (emulator → host loopback) |
| Physical phone/tablet | `http://<your-computer-LAN-IP>:8000` (same Wi‑Fi as the phone) |

Example (Android emulator):

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

### 3. Run

```bash
flutter run
```

Android dev builds already allow **cleartext HTTP** in `AndroidManifest.xml` for local APIs. Use **HTTPS** in production.

### 4. Tests (optional)

```bash
flutter test
```

---

## Troubleshooting

### API rate limits (429) on auth/upload/payment/quote actions

Sensitive mutation endpoints now use per-user/per-IP throttles. In local testing, repeated rapid retries can return HTTP `429 Too Many Requests`.

- Wait briefly and retry (cool-down is minute-scale for most buckets).
- For scripted tests, use realistic pacing and avoid burst retries on login/upload endpoints.
- If needed for debugging, inspect limiter keys in `backend/app/Providers/AppServiceProvider.php`.

### `Failed opening required '.../vendor/autoload.php'`

PHP dependencies are missing. From `backend/` run:

```bash
composer install
```

Do not commit `vendor/`; every fresh clone needs this step before `php artisan` works.

### Filament inquiry chat not updating / timeline missing after decline

If the admin inquiry chat or timeline looks stale after deploying recent Blade/Livewire changes:

```bash
php artisan optimize:clear
php artisan view:cache
```

Then hard-refresh the browser tab (`Cmd+Shift+R` on macOS).  
This ensures updated polling/render logic is loaded.

### Admin chat image upload returns 403

The admin chat upload endpoint is now restricted to authenticated admin users only.

- Ensure you are signed in as an admin account in Filament.
- Route: `POST /admin/inquiries/{inquiry}/chat-upload`.

### Artisan/customer making specs or photos not appearing

If newly added making specs (`metal_type`, `stone_details`) or product design photos do not show up in Artisan Job Detail or Customer Order Detail:

1. Ensure migrations are applied:

```bash
php artisan migrate
```

2. Rebuild backend caches after deployment:

```bash
php artisan optimize:clear
```

3. Confirm payload shape quickly:
   - `GET /api/v1/artisan/assignments/{id}`
   - `GET /api/v1/orders/{orderId}`
   - verify `metal_type_snapshot`, `stone_details_snapshot`, and `product.images[].storage_path` are present.

4. In Flutter, refresh the app state (hot restart) after backend schema/API updates.

### Firebase Storage for media uploads (optional, cross-device-safe)

By default, uploads use local storage. To store new chat/inquiry/progress media on Firebase Storage instead:

1. Set Firebase storage env values in `backend/.env`:

```bash
MEDIA_STORAGE_PROVIDER=firebase
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS=/absolute/path/to/service-account.json
FIREBASE_STORAGE_BUCKET=your-bucket-name
FIREBASE_STORAGE_PUBLIC_URL=https://storage.googleapis.com/your-bucket-name
```

2. Clear config cache:

```bash
php artisan config:clear
```

3. New uploads will go to Firebase Storage. Existing old local records are not part of the active workflow.

### Push notifications (Phase 3 foundation)

The backend now supports push-device token registration and user notification inbox APIs.

Authenticated endpoints:

- `POST /api/v1/push/devices`
- `DELETE /api/v1/push/devices`
- `GET /api/v1/notifications`
- `POST /api/v1/notifications/{notificationId}/read`

To enable live FCM push delivery (instead of DB-only fallback), ensure Firebase admin credentials are configured (`FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT_ID`) as documented in `docs/Firebase_Setup.md`.

### Push notifications (Phase 3.1 Flutter wiring)

Frontend now includes:

- session-based push token register/unregister during login/logout,
- per-role notification inbox (`Customer`, `Admin`, `Artisan`) with read indicators,
- foreground push banners and background tap routing.

Run and verify:

```bash
cd frontend
flutter pub get
flutter run
```

Quick manual smoke test:

1. Sign in on mobile (token auto-registers to `/api/v1/push/devices`).
2. Trigger a backend notification event (quote publish, payment review, etc.).
3. Confirm:
   - foreground: in-app banner appears,
   - inbox: new row appears as `Unread`,
   - tap row: marks as `Read` and navigates to related inquiry/order screen.
4. Sign out (token unregisters with `DELETE /api/v1/push/devices`).

---

## Quick checklist

1. Backend: `composer install` → `.env` + `key:generate` → DB file or MySQL → `migrate` → `db:seed` → `storage:link` → `php artisan serve`
2. Mobile: `flutter pub get` → `flutter run` with `--dart-define=API_BASE_URL=...` if not using `127.0.0.1`
3. Open **Filament** at `/admin` with `admin@jewelryhub.test` / `password`
4. Optional artisan login for mobile testing:
   - `artisan@jewelryhub.test` / `password`
5. Receipt/PDF feature notes:
   - Backend uses `barryvdh/laravel-dompdf` for canonical server-generated payment receipts.
   - To backfill receipts for previously confirmed payments: `php artisan receipts:generate-missing`
   - Customer app includes an **Orders** tab where receipts can be viewed and downloaded.
6. Three-way sync demo checklist (Admin → Artisan → Customer):
   - Admin publishes quote lines with `metal_type` + `stone_details`.
   - Customer accepts quote; admin confirms deposit.
   - Admin assigns artisan with special instructions.
   - Artisan opens job detail and sees specs + design photos + instructions.
   - Customer opens **Order Details** and confirms the same specs/photos.
7. If Command Center charts are empty after data cleanup:
   - run `php artisan db:seed --class=CommandCenterDemoSeeder`

For architecture and feature history, see `docs/FYP_Technical_Log.md` and `.cursorrules`.