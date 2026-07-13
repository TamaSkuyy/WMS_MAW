# MAW Logo Rebrand Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the generic TailAdmin placeholder branding (blue square "W") with the real Mitra Adhi Wasana logo across the sidebar, login page, favicon, and legacy account pages.

**Architecture:** Crop the circular badge out of the source JPEG (`public/images/maw/Logo_MAW.jpeg`), key its uniform near-white background to transparent, and export it as one reusable `logo-icon.png` plus a `favicon.ico` and `apple-touch-icon.png` derived from the same crop. Every usage site pairs that icon image with live HTML/Tailwind text ("MAW" or "Mitra Adhi Wasana") instead of a flattened image-with-text, so text stays crisp and dark-mode-aware. This is a static-asset + presentational change — no backend logic, so verification is `npm run build` plus visual inspection of generated images, not PHPUnit.

**Tech Stack:** Python 3 + Pillow (image processing, one-off), React/TSX + Tailwind (component markup), Laravel Blade (favicon `<link>` tags).

## Global Constraints

- Source file: `public/images/maw/Logo_MAW.jpeg` (1600×1301, background is a uniform `rgb(247,247,247)` on all edges — verified by direct pixel sampling).
- Badge-only crop box (excludes the "MITRA ADHI WASANA" text row, validated by row-density analysis): `(270, 90, 1336, 1120)` → 1066×1030px.
- No second "full lockup" (badge+text) raster — every usage pairs `logo-icon.png` with separate live text, matching how the current placeholder SVGs already separate icon from text.
- `Tailadmin/components/header/Header.tsx` is confirmed unused (not imported anywhere) — do not touch it.
- Only delete the four SVGs this change makes newly unused (`logo.svg`, `logo-dark.svg`, `logo-icon.svg`, `auth-logo.svg`). Leave the pre-existing orphaned `-old.svg` files alone — they were already dead before this change.
- Verify with `npm run build` after each frontend task. No new PHPUnit tests (no backend logic changed).
- Spec reference: `docs/superpowers/specs/2026-07-13-maw-logo-rebrand-design.md`

---

## File Structure

### Files to Create

```
public/images/maw/logo-icon.png          [512x512 transparent badge crop, used everywhere]
public/images/maw/apple-touch-icon.png   [180x180 opaque white-bg variant, iOS home screen]
public/favicon.ico                       [16/32/48px multi-size, replaces the empty file]
```

### Files to Modify

```
resources/js/Tailadmin/layout/AppSidebar.tsx       [sidebar header logo, expanded + collapsed]
resources/js/Tailadmin/layout/AppHeader.tsx        [mobile header logo]
resources/js/Tailadmin/pages/AuthPages/AuthPageLayout.tsx  [login page branding panel]
resources/js/Components/ApplicationLogo.jsx        [legacy Breeze logo component]
resources/views/app.blade.php                      [add favicon <link> tags]
```

### Files to Delete

```
public/images/tailadmin/logo/logo.svg
public/images/tailadmin/logo/logo-dark.svg
public/images/tailadmin/logo/logo-icon.svg
public/images/tailadmin/logo/auth-logo.svg
```

---

## Task 1: Generate the logo assets

**Files:**
- Create: `public/images/maw/logo-icon.png`
- Create: `public/images/maw/apple-touch-icon.png`
- Create: `public/favicon.ico`

**Interfaces:**
- Produces: `/images/maw/logo-icon.png` — 512×512 PNG, transparent background, badge centered in a square canvas. Referenced by Tasks 2–4.
- Produces: `/images/maw/apple-touch-icon.png` — 180×180 PNG, opaque white background. Referenced by Task 4.
- Produces: `/favicon.ico` — ICO containing 16×16/32×32/48×48 frames. Referenced by Task 4.

- [ ] **Step 1: Confirm Pillow is available**

Run: `python3 -c "import PIL; print(PIL.__version__)"`
Expected: prints a version number (no `ModuleNotFoundError`). If missing, run `pip install --user Pillow` first.

- [ ] **Step 2: Run the asset-generation script**

Run this exact command (validated crop box and processing already confirmed against the actual source file):

```bash
python3 <<'EOF'
from PIL import Image
import numpy as np

src = Image.open('public/images/maw/Logo_MAW.jpeg').convert('RGB')
crop = src.crop((270, 90, 1336, 1120))  # badge only, no text — 1066x1030
arr = np.array(crop).astype(int)
bg = np.array([247, 247, 247])
diff = np.abs(arr - bg).sum(axis=2)
alpha = np.clip((diff - 10) * 8, 0, 255).astype('uint8')
rgba = np.dstack([arr.astype('uint8'), alpha])
icon = Image.fromarray(rgba, 'RGBA')

# Pad to square (transparent) before downscaling, to avoid distorting the circle
w, h = icon.size
side = max(w, h)
square = Image.new('RGBA', (side, side), (0, 0, 0, 0))
square.paste(icon, ((side - w) // 2, (side - h) // 2), icon)

logo_icon = square.resize((512, 512), Image.LANCZOS)
logo_icon.save('public/images/maw/logo-icon.png')

# Apple touch icon: opaque white background (iOS boxes transparent PNGs otherwise)
apple = Image.new('RGBA', square.size, (255, 255, 255, 255))
apple.paste(square, (0, 0), square)
apple = apple.convert('RGB').resize((180, 180), Image.LANCZOS)
apple.save('public/images/maw/apple-touch-icon.png')

# Favicon: multi-size ICO
fav_src = square.resize((256, 256), Image.LANCZOS)
fav_src.save('public/favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])

print('Generated: logo-icon.png, apple-touch-icon.png, favicon.ico')
EOF
```

Expected output: `Generated: logo-icon.png, apple-touch-icon.png, favicon.ico`

- [ ] **Step 3: Verify the generated files exist and look correct**

Run: `ls -la public/images/maw/logo-icon.png public/images/maw/apple-touch-icon.png public/favicon.ico`
Expected: all three files exist, `favicon.ico` is no longer 0 bytes.

Visually inspect `public/images/maw/logo-icon.png` and `public/images/maw/apple-touch-icon.png` (e.g. open them in an image viewer or read them as images): confirm the blue-swirl/green-arrow badge is centered, background is transparent (checkerboard pattern in an editor, or renders cleanly on both light and dark surfaces), and there's no leftover "MITRA ADHI WASANA" text baked in (that would mean the crop box needs adjusting).

- [ ] **Step 4: Commit**

```bash
git add public/images/maw/logo-icon.png public/images/maw/apple-touch-icon.png public/favicon.ico
git commit -m "feat: generate MAW logo icon, apple-touch-icon, and favicon from source artwork"
```

---

## Task 2: Sidebar and mobile header logo

**Files:**
- Modify: `resources/js/Tailadmin/layout/AppSidebar.tsx`
- Modify: `resources/js/Tailadmin/layout/AppHeader.tsx`

**Interfaces:**
- Consumes: `/images/maw/logo-icon.png` from Task 1.

- [ ] **Step 1: Replace the sidebar logo block**

In `resources/js/Tailadmin/layout/AppSidebar.tsx`, replace:

```tsx
        <Link href="/">
          {isExpanded || isHovered || isMobileOpen ? (
            <>
              <img
                className="dark:hidden"
                src="/images/tailadmin/logo/logo.svg"
                alt="Logo"
                width={150}
                height={40}
              />
              <img
                className="hidden dark:block"
                src="/images/tailadmin/logo/logo-dark.svg"
                alt="Logo"
                width={150}
                height={40}
              />
            </>
          ) : (
            <img
              src="/images/tailadmin/logo/logo-icon.svg"
              alt="Logo"
              width={32}
              height={32}
            />
          )}
        </Link>
```

with:

```tsx
        <Link href="/" className="flex items-center gap-2.5">
          <img
            src="/images/maw/logo-icon.png"
            alt="Mitra Adhi Wasana"
            width={32}
            height={32}
          />
          {(isExpanded || isHovered || isMobileOpen) && (
            <span className="text-base font-bold text-[#0F172A] dark:text-white tracking-wide">
              MAW
            </span>
          )}
        </Link>
```

- [ ] **Step 2: Replace the mobile header logo block**

In `resources/js/Tailadmin/layout/AppHeader.tsx`, replace:

```tsx
          <Link href="/" className="lg:hidden">
            <img
              className="dark:hidden"
              src="./images/tailadmin/logo/logo.svg"
              alt="Logo"
            />
            <img
              className="hidden dark:block"
              src="./images/tailadmin/logo/logo-dark.svg"
              alt="Logo"
            />
          </Link>
```

with:

```tsx
          <Link href="/" className="lg:hidden flex items-center gap-2">
            <img
              src="/images/maw/logo-icon.png"
              alt="Mitra Adhi Wasana"
              width={28}
              height={28}
            />
            <span className="text-sm font-bold text-[#0F172A] dark:text-white">
              MAW
            </span>
          </Link>
```

- [ ] **Step 3: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `AppSidebar.tsx` or `AppHeader.tsx`.

- [ ] **Step 4: Manual verification**

With the dev server running, log in and confirm: sidebar shows the MAW badge + "MAW" text when expanded, badge only when collapsed to the icon rail (hover to re-expand also works), and the mobile header (narrow viewport, `<lg`) shows the same badge + "MAW" text. Check both light and dark mode.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Tailadmin/layout/AppSidebar.tsx resources/js/Tailadmin/layout/AppHeader.tsx
git commit -m "feat: replace sidebar and mobile header logo with MAW branding"
```

---

## Task 3: Login page branding panel

**Files:**
- Modify: `resources/js/Tailadmin/pages/AuthPages/AuthPageLayout.tsx`

**Interfaces:**
- Consumes: `/images/maw/logo-icon.png` from Task 1.

- [ ] **Step 1: Replace the auth panel logo block**

In `resources/js/Tailadmin/pages/AuthPages/AuthPageLayout.tsx`, replace:

```tsx
              <Link href="/" className="block mb-6">
                <img
                  width={231}
                  height={48}
                  src="/images/tailadmin/logo/auth-logo.svg"
                  alt="Logo"
                />
              </Link>
```

with:

```tsx
              <Link href="/" className="flex flex-col items-center mb-6">
                <img
                  width={64}
                  height={64}
                  src="/images/maw/logo-icon.png"
                  alt="Mitra Adhi Wasana"
                  className="mb-3"
                />
                <span className="text-lg font-bold text-white tracking-wide">
                  Mitra Adhi Wasana
                </span>
              </Link>
```

- [ ] **Step 2: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `AuthPageLayout.tsx`.

- [ ] **Step 3: Manual verification**

Open the login page. Confirm the right-side branding panel shows the MAW badge, "Mitra Adhi Wasana" text, then the existing "Warehouse Management System" heading and feature list below it, all still centered and readable against the dark panel background.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Tailadmin/pages/AuthPages/AuthPageLayout.tsx
git commit -m "feat: replace login page branding panel logo with MAW branding"
```

---

## Task 4: Legacy Breeze logo component and favicon link tags

**Files:**
- Modify: `resources/js/Components/ApplicationLogo.jsx`
- Modify: `resources/views/app.blade.php`

**Interfaces:**
- Consumes: `/images/maw/logo-icon.png`, `/favicon.ico`, `/images/maw/apple-touch-icon.png` from Task 1.
- No changes needed to `AuthenticatedLayout.jsx` (`h-9` sizing) or `GuestLayout.jsx` (`h-20 w-20` sizing) — both pass `className` through to `ApplicationLogo`, which now applies it to an `<img>` instead of an `<svg>`.

- [ ] **Step 1: Replace ApplicationLogo.jsx**

Replace the full contents of `resources/js/Components/ApplicationLogo.jsx` with:

```jsx
export default function ApplicationLogo(props) {
    return (
        <img
            {...props}
            src="/images/maw/logo-icon.png"
            alt="Mitra Adhi Wasana"
        />
    );
}
```

- [ ] **Step 2: Add favicon link tags to app.blade.php**

In `resources/views/app.blade.php`, add these two lines right after the `<title inertia>` line and before the `<!-- Fonts -->` comment:

```blade
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/images/maw/apple-touch-icon.png">
```

- [ ] **Step 3: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `ApplicationLogo.jsx`.

- [ ] **Step 4: Manual verification**

Open the Profile page (uses `AuthenticatedLayout`) and the Confirm/Reset Password pages (use `GuestLayout`) — confirm the MAW badge renders at the expected size in both (top nav for Profile, large centered badge for the guest pages). Reload any page and check the browser tab shows the new favicon instead of the old blank/default one.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/ApplicationLogo.jsx resources/views/app.blade.php
git commit -m "feat: replace legacy ApplicationLogo and wire up favicon link tags"
```

---

## Task 5: Remove unused placeholder logo files

**Files:**
- Delete: `public/images/tailadmin/logo/logo.svg`
- Delete: `public/images/tailadmin/logo/logo-dark.svg`
- Delete: `public/images/tailadmin/logo/logo-icon.svg`
- Delete: `public/images/tailadmin/logo/auth-logo.svg`

**Interfaces:**
- None — this task only removes files made unreferenced by Tasks 2–3.

- [ ] **Step 1: Confirm the files are no longer referenced**

Run: `grep -rn "tailadmin/logo/logo\.svg\|tailadmin/logo/logo-dark\.svg\|tailadmin/logo/logo-icon\.svg\|tailadmin/logo/auth-logo\.svg" resources/js/`
Expected: no output (no remaining references).

- [ ] **Step 2: Delete the files**

```bash
git rm public/images/tailadmin/logo/logo.svg public/images/tailadmin/logo/logo-dark.svg public/images/tailadmin/logo/logo-icon.svg public/images/tailadmin/logo/auth-logo.svg
```

- [ ] **Step 3: Full build to verify nothing broke**

Run: `npm run build`
Expected: build succeeds with no missing-asset errors.

- [ ] **Step 4: Commit**

```bash
git commit -m "chore: remove unused placeholder logo SVGs superseded by MAW branding"
```
