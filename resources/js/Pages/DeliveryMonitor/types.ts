export type SupplierStatus = 'live' | 'alert' | 'done' | 'standby';
export type ReceiptStatus = 'matched' | 'shortage' | 'pending' | 'over';

export interface Supplier {
    id: number;
    code: string;
    name: string;
    status: SupplierStatus;
}

export interface DeliveryCycle {
    supplierId: number;
    cycleNumber: number; // 1-6
    timeStart: string; // "07:30"
    timeEnd: string; // "09:30"
    planQty: number;
    actualQty: number;
}

export interface Part {
    id: number;
    partNumber: string;
    partName: string;
    category: string;
    supplierId: number;
}

export interface PartCycleReceipt {
    partId: number;
    cycleNumber: number; // 1-6
    planQty: number;
    receivedQty: number;
    status: ReceiptStatus;
}
