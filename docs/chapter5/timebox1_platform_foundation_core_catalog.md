# Timebox 1 — Platform Foundation & Core Catalog

## Timebox Summary (Implementation Overview)

Timebox 1 established the delivery baseline by translating the initial platform design into a working architecture across database schema, backend services, admin operations, and customer-facing mobile interfaces. The implementation flow started with core entity modeling and migration-ready structures, then progressed into Laravel API and Filament admin capabilities for authentication, category and product CRUD, image management, and pricing snapshot controls. In parallel, the Flutter application adopted the Dark-Gold design system and implemented foundational customer journeys for registration, login, catalog browsing, product detail viewing, and currency or weight display toggles, ensuring that design intent was consistently reflected in executable UI behavior.

This timebox also introduced business-critical pricing logic services that gate catalog price visibility until valid gold and FX snapshots are active, preventing unreliable public pricing. Integration points between admin-maintained data and customer catalog presentation were stabilized through presentation-layer formatting to ensure consistent output. Quality assurance was initiated immediately after core flows became testable: manual black-box coverage focused on authentication reliability, admin CRUD correctness, pricing-readiness behavior, and catalog usability, creating a verified foundation for subsequent inquiry, quotation, and fulfillment timeboxes.

## 1) Functional Overview

### Detailed feature categorization

#### A. Authentication module
- Customer can register a new account from the mobile app.
- Existing users can sign in and continue to their role-specific home area.
- Session continuity keeps users signed in until logout or account reset.
- Access boundaries ensure admin-only pages are restricted to authorized staff.

#### B. Catalog management module
- Admin can create and organize categories for jewelry collections.
- Admin can create and maintain product records with key selling details.
- Admin can manage product images so catalog pages remain visually complete.
- Customers can browse the catalog and open product detail screens.

#### C. Pricing and business rule controls
- Gold market snapshots are recorded to keep price baselines current.
- Foreign exchange snapshots are recorded to support MMK-to-USD visibility.
- Price visibility is controlled by pricing readiness, reducing misquoted listings.
- Currency/weight display support improves customer clarity without changing core ledger values.

#### D. Platform foundation and operational readiness
- Core data entities were established to support later timeboxes (inquiries, quotes, orders).
- Admin web baseline (Filament) was set up for structured back-office operation.
- Mobile baseline adopted a premium Dark-Gold visual language for brand consistency.

#### E. UI/UX enhancements delivered in this timebox
- Dark-Gold visual identity introduced across login and catalog surfaces.
- Product cards and detail pages present premium positioning suitable for a luxury brand.
- Display toggles support user-friendly currency and weight understanding.

## 2) Testing Suite

### 2.1 Test Plan Table (Manual QA, black-box)

| Test ID | Scenario | Preconditions | Test Data | Tester Actions (UI) | Expected UI Outcome | Priority |
|---|---|---|---|---|---|---|
| TB1-TP-01 | Customer registration and login flow | Mobile app installed; no active session | New customer name, email, password | Tester opens app, taps Register, fills form, submits, then signs in | App shows successful sign-in path and navigates to customer home/catalog | High |
| TB1-TP-02 | Admin manages categories and products | Admin account available; admin panel reachable | New category name, sample product details | Tester signs into admin panel, adds category, adds product under category | New category/product appear in management lists and are selectable | High |
| TB1-TP-03 | Pricing snapshots control catalog price visibility | Existing product available | Gold price value, FX value | Tester first views catalog without snapshots, then records both snapshot values in admin panel and reopens catalog | Before snapshots: pricing state indicates unavailable; after snapshots: price values appear on product screens | High |
| TB1-TP-04 | Customer product discovery and details | Products already published | Category with at least 3 products | Tester opens catalog, applies category filter, opens a product card | Filtered list appears correctly and product detail page shows matching information | Medium |
| TB1-TP-05 | Display preference clarity (currency/weight) | Product with visible pricing | Toggle between MMK/USD and g/Kyat/Pae/Yway view | Tester changes display options from catalog or detail context | Price/weight presentation updates immediately and remains readable | Medium |

### 2.2 Test Scripts (Manual QA execution)

#### TB1-TS-01 — Customer registration and login
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | User taps `Register` | Registration form opens | Actual Result matches Expected | Pass |
| 2 | User enters valid name, email, and password | Form accepts input without validation errors | Actual Result matches Expected | Pass |
| 3 | User taps submit on registration form | Registration completes successfully | Actual Result matches Expected | Pass |
| 4 | User signs in with newly created credentials | System navigates to customer catalog/home area | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Registration form fully completed before submit.
  - Successful registration confirmation state (or post-register login prompt).
  - Login success state immediately before navigation.
  - Final customer landing screen after sign-in.

#### TB1-TS-02 — Admin category and product management
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Admin enters valid login credentials | Admin dashboard opens | Actual Result matches Expected | Pass |
| 2 | Admin enters new category details and saves | New category appears in category list | Actual Result matches Expected | Pass |
| 3 | Admin enters product details and selects created category | Product form accepts data | Actual Result matches Expected | Pass |
| 4 | Admin saves product and returns to list | New product appears with assigned category | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Admin dashboard after successful login.
  - Category creation form completed.
  - Product creation form completed with category selected.
  - Product list showing newly saved item.

#### TB1-TS-03 — Pricing readiness and catalog display
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer opens catalog before pricing setup | Non-priced state is shown | Actual Result matches Expected | Pass |
| 2 | Admin enters and saves gold snapshot value | Gold snapshot appears in admin list/history | Actual Result matches Expected | Pass |
| 3 | Admin enters and saves FX snapshot value | FX snapshot appears in admin list/history | Actual Result matches Expected | Pass |
| 4 | Customer refreshes/reopens catalog | Numeric pricing appears on catalog/detail cards | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Catalog state before pricing setup.
  - Gold snapshot entry form and saved record state.
  - FX snapshot entry form and saved record state.
  - Catalog state after setup with visible price values.

#### TB1-TS-04 — Product browsing and detail navigation
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer opens catalog screen | Product list loads successfully | Actual Result matches Expected | Pass |
| 2 | Customer selects a category filter | List updates to filtered products | Actual Result matches Expected | Pass |
| 3 | Customer taps a product card | Product detail opens with matching information | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Unfiltered catalog overview.
  - Filter-selected catalog state.
  - Product detail screen for selected item.

#### TB1-TS-05 — Currency and weight display toggles
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer opens product detail screen | Detail view loads with current price/weight values | Actual Result matches Expected | Pass |
| 2 | Customer toggles currency display | Price format updates on-screen | Actual Result matches Expected | Pass |
| 3 | Customer toggles weight display format | Weight presentation updates to selected format | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Product detail in default display mode.
  - Product detail after currency toggle.
  - Product detail after weight toggle.

## 3) Primary UI Design Screens (Dark-Gold Showcase)

This section is for design presentation evidence only (separate from QA test proof).

| Screen Name | Design Objective | Key UI Elements To Highlight | Screenshot Placeholder |
|---|---|---|---|
| Customer Login Screen | Communicate premium trust at first touchpoint | Dark background, gold accents, clear input hierarchy | `[Insert Screenshot: TB1-UI-01]` |
| Customer Catalog Screen | Present luxury catalog browsing experience | Glass cards, premium typography, product grid rhythm | `[Insert Screenshot: TB1-UI-02]` |
| Product Detail Screen | Support purchase confidence with clarity | Hero image, price prominence, inquiry CTA, unit display | `[Insert Screenshot: TB1-UI-03]` |
| Admin Pricing Screen | Present operational confidence for staff | Structured form layout, validation feedback, history table | `[Insert Screenshot: TB1-UI-04]` |

## 4) Technical Diagrams

- Use Case Diagram: `diagrams/chapter5/timebox1_platform_usecase.puml`
- Class Diagram: `diagrams/chapter5/timebox1_platform_class.puml`

## 5) Diagram Descriptions (Business-Oriented)

### 5.1 Use Case Diagram Explanation

#### Element: Customer
| Field | Description |
|---|---|
| Business Purpose (Why) | Enables direct-to-customer sales engagement |
| Business Behavior (What it does) | Browses products, checks details, and prepares for inquiry or purchase decisions |

#### Element: Admin
| Field | Description |
|---|---|
| Business Purpose (Why) | Keeps product and price information reliable |
| Business Behavior (What it does) | Maintains the catalog and market-linked price settings |

#### Element: Register / Login
| Field | Description |
|---|---|
| Business Purpose (Why) | Protects account-based experiences |
| Business Behavior (What it does) | Confirms user identity before allowing personalized actions |

#### Element: Browse Catalog
| Field | Description |
|---|---|
| Business Purpose (Why) | Drives product discovery and conversion |
| Business Behavior (What it does) | Shows curated products customers can explore |

#### Element: View Product Detail
| Field | Description |
|---|---|
| Business Purpose (Why) | Supports informed buying interest |
| Business Behavior (What it does) | Displays deeper product context before customer commitment |

#### Element: Toggle Currency/Weight Display
| Field | Description |
|---|---|
| Business Purpose (Why) | Improves customer understanding |
| Business Behavior (What it does) | Lets users view information in familiar financial and weight formats |

#### Element: Manage Categories
| Field | Description |
|---|---|
| Business Purpose (Why) | Keeps product organization business-friendly |
| Business Behavior (What it does) | Groups items into navigable collections for easier merchandising |

#### Element: Manage Products
| Field | Description |
|---|---|
| Business Purpose (Why) | Keeps sellable inventory current |
| Business Behavior (What it does) | Adds and updates products that appear to customers |

#### Element: Manage Product Images
| Field | Description |
|---|---|
| Business Purpose (Why) | Protects premium brand presentation |
| Business Behavior (What it does) | Ensures each item has strong visual merchandising |

#### Element: Record Gold Snapshot
| Field | Description |
|---|---|
| Business Purpose (Why) | Anchors pricing to market movement |
| Business Behavior (What it does) | Updates base gold cost used for product price presentation |

#### Element: Record FX Snapshot
| Field | Description |
|---|---|
| Business Purpose (Why) | Supports multi-currency transparency |
| Business Behavior (What it does) | Updates conversion basis used for customer-facing equivalents |

### 5.2 Class Diagram Explanation

#### Element: User
| Field | Description |
|---|---|
| Business Purpose (Why) | Represents every person using the platform |
| Business Behavior (What it does) | Holds role identity for customer and admin journeys |

#### Element: Category
| Field | Description |
|---|---|
| Business Purpose (Why) | Structures product navigation |
| Business Behavior (What it does) | Groups similar jewelry items for discovery and reporting |

#### Element: Product
| Field | Description |
|---|---|
| Business Purpose (Why) | Represents sellable jewelry entries |
| Business Behavior (What it does) | Stores core selling details shown in catalog and detail views |

#### Element: GoldPriceSnapshot
| Field | Description |
|---|---|
| Business Purpose (Why) | Preserves market price checkpoints |
| Business Behavior (What it does) | Supplies the active gold basis for value calculation |

#### Element: ExchangeRateSnapshot
| Field | Description |
|---|---|
| Business Purpose (Why) | Preserves conversion checkpoints |
| Business Behavior (What it does) | Supplies the active conversion basis for alternate currency display |

#### Element: PricingService
| Field | Description |
|---|---|
| Business Purpose (Why) | Keeps pricing decisions consistent |
| Business Behavior (What it does) | Applies current market references when preparing values |

#### Element: CatalogProductPresenter
| Field | Description |
|---|---|
| Business Purpose (Why) | Ensures customer-ready presentation quality |
| Business Behavior (What it does) | Formats product information for clear and consistent catalog display |

