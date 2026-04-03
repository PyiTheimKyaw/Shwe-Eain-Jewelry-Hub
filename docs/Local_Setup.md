# Local development — backend & mobile

How to run the **Laravel API + Filament admin** (`backend/`) and the **Flutter app** (`frontend/`) on your machine.

## Prerequisites

- **Backend:** PHP **8.3+**, [Composer](https://getcomposer.org/), [Node.js](https://nodejs.org/) (for Vite / Filament assets if you use `composer run dev` or `npm run build`)
- **Database:** The default `.env.example` uses **SQLite** (`DB_CONNECTION=sqlite`). For **MySQL** (as in the FYP spec), set `DB_*` in `.env` and create an empty database before migrating.
- **Mobile:** [Flutter SDK](https://docs.flutter.dev/get-started/install) (stable), Xcode (iOS) and/or Android Studio (Android)

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

### `Failed opening required '.../vendor/autoload.php'`

PHP dependencies are missing. From `backend/` run:

```bash
composer install
```

Do not commit `vendor/`; every fresh clone needs this step before `php artisan` works.

---

## Quick checklist

1. Backend: `composer install` → `.env` + `key:generate` → DB file or MySQL → `migrate` → `db:seed` → `storage:link` → `php artisan serve`
2. Mobile: `flutter pub get` → `flutter run` with `--dart-define=API_BASE_URL=...` if not using `127.0.0.1`
3. Open **Filament** at `/admin` with `admin@jewelryhub.test` / `password`

For architecture and feature history, see `docs/FYP_Technical_Log.md` and `.cursorrules`.
