# WMS Frontend Redesign — MAW Warehouse System

## Decisions

### Branding & Identity
- **Palette**: Logistics Blue — navy foundation (#0F172A, #1E3A5F) with sky blue accents (#2563EB, #38BDF8), light background (#F8FAFC)
- **Style**: Modern SaaS — clean cards, professional spacing, polished interactions
- **Logo**: Lettermark + Wordmark — blue square "W" icon + "MAW" logotype + "WAREHOUSE SYSTEM" tagline in sky blue
- **Typography**: Keep Inter (already in Tailadmin), leverage Tailwind's font weights for hierarchy

### Sidebar & Navigation
- **4 menu groups** replacing generic "Menu" / "Others":
  - `main` → "MAIN NAVIGATION" — Dashboard only (home/default)
  - `master` → "MASTER DATA" — Products, Warehouses, Suppliers, Customers (reference data)
  - `transaction` → "TRANSACTIONS" — Receiving, Shipping, Transfers, Stock Adjust (operations)
  - `settings` → "SETTINGS" — Users, Roles, Permissions, Menus (admin config)
- Menu groups are managed via the existing Menu Management CRUD, just add `master` and `transaction` to the group options
- Sidebar section headers use brand-blue (#2563EB) color for the label text
- Active menu item uses blue-50 background with brand-blue text

### Dashboard
- Replace ecommerce components with WMS-specific content:
  - **4 KPI metric cards**: Total Inventory, Pending Shipments, Low Stock Items, Receiving Today
  - **Stock Movement chart** (7-day bar chart, replaces MonthlySalesChart)
  - **Warehouse Zone Utilization** (progress bars per zone, replaces MonthlyTarget)
  - **Recent Activity table** (Receive/Ship/Adjust events, replaces RecentOrders)
- Remove: StatisticsChart, DemographicCard (no WMS equivalent yet)
- Dashboard uses placeholder/demo data initially; real data wired later

### Management Pages Polish
- Users, Roles, Permissions, Menus pages keep current layout but get:
  - Consistent card styling matching the new palette
  - Better empty states ("No users yet" with action button)
  - Role badges with brand-blue styling instead of generic gray
  - Proper loading states via Inertia

### Implementation Scope
1. Update Tailwind theme/colors in config to reflect Logistics Blue palette
2. Replace logo assets (create MAW-branded SVGs)
3. Rewrite Dashboard.tsx with WMS widgets
4. Update AppSidebar.tsx group labels
5. Update Menus page to support 4 groups
6. CSS polish on existing management pages
