# Timebox 1 — Platform Foundation & Core Catalog

## 1) Functional Overview

### Implemented functions in this timebox
- Established Laravel API v1 foundation with role-based middleware and Sanctum authentication flow.
- Implemented base domain schema: users/profiles, catalog, pricing snapshots, inquiries/quotes/orders baseline tables.
- Implemented dynamic pricing foundation (`gold_price_snapshots`, `exchange_rate_snapshots`) and MMK-first pricing math.
- Delivered public catalog endpoints with snapshot-aware pricing visibility.
- Set up Filament admin foundation (`/admin`) with inventory and pricing resources.
- Implemented Flutter baseline architecture: authentication gate, dark-gold design system, customer catalog screens.

## 2) Testing Suite

### 2.1 Test Plan Table

| Test ID | Scenario | Requirement Link |
|---|---|---|
| TB1-TP-01 | User can register/login and receive tokenized access to API v1 | `backend/routes/api.php` (`/api/v1/auth/*`) |
| TB1-TP-02 | Public catalog returns products and category filters correctly | `backend/routes/api.php` (`/api/v1/catalog/*`) |
| TB1-TP-03 | Catalog pricing appears only when active gold + FX snapshots exist | `backend/app/Services/CatalogProductPresenter.php` |
| TB1-TP-04 | Admin can create pricing snapshots from Filament and API | `backend/app/Services/PricingService.php` |
| TB1-TP-05 | Flutter app applies shared Dark Gold theme and loads catalog after auth | `frontend/lib/core/ui/design_system.dart`, `frontend/lib/main.dart` |

### 2.2 Test Script Table

| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | `POST /api/v1/auth/register` with valid payload | Customer account created, token returned | Actual Result matches Expected | Pass |
| 2 | `POST /api/v1/auth/login` with seeded user | Bearer token returned; role identified | Actual Result matches Expected | Pass |
| 3 | `GET /api/v1/catalog/products` before snapshot seed | `meta.pricing_configured = false` and no pricing payload | Actual Result matches Expected | Pass |
| 4 | Seed pricing snapshots, then call catalog again | `meta.pricing_configured = true` and product pricing visible | Actual Result matches Expected | Pass |
| 5 | Open Flutter app and sign in | Auth gate routes to customer shell/catalog | Actual Result matches Expected | Pass |
| 6 | Toggle display currency/weight in catalog | UI updates with expected MMK/USD and grams/K·P·Y display | Actual Result matches Expected | Pass |

## 3) Visual Proofs (Screenshot Insertion Guide)

- **Admin - Login Page**: successful admin authentication into Filament.
- **Admin - Category Management Page**: category CRUD/list view.
- **Admin - Product Management Page**: product list/detail including publish flags.
- **Admin - Gold Price Snapshot Creation Page**: create snapshot form and resulting history row.
- **Admin - FX Snapshot Creation Page**: create exchange rate snapshot and active record.
- **Customer - Login Screen (Flutter)**: token-based entry point UI.
- **Customer - Product Catalog Screen**: product grid and premium dark-gold design system.
- **Customer - Product Detail Screen**: pricing breakdown and inquiry CTA.

## 4) Technical Diagrams

- Use Case Diagram: `diagrams/chapter5/timebox1_platform_usecase.puml`
- Class Diagram: `diagrams/chapter5/timebox1_platform_class.puml`

## 5) Diagram Descriptions

### 5.1 Use Case Diagram Elements

| Element | Description |
|---|---|
| Customer Actor | Mobile user interacting with authentication and catalog browsing. |
| Admin Actor | Filament operator managing inventory and pricing configuration. |
| Authenticate User | Register/login/logout via API v1 + token session. |
| Browse Catalog | Public product retrieval with filters and presentation. |
| Configure Pricing Snapshots | Admin operation that drives dynamic catalog price visibility. |
| Manage Inventory | Category/product/image maintenance actions from admin panel. |

### 5.2 Class Diagram Elements

| Element | Description |
|---|---|
| `User` | Core identity model with role checks and panel access control. |
| `Product` | Catalog item with weight/labor/gem fields and publication flags. |
| `Category` | Product grouping and curation container. |
| `GoldPriceSnapshot` | Time-effective MMK-per-gram pricing source. |
| `ExchangeRateSnapshot` | Time-effective MMK-per-USD conversion source. |
| `PricingService` | Resolves active snapshots and conversion operations. |
| `CatalogProductPresenter` | Composes customer-facing catalog JSON pricing payload. |

