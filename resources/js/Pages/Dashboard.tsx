import AppLayout from '../Tailadmin/layout/AppLayout';
import { Head } from '@inertiajs/react';
import EcommerceMetrics from '../Tailadmin/components/ecommerce/EcommerceMetrics';
import MonthlySalesChart from '../Tailadmin/components/ecommerce/MonthlySalesChart';
import StatisticsChart from '../Tailadmin/components/ecommerce/StatisticsChart';
import MonthlyTarget from '../Tailadmin/components/ecommerce/MonthlyTarget';
import RecentOrders from '../Tailadmin/components/ecommerce/RecentOrders';
import DemographicCard from '../Tailadmin/components/ecommerce/DemographicCard';

export default function Dashboard() {
    return (
        <AppLayout>
            <Head title="Dashboard" />
            
            <div className="grid grid-cols-12 gap-4 md:gap-6">
                <div className="col-span-12 space-y-6 xl:col-span-7">
                    <EcommerceMetrics />
                    <MonthlySalesChart />
                </div>

                <div className="col-span-12 xl:col-span-5">
                    <MonthlyTarget />
                </div>

                <div className="col-span-12">
                    <StatisticsChart />
                </div>

                <div className="col-span-12 xl:col-span-5">
                    <DemographicCard />
                </div>

                <div className="col-span-12 xl:col-span-7">
                    <RecentOrders />
                </div>
            </div>
        </AppLayout>
    );
}
