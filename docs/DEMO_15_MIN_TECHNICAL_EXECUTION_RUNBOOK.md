# 15-Minute Technical Demo Runbook (Execution Script)

Use **2 devices + 1 browser** for smooth recording:
- Browser: Filament Admin (`/admin`)
- Phone/Emulator A: Customer app
- Phone/Emulator B: Artisan app

Seed-backed credentials and data:
- Admin: `admin@jewelryhub.test` / `password`
- Customer: `customer@example.com` / `password`
- Artisan: `artisan@jewelryhub.test` / `password`
- Seeded SKUs you can reference: `RING-CL-SOL-001`, `NEC-PEND-LOT-002`, `CUST-BESPOKE-001`

## Runbook Table (strict 15:00)

| Time | Action (UI) | Input Data | Expected Outcome | Narration Script |
|---|---|---|---|---|
| 00:00-00:20 | Open Admin login page | URL: `/admin`, admin credentials | Admin dashboard/Command Center opens | “I’m starting from the Filament admin panel, which is the operational control layer for pricing, catalog, orders, and analytics.” |
| 00:20-00:40 | Open Customer app and sign in | `customer@example.com` / `password` | Customer lands on Catalog tab | “Now I authenticate as customer to show the live storefront and concierge flow from the client side.” |
| 00:40-01:00 | Open Artisan app and sign in | `artisan@jewelryhub.test` / `password` | Artisan Jobs screen loads | “I also sign in as artisan to demonstrate workshop execution and progress media uploads later in the flow.” |

## 1) Platform Config (Auth, Pricing Snapshots, Catalog Management)

| Time | Action (UI) | Input Data | Expected Outcome | Narration Script |
|---|---|---|---|---|
| 01:00-01:20 | Admin: open `Inventory -> Gold prices` | Click `Create` | Gold snapshot form opens | “Gold snapshots are the first pricing dependency; catalog estimates are calculated from active snapshot records.” |
| 01:20-01:40 | Submit gold snapshot | `price_mmk_per_gram: 168000`, effective now | Success notification, new row appears at top | “I’m recording a gold price snapshot; this updates the metal basis used by pricing services.” |
| 01:40-02:00 | Admin: open `Inventory -> Exchange rates` | Click `Create` | Exchange rate form opens | “Now I set the FX snapshot used for MMK to USD display conversion.” |
| 02:00-02:20 | Submit FX snapshot | `mmk_per_usd: 2100`, effective now | Success notification, new row appears | “With both gold and FX snapshots active, pricing is fully configured for storefront rendering.” |
| 02:20-02:40 | Admin: open `Inventory -> Categories` | Create category | Name: `Demo Bridal Set`, slug/auto, sort order `11` | “This is CRUD on category taxonomy, used to organize the public catalog and admin inventory filters.” |
| 02:40-03:10 | Admin: open `Inventory -> Products` and create item | `sku: DEMO-VID-001`, title `Demo Bridal Ring`, category `Demo Bridal Set`, weight `5.2`, labor `250000`, gems `120000`, publish `true` | Product saved; appears in product list | “Here I create a sellable product record with weight and MMK cost components that drive estimate calculations.” |
| 03:10-03:30 | Customer app: pull-to-refresh catalog | N/A | New product/category visible; `PriceTag` shows numeric MMK/USD (not request-only) | “The storefront reactively reflects admin data plus snapshot configuration, proving admin-to-customer integration.” |

## 2) Bespoke Flow (Browsing, Toggles, Inquiry + Attachments)

| Time | Action (UI) | Input Data | Expected Outcome | Narration Script |
|---|---|---|---|---|
| 03:30-03:50 | Customer: open display preferences (`tune` icon) | Currency: `USD`, Weight: `K·P·Y` | Price/weight labels animate to selected format | “Display preferences change presentation only; ledger values remain MMK and grams in backend.” |
| 03:50-04:10 | Customer: tap seeded product (e.g. `Classic Solitaire Ring`) | N/A | Product detail opens with pricing breakdown | “This detail page shows computed estimate components: metal, labor, gems, and totals.” |
| 04:10-04:40 | Customer: tap `Send inquiry` from product detail | Message: `Need a bespoke family crest variant in 18K.` + attach 1-2 photos | Inquiry created and chat screen opens | “The inquiry is created with optional media; this starts a managed case linked to customer and optional product.” |
| 04:40-05:00 | Customer: send first text in chat | `Please quote for wedding deadline next month.` | New customer bubble appears instantly | “Messages are written into the inquiry thread, with role-tagged payload and server timestamps.” |
| 05:00-05:20 | Customer: send an image in chat | Pick gallery image | Image message appears in chat | “This demonstrates chat media upload from mobile and the image message model used in realtime conversation.” |

## 3) Real-time Pipeline (Firestore Sync, Quote Generation, Acceptance)

| Time | Action (UI) | Input Data | Expected Outcome | Narration Script |
|---|---|---|---|---|
| 05:20-05:40 | Admin: open `Inquiries -> Chat` for latest inquiry | Select newly created inquiry | Concierge chat opens with same thread | “Admin and customer are now on the same Firestore-backed thread with shared pipeline context.” |
| 05:40-06:00 | Admin: verify incoming customer text/image | N/A | Customer messages visible without manual refresh | “This confirms realtime synchronization between Flutter client and Filament concierge console.” |
| 06:00-06:30 | Admin: click `Generate quote` | In quote dialog use one line: weight `5.2`, labor `250000`, gems `120000`, title `Bespoke Crest Ring`, deposit `% 30` | Quote published success notice | “QuoteBuilderService calculates totals from snapshots and publishes a quote card into the conversation.” |
| 06:30-06:50 | Customer: observe quote card | N/A | Quote card appears with Accept/Decline actions | “The quote is now rendered as a structured card, not plain text, with explicit decision controls.” |
| 06:50-07:10 | Customer: tap `Accept` on quote card | Confirm action | Snackbar/order message: `awaiting_deposit` | “Accepting the quote transitions inquiry pipeline and creates an executable order path.” |
| 07:10-07:30 | Admin: in inquiry chat click `Send payment info` | Choose active payment profile and submit | Payment info card appears in both chats | “Admin sends deposit instructions as a payment card, which becomes the trigger for financial proof submission.” |

## 4) Financials (Payment Proof Upload + Verification)

| Time | Action (UI) | Input Data | Expected Outcome | Narration Script |
|---|---|---|---|---|
| 07:30-08:00 | Customer: click `Upload payment proof` in inquiry chat | Select image file (receipt screenshot) | Toast/snackbar: payment proof submitted; proof image appears in thread | “Customer uploads deposit evidence; backend stores proof and updates payment state to verification queue.” |
| 08:00-08:30 | Admin: open `Payments` verification list (or order payment section) | Filter latest pending deposit | Record visible as awaiting verification | “This queue is the control point for finance validation before workshop work can begin.” |
| 08:30-09:00 | Admin: confirm deposit proof | Click `Confirm` | Status moves to confirmed; inquiry pipeline advances | “Once confirmed, payment gating logic unlocks production operations and updates timeline metadata.” |

## 5) Artisan Bridge (Assignment, Progress Photos, Timeline Sync)

| Time | Action (UI) | Input Data | Expected Outcome | Narration Script |
|---|---|---|---|---|
| 09:00-09:40 | Admin: open `Orders -> View` latest order, click `Assign artisan` | Artisan: `Test Artisan`; scope `full_order`; priority `high`; optional instructions `Engraving priority`; select reference images | Success notification: `Artisan assigned` | “OrderLifecycleService applies guarded assignment, including optional reference image bridge from inquiry to workshop.” |
| 09:40-10:00 | Artisan app: refresh Jobs list, open assigned job | N/A | Assigned job appears with order number and instructions | “Assignment is now available on artisan mobile with making specs and selected references.” |
| 10:00-10:20 | Artisan: tap `Start job` | N/A | Snackbar: `Production started.`; status changes | “This transitions the order into in-production state through role-protected workflow endpoints.” |
| 10:20-10:50 | Artisan: tap `Upload progress photos` | Stage: `polishing`; Note: `Band finishing done`; select 1-2 photos | Snackbar: `Progress uploaded.` | “Progress events are persisted with attachments and mirrored to inquiry chat as server image messages.” |
| 10:50-11:10 | Customer: return to inquiry chat and order timeline | N/A | Workshop progress image/timeline entry visible | “Customer sees near-realtime workshop transparency through synchronized chat and timeline data.” |
| 11:10-11:30 | Artisan: tap `Ready for review` | N/A | Snackbar confirms ready-for-review | “This moves the order from in-production to pending QC, preparing final quality gate.” |

## 6) Completion (QC, Receipt PDF, Admin Analytics Widgets)

| Time | Action (UI) | Input Data | Expected Outcome | Narration Script |
|---|---|---|---|---|
| 11:30-12:00 | Admin: in order detail click `QC approve` | Confirm action | Success: order becomes `ready`; system QC message posted to chat | “QC approval enforces the final lifecycle transition and posts a system message to customer thread.” |
| 12:00-12:30 | Admin: in same order click `Record final payment` | Amount defaults to remaining MMK; confirm | Final payment verified; receipt generated | “Final settlement triggers formal financial closure and server-side receipt issuance.” |
| 12:30-12:50 | Customer: open Orders -> Order Detail -> `Official Receipts` | Tap latest receipt chip | Receipt detail screen opens with PDF/view option | “Customer can retrieve official deposit/final receipts, demonstrating auditable post-payment documentation.” |
| 12:50-13:30 | Admin: open `Command center` dashboard | N/A | KPI strips/charts/widgets visible (Revenue, Conversion, Funnel, Operations Pulse, Quick Export) | “This is the analytics layer: operational pulse, conversion funnel, and reporting widgets driven by seeded and live data.” |
| 13:30-14:10 | Admin: trigger quick export/report action | Click dashboard/header export button | File generation/download starts | “ReportExportService provides management-ready outputs for governance and business review.” |
| 14:10-14:40 | Artisan: open Notifications screen | N/A | Notification list with unread/read states | “Artisan notifications close the loop by surfacing assignment or revised workflow events to workshop users.” |
| 14:40-15:00 | Final recap on split-screen (Admin + Customer + Artisan) | N/A | All key states visible: configured pricing, accepted quote, verified payment, production updates, QC ready, receipt | “End-to-end delivery is complete: platform config, concierge quoting, financial gating, artisan execution, and analytics-backed completion.” |

## Quick Input Data Pack (for copy/paste during recording)

- **Admin login**: `admin@jewelryhub.test` / `password`
- **Customer login**: `customer@example.com` / `password`
- **Artisan login**: `artisan@jewelryhub.test` / `password`
- **Gold snapshot**: `168000` MMK/gram
- **FX snapshot**: `2100` MMK per USD
- **Demo category**: `Demo Bridal Set`
- **Demo product**:
  - SKU: `DEMO-VID-001`
  - Title: `Demo Bridal Ring`
  - Weight: `5.2`
  - Labor: `250000`
  - Gems: `120000`
- **Inquiry message**: `Need a bespoke family crest variant in 18K.`
- **Chat message**: `Please quote for wedding deadline next month.`
- **Quote line**:
  - Weight: `5.2`
  - Labor: `250000`
  - Gems: `120000`
  - Title: `Bespoke Crest Ring`
  - Deposit `%`: `30`
- **Artisan assignment note**: `Engraving priority`
- **Progress upload meta**:
  - Stage: `polishing`
  - Note: `Band finishing done`
