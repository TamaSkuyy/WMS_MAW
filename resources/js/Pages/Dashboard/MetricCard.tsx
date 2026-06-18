import type { ReactNode } from 'react';

interface MetricCardProps {
  title: string;
  value: string;
  subtitle?: string;
  change?: { value: string; positive: boolean };
  alert?: boolean | string;
  icon: ReactNode;
  accentBar: string;
  iconBg: string;
}

export default function MetricCard({ title, value, subtitle, change, alert, icon, accentBar, iconBg }: MetricCardProps) {
  return (
    <div className="relative rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 md:p-6 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
      <div className={`absolute top-0 left-0 right-0 h-1 ${accentBar}`}></div>
      <div className="flex items-center justify-between">
        <div>
          <span className="text-sm text-gray-500 dark:text-gray-400">{title}</span>
          <h4 className="mt-2 font-bold text-gray-800 text-title-sm dark:text-white/90">{value}</h4>
        </div>
        <div className={`flex items-center justify-center w-12 h-12 rounded-xl ${iconBg}`}>
          {icon}
        </div>
      </div>
      {(subtitle || change || alert) && (
        <div className="mt-4">
          {subtitle && (
            <span className={`text-xs font-medium ${typeof alert === 'boolean' && alert ? 'text-error-600' : 'text-gray-500 dark:text-gray-400'}`}>
              {subtitle}
            </span>
          )}
          {change && (
            <span className={`text-xs font-medium ${change.positive ? 'text-success-600' : 'text-error-600'}`}>
              {change.positive ? '↑' : '↓'} {change.value}
            </span>
          )}
          {typeof alert === 'string' && (
            <span className="text-xs font-medium text-error-600">{alert}</span>
          )}
        </div>
      )}
    </div>
  );
}
