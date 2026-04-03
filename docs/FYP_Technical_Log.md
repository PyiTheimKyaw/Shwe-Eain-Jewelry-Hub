# FYP Technical Log — Jewelry Hub

**Run locally:** see [Local_Setup.md](./Local_Setup.md) (Laravel + Filament + Flutter).

## 2026-04-03 — Database schema and REST API v1 scaffold

### Summary

Implemented the planned MySQL schema (migrations under `backend/database/migrations/`) and Laravel REST API under `https://{host}/api/v1`, authenticated with **Laravel Sanctum** bearer tokens. Weights are stored in **grams**; monetary fields in **USD** with **MMK snapshots** on quotes and orders tied to `exchange_rate_snapshots`.

### Migrations (domain)

| Migration | Purpose |
|-----------|---------|
| `0001_01_01_000000_create_users_table` | `users` + `role` enum (`admin`, `artisan`, `customer`) |
| `2026_04_02_*_create_personal_access_tokens_table` | Sanctum tokens |
| `2026_04_03_100000` … `100150` | Profiles, catalog, pricing snapshots, inquiries, quotes, orders, assignments, progress + attachments |

### Firebase bridge (chat + progress sync)

- **`inquiries.firebase_thread_id`**: Nullable string. First call to `POST /api/v1/conversations/{inquiry}/firebase-bootstrap` (authenticated; `InquiryPolicy::view`) generates a UUID if missing and persists it. Flutter/Firebase security rules can key threads on this value while MySQL remains the inquiry source of truth.
- **`order_progress_events.firebase_event_id`**: Optional dedupe key. `POST` progress endpoints reject duplicate `firebase_event_id` with HTTP 409 so a client cannot double-insert the same Firebase-written event into MySQL when mirroring for reporting.

### API surface

All routes are registered in `backend/routes/api.php` under the `v1` prefix. Role gates use middleware `role:admin`, `role:artisan`, or `role:customer` where appropriate; resource access also uses policies (`OrderPolicy`, `InquiryPolicy`, `QuotePolicy`, etc.) on `orders/{order}/progress` and related actions.

### Services

- `App\Services\PricingService` — current gold/FX snapshot resolution; closing prior open-ended rows when recording new snapshots.
- `App\Services\QuoteBuilderService` — builds `quotes` + `quote_line_items` with metal/labor/gem math and MMK line snapshots.
- `App\Services\OrderBuilderService` — creates `orders` + `order_items` from an **accepted** quote.

### Form requests (initial)

- `App\Http\Requests\Api\V1\RegisterRequest`
- `App\Http\Requests\Api\V1\LoginRequest`

### Next steps (not done in this entry)

- Flutter Firebase chat/progress listeners wired to the IDs above.
- Admin web UI (Laravel Blade/Inertia or separate SPA) consuming the same API.
- Optional: presigned S3/MinIO uploads replacing `POST /api/v1/uploads` for production scale.

---

## 2026-04-03 — Pricing & unit conversion implementation

### Laravel pricing engine

- **`App\Services\PricingService`** exposes:
  - `currentGoldMmkPerGram()` / `currentMmkPerUsd()` — scalar accessors from the active snapshot (or `null` if none); plus `mmkToUsd` / `usdToMmk` for conversions.
  - `burmeseMassStandards()` / `currentPricingContext()` — shared constants for **mass** kyat conversion: **16.606 g = 1 Kyat**, **16 Pae = 1 Kyat**, **8 Yway = 1 Pae** (aligned with `.cursorrules`).
- **Public API** (no auth required):
  - `GET /api/v1/pricing/gold/current` — `{ data: { id, price_mmk_per_gram, effective_from, effective_to } | null, meta: { configured, weight_standards } }`
  - `GET /api/v1/pricing/fx/current` — `{ data: { id, mmk_per_usd, effective_from, effective_to } | null, meta: { configured, weight_standards } }`
- Datetimes are ISO-8601 strings. `meta.weight_standards` lets the app stay in sync with backend constants without a separate config endpoint.
- Feature coverage: `backend/tests/Feature/PricingCurrentEndpointsTest.php`.

### Flutter — grams → Kyat / Pae / Yway

- New module: `frontend/lib/core/units/myanmar_mass.dart`
  - `MyanmarMassStandards` — constants matching the API / FYP rules.
  - `MyanmarMassBreakdown.fromGrams(double)` — decomposition for UI.
  - `toDisplayString(...)` — formatted label (optional gram suffix).
  - **`extension MyanmarMassGramsExtension on num`** — e.g. `16.606.asBurmeseMass`.
  - **`mixin MyanmarMassDisplay`** — optional helper for widgets/state (`formatBurmeseMass`).
- Tests: `frontend/test/myanmar_mass_test.dart`.

---

## Step 3: Product Catalog & Global UI Implementation (2026-04-03)

### Laravel — catalog data & API

- Migration `2026_04_03_120000_add_is_ready_made_to_products_table`: boolean `is_ready_made` (default `true`); existing rows with `is_custom_order_template = true` backfilled to `is_ready_made = false`.
- **`App\Services\CatalogProductPresenter`**: enriches catalog JSON with `images[].url` (public disk), optional **`pricing`** (metal/labor/gem/totals + snapshot ids) from current gold/FX via `PricingService`; pagination responses include **`meta.pricing_configured`** and **`meta.weight_standards`**.
- **`GET /api/v1/catalog/products`**: filters `category_id`, `search`, **`ready_made`** (`1`/`0`); transformed product payloads as above.
- **`GET /api/v1/catalog/products/{id}`**: same shape + `meta`.
- Admin JSON **`ProductController`**: stricter validation — `weight_grams` max `10000`, **`labor_fee_mmk` required** on create; **`is_ready_made`** on create/update.
- Tests: `backend/tests/Feature/CatalogApiTest.php`.

### Laravel — Filament admin dashboard

- Package: **Filament v4** (`filament/filament`). Panel path **`/admin`**, primary color `#D4AF37`.
- **`User`** implements `FilamentUser`; **`canAccessPanel`** allows only `role === admin`.
- Resources: **`CategoryResource`** (slug auto from name on blur), **`ProductResource`** (validated weights/fees/toggles), **`ImagesRelationManager`** for `product_images` via `FileUpload` on `public` disk under `products/{id}/`.
- **Pricing** (navigation group): **`GoldPriceSnapshotResource`** (“Gold prices”) and **`ExchangeRateSnapshotResource`** (“Exchange rates”) — list history + **create** only; new rows go through **`PricingService::recordGoldPrice` / `recordExchangeRate`** so the previous open-ended snapshot gets `effective_to` set.
- Seeders: `admin@jewelryhub.test` / `password` (change in production) via `User::factory()->admin()`; **`PricingSnapshotSeeder`** — idempotent active **gold** (`price_mmk_per_gram` demo `168000`) + **FX** (`mmk_per_usd` demo `2100`) so **`meta.pricing_configured`** is true and mobile/catalog show numeric estimates after `php artisan db:seed`; **`CatalogSeeder`** — 4 categories and 7 published products (idempotent by `slug` / `sku`). Order in **`DatabaseSeeder`**: users → pricing snapshots → catalog.
- Test: `backend/tests/Feature/SeededCatalogPricingTest.php` asserts a full `DatabaseSeeder` run yields catalog `pricing` on products.

### Flutter — design system & customer catalog

- **`frontend/lib/core/ui/design_system.dart`**: `AppColors`, `AppRadii`, `AppGradients`, `AppTheme.darkGold()` (Cormorant Garamond + Cinzel Decorative + Outfit via `google_fonts`).
- **Widgets** (`frontend/lib/core/ui/widgets/`): `app_glass_card.dart`, `app_gold_button.dart`, `premium_text.dart`, **`price_tag.dart`** (USD/MMK + `AnimatedSwitcher`), **`weight_badge.dart`** (g vs K·P·Y + `AnimatedSwitcher`, uses `myanmar_mass.dart`).
- **Providers**: `DisplayPreferences` (currency + weight toggles — **local `notifyListeners()` only**, no refetch per toggle), `CatalogPricing` (fetches `GET /pricing/fx/current` for fallback MMK when needed).
- **Screens**: `ProductCatalogScreen` — pinned **`CatalogToggleBar`**, shimmer grid, **`Hero`** image to `ProductDetailScreen`; detail shows pricing breakdown from API.
- **Config**: `ApiConfig.apiV1` — override with `--dart-define=API_BASE_URL=...` (Android: `usesCleartextTraffic` + `INTERNET` for dev HTTP).
- Dependencies: `provider`, `http`, `shimmer`, `cached_network_image`, `google_fonts`, `intl`.

### Dual-engine rationale (pricing & units)

- **Backend** remains the source of truth for quoted/stored money and catalog **estimates** (`pricing` on each product when snapshots exist).
- **Frontend** mirrors **display** choices only: toggling USD/MMK or grams vs Burmese mass updates the UI via **providers** immediately (fade transitions), without a network round-trip per toggle; USD may use API `line_total_usd` or client `mmk_per_usd` from the FX endpoint when only one side is present.

---

## 2026-04-04 — MMK as base stored currency

- **Schema** (original catalog/quote/order migrations edited for new installs): `products.labor_fee_mmk` / `gem_price_mmk`; `gold_price_snapshots.price_mmk_per_gram`; `exchange_rate_snapshots.mmk_per_usd` (MMK per 1 USD, same numeric meaning as former `usd_to_mmk`); quotes/orders use `total_mmk` + `total_usd_snapshot`; line items store MMK components + `line_total_mmk` + `line_total_usd_snapshot`.
- **Logic**: `PricingService` adds `mmkToUsd` / keeps `usdToMmk`; catalog and quote math run in MMK first; USD is derived for display/snapshots.
- **API**: `GET/POST` pricing payloads use `price_mmk_per_gram` and `mmk_per_usd`; catalog `pricing` object exposes `*_mmk` fields and `line_total_usd` as estimate.
- **Flutter**: default catalog currency **MMK**; `CatalogPricing` reads `mmk_per_usd` (with fallback key `usd_to_mmk` for older servers).
- **Existing DBs**: run `php artisan migrate:fresh` (or add a dedicated rename migration) if an older USD-based schema was already migrated.

### Product list prices (customer API & app)

- Catalog **`pricing`** is omitted until **both** a current `gold_price_snapshots` row and a current `exchange_rate_snapshots` row exist (`CatalogController` / `CatalogProductPresenter`).
- **Local / FYP demo**: run **`php artisan migrate:fresh --seed`** (or `db:seed` on an empty pricing table) so **`PricingSnapshotSeeder`** runs before **`CatalogSeeder`**; the Flutter **`PriceTag`** then shows MMK/USD instead of “Price on request”.
- **Production / staging**: set rates via **Filament → Pricing** or authenticated **`POST /api/v1/pricing/gold`** and **`POST /api/v1/pricing/fx`** (admin).

---

## 2026-04-03 — Flutter authentication & session

### Summary

Mobile app now **requires sign-in** before the customer catalog: Laravel **Sanctum** bearer token is stored in **`flutter_secure_storage`**, session is restored on cold start via **`GET /api/v1/auth/me`**, and the catalog screen exposes **logout**.

### Implementation

- **`frontend/lib/core/auth/auth_token_storage.dart`** — key `sanctum_token`; Android uses encrypted shared preferences.
- **`frontend/lib/features/auth/data/auth_api.dart`** — `register` / `login` / `logout` / `me` against `/api/v1/auth/*` with `Accept` + `Content-Type: application/json`; Bearer on authenticated routes; parses Laravel **`message`** / **`errors`** for failures.
- **`frontend/lib/features/auth/models/auth_user.dart`** — `id`, `name`, `email`, `role` from API user payload.
- **`frontend/lib/core/providers/auth_provider.dart`** — `bootstrap()` reads token, validates with `me`, clears invalid token; `login` / `register` persist token; `logout` calls API then clears storage.
- **`frontend/lib/features/auth/ui/login_screen.dart`** & **`register_screen.dart`** — `AppGlassCard`, `AppGoldButton`, `PremiumText`, `AnimatedSwitcher` for loading; register collects optional `phone`; password rules aligned with backend (confirm + min length on client).
- **`frontend/lib/main.dart`** — `MultiProvider` includes **`AuthProvider`** first; **`_AuthGate`** with **`AnimatedSwitcher`**: bootstrap splash → **`LoginScreen`** or **`ProductCatalogScreen`**.
- **`frontend/lib/features/catalog/ui/product_catalog_screen.dart`** — **`SliverAppBar`** **`IconButton`** → **`AuthProvider.logout()`**.
- **`frontend/lib/core/ui/design_system.dart`** — shared **`inputDecorationTheme`** (gold focus border, glass fill) for auth (and future forms).
- **Dependencies**: `flutter_secure_storage` (see `pubspec.yaml`).
- **Tests**: `test/widget_test.dart` — **`FlutterSecureStorage.setMockInitialValues({})`**; expects **Sign in** after bootstrap (avoids `pumpAndSettle` on indeterminate progress indicator).

### Demo accounts

Use seeded **`customer@example.com`** / **`password`** or **`admin@jewelryhub.test`** / **`password`** after `php artisan db:seed`, or **Create an account** (API registers **customer** role only).