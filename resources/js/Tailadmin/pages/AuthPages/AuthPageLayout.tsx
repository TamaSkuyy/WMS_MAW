import React from "react";
import GridShape from "../../components/common/GridShape";
import { Link } from "@inertiajs/react";
import ThemeTogglerTwo from "../../components/common/ThemeTogglerTwo";
import {
  BoxCubeIcon,
  TableIcon,
  PieChartIcon,
  GroupIcon,
} from "../../icons";

const features = [
  {
    icon: BoxCubeIcon,
    label: "Inventory Tracking",
  },
  {
    icon: TableIcon,
    label: "Order Management",
  },
  {
    icon: PieChartIcon,
    label: "Real-time Reports",
  },
  {
    icon: GroupIcon,
    label: "Team Collaboration",
  },
];

export default function AuthLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="relative p-6 bg-white z-1 dark:bg-gray-900 sm:p-0">
      <div className="relative flex flex-col justify-center w-full h-screen lg:flex-row dark:bg-gray-900 sm:p-0">
        {children}
        <div className="items-center hidden w-full h-full lg:w-1/2 bg-brand-950 dark:bg-white/5 lg:grid">
          <div className="relative flex items-center justify-center z-1">
            <GridShape />
            <div className="flex flex-col items-center max-w-md px-8">
              <Link href="/" className="block mb-6">
                <img
                  width={231}
                  height={48}
                  src="/images/tailadmin/logo/auth-logo.svg"
                  alt="Logo"
                />
              </Link>

              <h2 className="mb-3 text-2xl font-semibold text-white">
                Warehouse Management System
              </h2>
              <p className="mb-10 text-center text-gray-400 dark:text-white/60">
                Streamline your warehouse operations with real-time inventory
                tracking, order management, and comprehensive reporting.
              </p>

              <div className="grid grid-cols-2 gap-4 w-full max-w-sm">
                {features.map(({ icon: Icon, label }) => (
                  <div
                    key={label}
                    className="flex items-center gap-3 px-4 py-3 rounded-lg bg-white/10"
                  >
                    <Icon className="size-5 text-brand-300 shrink-0" />
                    <span className="text-sm font-medium text-gray-200">
                      {label}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
