# Pricing & Calculation Formulas Reference

Date: 2026-04-26  
Scope: Canonical formulas used by Jewelry Hub for mass conversion, catalog pricing, bespoke quote math, and currency conversion.

---

## 1) Mass Conversion Formulas (Grams <-> Kyat/Pae/Yway)

These formulas are for **Myanmar gold mass units** (not MMK currency).

### Constants (system standard)

- `grams_per_kyat_mass = 16.606`
- `pae_per_kyat = 16`
- `yway_per_pae = 8`

From code:
- `backend/app/Services/PricingService.php`
- `frontend/lib/core/units/myanmar_mass.dart`

### Forward conversion (grams -> kyat/pae/yway)

Given:
- `g = grams`

Steps:

1. `total_kyat = g / 16.606`
2. `kyat = floor(total_kyat)`
3. `remaining_kyat = total_kyat - kyat`
4. `pae_precise = remaining_kyat * 16`
5. `pae = floor(pae_precise)`
6. `remaining_pae = pae_precise - pae`
7. `yway = remaining_pae * 8`

Normalization guards in implementation:
- if `yway >= 8`, carry into `pae`
- if `pae >= 16`, carry into `kyat`

### Reverse conversion (kyat/pae/yway -> grams)

If needed for manual verification:

`grams = (kyat * 16.606) + (pae / 16 * 16.606) + (yway / (16 * 8) * 16.606)`

Equivalent compact form:

`grams = 16.606 * (kyat + pae/16 + yway/128)`

---

## 2) Dynamic Product Pricing (Catalog Ready-made Estimates)

Catalog estimate uses active snapshots:
- Gold snapshot: `price_mmk_per_gram`
- FX snapshot: `mmk_per_usd`

From code:
- `backend/app/Services/CatalogProductPresenter.php`
- `backend/app/Services/PricingService.php`

### Variables

- `W = product.weight_grams`
- `G = gold.price_mmk_per_gram` (MMK per gram)
- `L = product.labor_fee_mmk`
- `Gem = product.gem_price_mmk` (nullable -> treated as `0`)
- `R = fx.mmk_per_usd`

### Formula set

1. **Metal value (MMK):**
   - `metal_value_mmk = W * G`

2. **Estimated line total (MMK):**
   - `line_total_mmk = metal_value_mmk + L + Gem`

3. **Estimated line total (USD):**
   - `line_total_usd = line_total_mmk / R`

4. **Pricing visibility rule:**
   - If either gold snapshot or FX snapshot is missing:
     - `pricing = null`
     - `meta.pricing_configured = false`

### Worked example

If:
- `W = 10`
- `G = 160000`
- `L = 100000`
- `Gem = 40000`
- `R = 2000`

Then:
- `metal_value_mmk = 10 * 160000 = 1,600,000`
- `line_total_mmk = 1,600,000 + 100,000 + 40,000 = 1,740,000`
- `line_total_usd = 1,740,000 / 2,000 = 870`

---

## 3) Bespoke Quote Math (QuoteBuilderService)

Custom/bespoke quote line math is the same core structure, applied per quote line and then summed.

From code:
- `backend/app/Services/QuoteBuilderService.php`

### Per-line variables

- `W_i = line.weight_grams`
- `G = gold.price_mmk_per_gram` (snapshot attached to quote)
- `L_i = line.labor_fee_mmk`
- `Gem_i = line.gem_price_mmk`
- `R = fx.mmk_per_usd` (snapshot attached to quote)

If `product_id` is present on line, service may prefill/override labor/gem from product values (see `createQuote` and `normalizeQuoteLines` behavior).

### Per-line formulas

1. `metal_value_mmk_i = W_i * G`
2. `line_total_mmk_i = metal_value_mmk_i + L_i + Gem_i`
3. `line_total_usd_snapshot_i = line_total_mmk_i / R`

### Quote totals

For `n` lines:

- `quote_total_mmk = sum(line_total_mmk_i), i=1..n`
- `quote_total_usd_snapshot = sum(line_total_usd_snapshot_i), i=1..n`

Both totals are stored on `quotes` as snapshot totals:
- `total_mmk`
- `total_usd_snapshot`

### Precision behavior

System uses BCMath with scale `4` in pricing-critical operations:
- multiplication/division/addition commonly use 4 decimal places.

---

## 4) Currency Conversion Logic (MMK <-> USD)

From code:
- `backend/app/Services/PricingService.php`

### Definitions

- `R = mmk_per_usd` (MMK per 1 USD)

### MMK -> USD

`usd = mmk / R`

Implementation rule:
- if `R == 0`, returns `'0'` (guard against division-by-zero).

### USD -> MMK

`mmk = usd * R`

---

## 5) Formula Hierarchy in System Flows

### Catalog flow

1. Resolve active snapshots (`gold`, `fx`)
2. Compute per-product estimate using catalog formula
3. Return:
   - `pricing` payload (if configured)
   - `pricing_configured` flag in `meta`

### Bespoke quote flow

1. Freeze snapshots (`gold_price_snapshot_id`, `exchange_rate_snapshot_id`)
2. Compute per-line MMK/USD snapshot values
3. Sum to quote totals
4. Persist quote + line snapshots as immutable reference math for that quote

---

## 6) Notes and Guardrails

- MMK is the system base monetary unit for storage and calculations.
- USD values in quote/catalog are derived from `mmk_per_usd` snapshots.
- Mass kyat/pae/yway is a weight display conversion layer, not currency logic.
- Catalog pricing is an estimate based on current snapshots; bespoke quote totals are snapshot-frozen per quote creation.
