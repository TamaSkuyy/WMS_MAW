import { useEffect, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown, Inbox, Search, Warehouse, X } from 'lucide-react';

interface RackAlert {
    id: number;
    code: string;
    zone: string | null;
    usage: number;
    capacity: number | null;
    pct: number | null;
}

interface Props {
    rackAlerts: RackAlert[];
    rackFullCount: number;
    rackNearFullCount: number;
}

type RackStatus = 'over' | 'near' | 'safe' | 'filled' | 'empty';
type StatusFilter = 'all' | 'over' | 'near' | 'filled' | 'empty';

const STATUS_STYLE: Record<RackStatus, { card: string; badge: string; bar: string; text: string }> = {
    over: {
        card: 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-800',
        badge: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
        bar: 'bg-red-500',
        text: 'text-red-600 dark:text-red-400',
    },
    near: {
        card: 'bg-orange-50 dark:bg-orange-500/10 border-orange-200 dark:border-orange-800',
        badge: 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300',
        bar: 'bg-orange-400',
        text: 'text-orange-600 dark:text-orange-400',
    },
    safe: {
        card: 'bg-green-50 dark:bg-green-500/10 border-green-200 dark:border-green-800',
        badge: 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
        bar: 'bg-green-500',
        text: 'text-green-600 dark:text-green-400',
    },
    filled: {
        card: 'bg-sky-50 dark:bg-sky-500/10 border-sky-200 dark:border-sky-800',
        badge: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        bar: 'bg-sky-500',
        text: 'text-sky-600 dark:text-sky-400',
    },
    empty: {
        card: 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
        badge: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300',
        bar: 'bg-gray-300',
        text: 'text-gray-500 dark:text-gray-400',
    },
};

/** Grup rak = huruf pertama kode rak (A16 → A, Buffer GO3 → B). */
function groupKeyOf(code: string): string {
    const match = (code || '').trim().match(/[A-Za-z]/);
    return match ? match[0].toUpperCase() : '#';
}

function statusOf(rack: RackAlert): RackStatus {
    const hasCap = rack.capacity !== null && rack.capacity > 0;
    if (hasCap && (rack.pct ?? 0) >= 100) return 'over';
    if (hasCap && (rack.pct ?? 0) >= 80) return 'near';
    if (hasCap) return rack.usage > 0 ? 'safe' : 'empty';
    return rack.usage > 0 ? 'filled' : 'empty';
}

export default function RackCapacity({ rackAlerts, rackFullCount, rackNearFullCount }: Props) {
    const racks = rackAlerts ?? [];

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [activeGroup, setActiveGroup] = useState<string>('all');
    const [overrides, setOverrides] = useState<Record<string, boolean>>({});

    const enriched = useMemo(
        () => racks.map((rack) => ({ ...rack, group: groupKeyOf(rack.code), status: statusOf(rack) })),
        [racks],
    );

    const groups = useMemo(() => {
        const map = new Map<string, typeof enriched>();
        enriched.forEach((rack) => {
            const bucket = map.get(rack.group) ?? [];
            bucket.push(rack);
            map.set(rack.group, bucket);
        });

        return [...map.entries()]
            .map(([key, items]) => ({
                key,
                items,
                total: items.length,
                usage: items.reduce((sum, r) => sum + r.usage, 0),
                over: items.filter((r) => r.status === 'over').length,
                near: items.filter((r) => r.status === 'near').length,
                empty: items.filter((r) => r.usage === 0).length,
            }))
            .sort((a, b) => (a.key === '#' ? 1 : b.key === '#' ? -1 : a.key.localeCompare(b.key)));
    }, [enriched]);

    /**
     * Default tampilan: semua grup terlipat supaya halaman tidak panjang.
     * Hanya grup yang punya rak PENUH (>=100%) yang dibuka otomatis.
     */
    const criticalGroups = useMemo(
        () => new Set(groups.filter((g) => g.over > 0).map((g) => g.key)),
        [groups],
    );

    const keyword = search.trim().toLowerCase();
    const isFiltering = keyword !== '' || statusFilter !== 'all' || activeGroup !== 'all';

    const visibleGroups = useMemo(() => {
        return groups
            .filter((g) => activeGroup === 'all' || g.key === activeGroup)
            .map((g) => ({
                ...g,
                items: g.items.filter((rack) => {
                    if (keyword && !`${rack.code} ${rack.zone ?? ''}`.toLowerCase().includes(keyword)) return false;
                    if (statusFilter === 'over') return rack.status === 'over';
                    if (statusFilter === 'near') return rack.status === 'near';
                    if (statusFilter === 'filled') return rack.usage > 0;
                    if (statusFilter === 'empty') return rack.usage === 0;
                    return true;
                }),
            }))
            .filter((g) => g.items.length > 0);
    }, [groups, activeGroup, keyword, statusFilter]);

    // Buka ulang grup secara otomatis setiap filter berubah, supaya hasil pencarian langsung terlihat.
    useEffect(() => {
        setOverrides({});
    }, [search, statusFilter, activeGroup]);

    const isOpen = (key: string) => overrides[key] ?? (isFiltering || criticalGroups.has(key));
    const toggle = (key: string) => setOverrides((prev) => ({ ...prev, [key]: !isOpen(key) }));
    const allOpen = visibleGroups.length > 0 && visibleGroups.every((g) => isOpen(g.key));
    const setAllOpen = (open: boolean) =>
        setOverrides(() => Object.fromEntries(visibleGroups.map((g) => [g.key, open])));

    const visibleCount = visibleGroups.reduce((sum, g) => sum + g.items.length, 0);

    const statusChips: { key: StatusFilter; label: string; badge?: string }[] = [
        { key: 'all', label: 'Semua' },
        { key: 'over', label: 'Penuh', badge: rackFullCount > 0 ? String(rackFullCount) : undefined },
        { key: 'near', label: 'Hampir penuh', badge: rackNearFullCount > 0 ? String(rackNearFullCount) : undefined },
        { key: 'filled', label: 'Ada isi' },
        { key: 'empty', label: 'Kosong' },
    ];

    return (
        <div className="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 shadow-sm">
            <div className="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center flex-wrap gap-2">
                <h3 className="text-sm font-semibold text-gray-800 dark:text-white/90 flex items-center gap-2">
                    <Warehouse className="w-4 h-4 text-brand-500" />
                    Kapasitas Rak
                    <span className="text-xs font-normal text-gray-400 dark:text-gray-500">
                        {racks.length} rak · {groups.length} grup
                    </span>
                </h3>
                <div className="flex items-center gap-3 text-xs">
                    {rackFullCount > 0 && <span className="text-red-600 font-medium">⚠ {rackFullCount} penuh</span>}
                    {rackNearFullCount > 0 && <span className="text-orange-500 font-medium">{rackNearFullCount} hampir penuh</span>}
                    <Link href={route('racks.index')} className="text-brand-500 hover:text-brand-600 font-medium">
                        Semua Rak →
                    </Link>
                </div>
            </div>

            {racks.length === 0 ? (
                <div className="flex items-center justify-center gap-2 py-6 text-sm text-gray-400 dark:text-gray-500">
                    <Inbox className="w-4 h-4 shrink-0" />
                    <span>Belum ada data rak</span>
                </div>
            ) : (
                <>
                    {/* Toolbar: pencarian, filter status, grup */}
                    <div className="px-4 pt-4 flex flex-col lg:flex-row lg:items-center gap-3">
                        <div className="relative lg:w-64 shrink-0">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari kode atau zona rak…"
                                className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-8 py-2 text-sm text-gray-700 dark:text-gray-200 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none"
                            />
                            {search !== '' && (
                                <button
                                    type="button"
                                    onClick={() => setSearch('')}
                                    title="Bersihkan pencarian"
                                    className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-1.5">
                            {statusChips.map((chip) => (
                                <button
                                    key={chip.key}
                                    type="button"
                                    onClick={() => setStatusFilter(chip.key)}
                                    className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors ${
                                        statusFilter === chip.key
                                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    {chip.label}
                                    {chip.badge && (
                                        <span className="rounded-full bg-white/70 dark:bg-gray-900/60 px-1.5 tabular-nums">{chip.badge}</span>
                                    )}
                                </button>
                            ))}
                        </div>

                        <button
                            type="button"
                            onClick={() => setAllOpen(!allOpen)}
                            className="lg:ml-auto shrink-0 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            {allOpen ? 'Tutup semua grup' : 'Buka semua grup'}
                        </button>
                    </div>

                    {/* Grup rak */}
                    <div className="px-4 pt-3 flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => setActiveGroup('all')}
                            className={`rounded-lg px-2.5 py-1 text-xs font-semibold font-mono transition-colors ${
                                activeGroup === 'all'
                                    ? 'bg-brand-500 text-white'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                            }`}
                        >
                            ALL
                        </button>
                        {groups.map((group) => (
                            <button
                                key={group.key}
                                type="button"
                                onClick={() => setActiveGroup(activeGroup === group.key ? 'all' : group.key)}
                                title={`Grup ${group.key} — ${group.total} rak${group.over > 0 ? ` · ${group.over} penuh` : ''}`}
                                className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold font-mono transition-colors ${
                                    activeGroup === group.key
                                        ? 'bg-brand-500 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                                }`}
                            >
                                {group.key}
                                <span className={`tabular-nums ${activeGroup === group.key ? 'text-white/80' : 'text-gray-400'}`}>
                                    {group.total}
                                </span>
                                {group.over > 0 && <span className="text-red-500">•</span>}
                            </button>
                        ))}
                    </div>

                    {/* Daftar grup */}
                    <div className="p-4 space-y-2">
                        {visibleGroups.length === 0 ? (
                            <p className="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                Tidak ada rak yang cocok dengan pencarian
                            </p>
                        ) : (
                            visibleGroups.map((group) => {
                                const open = isOpen(group.key);
                                return (
                                    <div
                                        key={group.key}
                                        className="rounded-lg border border-gray-100 dark:border-gray-800 overflow-hidden"
                                    >
                                        <button
                                            type="button"
                                            onClick={() => toggle(group.key)}
                                            aria-expanded={open}
                                            className="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800/60 transition-colors"
                                        >
                                            <ChevronDown
                                                className={`w-4 h-4 shrink-0 text-gray-400 transition-transform ${open ? '' : '-rotate-90'}`}
                                            />
                                            <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-brand-50 dark:bg-brand-500/20 text-[11px] font-bold text-brand-700 dark:text-brand-300 font-mono">
                                                {group.key}
                                            </span>
                                            <span className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                Grup {group.key}
                                            </span>
                                            <span className="text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                                {group.items.length === group.total
                                                    ? `${group.total} rak`
                                                    : `${group.items.length} dari ${group.total} rak`}{' '}
                                                · {group.usage.toLocaleString()} unit
                                            </span>
                                            <span className="ml-auto flex items-center gap-2 text-[11px] font-medium">
                                                {group.over > 0 && (
                                                    <span className="rounded-full bg-red-100 dark:bg-red-500/20 px-2 py-0.5 text-red-700 dark:text-red-300">
                                                        {group.over} penuh
                                                    </span>
                                                )}
                                                {group.near > 0 && (
                                                    <span className="rounded-full bg-orange-100 dark:bg-orange-500/20 px-2 py-0.5 text-orange-700 dark:text-orange-300">
                                                        {group.near} hampir penuh
                                                    </span>
                                                )}
                                                {group.empty > 0 && (
                                                    <span className="hidden sm:inline text-gray-400 dark:text-gray-500">
                                                        {group.empty} kosong
                                                    </span>
                                                )}
                                            </span>
                                        </button>

                                        {open && (
                                            <div className="border-t border-gray-100 dark:border-gray-800 p-3">
                                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                                    {group.items.map((rack) => {
                                                        const hasCap = rack.capacity !== null && rack.capacity > 0;
                                                        const style = STATUS_STYLE[rack.status];
                                                        const sisa = hasCap ? (rack.capacity as number) - rack.usage : null;
                                                        return (
                                                            <Link
                                                                key={rack.id}
                                                                href={route('racks.show', rack.id)}
                                                                className={`block p-4 rounded-lg hover:shadow-md transition-all border ${style.card}`}
                                                            >
                                                                <div className="flex items-center justify-between mb-2">
                                                                    <div>
                                                                        <p className="text-sm font-semibold text-gray-800 dark:text-white/90 font-mono">
                                                                            {rack.code}
                                                                        </p>
                                                                        <p className="text-[11px] text-gray-500">{rack.zone || '-'}</p>
                                                                    </div>
                                                                    {hasCap ? (
                                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold tabular-nums ${style.badge}`}>
                                                                            {rack.pct}%
                                                                        </span>
                                                                    ) : (
                                                                        <span
                                                                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${style.badge}`}
                                                                            title="Rak tanpa kapasitas"
                                                                        >
                                                                            ∞
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                {hasCap ? (
                                                                    <>
                                                                        <div className="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2.5 overflow-hidden mb-1">
                                                                            <div
                                                                                className={`h-full rounded-full transition-all ${style.bar}`}
                                                                                style={{ width: `${Math.min(100, rack.pct ?? 0)}%` }}
                                                                            />
                                                                        </div>
                                                                        <div className="flex justify-between text-[11px]">
                                                                            <span className="text-gray-500 tabular-nums">
                                                                                {rack.usage.toLocaleString()} / {rack.capacity?.toLocaleString()} unit
                                                                            </span>
                                                                            {rack.status === 'over' ? (
                                                                                <span className="text-red-600 font-medium">⚠ Over!</span>
                                                                            ) : sisa !== null && sisa > 0 ? (
                                                                                <span className={`font-medium tabular-nums ${style.text}`}>
                                                                                    Sisa {sisa.toLocaleString()}
                                                                                </span>
                                                                            ) : (
                                                                                <span className="text-gray-400">Kosong</span>
                                                                            )}
                                                                        </div>
                                                                    </>
                                                                ) : (
                                                                    <p className={`text-[11px] tabular-nums ${rack.usage > 0 ? style.text : 'text-gray-400'}`}>
                                                                        {rack.usage.toLocaleString()} unit —{' '}
                                                                        <span className="italic">tanpa batas</span>
                                                                    </p>
                                                                )}
                                                            </Link>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {isFiltering && (
                        <div className="px-4 pb-3 text-[11px] text-gray-400 dark:text-gray-500">
                            Menampilkan {visibleCount} dari {racks.length} rak
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
