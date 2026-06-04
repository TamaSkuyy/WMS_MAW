import AppLayout from '../Tailadmin/layout/AppLayout';
import { Head } from '@inertiajs/react';
import { BoxCubeIcon, ArrowUpIcon, AlertIcon, ArrowDownIcon } from '../Tailadmin/icons';
import MetricCard from './Dashboard/MetricCard';
import StockMovementChart from './Dashboard/StockMovementChart';
import ZoneUtilization from './Dashboard/ZoneUtilization';
import RecentActivity from './Dashboard/RecentActivity';

export default function Dashboard() {
  return (
    <AppLayout>
      <Head title="Dashboard - MAW Warehouse System" />

      <div className="flex items-center gap-3 mb-6">
        <div className="w-1 h-8 rounded-full bg-brand-500"></div>
        <div>
          <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">Dashboard</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Overview of warehouse operations and inventory status
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
        <MetricCard
          title="Total Inventory"
          value="12,450"
          change={{ value: '8.2%', positive: true }}
          icon={<BoxCubeIcon className="text-brand-500" />}
          accentBar="bg-brand-500"
          iconBg="bg-brand-50 dark:bg-brand-500/20"
        />
        <MetricCard
          title="Pending Shipments"
          value="48"
          alert="3 due today"
          icon={<ArrowUpIcon className="text-warning-500" />}
          accentBar="bg-warning-500"
          iconBg="bg-warning-50 dark:bg-warning-500/20"
        />
        <MetricCard
          title="Low Stock Items"
          value="23"
          alert="Needs reorder"
          icon={<AlertIcon className="text-error-500" />}
          accentBar="bg-error-500"
          iconBg="bg-error-50 dark:bg-error-500/20"
        />
        <MetricCard
          title="Receiving Today"
          value="15"
          change={{ value: '5 completed', positive: true }}
          icon={<ArrowDownIcon className="text-success-500" />}
          accentBar="bg-success-500"
          iconBg="bg-success-50 dark:bg-success-500/20"
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
