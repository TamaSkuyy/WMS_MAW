<?php

namespace Tests\Feature;

use App\Http\Controllers\StockOpnameController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Regresi parsing file hasil opname (header "Part No | Part Name | RAK | SAP | Qty Opname").
 *
 * Melindungi dua perbaikan:
 *  1. Normalisasi header harus menurunkan huruf KAPITAL dulu sebelum membuang
 *     karakter non-alphanumerik (sebelumnya "Part No" → "arto", "RAK" → "").
 *  2. Delimiter CSV tidak boleh dipaksa koma — file TSV / titik-koma harus
 *     terbaca (auto-detect).
 */
class StockOpnameParseTest extends TestCase
{
    /** @return array<int, array{part_number:string, rack_code:string, actual_qty:int|null}> */
    private function parseThroughController(string $fileName, string $content): array
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent($fileName, $content);

        $method = new \ReflectionMethod(StockOpnameController::class, 'parseUploadedFile');
        $method->setAccessible(true);

        return $method->invoke(new StockOpnameController(), $file);
    }

    /** Bytes xlsx dengan header & satu baris persis seperti template aplikasi. */
    private function templateXlsxBytes(): string
    {
        $export = new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function headings(): array
            {
                return ['Part No', 'Part Name', 'RAK', 'SAP', 'Qty Opname'];
            }

            public function array(): array
            {
                return [['ABC-001', 'Part Satu', 'A-01', 5, 3]];
            }
        };

        Excel::store($export, 'opname-template.xlsx', 'local');

        return Storage::disk('local')->get('opname-template.xlsx');
    }

    public function test_template_xlsx_headers_are_recognized(): void
    {
        $rows = $this->parseThroughController('hasil-opname.xlsx', $this->templateXlsxBytes());

        $this->assertCount(1, $rows);
        $this->assertSame('ABC-001', $rows[0]['part_number']);
        $this->assertSame('A-01', $rows[0]['rack_code']);
        $this->assertSame(3, $rows[0]['actual_qty']);
    }

    public function test_tab_delimited_csv_is_recognized(): void
    {
        $content = "Part No\tPart Name\tRAK\tSAP\tQty Opname\n"
            . "ABC-002\tPart Dua\tB-02\t8\t6\n";

        $rows = $this->parseThroughController('hasil-opname.csv', $content);

        $this->assertCount(1, $rows);
        $this->assertSame('ABC-002', $rows[0]['part_number']);
        $this->assertSame('B-02', $rows[0]['rack_code']);
        $this->assertSame(6, $rows[0]['actual_qty']);
    }

    public function test_comma_csv_with_template_headers_is_recognized(): void
    {
        $content = "Part No,Part Name,RAK,SAP,Qty Opname\n"
            . "ABC-003,Part Tiga,C-03,2,2\n";

        $rows = $this->parseThroughController('hasil-opname.csv', $content);

        $this->assertCount(1, $rows);
        $this->assertSame('ABC-003', $rows[0]['part_number']);
        $this->assertSame('C-03', $rows[0]['rack_code']);
        $this->assertSame(2, $rows[0]['actual_qty']);
    }

    public function test_missing_part_no_column_is_rejected(): void
    {
        $content = "Kode,Part Name,RAK,SAP,Qty Opname\n"
            . "ABC-004,Part Empat,D-04,1,1\n";

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Kolom "Part No" tidak ditemukan');

        $this->parseThroughController('hasil-opname.csv', $content);
    }
}
