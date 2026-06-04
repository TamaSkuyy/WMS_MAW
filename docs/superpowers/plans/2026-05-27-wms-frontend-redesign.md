# WMS Frontend Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the ecommerce-themed Tailadmin dashboard into a professional Warehouse Management System with Logistics Blue branding, WMS-specific dashboard widgets, and properly organized navigation.

**Architecture:** Update Tailwind v4 CSS theme tokens for the Logistics Blue palette, replace Tailadmin logo SVGs with MAW-branded versions, rewrite the Dashboard page with warehouse KPI widgets (removing ecommerce components), update the AppSidebar to use 4 WMS-appropriate menu groups, and extend the Menus management page to support all 4 groups.

**Tech Stack:** Laravel + React/Inertia.js, Tailwind CSS v4 (CSS-based config via `@theme`), TypeScript, Tailadmin admin template

**Files to modify:**
- `resources/css/app.css` — brand color tokens
- `resources/js/Pages/Dashboard.tsx` — WMS dashboard
- `resources/js/Tailadmin/layout/AppSidebar.tsx` — group labels
- `resources/js/Pages/Menus/Index.tsx` — group options

**Files to create:**
- `public/images/tailadmin/logo/logo.svg` — MAW logo light
- `public/images/tailadmin/logo/logo-dark.svg` — MAW logo dark
- `public/images/tailadmin/logo/logo-icon.svg` — MAW icon only
- `resources/js/Pages/Dashboard/` — WMS dashboard widgets (MetricCard, StockChart, ZoneUtilization, RecentActivity)

---

### Task 1: Update Brand Colors to Logistics Blue

**Files:**
- Modify: `resources/css/app.css:44-55`

- [ ] **Step 1: Replace brand color tokens in app.css**

Replace the existing indigo/purple brand palette with Logistics Blue tones.

From:
```css
--color-brand-25: #f2f7ff;
--color-brand-50: #ecf3ff;
--color-brand-100: #dde9ff;
--color-brand-200: #c2d6ff;
--color-brand-300: #9cb9ff;
--color-brand-400: #7592ff;
--color-brand-500: #465fff;
--color-brand-600: #3641f5;
--color-brand-700: #2a31d8;
--color-brand-800: #252dae;
--color-brand-900: #262e89;
--color-brand-950: #161950;
```

To:
```css
--color-brand-25: #f5f9ff;
--color-brand-50: #eff6ff;
--color-brand-100: #dbeafe;
--color-brand-200: #bfdbfe;
--color-brand-300: #93c5fd;
--color-brand-400: #60a5fa;
--color-brand-500: #2563eb;
--color-brand-600: #1d4ed8;
--color-brand-700: #1e40af;
--color-brand-800: #1e3a8a;
--color-brand-900: #172554;
--color-brand-950: #0f172a;
```

- [ ] **Step 2: Update shadow-focus-ring to match new brand color**

In `resources/css/app.css:150`, change:
```css
--shadow-focus-ring: 0px 0px 0px 4px rgba(70, 95, 255, 0.12);
```
To:
```css
--shadow-focus-ring: 0px 0px 0px 4px rgba(37, 99, 235, 0.12);
```

- [ ] **Step 3: Update flatpickr selected day background references**

In `resources/css/app.css`, the flatpickr styles at lines 526-533 hardcode `#465fff` as background. Replace all occurrences of `#465fff` with `#2563eb`:

Line 526: `background: #465fff;` → `background: #2563eb;`
Line 532: `box-shadow: -10px 0 0 #465fff;` → `box-shadow: -10px 0 0 #2563eb;`

- [ ] **Step 4: Verify CSS builds**

Run: `npm run build`
Expected: Build completes without CSS errors.

- [ ] **Step 5: Commit**

```bash
git add resources/css/app.css
git commit -m "style: update brand colors to Logistics Blue palette"
```

---

### Task 2: Create MAW Logo SVGs

**Files:**
- Create: `public/images/tailadmin/logo/logo.svg`
- Create: `public/images/tailadmin/logo/logo-dark.svg`
- Create: `public/images/tailadmin/logo/logo-icon.svg`

- [ ] **Step 1: Backup old logos**

```bash
cp public/images/tailadmin/logo/logo.svg public/images/tailadmin/logo/logo-old.svg
cp public/images/tailadmin/logo/logo-dark.svg public/images/tailadmin/logo/logo-dark-old.svg
cp public/images/tailadmin/logo/logo-icon.svg public/images/tailadmin/logo/logo-icon-old.svg
```

- [ ] **Step 2: Create light logo SVG (logo.svg)**

Write `public/images/tailadmin/logo/logo.svg`:
```xml
<svg xmlns="http://www.w3.org/2000/svg" width="150" height="40" viewBox="0 0 150 40">
  <rect x="0" y="4" width="32" height="32" rx="7" fill="#2563eb"/>
  <text x="16" y="27" font-family="Outfit, sans-serif" font-weight="900" font-size="19" fill="white" text-anchor="middle">W</text>
  <text x="42" y="24" font-family="Outfit, sans-serif" font-weight="700" font-size="16" fill="#0f172a">MAW</text>
  <text x="42" y="34" font-family="Outfit, sans-serif" font-weight="500" font-size="8" fill="#2563eb" letter-spacing="2">WAREHOUSE SYSTEM</text>
</svg>
```

- [ ] **Step 3: Create dark logo SVG (logo-dark.svg)**

Write `public/images/tailadmin/logo/logo-dark.svg`:
```xml
<svg xmlns="http://www.w3.org/2000/svg" width="150" height="40" viewBox="0 0 150 40">
  <rect x="0" y="4" width="32" height="32" rx="7" fill="#2563eb"/>
  <text x="16" y="27" font-family="Outfit, sans-serif" font-weight="900" font-size="19" fill="white" text-anchor="middle">W</text>
  <text x="42" y="24" font-family="Outfit, sans-serif" font-weight="700" font-size="16" fill="white">MAW</text>
  <text x="42" y="34" font-family="Outfit, sans-serif" font-weight="500" font-size="8" fill="#38bdf8" letter-spacing="2">WAREHOUSE SYSTEM</text>
</svg>
```

- [ ] **Step 4: Create icon-only logo (logo-icon.svg)**

Write `public/images/tailadmin/logo/logo-icon.svg`:
```xml
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
  <rect x="0" y="0" width="32" height="32" rx="7" fill="#2563eb"/>
  <text x="16" y="23" font-family="Outfit, sans-serif" font-weight="900" font-size="19" fill="white" text-anchor="middle">W</text>
</svg>
```

- [ ] **Step 5: Commit**

```bash
git add public/images/tailadmin/logo/logo.svg public/images/tailadmin/logo/logo-dark.svg public/images/tailadmin/logo/logo-icon.svg public/images/tailadmin/logo/logo-old.svg public/images/tailadmin/logo/logo-dark-old.svg public/images/tailadmin/logo/logo-icon-old.svg
git commit -m "feat: add MAW-branded logo SVGs with Logistics Blue styling"
```

---

### Task 3: Create WMS Dashboard Widgets

**Files:**
- Create: `resources/js/Pages/Dashboard/MetricCard.tsx`
- Create: `resources/js/Pages/Dashboard/StockMovementChart.tsx`
- Create: `resources/js/Pages/Dashboard/ZoneUtilization.tsx`
- Create: `resources/js/Pages/Dashboard/RecentActivity.tsx`

- [ ] **Step 1: Create MetricCard component**

Write `resources/js/Pages/Dashboard/MetricCard.tsx`:
```tsx
interface MetricCardProps {
  title: string;
  value: string;
  change?: { value: string; positive: boolean };
  alert?: string;
  icon: string;
  iconBg: string;
}

export default function MetricCard({ title, value, change, alert, icon, iconBg }: MetricCardProps) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
      <div className="flex items-center justify-between">
        <div>
          <span className="text-sm text-gray-500 dark:text-gray-400">{title}</span>
          <h4 className="mt-2 font-bold text-gray-800 text-title-sm dark:text-white/90">{value}</h4>
        </div>
        <div className={`flex items-center justify-center w-12 h-12 rounded-xl ${iconBg}`}>
          <span className="text-xl">{icon}</span>
        </div>
      </div>
      {(change || alert) && (
        <div className="mt-4">
          {change && (
            <span className={`text-xs font-medium ${change.positive ? 'text-success-600' : 'text-error-600'}`}>
              {change.positive ? '↑' : '↓'} {change.value} vs last month
            </span>
          )}
          {alert && (
            <span className="text-xs font-medium text-error-600">{alert}</span>
          )}
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Create StockMovementChart component**

Write `resources/js/Pages/Dashboard/StockMovementChart.tsx`:
```tsx
const data = [
  { day: 'Mon', incoming: 65, outgoing: 45 },
  { day: 'Tue', incoming: 78, outgoing: 52 },
  { day: 'Wed', incoming: 42, outgoing: 38 },
  { day: 'Thu', incoming: 85, outgoing: 60 },
  { day: 'Fri', incoming: 68, outgoing: 48 },
  { day: 'Sat', incoming: 50, outgoing: 35 },
  { day: 'Sun', incoming: 72, outgoing: 55 },
];

export default function StockMovementChart() {
  const maxValue = Math.max(...data.map(d => Math.max(d.incoming, d.outgoing)));

  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
      <div className="flex items-center justify-between mb-6">
        <h3 className="font-semibold text-gray-800 dark:text-white/90">Stock Movement (Last 7 Days)</h3>
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2">
            <span className="w-3 h-3 rounded-full bg-brand-500"></span>
            <span className="text-xs text-gray-500">Incoming</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="w-3 h-3 rounded-full bg-sky-400"></span>
            <span className="text-xs text-gray-500">Outgoing</span>
          </div>
        </div>
      </div>
      <div className="flex items-end gap-3 h-48">
        {data.map((d) => (
          <div key={d.day} className="flex-1 flex flex-col items-center gap-1">
            <div className="w-full flex flex-col items-center gap-1">
              <div
                className="w-full max-w-[20px] bg-brand-500 rounded-t-sm transition-all"
                style={{ height: `${(d.incoming / maxValue) * 140}px` }}
              ></div>
              <div
                className="w-full max-w-[20px] bg-sky-400 rounded-t-sm transition-all"
                style={{ height: `${(d.outgoing / maxValue) * 140}px` }}
              ></div>
            </div>
            <span className="text-xs text-gray-400 mt-1">{d.day}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Create ZoneUtilization component**

Write `resources/js/Pages/Dashboard/ZoneUtilization.tsx`:
```tsx
const zones = [
  { name: 'Zone A', percentage: 78, color: 'bg-brand-500' },
  { name: 'Zone B', percentage: 45, color: 'bg-sky-400' },
  { name: 'Zone C', percentage: 92, color: 'bg-warning-400' },
  { name: 'Zone D', percentage: 31, color: 'bg-success-500' },
];

export default function ZoneUtilization() {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
      <h3 className="font-semibold text-gray-800 dark:text-white/90 mb-6">Warehouse Zone Utilization</h3>
      <div className="space-y-4">
        {zones.map((zone) => (
          <div key={zone.name}>
            <div className="flex justify-between mb-1.5">
              <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{zone.name}</span>
              <span className={`text-sm font-medium ${zone.percentage > 85 ? 'text-error-600' : zone.percentage < 40 ? 'text-success-600' : 'text-gray-500'}`}>
                {zone.percentage}%
              </span>
            </div>
            <div className="h-2.5 bg-gray-100 rounded-full dark:bg-gray-800">
              <div
                className={`h-full rounded-full ${zone.color} transition-all duration-500`}
                style={{ width: `${zone.percentage}%` }}
              ></div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Create RecentActivity component**

Write `resources/js/Pages/Dashboard/RecentActivity.tsx`:
```tsx
const activities = [
  { type: 'Receive', ref: 'RCV-2024-089', warehouse: 'Main WH', status: 'Completed', time: '10 min ago' },
  { type: 'Ship', ref: 'SHP-2024-156', warehouse: 'East WH', status: 'Pending', time: '25 min ago' },
  { type: 'Adjust', ref: 'ADJ-2024-034', warehouse: 'Main WH', status: 'Completed', time: '1 hour ago' },
  { type: 'Receive', ref: 'RCV-2024-088', warehouse: 'North WH', status: 'Processing', time: '2 hours ago' },
  { type: 'Transfer', ref: 'TRF-2024-067', warehouse: 'East WH', status: 'Completed', time: '3 hours ago' },
];

const typeBadge: Record<string, string> = {
  Receive: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
  Ship: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
  Adjust: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
  Transfer: 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
};

const statusBadge: Record<string, string> = {
  Completed: 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
  Pending: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
  Processing: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
};

export default function RecentActivity() {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
      <h3 className="font-semibold text-gray-800 dark:text-white/90 mb-6">Recent Activity</h3>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 dark:border-gray-800">
              <th className="pb-3 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
              <th className="pb-3 text-left text-xs font-medium text-gray-400 uppercase">Reference</th>
              <th className="pb-3 text-left text-xs font-medium text-gray-400 uppercase">Warehouse</th>
              <th className="pb-3 text-left text-xs font-medium text-gray-400 uppercase">Status</th>
              <th className="pb-3 text-right text-xs font-medium text-gray-400 uppercase">Time</th>
            </tr>
          </thead>
          <tbody>
            {activities.map((a) => (
              <tr key={a.ref} className="border-b border-gray-50 dark:border-gray-800/50">
                <td className="py-3">
                  <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${typeBadge[a.type]}`}>{a.type}</span>
                </td>
                <td className="py-3 font-medium text-gray-800 dark:text-white/90">{a.ref}</td>
                <td className="py-3 text-gray-500">{a.warehouse}</td>
                <td className="py-3">
                  <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBadge[a.status]}`}>{a.status}</span>
                </td>
                <td className="py-3 text-right text-gray-400">{a.time}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Dashboard/
git commit -m "feat: add WMS dashboard widgets (MetricCard, StockChart, ZoneUtilization, RecentActivity)"
```

---

### Task 4: Rewrite Dashboard Page

**Files:**
- Modify: `resources/js/Pages/Dashboard.tsx`

- [ ] **Step 1: Replace Dashboard.tsx with WMS dashboard**

Rewrite `resources/js/Pages/Dashboard.tsx`:
```tsx
import AppLayout from '../Tailadmin/layout/AppLayout';
import { Head } from '@inertiajs/react';
import MetricCard from './Dashboard/MetricCard';
import StockMovementChart from './Dashboard/StockMovementChart';
import ZoneUtilization from './Dashboard/ZoneUtilization';
import RecentActivity from './Dashboard/RecentActivity';

export default function Dashboard() {
  return (
    <AppLayout>
      <Head title="Dashboard - MAW Warehouse System" />

      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-gray-800 dark:text-white/90">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Overview of warehouse operations and inventory status
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
        <MetricCard
          title="Total Inventory"
          value="12,450"
          change={{ value: '8.2%', positive: true }}
          icon="📦"
          iconBg="bg-blue-light-50 dark:bg-blue-light-500/15"
        />
        <MetricCard
          title="Pending Shipments"
          value="48"
          alert="3 due today"
          icon="🚚"
          iconBg="bg-warning-50 dark:bg-warning-500/15"
        />
        <MetricCard
          title="Low Stock Items"
          value="23"
          alert="Needs reorder"
          icon="⚠️"
          iconBg="bg-error-50 dark:bg-error-500/15"
        />
        <MetricCard
          title="Receiving Today"
          value="15"
          change={{ value: '5 completed', positive: true }}
          icon="📥"
          iconBg="bg-success-50 dark:bg-success-500/15"
        />
      </div>

      <div className="grid grid-cols-12 gap-4 md:gap-6">
        <div className="col-span-12 xl:col-span-7">
          <StockMovementChart />
        </div>
        <div className="col-span-12 xl:col-span-5">
          <ZoneUtilization />
        </div>
        <div className="col-span-12">
          <RecentActivity />
        </div>
      </div>
    </AppLayout>
  );
}
```

- [ ] **Step 2: Build and verify**

Run: `npm run build`
Expected: Build completes without TypeScript errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Dashboard.tsx
git commit -m "feat: rewrite dashboard with WMS-specific widgets replacing ecommerce components"
```

---

### Task 5: Update Sidebar Menu Group Labels

**Files:**
- Modify: `resources/js/Tailadmin/layout/AppSidebar.tsx:24-28`

- [ ] **Step 1: Add group label mapping and update group filter logic**

In `AppSidebar.tsx`, replace lines 24-28 with a group definition and label map. Replace:

```tsx
const navItems = useMemo(() => dbMenus.filter((m) => m.group === 'main'), [dbMenus]);
const othersItems = useMemo(() => dbMenus.filter((m) => m.group === 'others'), [dbMenus]);
```

With:
```tsx
const groupOrder: { key: string; label: string }[] = [
  { key: 'main', label: 'MAIN NAVIGATION' },
  { key: 'master', label: 'MASTER DATA' },
  { key: 'transaction', label: 'TRANSACTIONS' },
  { key: 'settings', label: 'SETTINGS' },
];

const groupedMenus = useMemo(() => {
  return groupOrder
    .map(g => ({
      ...g,
      items: dbMenus.filter((m: Menu) => m.group === g.key),
    }))
    .filter(g => g.items.length > 0);
}, [dbMenus]);
```

- [ ] **Step 2: Replace the hardcoded nav/others sections with dynamic rendering**

Replace the entire `<nav className="mb-6">` block (lines 247-281) with:

```tsx
<nav className="mb-6">
  <div className="flex flex-col gap-4">
    {groupedMenus.map((group) => (
      <div key={group.key}>
        <h2
          className={`mb-4 text-xs uppercase flex leading-[20px] text-gray-400 font-semibold tracking-wider ${
            !isExpanded && !isHovered
              ? "lg:justify-center"
              : "justify-start"
          }`}
        >
          {isExpanded || isHovered || isMobileOpen ? (
            group.label
          ) : (
            <HorizontaLDots className="size-6" />
          )}
        </h2>
        {renderMenuItems(group.items, group.key as "main" | "others")}
      </div>
    ))}
  </div>
</nav>
```

- [ ] **Step 3: Update types from `"main" | "others"` to `string`**

Three things need `string` instead of the old union:

a) `openSubmenu` state declaration (line 30-33):
```tsx
const [openSubmenu, setOpenSubmenu] = useState<{
  type: string;
  index: number;
} | null>(null);
```

b) `renderMenuItems` parameter (line 99):
```tsx
const renderMenuItems = (items: Menu[], menuType: string) => (
```

c) The useEffect that auto-opens submenu (lines 50-66) — change the hardcoded `["main", "others"]` loop to use `groupOrder` keys:
```tsx
useEffect(() => {
  let submenuMatched = false;
  groupOrder.forEach(({ key }) => {
    const items = dbMenus.filter((m: Menu) => m.group === key);
    items.forEach((nav, index) => {
      if (nav.sub_menus && nav.sub_menus.length > 0) {
        nav.sub_menus.forEach((subItem) => {
          if (subItem.path && subItem.path === url) {
            setOpenSubmenu({ type: key, index });
            submenuMatched = true;
          }
        });
      }
    });
  });
  if (!submenuMatched) {
    setOpenSubmenu(null);
  }
}, [url, dbMenus]);
```

- [ ] **Step 4: Build and verify**

Run: `npm run build`
Expected: Build completes without errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Tailadmin/layout/AppSidebar.tsx
git commit -m "feat: update sidebar with 4 WMS menu groups (Main Nav, Master Data, Transactions, Settings)"
```

---

### Task 6: Update Menus Page for 4 Groups

**Files:**
- Modify: `resources/js/Pages/Menus/Index.tsx:109-118`

- [ ] **Step 1: Update group options in Menus Index**

Replace lines 109-118 in `Menus/Index.tsx`:
```tsx
<SearchableSelect
    options={[
        { value: 'main', label: 'Main Menu' },
        { value: 'others', label: 'Others' }
    ]}
```

With:
```tsx
<SearchableSelect
    options={[
        { value: 'main', label: 'Main Navigation' },
        { value: 'master', label: 'Master Data' },
        { value: 'transaction', label: 'Transactions' },
        { value: 'settings', label: 'Settings' },
    ]}
```

- [ ] **Step 2: Build and verify**

Run: `npm run build`
Expected: Build completes without errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Menus/Index.tsx
git commit -m "feat: add master, transaction, and settings group options to menu management"
```

---

### Task 7: Final Verification

- [ ] **Step 1: Full production build**

Run: `npm run build`
Expected: Clean build, no errors.

- [ ] **Step 2: Check git status**

```bash
git status
```

- [ ] **Step 3: Verify all changes are committed**

```bash
git log --oneline -7
```
Expected: 6 new commits on top of `eb32eb1`.
