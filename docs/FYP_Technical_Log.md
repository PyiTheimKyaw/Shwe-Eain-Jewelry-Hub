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
- **`frontend/lib/main.dart`** — `MultiProvider` includes **`AuthProvider`** first; **`_AuthGate`** with **`AnimatedSwitcher`**: bootstrap splash → **`LoginScreen`** or **`CustomerShell`** (catalog + inquiries).
- **`frontend/lib/features/catalog/ui/product_catalog_screen.dart`** — **`SliverAppBar`** **`IconButton`** → **`AuthProvider.logout()`** (catalog tab).
- **`frontend/lib/core/ui/design_system.dart`** — shared **`inputDecorationTheme`** (gold focus border, glass fill) for auth (and future forms).
- **Dependencies**: `flutter_secure_storage` (see `pubspec.yaml`).
- **Tests**: `test/widget_test.dart` — **`FlutterSecureStorage.setMockInitialValues({})`**; expects **Sign in** after bootstrap (avoids `pumpAndSettle` on indeterminate progress indicator).

### Demo accounts

Use seeded **`customer@example.com`** / **`password`** or **`admin@jewelryhub.test`** / **`password`** after `php artisan db:seed`, or **Create an account** (API registers **customer** role only).

---

## 2026-04-05 — Real-time inquiry system (Firestore + Flutter + Filament)

### Summary

Customers can **create inquiries** (message + optional photo attachments, optional **`product_id`**), see **my inquiries** with status badges, and open **Firestore-backed chat** per inquiry. **`firebase_thread_id`** is generated **when the inquiry is created**. Laravel mints **Firebase custom tokens** (`kreait/firebase-php`) so Flutter and the Filament web chat use **Firebase Auth** + **Firestore security rules**. Admin manages inquiries in **Filament** (`InquiryResource`) with **Open chat** and a dashboard **Active inquiries** stat.

### Backend

- **Composer**: `kreait/firebase-php`.
- **Migration**: `product_id` nullable FK on `inquiries` → `products`.
- **`InquiryController::store`**: sets **`firebase_thread_id`** = `Str::uuid()` on create; validates optional **`product_id`**; calls **`FirebaseInquiryService::syncInquiryThreadDocument`** (Firestore metadata when configured).
- **`POST /api/v1/conversations/{inquiry}/firebase-token`** (auth:sanctum, `InquiryPolicy::view`) — returns **`firebase_token`**, **`firebase_thread_id`**, **`auth_uid`** (`laravel_{id}`). Custom token claims: **`role`**, **`thread_id`**, **`inquiry_id`**.
- **`App\Services\FirebaseInquiryService`**: singleton from `FIREBASE_CREDENTIALS` in **`config/services.php`**; **`mintChatToken`**, **`syncInquiryThreadDocument`**.
- **Env** (see **`backend/.env.example`**): `FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT_ID`, `FIREBASE_WEB_API_KEY` (web key + project id for Filament JS + rules alignment).
- **Firestore rules**: [`firebase/firestore.rules`](../firebase/firestore.rules) — deploy with Firebase CLI (`firebase deploy --only firestore:rules`). **`firebase/firebase.json`** points at rules.
- **Test**: `tests/Feature/InquiryApiTest.php` — create inquiry returns non-empty **`firebase_thread_id`**.

### Flutter

- **Packages**: `firebase_core`, `firebase_auth`, `cloud_firestore`, `image_picker`; plus existing **`http`**, **`provider`**.
- **`lib/firebase_options.dart`**: placeholder — run **`flutterfire configure`** or paste keys (see **`docs/Firebase_Setup.md`**).
- **`main.dart`**: **`Firebase.initializeApp`** (errors logged; app still runs).
- **`CustomerShell`**: bottom navigation **Catalog** \| **Inquiries** (`IndexedStack`).
- **`features/inquiries/`**: **`inquiry_api.dart`**, **`inquiry_summary.dart`**, **`inquiry_list_screen.dart`**, **`new_inquiry_screen.dart`**, **`inquiry_chat_screen.dart`**, **`inquiry_status_badge.dart`**; **`core/storage/public_storage_url.dart`** for attachment URLs.
- **Product detail**: **Send inquiry** → **`NewInquiryScreen(productId: …)`**.
- **Chat**: **`POST`** firebase-token → **`signInWithCustomToken`** → Firestore `inquiry_threads/{threadId}/messages` with **`text`**, **`sender_role`**, **`created_at`**, **`laravel_user_id`**.

### Filament

- **`InquiryResource`** (group **Inquiries**): list / view / edit; **no** create (inquiries originate from API).
- **`Edit`**: status + internal notes only.
- **`View`**: header **Open chat** → **`ChatInquiry`** page (`/{record}/chat`).
- **`ChatInquiry`**: Livewire page + **`resources/views/filament/resources/inquiries/chat-inquiry.blade.php`** — Firebase compat JS, real-time message list + send as **`sender_role: admin`**.
- **`OpenInquiriesStats`** widget: count **open + reviewing** on the dashboard.

### Documentation

- **`docs/Firebase_Setup.md`** — Firebase project, credentials, rules deploy, Flutter config.

### Security note

Service account JSON and web API keys must stay private. Firestore rules rely on **custom token claims**; misconfiguration can expose data — treat **FYP** config as **non-production** unless reviewed.

---

## 2026-04-06 — Inquiry & chat: documentation standards (luxury UX + Firebase)

### Purpose

Align **FYP documentation** and **`.cursorrules`** with the intended **boutique concierge** experience (not a basic admin table UI): **Dark Gold** (`#D4AF37`), **glassmorphism**, and **Firebase** for all live chat behavior while **MySQL** remains the system of record for inquiries, quotes, and statuses.

### Target product behavior (reference for future implementation)

| Area | Intent |
|------|--------|
| **Customer app (Flutter)** | Chat feels like a high-end atelier: soft rounded bubbles; **customer** messages use **gold** accents; **concierge/admin** messages use **translucent slate / glass** (see `AppColors` + shared widgets). Optional **progress stepper** at top: e.g. **Consultation → Quoted → Production → Ready**, driven by **`inquiries.status`** with instant UI updates when **Firestore thread metadata** mirrors that status. |
| **Live feel** | **Typing indicators** (`inquiry_threads/{threadId}/typing/{uid}` or equivalent) and **Messenger-style** delivery using Firestore listeners on **`messages`**. |
| **Thread document** | Optional fields on **`inquiry_threads/{threadId}`** (server or trusted path): e.g. **`inquiry_status`**, **`updated_at`**, so Flutter can subscribe to **one document** for pipeline state without polling MySQL. **Requires** `google/cloud-firestore` (+ often **ext-grpc**) on Laravel for `FirebaseInquiryService::syncInquiryThreadDocument`, or another approved sync strategy. |
| **Quote in chat** | Admin publishes a **final MMK** quote from the inquiry/chat context; customer receives a **premium quote card** in the thread with **Accept / Decline**; backend updates **`quotes`** + **`inquiries.status`** (e.g. accept → production). |
| **Filament** | Unified **chat console** (slide-over or full page) with the same Firestore thread as Flutter — not a static message dump. Styling should match **Dark Gold** panel branding. |

### Firestore rules evolution

Deployed rules live in **`firebase/firestore.rules`**. Any addition of **image-only messages**, **`quote_card`** payloads, or **typing** writes must be reflected in rules and redeployed (`firebase deploy --only firestore:rules`). See **`.cursorrules`** for UX and architecture expectations.

### Local server

For LAN/mobile testing against a machine IP, run Laravel with an open bind, e.g.:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

(Also documented in [Local_Setup.md](./Local_Setup.md).)

---

## 2026-04-07 — Luxury Concierge inquiry overhaul (pipeline, quote cards, accept/decline)

### Summary

Implemented the **concierge** inquiry flow: **derived pipeline** from MySQL, **customer accept/decline** with automatic **order** creation, **admin publish quote** (API + Filament), **Firestore** `quote_card` messages (server-side) and richer **thread metadata**, **Messenger-style** Flutter chat with **stepper** + **quote card** UI, and **Filament** chat console with **Generate quote** form.

### Backend

- **`App\Services\InquiryPipelinePresenter`**: steps `consulting` | `quoted` | `in_production` | `ready` from inquiry + quotes + orders; exposed on **`GET /api/v1/inquiries/{inquiry}`** and **`GET /api/v1/admin/inquiries/{inquiry}`** as **`meta.pipeline`** (includes `order_id`, `order_status`, `active_quote_id`, nested `order` when present).
- **`QuoteBuilderService::createQuote(..., $initialStatus)`**: supports **`draft`** (default) or **`sent`** for publish flows.
- **`POST /api/v1/quotes/{quote}/accept`** (customer): accepts **`sent`** quote, creates **`Order`** via **`OrderBuilderService`**, syncs Firestore thread.
- **`POST /api/v1/quotes/{quote}/decline`** (customer): sets quote **`void`**, inquiry **`reviewing`**.
- **`POST /api/v1/admin/inquiries/{inquiry}/quotes/publish`**: creates **`sent`** quote with lines, **quoted** inquiry, **`publishQuoteCardMessage`** + **`syncInquiryThreadDocument`**.
- **`FirebaseInquiryService`**: **`publishQuoteCardMessage`** writes `messages` doc (`type: quote_card`, totals, `lines[]`); thread doc adds **`pipeline_step`**, **`order_id`**, **`order_number`**, **`order_status`**, **`active_quote_id`**.

### Firebase

- **`firebase/firestore.rules`**: legacy `text` + `sender_role`; optional **`type`** + **`created_at`** for **`text`** and **`image`** client messages; **`quote_card`** remains **server-only** (Admin SDK bypasses rules).

### Flutter

- **`InquiryPipeline`**, **`InquiryApi.fetchPipeline`**, **`acceptQuote`**, **`declineQuote`**.
- **`ConciergePipelineStepper`**, **`InquiryQuoteCard`**, **`inquiry_chat_screen.dart`**: nested **thread** + **messages** streams, **reverse** list, gold vs glass bubbles, quote card **Accept / Decline**.
- **`PremiumText`**: optional **`color`** on **caption** / **headline**.

### Filament

- **`ChatInquiry`**: **`publishQuoteFromConsole`** (Livewire), **`pipeline`** stepper in Blade, **Dark Gold** layout, **Generate quote** form (multi-line), Firebase JS renders **`quote_card`** messages.

### Tests

- **`tests/Feature/InquiryQuoteFlowTest.php`**: publish → accept → order; draft accept forbidden; decline → void + reviewing.

---

## 2026-04-10 — Bespoke order workflow (deposit gate, artisan jobs, QC)

### Summary

**MySQL** is extended with **`payments`** and richer **`orders.status`** (`awaiting_deposit`, `deposit_confirmed`, `pending_qc`, etc.). **Accept quote** creates an order in **`awaiting_deposit`** with a computed **deposit %**. Admins send **`payment_info_card`** to Firestore; customers upload **deposit proof** via API; admins **confirm/reject** in **Filament** or API. **Assignment** is gated on **confirmed deposit**; artisans **start job**, upload **progress** (photos sync to chat as server **`image`** messages), **ready for review**, then admin **QC approve** → **`ready`**. **Flutter**: 6-step **ConciergePipelineStepper**, payment card + workshop images in chat, **upload proof** CTA; **artisan** role opens a **job list** and **job detail** actions.

### Backend

- **Migration** `2026_04_10_120000_bespoke_order_workflow_payments_and_order_status`: **`payments`** table; **`orders.deposit_amount_mmk`**, **`deposit_instructions_sent_at`**; status vocabulary migration (SQLite-safe).
- **`InquiryPipelinePresenter`**: steps include **`awaiting_deposit`**, **`pre_production`**, **`in_production`**, **`pending_qc`**, **`ready`**; **`deposit_state`**, **`assignment_state`**, etc. in **`meta.pipeline`**.
- **API**: `POST /api/v1/orders/{order}/deposit-proof` (customer); `POST /api/v1/admin/orders/{order}/payment-instructions`; `POST /api/v1/admin/payments/{payment}/confirm|reject`; `POST /api/v1/artisan/orders/{order}/start`; `POST /api/v1/artisan/orders/{order}/ready-for-review`; `POST /api/v1/admin/orders/{order}/qc/approve`; gated **`storeAssignment`**.
- **`FirebaseInquiryService`**: **`publishPaymentInfoCard`**, **`publishProgressImageMessage`**; thread doc merges **`deposit_state`**, **`assignment_state`**, **`primary_artisan_user_id`**, etc.
- **`PaymentVerificationService`**: shared confirm/reject logic for API + Filament.

### Filament

- **`PaymentResource`**: list deposits with **Confirm deposit** / **Reject**.
- **`ChatInquiry`**: **Send payment info to chat** (`sendPaymentInstructionsToChat`); Blade stepper expanded to six steps; JS renders **`payment_info_card`** and **`image`**.

### Flutter

- **`InquiryPipeline`**: deposit fields; **`ConciergePipelineStepper`** (6 steps); **`InquiryPaymentInfoCard`**; chat tiles for **`payment_info_card`** + **`image`**; **`uploadDepositProof`**; accept-quote snackbar/pipeline → **`awaiting_deposit`**.
- **`ArtisanShell`**, **`ArtisanJobsScreen`**, **`ArtisanJobDetailScreen`**, **`ArtisanApi`**.

### Tests

- **`tests/Feature/BespokeOrderWorkflowTest.php`**: end-to-end deposit → confirm → assign → start → progress → ready-for-review → QC.
- **`InquiryQuoteFlowTest`**: order **`awaiting_deposit`** after accept.

---

## 2026-04-11 — Quote supersession, deposit API mirror, concierge chat hardening

### Summary

**Accepting a newer quote** supersedes **all non-cancelled** orders on the inquiry: **confirmed** deposits are **credited** and **deposit payments are reparented** to the new order. **Publishing** is always allowed; **confirmed** deposit → **`meta.warning`** / Filament warning. **`meta.pipeline.deposit_payment`** includes optional **total / credited / balance** fields. **Filament** + **Flutter** payment cards show breakdown when applicable. *(Supersedes the stricter publish/accept rules described in earlier drafts of this entry; see **2026-04-16**.)*

### Backend — quote publish advisor

- **`App\Services\InquiryQuoteCommitmentGate`**: **`warningForPublishing`** only — **`meta.warning`** on publish endpoints when a **confirmed** deposit exists on an active order for the inquiry.

### Backend — quote accept & orders

- **`OrderBuilderService`**: supersede **all** non-**cancelled** inquiry orders; sum **confirmed** deposit credit; **`insertOrderAndItemsFromQuote`** applies credit to **`deposit_amount_mmk`** / **`status`**; **reparent** deposit **`Payment`** rows; **cancel** old orders; **migrate** **`OrderAssignment`**; **`QuoteRevisedAcceptedForArtisan`**.
- **`CustomerQuoteController::accept`** → **`acceptSentQuoteAndCreateOrder`**.

### Backend — pipeline & payments

- **`InquiryPipelinePresenter`**: **`paymentBalanceBreakdown`** merged into **`deposit_payment`**; **`FirebaseInquiryService::publishPaymentInfoCard`** sends the same optional keys.

### Flutter — customer chat

- **`InquiryPipeline.depositPayment`**, **`InquiryApi.fetchPipeline`**, **`_effectivePipeline`** keeps REST **`deposit_payment`** when merging with Firestore thread metadata.
- **`inquiry_chat_screen.dart`**: **`CustomScrollView`** slivers (stepper, **`InquiryApiQuoteBanner`**, **`InquiryPaymentInfoCard`** from pipeline when no matching Firestore card, **`SliverList`** messages chronological); **`_attachPipelineSyncListeners`** + **450ms debounce** → **`_loadPipeline()`** on thread/doc + messages snapshots; **`resizeToAvoidBottomInset: true`**; composer inside scroll column’s sibling flex region (see earlier layout fix).

### Filament — admin chat (`chat-inquiry.blade.php` + `ChatInquiry.php`)

- **`$adminQuoteCardPayload`** on **`ChatInquiry`**, refreshed in **`refreshPipeline()`** from active sent/accepted quote line items.
- Blade: **`#jh-admin-static-json`** + **`#jh-server-cards`** above **`#jh-messages`** inside **`.jh-scroll-thread`**; JS **`renderMysqlCards`** + **`quoteCardHtml` / `paymentCardHtml`**; **`shouldSkipFirestoreMsg`** when Firestore duplicates MySQL **`quote_id`** / **`order_number`**.
- **`window.jhAdminChatRefreshStatic`**; **`$this->js`** after publish quote / send payment: staggered **`setTimeout(0,200,600)`** so Livewire DOM includes updated JSON before redraw.

### Tests

- **`InquiryQuoteFlowTest`**: supersede + **publish allowed** while deposit **awaiting verification** (no **`meta.warning`** without confirmed deposit).
- **`BespokeOrderWorkflowTest`**: **`meta.warning`** when republishing after **confirmed** deposit; accept-after-confirm **reparents** payment and reduces **due**; assignment **migration** + **`notifications`** row for artisan.

### Operational note

- **Firestore** (`quote_card`, `payment_info_card`, thread sync) still requires **`google/cloud-firestore`** and often **PHP ext-grpc** on the server; when absent, Laravel logs skips—**MySQL + `meta.pipeline.deposit_payment`** and admin **server cards** keep UX usable. **`.cursorrules`** is kept in sync with these rules for AI-assisted work.

---

## 2026-04-16 — Relaxed quote publishing, deposit credit, artisan notifications

### Summary

- **Migration** **`2026_04_16_100000_create_notifications_table`**: standard Laravel **`notifications`** for database channel.
- **`QuoteRevisedAcceptedForArtisan`**: notifies assigned artisans when a customer accept supersedes an order they were on.
- **`GET /api/v1/artisan/notifications`**: list recent notifications for the mobile artisan app.
- **Admin chat `paymentCardHtml`**: optional **order total / recognized paid / balance** block when **`credited_deposit_mmk`** &gt; 0.
- **Flutter `InquiryPaymentInfoCard`**: same optional breakdown for REST and Firestore payloads.

---

## 2026-04-22 — Production & QC workflow completion (Filament + Flutter + Firestore)

### Summary

Completed the missing production/QC workflow surface by adding **Filament order detail actions** for artisan assignment and QC approval, introducing a shared backend lifecycle service for assignment/start/review/QC transitions, adding artisan notification deep-linking in Flutter, and publishing a required QC-ready system message to customer chat.

### Backend

- New shared service: **`App\Services\OrderLifecycleService`**
  - `assignArtisan(...)`
  - `startProduction(...)`
  - `markReadyForReview(...)`
  - `approveQc(...)`
- API controllers now reuse lifecycle methods:
  - **`Admin\OrderController::storeAssignment`**
  - **`Artisan\OrderWorkflowController::start|readyForReview`**
  - **`Admin\OrderQcController::approve`**
- Firebase service added **`publishSystemTextMessage(Inquiry $inquiry, string $message)`** in **`FirebaseInquiryService`**.
- QC approve now posts exact text to chat thread:
  - *`Your order has passed Quality Control and is now Ready for Collection!`*
  - then syncs thread metadata/pipeline.

### Filament

- Added **Order** resource:
  - **`OrderResource`**
  - **`ListOrders`**
  - **`ViewOrder`**
  - **`OrdersTable`**
- Order detail actions:
  - **Assign artisan** (with task scope + optional line item/instructions)
  - **QC approve** (only valid from `pending_qc`)

### Flutter (Artisan)

- Added artisan notifications domain + API + UI:
  - **`ArtisanNotificationItem`**
  - **`ArtisanApi.fetchNotifications`**
  - **`ArtisanNotificationsScreen`**
- Added deep-link behavior:
  - tapping notification resolves assignment by `order_id`
  - navigates to **`ArtisanJobDetailScreen(assignmentId: ...)`**
  - shows fallback snackbar if no linked assignment is available.

### Validation

- Backend tests pass:
  - **`InquiryQuoteFlowTest`**
  - **`BespokeOrderWorkflowTest`**
- Flutter static checks pass (`flutter analyze`) on modified artisan/inquiry files.

---

## 2026-04-22 — Admin Order transparency + formal receipt workflow

### Summary

Implemented the next customer-confidence/admin-visibility milestone:
- expanded Filament `View Order` into a complete operational overview surface
- introduced a formal receipt domain in Laravel
- generated canonical PDF receipts server-side on confirmed payments
- exposed secure customer receipt APIs and added Flutter order/receipt UX with PDF download.

### Backend

- Added receipt persistence:
  - `receipts` table migration
  - `App\\Models\\Receipt` with `Order`/`Payment` relations
- Added `App\\Services\\ReceiptService::issueForConfirmedPayment(Payment $payment)`:
  - idempotent by `payment_id`
  - immutable snapshot fields
  - server PDF render (`barryvdh/laravel-dompdf`)
  - private storage path + SHA256 checksum.
- Integrated receipt issuance into `PaymentVerificationService::confirm`.
- Added backfill command:
  - `php artisan receipts:generate-missing`
- Added customer receipt endpoints:
  - `GET /api/v1/orders/{order}/receipts`
  - `GET /api/v1/orders/{order}/receipts/{receipt}`
  - `GET /api/v1/orders/{order}/receipts/{receipt}/download`
  with strict owner checks.

### Filament

- Upgraded `OrderResource` eager loading for deeper relations (`customerProfile`, `items`, `payments.receipt`, assignments, progress events).
- Expanded `OrderInfolist` to include:
  - customer contact + address
  - workflow stage label
  - financial summary (confirmed payments / remaining balance)
  - itemized spec/pricing summary
  - artisan assignment summary
  - payment ledger (including receipt number)
  - workflow timeline.

### Flutter (Customer)

- Added `Orders` tab in `CustomerShell`.
- Added customer order/receipt API layer and receipt model.
- Added premium screens:
  - `CustomerOrdersScreen`
  - `ReceiptDetailScreen`
- Added authenticated PDF download/open flow for receipts.

### Receipt chat CTA update

- On payment confirmation, backend now also publishes a Firestore system card message:
  - `type: receipt_card`
  - includes `receipt_id`, `order_id`, `receipt_number`, `amount_mmk`
  - CTA label: `View Receipt`
- `PaymentVerificationService::confirm` now calls:
  - `ReceiptService::issueForConfirmedPayment(...)`
  - `FirebaseInquiryService::publishReceiptCardMessage(...)`
  - then thread sync.
- Flutter `InquiryChatScreen` now renders `receipt_card` and opens `ReceiptDetailScreen` when the customer taps **View Receipt**.

---

## 2026-04-23 — Quote decline synchronization + persistent timeline reliability

### Summary

Resolved the customer quote-decline synchronization gap so admin and customer views stay aligned without manual refresh. Also fixed a timeline serialization bug that caused workflow logs to disappear after decline events.

### Backend

- **`CustomerQuoteController::decline`** now publishes a persistent workflow log and syncs thread metadata after status changes:
  - `--- Quote was declined by Customer ---`
  - `FirebaseInquiryService::syncInquiryThreadDocument(...)`
- **`FirebaseInquiryService::publishTimelineLogMessage`** path is used for decline so timeline events are written to MySQL (`inquiry_timeline_logs`) first, then mirrored to Firestore best-effort.
- **`InquiryPipelinePresenter::timelineLogs`** fixed with `->values()` after `sortBy('id')` so `timeline_logs` is always encoded as a JSON array (not an object with sparse keys).

### Filament (Admin)

- **`InquiriesTable`** polling tightened to `1s` for faster status reflection in the inquiries list.
- **`ChatInquiry`** + `chat-inquiry.blade.php` polling hardened:
  - periodic pipeline refresh for chat page
  - modal-safe polling behavior so opening quote/payment dialogs no longer auto-closes them.

### Flutter (Customer)

- Customer decline action continues to call decline API then refresh pipeline.
- Chat timeline rendering now correctly keeps persisted MySQL timeline logs (including decline) visible across app refresh/reopen when pipeline includes `timeline_logs` array.

### Validation

- `tests/Feature/InquiryQuoteFlowTest.php` decline case passes.
- Blade templates compile successfully (`php artisan view:cache`).

---

## 2026-04-23 — Artisan Making Context + three-way sync contract

### Summary

Implemented a single source-of-truth flow for making specs so admin entry, artisan job context, and customer order detail all read the same snapshot data and image references.

### Backend data contract

- Added structured spec fields:
  - `quote_line_items.metal_type`
  - `quote_line_items.stone_details`
  - `order_items.metal_type_snapshot`
  - `order_items.stone_details_snapshot`
- Quote creation now accepts and persists `metal_type` + `stone_details` on each line.
- Order creation snapshots those fields from quote line items into order items.
- Admin quote publish/draft APIs now validate and pass the structured spec fields.

### Admin entry and assignment rules

- Filament quote publish form (`ChatInquiry`) now captures optional **metal type** and **stone details** per line.
- Assignment form labels instructions as **Special instructions** and requires instructions when `task_scope = line_item`.
- API assignment endpoint now enforces `instructions` on `line_item` scope as part of the validation contract.

### Artisan + customer payload parity

- Artisan assignment endpoints now eager-load:
  - `order.items.product.images`
  - `orderItem.product.images`
- Customer order endpoints now eager-load:
  - `items.product.images`
- Both payloads expose the same order-item snapshots (`metal_type_snapshot`, `stone_details_snapshot`) and product image `storage_path` data.

### Flutter UI wiring

- Artisan app (`ArtisanJobDetailScreen`) now shows:
  - assignment status + order status
  - admin instructions
  - making specs per visible item (title, weight, metal type, stone details)
  - design photo gallery sourced from product images
- Customer app adds canonical `CustomerOrderDetailScreen` and links it from Orders + Timeline.
- Timeline remains milestone/progress focused, with a direct link to order detail as the canonical spec/photo view.

### Validation

- Added parity coverage in `BespokeOrderWorkflowTest`:
  - confirms the same snapshot values and image paths are returned to both artisan assignment show and customer order show payloads.
- Test run:
  - `php artisan test --filter=BespokeOrderWorkflowTest` ✅
- Flutter static checks:
  - `flutter analyze` on modified artisan/order files ✅

---

## 2026-04-23 — Firebase Storage media migration (new uploads)

### Summary

Migrated new media uploads to a configurable storage provider so chat and workflow images can be shared across devices/environments without relying on localhost public disk paths.

### Backend changes

- Added GCS/Firebase filesystem support (`spatie/laravel-google-cloud-storage`) and a new `firebase` disk in `config/filesystems.php`.
- Added media provider toggle in `config/services.php`:
  - `services.media.provider` (`local` or `firebase`)
- Introduced `App\\Services\\MediaStorageService` as a single upload + URL-resolution abstraction.
- Refactored upload write paths to use `MediaStorageService`:
  - `UploadController`
  - `InquiryController` (initial inquiry attachments)
  - `OrderProgressController` (artisan progress photos)
  - `CustomerOrderDepositController` (deposit proof)
- Updated `FirebaseInquiryService` image message publishing to resolve URLs through `MediaStorageService` instead of hardcoded `Storage::disk('public')` URL logic.

### Compatibility behavior

- Legacy local records remain supported.
- New Firebase-provider uploads are stored as absolute URLs in existing `storage_path` fields, so existing API/Firestore contracts remain stable.
- `publicStorageUrl(...)` in Flutter now passes through absolute URLs and only prefixes local relative paths.
- Filament inquiry sidebar initial-photo rendering now handles both local relative paths and absolute URLs.

### Validation

- `php artisan test --filter=InquiryApiTest` ✅
- `php artisan test --filter=BespokeOrderWorkflowTest` ✅
- `php artisan view:cache` ✅
- `flutter analyze` on modified inquiry/order/artisan URL files ✅

---

## 2026-04-24 — P0 security hardening and P1 admin operations prep

### P0 — API hardening implemented

- Added named API rate limiters in `AppServiceProvider` and bound them in `routes/api.php`:
  - `auth-login`, `auth-register`, `uploads`, `payment-proof-upload`, `payment-admin-actions`, `quote-customer-decision`, `quote-admin-mutations`.
- Enforced payment transition guards:
  - `PaymentVerificationService::confirm` now only allows `awaiting_verification` (plus idempotent `confirmed`).
  - `PaymentVerificationService::reject` now only allows `awaiting_verification` and blocks `confirmed`.
  - `CustomerOrderDepositController` only allows proof upload from `pending_proof` or `rejected` and blocks overwrite of confirmed records.
  - `OrderPaymentInstructionController` and Filament `ChatInquiry` block instruction reset when deposit verification is already in progress or confirmed.
  - `Admin\OrderController` now enforces confirmed deposit invariant for both `deposit_confirmed` and `in_production`.
- Hardened admin-sensitive upload paths:
  - Admin chat upload route is now `auth + role:admin`.
  - `InquiryChatUploadController` now authorizes with admin-level inquiry `update` permission.
  - Generic `UploadController` now enforces strict image MIME allowlist and role-based folder constraints.

### P0 — regression coverage

- Added `tests/Feature/SecurityHardeningTest.php` to cover:
  - login throttling (429 behavior),
  - admin chat upload authorization boundary,
  - generic upload MIME validation,
  - payment transition invariants (cannot reject confirmed / confirm pending proof),
  - confirmed-payment proof overwrite protection.
- Re-ran core suites:
  - `SecurityHardeningTest` ✅
  - `BespokeOrderWorkflowTest` ✅
  - `InquiryQuoteFlowTest` ✅

### P1 — admin operations kickoff

- Payments queue triage upgrades:
  - status tabs (`awaiting_verification`, `rejected`, `confirmed`, `all`) in Filament list.
  - filters for status, submitted date window, order/customer lookup.
  - default sort tuned for triage (`submitted_at` descending).
- Unified Filament `UserResource` added with role-aware create/edit/list management for Admin, Artisan, and Customer records, including artisan profile fields.

---

## 2026-04-25 — Admin audit logs + operations KPI dashboard foundation

### Summary

Implemented the first P0/P1 admin hardening block by introducing persistent audit traceability for sensitive admin actions and adding top-level operational KPI widgets in Filament.

### Backend audit log foundation

- Added `audit_logs` table migration:
  - `actor_user_id`, `category`, `action`, `target_type`, `target_id`, `context`, request metadata (`ip_address`, `user_agent`), `occurred_at`.
- Added `App\Models\AuditLog` and centralized writer `App\Services\AdminAuditLogService`.
- Service is non-blocking for business flow (logs warning on write failure instead of throwing).

### Filament admin: audit visibility

- Added read-only `AuditLogResource` under **Settings**:
  - list + view only (no create/edit/delete pages),
  - filters by category / actor / date window,
  - detailed context JSON and target metadata.

### Logged sensitive actions (initial coverage)

- Quote publishing:
  - Filament concierge chat publish (`ChatInquiry`)
  - admin API inquiry quote publish (`InquiryQuotePublishController`)
  - admin quote create/status update (`QuoteController`)
- Payment verification:
  - confirm/reject in `PaymentVerificationService`
- Order workflow:
  - payment instructions sent (Filament + API paths),
  - artisan assignment + QC approval in `OrderLifecycleService`,
  - admin order create-from-quote / update in `OrderController`.
- Admin settings/actions:
  - user create/update/delete (Filament user pages),
  - gold and FX snapshot creation pages.

### Filament operational KPI widgets

- Added dashboard widgets:
  - `RevenueStats`: revenue **today** and **this month** in MMK and derived USD (from current FX snapshot).
  - `ActionQueuesStats`: payments awaiting verification + inquiries needing quotes.
  - `ArtisanOverviewStats`: orders currently in production.

### Validation

- Backend feature suites passed after integration:
  - `SecurityHardeningTest`
  - `BespokeOrderWorkflowTest`
  - `InquiryQuoteFlowTest`

---

## 2026-04-25 — Reporting & Export Engine (Filament)

### Summary

Implemented the first full reporting/export surface for Filament admin:
- bulk selected-record exports (CSV / Excel) for orders and payments,
- individual PDF reports for order/payment records,
- advanced table filtering for report slicing,
- dashboard sales-summary PDF exports,
- dedicated report hub resource for date-ranged exports across multiple report types.

### Package and foundations

- Added Excel support package:
  - `maatwebsite/excel` (Composer).
- Added reusable export stack:
  - `App\Exports\ArrayReportExport`
  - `App\Services\ReportExportService`
- Added report blade templates:
  - `resources/views/reports/order-detail.blade.php`
  - `resources/views/reports/payment-detail.blade.php`
  - `resources/views/reports/sales-summary.blade.php`
  - `resources/views/reports/dynamic-report.blade.php`
  - shared header partial with optional logo support at `public/images/logo.png`.

### Filament resource/table enhancements

- `OrdersTable`:
  - status + created-date filters,
  - bulk **Export selected** action (CSV/XLSX).
- `PaymentsTable`:
  - submitted + verified date window filters,
  - status filter retained,
  - bulk **Export selected** action (CSV/XLSX).
- `InquiriesTable`:
  - added status + created-date filters for operational report slicing.

### Record-level PDF actions

- `ViewOrder` now includes **Download PDF report** action.
- `ViewPayment` now includes **Download PDF report** action.

### Dashboard and quick exports

- Created custom Filament dashboard page (`App\Filament\Pages\Dashboard`) with header action:
  - **Download Current Month Sales Summary (PDF)**.
- Added `QuickExportWidget`:
  - one-click **Today's Sales Summary** PDF,
  - one-click **Current Month** sales PDF.
- Added admin-protected web routes:
  - `/admin/reports/sales-summary/today`
  - `/admin/reports/sales-summary/current-month`
  via `App\Http\Controllers\Admin\ReportDownloadController`.

### Dedicated report resource

- Added `ReportExportResource` (Reports navigation group) with `ReportExportHub` page.
- Export dialog supports:
  - report type: Sales, Payments, Artisan Performance, Order Lifecycle
  - date range pickers
  - format: PDF / XLSX / CSV.

### Validation

- Post-change regression pass:
  - `SecurityHardeningTest` ✅
- Static analysis on changed reporting/export files: no linter errors.

---

## 2026-04-25 — Admin Command Center polish (charts, pulse, nav ordering)

### Summary

Completed the admin dashboard upgrade from basic stats to a data-driven **Command Center** with live chart widgets, operational pulse signals, luxury theme alignment, grouped navigation, and quick-access pricing/audit controls.

### Dashboard analytics (Filament widgets)

- Added chart widgets backed by real DB data:
  - `RevenueBarChartWidget` — monthly confirmed payment revenue for MMK + USD series.
  - `OrderStageDoughnutWidget` — distribution for Pending / Production / QC / Ready (excluding cancelled orders).
  - `InquiryConversionLineWidget` — 6-month inquiry-to-order trend signal.
- Added top-row KPI widgets:
  - `CommandCenterKpisWidget` — spot gold & FX, active inquiries, SLA-risk orders.
  - `OperationsPulseWidget` — pending payment verifications + active artisan production assignments.
- Added command strip widget:
  - `CommandCenterStripWidget` + Blade view with quick links for global pricing snapshots and audit trail access.

### Data and service layer

- Added `App\Services\DashboardAnalyticsService` for shared dashboard aggregates:
  - `monthlyRevenueSeries(...)`
  - `orderStageDistribution()`
  - `inquiryOrderConversionSeries(...)`
  - `slaBreachCount()`
- Query implementation is SQLite-friendly for test environment compatibility (PHP-side grouping where needed).

### Dashboard page composition

- `App\Filament\Pages\Dashboard` now explicitly controls widget order/layout for command-center composition:
  - KPIs → Operations Pulse → Command Strip → Quick Export → Charts.
- Legacy auto-discovered stats widgets are intentionally disabled from discovery to avoid duplicate/legacy dashboard blocks.

### Navigation and UX structure

- Navigation groups normalized to:
  - **Inventory**
  - **Operations**
  - **Finance**
  - **Reports**
  - **System Settings**
- Group order is explicitly enforced in `AdminPanelProvider` via `navigationGroups([...])`.
- Theme polish includes chart section visual alignment with dark-gold styling and chart datasets matched to navy/gold/cream palette.

### Reporting / audit integration touchpoints

- Report exports remain under **Reports** via `ReportExportResource`.
- Audit trail is now directly reachable from the dashboard command strip.
- `AuditLogsTable` now includes action-focused filtering for payment confirmation/rejection and workflow actions.

### Validation

- `phpunit` full backend suite passed after command center integration.
- Updated files pass syntax checks and lints.

---

## 2026-04-25 — Business Config Console + P0/P1 Table Polish

### Summary

Implemented the final P0/P1 admin operations polish by introducing a DB-backed **Business Config Console** in Filament and integrating high-value UX/logic improvements across Payments, Orders, Inquiries, and Users tables.

### Global Business Config Console

- Added `business_configs` table + default singleton row:
  - `default_deposit_percentage`
  - `payment_at_risk_hours`
  - `order_at_risk_hours`
  - `currency_refresh_interval_minutes`
  - `payment_accept_template`
  - `payment_reject_template`
- Added `BusinessConfigResource` under **System Settings** for admin-managed configuration.
- Added `BusinessConfigService` and integrated it into core logic:
  - `OrderBuilderService` now resolves default deposit percentage from DB config.
  - `DashboardAnalyticsService::slaBreachCount()` now uses payment/order SLA thresholds from DB config.
  - Command-center widget polling intervals now derive from configured currency refresh interval.
  - `PaymentVerificationService` now uses configurable accept/reject notification templates.

### Payments table polish

- Added **aging badge** column with SLA-friendly color coding:
  - green `< 1h`
  - yellow `1–4h`
  - red `> 4h`
- Enhanced reject UX:
  - common reason dropdown (`Incomplete amount`, `Blur image`, `Wrong account`)
  - dedicated **Quick reject** action while still using existing service guards.

### Orders table polish

- Added custom status badge colors by workflow stage.
- Added **Workflow age** badge column.
- Added quick action to **Open related inquiry chat** (when quote/inquiry link exists).

### Inquiries table polish

- Added **Unanswered/Responded** marker badge.
- Added current **Quote status** shortcut badge (`none`, `draft`, `sent`, `accepted`, etc.).

### Users table polish + account controls

- Added `users.account_status` (`active`/`suspended`) and `users.force_password_reset`.
- Added table actions:
  - account status toggle (`Suspend`/`Activate`)
  - **Forced password reset** (flags account + revokes active tokens).
- Added account status + reset state indicators to the users table.
- Auth flow integration:
  - login now blocks suspended accounts
  - login now blocks accounts with forced reset pending.

### Validation

- New migrations applied successfully.
- Critical backend test suites passed:
  - `SecurityHardeningTest`
  - `InquiryQuoteFlowTest`
  - `BespokeOrderWorkflowTest`
- Updated files pass syntax/lint checks.

---

## 2026-04-25 — Final UX pass: table density, alignment, triage flow

### Summary

Completed a final Filament UX polish pass focused on triage speed and professional consistency across admin tables (Payments, Orders, Inquiries), with badge normalization and cleaner row actions for dense operational workflows.

### Payments table

- Reordered columns so triage-critical fields lead:
  - Aging badge → Status → Order/Customer.
- Grouped row actions into an **Actions** dropdown to avoid action-row clutter.
- Kept quick reject and full reject flows while preserving `PaymentVerificationService` guards.
- Marked lower-priority columns as toggleable by default for smaller/tablet screens.

### Orders table

- Reordered left-side triage columns:
  - Workflow Age badge → Status badge → Order/Customer.
- Kept quick open action for related inquiry chat.
- Marked non-critical columns as toggleable to improve compact layouts.

### Inquiries table

- Reordered triage-first columns:
  - Unanswered marker → Inquiry Status → Quote Status → Customer.
- Kept message context visible while moving non-critical fields to toggleable columns.

### Visual consistency

- Added global table badge normalization in Filament theme:
  - consistent minimum width
  - centered badge content
- This improves visual rhythm for status/aging badges across all admin tables.

### Responsiveness notes

- Introduced toggleable defaults on lower-priority columns in triage-heavy tables.
- Result: better scanability and usable layouts on narrower admin viewports/tablets without losing access to detail fields.

---

## 2026-04-25 — Phase 3 kickoff: real-time push notifications (Customer + Admin)

### Summary

Implemented the Phase 3 push-notification foundation with device-token registration, unified notification delivery service, role-targeted trigger points, and API inbox/read flows for authenticated users.

### Backend data and API foundation

- Added `push_device_tokens` table for per-user FCM token registration:
  - token, platform, app_version, active flag, last_seen.
- Added model: `App\Models\PushDeviceToken`.
- Added authenticated push token APIs:
  - `POST /api/v1/push/devices` (register/update token)
  - `DELETE /api/v1/push/devices` (deactivate token)
- Added authenticated notification APIs:
  - `GET /api/v1/notifications` (recent inbox)
  - `POST /api/v1/notifications/{notificationId}/read`

### Notification service layer

- Added `App\Services\PushNotificationService`:
  - persists in-app notifications to database (`notifications` table),
  - attempts FCM push delivery via Firebase Messaging when available,
  - fails gracefully with logging when push provider is unavailable.
- Added `App\Notifications\AppPushNotification` for normalized database payloads.
- Extended Firebase service to expose messaging client:
  - `FirebaseInquiryService::messaging()`.

### Triggered push events (initial set)

- **Customer → Admin**
  - new inquiry created,
  - deposit proof submitted,
  - quote accepted/declined by customer.
- **Admin → Customer**
  - quote published,
  - payment confirmed/rejected.

### Integration notes

- Existing workflow/state guards remain authoritative (`PaymentVerificationService`, quote/order guards).
- Push dispatch is additive and non-blocking; core business state transitions do not depend on push success.

### Validation

- New migration applied successfully:
  - `2026_04_25_162000_create_push_device_tokens_table`.
- Regression checks passed:
  - `SecurityHardeningTest`
  - `InquiryQuoteFlowTest`

---

## 2026-04-25 — Phase 3.1 frontend wiring: FCM lifecycle + notification inbox + deep linking

### Summary

Completed Flutter-side push notification wiring across customer/admin/artisan flows: session-based FCM token registration, dedicated notification inbox UI with read indicators, foreground/background listeners, and tap deep-link routing into inquiry/order workflows.

### Flutter push lifecycle integration

- Added `firebase_messaging` dependency.
- Introduced `PushNotificationManager` to centralize:
  - FCM permission request,
  - foreground push listener (`onMessage`),
  - background/tapped listener (`onMessageOpenedApp`, `getInitialMessage`),
  - token register/unregister orchestration via backend API.
- Added app-level keys:
  - `appNavigatorKey` for deep-link navigation,
  - `appMessengerKey` for foreground toast/snackbar delivery.

### Auth/session token management

- `AuthProvider` now performs token sync during:
  - bootstrap (`/auth/me` success),
  - login,
  - register,
  - logout (unregister before session clear).
- Push registration failures are non-blocking; authentication remains resilient.

### Notification inbox UI (all roles)

- Added shared `NotificationsScreen` with:
  - API-backed list via `GET /api/v1/notifications`,
  - tap-to-mark-read via `POST /api/v1/notifications/{id}/read`,
  - explicit read-state presentation (`Unread` / `Read`) plus visual dot.
- Added `AppNotificationItem` model for payload parsing (`inquiry_id`, `order_id`, `payment_id`, etc.).
- Added `NotificationApi` for push-device + inbox endpoints.

### Role shells and navigation

- Customer shell now includes an **Alerts** tab.
- Artisan shell now includes an **Alerts** tab (jobs + notifications split).
- Admin role now routes into a dedicated mobile alerts shell using the same inbox screen.

### Deep-link behavior

- Push tap deep-links currently route as follows:
  - `order_id` present → `CustomerOrderDetailScreen`,
  - `inquiry_id` present → `InquiryListScreen`,
  - artisan role → `ArtisanNotificationsScreen` (optionally auto-opens linked job by order id),
  - fallback → `CustomerOrdersScreen`.
- Foreground pushes show an in-app snackbar without forcing navigation.

### Validation

- `flutter pub get` completed successfully after dependency updates.
- `flutter analyze` clean (`No issues found!`).

