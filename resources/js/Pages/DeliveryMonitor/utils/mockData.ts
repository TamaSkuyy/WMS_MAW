import { Supplier, DeliveryCycle, Part, PartCycleReceipt, SupplierStatus, ReceiptStatus } from '../types';

// Deterministic PRNG (mulberry32) so mock data stays stable across re-renders/reloads
// instead of reshuffling every time this module runs.
function mulberry32(seed: number) {
    return function random() {
        seed |= 0;
        seed = (seed + 0x6d2b79f5) | 0;
        let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const rand = mulberry32(20260714);

function pick<T>(arr: T[]): T {
    return arr[Math.floor(rand() * arr.length)];
}

function randInt(min: number, max: number): number {
    return Math.floor(rand() * (max - min + 1)) + min;
}

function shuffled<T>(arr: T[]): T[] {
    return [...arr]
        .map((item) => ({ item, sort: rand() }))
        .sort((a, b) => a.sort - b.sort)
        .map(({ item }) => item);
}

export const CYCLE_WINDOWS: { cycleNumber: number; timeStart: string; timeEnd: string }[] = [
    { cycleNumber: 1, timeStart: '07:30', timeEnd: '09:30' },
    { cycleNumber: 2, timeStart: '09:30', timeEnd: '11:30' },
    { cycleNumber: 3, timeStart: '11:30', timeEnd: '13:30' },
    { cycleNumber: 4, timeStart: '13:30', timeEnd: '15:30' },
    { cycleNumber: 5, timeStart: '15:30', timeEnd: '17:30' },
    { cycleNumber: 6, timeStart: '17:30', timeEnd: '19:30' },
];

// Determines which of the 6 daily cycles is "current" based on wall-clock time,
// so the dashboard reflects today's active cycle automatically as time passes.
// Clamps to the first/last cycle outside the 07:30-19:30 operating window.
export function getCurrentCycleNumber(now: Date): number {
    const minutesNow = now.getHours() * 60 + now.getMinutes();
    const toMinutes = (hhmm: string) => {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + m;
    };

    for (const window of CYCLE_WINDOWS) {
        if (minutesNow >= toMinutes(window.timeStart) && minutesNow < toMinutes(window.timeEnd)) {
            return window.cycleNumber;
        }
    }

    if (minutesNow < toMinutes(CYCLE_WINDOWS[0].timeStart)) return CYCLE_WINDOWS[0].cycleNumber;
    return CYCLE_WINDOWS[CYCLE_WINDOWS.length - 1].cycleNumber;
}

const COMPANY_WORDS = [
    'ADI', 'AJI', 'JAYA', 'MAKMUR', 'SEJAHTERA', 'MANDIRI', 'UTAMA', 'SENTOSA',
    'ABADI', 'PERSADA', 'SAKTI', 'PERKASA', 'GEMILANG', 'PRATAMA', 'CIPTA',
    'KARYA', 'MULIA', 'BERKAH', 'SUKSES', 'MITRA',
];

const CATEGORIES = [
    'Body Parts', 'Engine Parts', 'Electrical', 'Interior', 'Suspension',
    'Brake System', 'Transmission', 'Exhaust System', 'Cooling System', 'Fuel System',
];

const PART_TYPES: Record<string, string[]> = {
    'Body Parts': ['Bumper', 'Fender', 'Door Panel', 'Hood', 'Grille', 'Mirror Housing', 'Emblem'],
    'Engine Parts': ['Piston', 'Camshaft', 'Cylinder Head', 'Timing Belt', 'Oil Filter', 'Spark Plug'],
    Electrical: ['Wiring Harness', 'ECU Module', 'Headlamp Assembly', 'Tail Lamp', 'Relay', 'Fuse Box'],
    Interior: ['Seat Cover', 'Dashboard Panel', 'Door Trim', 'Carpet Set', 'Steering Wheel'],
    Suspension: ['Shock Absorber', 'Coil Spring', 'Control Arm', 'Stabilizer Link', 'Ball Joint'],
    'Brake System': ['Brake Pad', 'Brake Disc', 'Brake Caliper', 'Master Cylinder', 'Brake Hose'],
    Transmission: ['Clutch Kit', 'Gear Set', 'CV Joint', 'Drive Shaft', 'Transmission Mount'],
    'Exhaust System': ['Muffler', 'Catalytic Converter', 'Exhaust Pipe', 'Exhaust Manifold'],
    'Cooling System': ['Radiator', 'Water Pump', 'Thermostat', 'Cooling Fan', 'Radiator Hose'],
    'Fuel System': ['Fuel Pump', 'Fuel Injector', 'Fuel Filter', 'Fuel Tank', 'Fuel Line'],
};

// Weighted so most suppliers are actively delivering, a minority are in each edge state.
const SUPPLIER_STATUS_POOL: SupplierStatus[] = [
    'live', 'live', 'live', 'live',
    'alert', 'alert',
    'done', 'done', 'done', 'done',
    'standby', 'standby',
];

function buildSupplierName(usedCodes: Set<string>): { code: string; name: string } {
    for (let attempt = 0; attempt < 30; attempt++) {
        const wordCount = randInt(2, 3);
        const words: string[] = [];
        while (words.length < wordCount) {
            const w = pick(COMPANY_WORDS);
            if (!words.includes(w)) words.push(w);
        }
        const code = words.map((w) => w[0]).join('');
        if (!usedCodes.has(code)) {
            usedCodes.add(code);
            return { code, name: `PT. ${words.join(' ')}` };
        }
    }
    // Extremely unlikely fallback: append a numeric suffix to guarantee uniqueness.
    const fallbackCode = `X${usedCodes.size}`;
    usedCodes.add(fallbackCode);
    return { code: fallbackCode, name: `PT. ${pick(COMPANY_WORDS)} ${pick(COMPANY_WORDS)}` };
}

export interface MockData {
    suppliers: Supplier[];
    cycles: DeliveryCycle[];
    parts: Part[];
    receipts: PartCycleReceipt[];
    todayOtifPercent: number;
}

const SUPPLIER_COUNT = 30;
const TOTAL_PARTS = 1650;
const PARTS_PER_SUPPLIER = TOTAL_PARTS / SUPPLIER_COUNT; // 55, divides evenly

export function generateMockData(): MockData {
    // --- Suppliers ---
    const usedCodes = new Set<string>();
    const suppliers: Supplier[] = [];
    for (let i = 1; i <= SUPPLIER_COUNT; i++) {
        const { code, name } = buildSupplierName(usedCodes);
        suppliers.push({ id: i, code, name, status: pick(SUPPLIER_STATUS_POOL) });
    }

    // --- Delivery cycles (today's schedule per supplier) ---
    // Standby suppliers have little/no schedule today; everyone else has a partial-to-full C1-C6 schedule.
    const cycles: DeliveryCycle[] = [];
    for (const supplier of suppliers) {
        const scheduledCount = supplier.status === 'standby' ? randInt(0, 1) : randInt(2, 6);
        const scheduledWindows = shuffled(CYCLE_WINDOWS).slice(0, scheduledCount);

        for (const window of scheduledWindows) {
            const planQty = randInt(50, 800);
            let actualQty: number;
            switch (supplier.status) {
                case 'done':
                    actualQty = planQty;
                    break;
                case 'alert':
                    actualQty = Math.floor(planQty * (randInt(40, 85) / 100));
                    break;
                case 'live':
                    actualQty = Math.floor(planQty * (randInt(0, 95) / 100));
                    break;
                default:
                    actualQty = 0;
            }

            cycles.push({
                supplierId: supplier.id,
                cycleNumber: window.cycleNumber,
                timeStart: window.timeStart,
                timeEnd: window.timeEnd,
                planQty,
                actualQty,
            });
        }
    }

    // --- Parts (55 per supplier, 30 x 55 = 1650) ---
    const parts: Part[] = [];
    let partId = 1;
    for (const supplier of suppliers) {
        for (let j = 0; j < PARTS_PER_SUPPLIER; j++) {
            const category = pick(CATEGORIES);
            const partType = pick(PART_TYPES[category]);
            parts.push({
                id: partId,
                partNumber: `${supplier.code}-${category.slice(0, 2).toUpperCase()}${String(partId).padStart(4, '0')}`,
                partName: partType,
                category,
                supplierId: supplier.id,
            });
            partId++;
        }
    }

    // --- Part receipts (one per part per cycle scheduled for its supplier) ---
    const cyclesBySupplier = new Map<number, DeliveryCycle[]>();
    for (const cycle of cycles) {
        const list = cyclesBySupplier.get(cycle.supplierId) ?? [];
        list.push(cycle);
        cyclesBySupplier.set(cycle.supplierId, list);
    }

    const receipts: PartCycleReceipt[] = [];
    for (const part of parts) {
        const supplierCycles = cyclesBySupplier.get(part.supplierId) ?? [];
        for (const cycle of supplierCycles) {
            const planQty = randInt(5, 60);
            const roll = rand();
            let status: ReceiptStatus;
            let receivedQty: number;

            if (roll < 0.55) {
                status = 'matched';
                receivedQty = planQty;
            } else if (roll < 0.75) {
                status = 'shortage';
                receivedQty = Math.max(0, planQty - randInt(1, Math.min(planQty, 15)));
            } else if (roll < 0.9) {
                status = 'pending';
                receivedQty = 0;
            } else {
                status = 'over';
                receivedQty = planQty + randInt(1, 10);
            }

            receipts.push({ partId: part.id, cycleNumber: cycle.cycleNumber, planQty, receivedQty, status });
        }
    }

    return { suppliers, cycles, parts, receipts, todayOtifPercent: calculateOtifPercent(receipts) };
}

// Matched (on-time, in-full) qty vs total planned qty across a set of receipts.
// Extracted so callers can recompute this live (e.g. after "Reset Receipts") using
// the exact same formula the initial generation used.
export function calculateOtifPercent(receipts: PartCycleReceipt[]): number {
    const totalPlan = receipts.reduce((sum, r) => sum + r.planQty, 0);
    const totalMatched = receipts.filter((r) => r.status === 'matched').reduce((sum, r) => sum + r.receivedQty, 0);
    return totalPlan > 0 ? Math.round((totalMatched / totalPlan) * 1000) / 10 : 0;
}
