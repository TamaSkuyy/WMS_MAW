<?php

namespace App\Services\StockOpname;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Stock Opname (xlsx):
 *   Part No | Part Name | RAK | SAP | Qty Opname
 *
 * Baris data terisi dari stok sistem saat ini (kolom SAP = qty sistem).
 * Kolom "Qty Opname" (E) dikosongkan — diisi hasil hitung fisik di lapangan.
 */
class StockOpnameTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * @param  array<int, array{0:string, 1:string, 2:string, 3:int}>  $rows
     */
    public function __construct(private array $rows) {}

    public function headings(): array
    {
        return ['Part No', 'Part Name', 'RAK', 'SAP', 'Qty Opname'];
    }

    public function array(): array
    {
        // 4 kolom pertama terisi; kolom "Qty Opname" dibiarkan kosong untuk diisi
        return array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[3], null], $this->rows);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            // Sorot header kolom "Qty Opname" (E1) supaya jelas mana yang diisi
            'E1' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF3BF'],
                ],
            ],
        ];
    }
}
