# Receiving & Shopping Report — Colorful Stat Cards

Date: 2026-07-13

## Background

`Reports/Receiving.tsx` and `Reports/Shopping.tsx` each render 3 summary numbers (Total Transaksi, Total Qty, Produk Unik) as plain white `ComponentCard` boxes with no icon or color — flat and uninteresting compared to the rest of the app.

## Scope

Replace the 3 plain summary boxes on both report pages with the existing `resources/js/Pages/Dashboard/MetricCard.tsx` component (icon chip + colored top accent bar), already used on the main Dashboard. No new component is created — reuse keeps the app's stat-tile look consistent everywhere.

## Design

Color/icon assigned **by metric category, fixed across both pages** (never re-cycled per metric position) so "Total Transaksi" always reads the same color on both report pages:

| Metric | Color | Icon |
|---|---|---|
| Total Transaksi | brand (indigo/blue) | `ListIcon` |
| Total Qty | success (green) | `ArrowDownIcon` on Receiving (incoming), `ArrowUpIcon` on Shopping (outgoing) — direction conveyed by icon, not color |
| Produk Unik | warning (amber) | `BoxCubeIcon` |

Both pages keep their existing `grid grid-cols-1 sm:grid-cols-3` layout — only the card content changes from a bare `<ComponentCard><div className="text-3xl font-semibold">{value}</div></ComponentCard>` to a `<MetricCard title=".." value=".." icon={...} accentBar=".." iconBg="..">`, matching the exact prop pattern already used in `Dashboard.tsx`.

## Testing

Presentational-only change, no backend/prop-shape change. Verify with `npm run build` and a visual check (light + dark mode) — no new automated tests needed.
