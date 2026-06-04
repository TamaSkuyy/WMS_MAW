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
            <span className="w-3 h-3 rounded-sm bg-brand-500"></span>
            <span className="text-xs text-gray-500">Incoming</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="w-3 h-3 rounded-sm bg-sky-400"></span>
            <span className="text-xs text-gray-500">Outgoing</span>
          </div>
        </div>
      </div>
      <div className="flex items-end gap-3 h-44">
        {data.map((d) => (
          <div key={d.day} className="flex-1 flex flex-col items-center gap-1">
            <div className="flex items-end gap-[3px]">
              <div
                className="w-[10px] bg-brand-500 rounded-t-sm"
                style={{ height: `${(d.incoming / maxValue) * 120}px` }}
              ></div>
              <div
                className="w-[10px] bg-sky-400 rounded-t-sm"
                style={{ height: `${(d.outgoing / maxValue) * 120}px` }}
              ></div>
            </div>
            <span className="text-xs text-gray-400 mt-1">{d.day}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
