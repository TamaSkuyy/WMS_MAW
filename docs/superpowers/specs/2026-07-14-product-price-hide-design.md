# Hide Product Price (base_price) — Design

Date: 2026-07-14

## Background

The app's real column is `products.base_price` (decimal(12,2), nullable, no default) — there's no separate "harga"/price column. The user wants it hidden from the Product master UI (app now focuses on stock/dashboard, not pricing) and defaulted to 0.

## Scope

- DB: backfill existing NULL `base_price` rows to 0, then alter the column to `default(0)` and `NOT NULL`.
- Backend: drop `base_price` from `ProductController@store`/`update` validation (never read from user input again); remove it from `ProductImporter`/`ProductExporter` (dropped from both import/export scope per requirements gathering, not just the Create/Edit form).
- Frontend: remove all `base_price`/"Harga" UI — `Create.tsx`, `Edit.tsx` (form field + state), `Index.tsx` (table column + import-modal field def), `Show.tsx` (detail row).
- **Factory bug caught during design**: `ProductFactory` currently sets `base_price` via `$this->faker->optional()->randomFloat(...)`, which resolves to `null` about half the time. Once the column becomes `NOT NULL`, that would break every `Product::factory()->create()` call intermittently across the whole test suite (many unrelated tests create Products). Fix: remove `base_price` from the factory definition entirely and let the DB default(0) apply.
- Tests: drop `base_price` from `ProductControllerTest` payloads; drop `Harga`/`base_price` from the CSV fixture and column mapping in `ProductImportExportTest`.

`Product.php` model ($fillable/cast) is left as-is — harmless since nothing sends the field anymore, and low-level code (e.g. a future feature) could still legitimately set it directly.

## Testing

`php artisan migrate`, then full `php artisan test` (the factory fix is the thing most likely to cause widespread failures if missed — verify no NOT-NULL constraint violations anywhere), plus `npm run build`.
