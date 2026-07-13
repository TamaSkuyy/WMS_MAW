# MAW Logo Rebrand — Design Spec

Date: 2026-07-13

## Background

Requirement: "bantu ubah icon aplikasi dan logo lainnya menggunakan file logo yang sudah ada pada public/assets/Logo_MAW.jpeg" — replace the application's branding with the company's real logo. The file was later placed at `public/images/maw/Logo_MAW.jpeg` (1600×1301 JPEG, uniform near-white background `rgb(247,247,247)` on all edges, a blue-swirl-and-green-arrow circular badge with "MITRA ADHI WASANA" text beneath it).

Investigation found every current logo/icon touchpoint is still the **unmodified default TailAdmin template placeholder** — a generic blue square with a "W" (`logo.svg`, `logo-icon.svg`, `logo-dark.svg`) or the stock TailAdmin sample mark (`auth-logo.svg`), not any prior MAW branding. There is also no favicon wired up at all today (`public/favicon.ico` is 0 bytes and `resources/views/app.blade.php` has no `<link rel="icon">` tag).

## Scope

Replace branding at these four locations (per requirements gathering):
1. Sidebar header (`AppSidebar.tsx` expanded + collapsed states, `AppHeader.tsx` mobile logo)
2. Login page branding panel (`AuthPageLayout.tsx`)
3. Browser favicon / tab icon (new — didn't exist before)
4. Legacy account pages using the Breeze layouts (`ApplicationLogo.jsx`, consumed by `AuthenticatedLayout.jsx` → Profile/Edit, and `GuestLayout.jsx` → Confirm/Reset Password, Verify Email)

Out of scope: `Tailadmin/components/header/Header.tsx` (confirmed unused — not imported anywhere), the pre-existing orphaned `-old.svg` files (already dead before this change, unrelated to it), and the TV Dashboard kiosk page (has no logo today; adding one there is a new feature, not a rebrand of an existing element — not requested).

## 1. Source image processing

The JPEG's background is a uniform `rgb(247,247,247)` across all four corners (verified by direct pixel sampling), which makes clean color-key transparency removal reliable. Row-density analysis of the source found the circular badge artwork occupies rows 109–1101 and the "MITRA ADHI WASANA" text sits separately in rows 1150–1230, with a fully-empty gap between them — so the badge can be cleanly cropped without any text bleeding in.

**Crop region:** approximately `[y: 90–1120, x: 270–1336]` (small padding around the tight content bbox of rows 109–1101 / cols 290–1316), which is nearly square (~1026×992) — ideal for a square icon asset.

**Generated files:**
- `public/images/maw/logo-icon.png` — badge-only crop, background keyed to transparent, resized to 512×512 (high enough for retina display at all usage sizes down to 32px).
- `public/favicon.ico` — multi-resolution ICO (16×16, 32×32, 48×48) built from the same crop, replacing the current empty file.
- `public/images/maw/apple-touch-icon.png` — 180×180 PNG (opaque, since iOS composites touch icons onto its own background — transparent PNGs get a black box on iOS home screens) from the same crop.

No second "full lockup" (badge+text) raster is generated — every usage site pairs the icon image with live HTML text instead (see §2), which stays crisp at any size and can react to light/dark mode via Tailwind classes, matching how the current placeholder SVGs already separate icon (colored square) from text (separate `<text>` elements).

## 2. Component changes

| File | Change |
|---|---|
| `resources/js/Tailadmin/layout/AppSidebar.tsx` | Expanded/hovered state: replace the `logo.svg`/`logo-dark.svg` `<img>` pair with `<img src="/images/maw/logo-icon.png" width={32} height={32}>` + `<span>MAW</span>` (text styled to match current weight/tracking, with a `dark:text-white`-style class so it stays legible in dark mode). Collapsed state: replace `logo-icon.svg` with the same `logo-icon.png`, no text. |
| `resources/js/Tailadmin/layout/AppHeader.tsx` | Mobile logo (`lg:hidden`): same icon + "MAW" text pattern, compact sizing to fit the mobile header bar. |
| `resources/js/Tailadmin/pages/AuthPages/AuthPageLayout.tsx` | Replace the `auth-logo.svg` `<img width={231} height={48}>` with `<img src="/images/maw/logo-icon.png" width={64} height={64}>` + a `<span>`/`<h1>` reading "Mitra Adhi Wasana", placed above the existing "Warehouse Management System" heading (kept as-is). |
| `resources/js/Components/ApplicationLogo.jsx` | Replace the inline generic diamond SVG `<path>` with `<img src="/images/maw/logo-icon.png" className={props.className} alt="Mitra Adhi Wasana" />`, preserving the `className` passthrough so `AuthenticatedLayout.jsx` (`h-9`) and `GuestLayout.jsx` (`h-20 w-20`) need no changes themselves. |
| `resources/views/app.blade.php` | Add to `<head>`: `<link rel="icon" href="/favicon.ico" sizes="any">` and `<link rel="apple-touch-icon" href="/images/maw/apple-touch-icon.png">` — there is currently no icon link tag at all. |

## 3. Cleanup

`public/images/tailadmin/logo/logo.svg`, `logo-dark.svg`, `logo-icon.svg`, and `auth-logo.svg` become unused once §2 lands and will be deleted (not left as orphaned dead files). The pre-existing `-old.svg` variants and the unused `Header.tsx` duplicate are untouched — they were already dead before this change and are unrelated to it.

## 4. Testing / verification

This is a static-asset + presentational change with no backend logic, so there's no new PHPUnit coverage to add. Verification consists of:
- `npm run build` — confirms no TS/JSX errors from the markup changes.
- Visually inspecting each generated PNG (crop framing, transparency, no jagged edges) before wiring it in.
- Manual check in a browser (light + dark mode, sidebar expanded/collapsed, mobile width, login page, favicon tab, Profile/Reset-Password pages) — flagged explicitly as something the assistant cannot do itself in this session (no browser automation tool available); the user should do a final visual pass.
