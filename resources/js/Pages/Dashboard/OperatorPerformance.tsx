import { ArrowDownToLine, ShoppingCart, Users } from 'lucide-react';

interface OperatorBar {
    name: string;
    qty: number;
}

function OperatorChart({ title, icon, data, colorClass, emptyText }: {
    title: string;
    icon: React.ReactNode;
    data: OperatorBar[];
    colorClass: string;
    emptyText: string;
}) {
    const maxQty = Math.max(...data.map((d) => d.qty), 1);

    return (
        <div>
            <h4 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-2">
                {icon}
                {title}
            </h4>
            {data.length === 0 ? (
                <p className="text-sm text-gray-400 dark:text-gray-500 py-6 text-center">{emptyText}</p>
            ) : (
                <div className="space-y-2.5">
                    {data.map((d) => (
                        <div key={d.name} title={`${d.name}: ${d.qty.toLocaleString()} qty`}>
                            <div className="flex justify-between mb-1">
                                <span className="text-xs font-medium text-gray-600 dark:text-gray-300 truncate">{d.name}</span>
                                <span className="text-xs font-semibold text-gray-800 dark:text-white/90 tabular-nums ml-2">
                                    {d.qty.toLocaleString()}
                                </span>
                            </div>
                            <div className="h-2.5 bg-gray-100 rounded-full dark:bg-gray-800 overflow-hidden">
                                <div
                                    className={`h-full rounded-full ${colorClass} transition-all duration-500`}
                                    style={{ width: `${Math.max(2, (d.qty / maxQty) * 100)}%` }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function OperatorPerformance({ operatorPerformance }: any) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
            <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                    <Users className="w-4 h-4 text-brand-500" />
                    Performa Operator Hari Ini
                </h3>
                <span className="text-[11px] text-gray-400 flex items-center gap-1.5">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                    </span>
                    Live · 15 dtk
                </span>
            </div>
            <div className="p-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <OperatorChart
                    title="Qty Shopping"
                    icon={<ShoppingCart className="w-4 h-4 text-brand-500" />}
                    data={operatorPerformance?.shopping || []}
                    colorClass="bg-brand-500"
                    emptyText="Belum ada aktivitas shopping hari ini"
                />
                <OperatorChart
                    title="Qty Receiving"
                    icon={<ArrowDownToLine className="w-4 h-4 text-sky-500" />}
                    data={operatorPerformance?.receiving || []}
                    colorClass="bg-sky-500"
                    emptyText="Belum ada aktivitas receiving hari ini"
                />
            </div>
        </div>
    );
}
