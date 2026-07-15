export interface SlotWindow {
    cycleNumber: number;
    timeStart: string;
    timeEnd: string;
}

export function getCurrentCycleNumber(now: Date, slots: SlotWindow[]): number {
    if (slots.length === 0) return 1;

    const minutesNow = now.getHours() * 60 + now.getMinutes();
    const toMinutes = (hhmm: string) => {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + m;
    };

    const sorted = [...slots].sort((a, b) => a.cycleNumber - b.cycleNumber);

    for (const window of sorted) {
        if (minutesNow >= toMinutes(window.timeStart) && minutesNow < toMinutes(window.timeEnd)) {
            return window.cycleNumber;
        }
    }

    if (minutesNow < toMinutes(sorted[0].timeStart)) return sorted[0].cycleNumber;
    return sorted[sorted.length - 1].cycleNumber;
}
