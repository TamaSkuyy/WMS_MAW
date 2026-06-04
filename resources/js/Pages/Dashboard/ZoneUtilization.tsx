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
