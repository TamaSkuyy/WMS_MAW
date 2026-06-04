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
