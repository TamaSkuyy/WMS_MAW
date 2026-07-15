import { PartCycleReceipt } from '../types';

export function calculateOtifPercent(receipts: PartCycleReceipt[]): number {
    const totalPlan = receipts.reduce((sum, r) => sum + r.planQty, 0);
    const totalMatched = receipts.filter((r) => r.status === 'matched').reduce((sum, r) => sum + r.receivedQty, 0);
    return totalPlan > 0 ? Math.round((totalMatched / totalPlan) * 1000) / 10 : 0;
}
