# Shopping Import dari Excel SharePoint — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fitur import Shopping dari file Excel SharePoint (upload manual): baris dikelompokkan per Frame Number jadi satu Shopping, merge ke draft yang sudah ada, filter Confirmed, part tak dikenal dilaporkan gagal per baris.

**Architecture:** `ShoppingImporter` baru mengikuti pola `CycleImporter` (grouping hirarkis), dijalankan via `ImportManager` (preview/import/template), route static sebelum parameterized, dan `ImportModal` diperluas dengan slot lokasi tujuan.

**Tech Stack:** Laravel + Inertia + React 18 + TypeScript + Tailwind CSS v4.

**Spec acuan:** `docs/superpowers/specs/2026-08-18-shopping-sharepoint-import-design.md`

## Global Constraints

- **RowTransformException HANYA dilempar di `transformRow()`** — `ProcessImport` hanya menangkap exception itu di `transformRow` (baris 75–77); exception di `isDuplicate`/`insertRow` akan menggagalkan seluruh import.
- **Tidak ada commit**: user yang commit sendiri. JANGAN jalankan `git commit`/`git add`.
- Queue testing = sync (`phpunit.xml`), sehingga job import berjalan langsung saat POST.
- Test memberi permission eksplisit (`Permission::findOrCreate('create shoppings')`) karena route memakai middleware permission.
- Route static `shoppings/import*` HARUS didaftarkan di dalam group "Create (static paths first)" — sebelum `shoppings/{shopping}`.
- Format tanggal kolom `Modify Date` = `d/m/Y H:i:s` (parse eksplisit, hindari ambiguitas m/d).
- `Confirmed` kosong → diimpor; hanya `FALSE`/`0`/`no` eksplisit yang di-skip.

---

### Task 1: TDD RED — tulis ShoppingImportExportTest

**Files:**
- Create: `tests/Feature/ImportExport/ShoppingImportExportTest.php`

**Interfaces:**
- Consumes: routes `shoppings.import.preview`, `shoppings.import`, `shoppings.import-template` (belum ada → RED).
- Produces: kontrak perilaku yang harus dipenuhi Task 2–3.

- [ ] **Step 1: Tulis file test**

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Product;
use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShoppingImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ShoppingLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findOrCreate('create shoppings'));
        $this->location = ShoppingLocation::create(['name' => 'Line A']);
    }

    private function sampleCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'shopping.csv',
            "Frame Number,Part Number,Quantity,Confirmed,Modify Date\n"
            . "MHKAA1BY4TJ021240,P5022-BYA03,1,TRUE,10/08/2026 21:18:09\n"
            . "MHKAA1BY4TJ021240,60118-TAD26,1,TRUE,10/08/2026 21:18:09\n"
            . "MHKAA1BY4TJ021240,21004-TAD26,1,FALSE,10/08/2026 21:18:08\n"
            . "MHK6GK6JTJ093724,P5634-BYA18,1,TRUE,10/08/2026 21:19:10"
        );
    }

    private function mapping(): array
    {
        return [
            'frame_number' => 'Frame Number',
            'part_number' => 'Part Number',
            'quantity' => 'Quantity',
            'confirmed' => 'Confirmed',
            'modify_date' => 'Modify Date',
        ];
    }

    public function test_import_template_downloads_successfully(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('shoppings.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_preview_returns_headers(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('shoppings.import.preview'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
        ]);

        $response->assertOk();
        $this->assertContains('Frame Number', $response->json('headers'));
    }

    public function test_import_creates_shoppings_grouped_by_frame(): void
    {
        Product::factory()->create(['part_number' => 'P5022-BYA03']);
        Product::factory()->create(['part_number' => '60118-TAD26']);
        Product::factory()->create(['part_number' => '21004-TAD26']);
        Product::factory()->create(['part_number' => 'P5634-BYA18']);
        $this->actingAs($this->user);

        $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $frame1 = Shopping::where('frame_number', 'MHKAA1BY4TJ021240')->first();
        $frame2 = Shopping::where('frame_number', 'MHK6GK6JTJ093724')->first();

        $this->assertNotNull($frame1);
        $this->assertNotNull($frame2);
        $this->assertSame('draft', $frame1->status);
        $this->assertSame($this->location->id, $frame1->shopping_location_id);
        $this->assertSame('2026-08-10', $frame1->shopping_date->format('Y-m-d'));
        $this->assertCount(2, $frame1->items); // baris FALSE di-skip
        $this->assertCount(1, $frame2->items);
    }

    public function test_import_skips_unknown_part_and_continues(): void
    {
        Product::factory()->create(['part_number' => 'P5022-BYA03']);
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->createWithContent(
            'shopping.csv',
            "Frame Number,Part Number,Quantity,Confirmed,Modify Date\n"
            . "MHKAA1BY4TJ021240,P5022-BYA03,1,TRUE,10/08/2026 21:18:09\n"
            . "MHKAA1BY4TJ021240,UNKNOWN-PART,1,TRUE,10/08/2026 21:18:09"
        );

        $this->post(route('shoppings.import'), [
            'file' => $file,
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $shopping = Shopping::where('frame_number', 'MHKAA1BY4TJ021240')->first();
        $this->assertNotNull($shopping);
        $this->assertCount(1, $shopping->items);
    }

    public function test_import_merges_into_existing_draft_frame(): void
    {
        $existing = Product::factory()->create(['part_number' => 'P5022-BYA03']);
        $newPart = Product::factory()->create(['part_number' => '60118-TAD26']);

        $shopping = Shopping::create([
            'shopping_location_id' => $this->location->id,
            'shopping_date' => now(),
            'frame_number' => 'MHKAA1BY4TJ021240',
            'status' => 'draft',
        ]);
        $shopping->items()->create(['product_id' => $existing->id, 'quantity' => 1]);
        $this->actingAs($this->user);

        $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $shopping->refresh();
        $this->assertCount(2, $shopping->items); // part lama tidak diduplikasi, part baru ditambah
        $this->assertTrue($shopping->items()->where('product_id', $newPart->id)->exists());
    }

    public function test_import_rejects_merge_into_shipped_frame(): void
    {
        $part = Product::factory()->create(['part_number' => 'P5022-BYA03']);
        Product::factory()->create(['part_number' => '60118-TAD26']);

        $shopping = Shopping::create([
            'shopping_location_id' => $this->location->id,
            'shopping_date' => now(),
            'frame_number' => 'MHKAA1BY4TJ021240',
            'status' => 'shipped',
        ]);
        $shopping->items()->create(['product_id' => $part->id, 'quantity' => 1]);
        $this->actingAs($this->user);

        $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'shopping_location_id' => $this->location->id,
            'column_mapping' => $this->mapping(),
        ])->assertOk();

        $shopping->refresh();
        $this->assertCount(1, $shopping->items); // tidak ada item baru
        $this->assertSame(2, Shopping::count()); // tidak ada frame baru untuk frame shipped; frame kedua tetap dibuat
    }

    public function test_import_requires_location(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('shoppings.import'), [
            'file' => $this->sampleCsv(),
            'column_mapping' => $this->mapping(),
        ]);

        $response->assertSessionHasErrors('shopping_location_id');
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan RED**

Run: `php artisan test tests/Feature/ImportExport/ShoppingImportExportTest.php`
Expected: FAIL/error — sebagian besar error `Route [shoppings.import...] not defined` (route belum ada), atau 404. Ini RED yang benar: fitur belum ada.

---

### Task 2: ShoppingImporter

**Files:**
- Create: `app/Services/ImportExport/Imports/ShoppingImporter.php`

**Interfaces:**
- Consumes: `BaseImporter` (`app/Services/ImportExport/Base/BaseImporter.php`), `Importable`, `RowTransformException`.
- Produces: `ShoppingImporter` dengan constructor `(int $shoppingLocationId)` — dipakai Task 3 (controller).

- [ ] **Step 1: Tulis importer**

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Product;
use App\Models\Shopping;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;
use App\Services\ImportExport\Exceptions\RowTransformException;
use Illuminate\Support\Carbon;

class ShoppingImporter extends BaseImporter implements Importable
{
    private ?string $currentFrameNumber = null;
    private ?Shopping $currentShopping = null;

    public function __construct(private int $shoppingLocationId) {}

    public function modelType(): string
    {
        return Shopping::class;
    }

    public function uniqueKey(): string|array
    {
        return 'frame_number';
    }

    public function rules(): array
    {
        return [
            'frame_number' => ['required', 'string', 'max:100'],
            'part_number' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'confirmed' => ['nullable', 'string'],
            'modify_date' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Frame Number', 'Part Number', 'Quantity', 'Confirmed', 'Modify Date'];
    }

    public function transformRow(array $mapped): array
    {
        // Hanya baris terkonfirmasi (kosong dianggap TRUE)
        $confirmed = strtolower(trim((string) ($mapped['confirmed'] ?? '')));
        if (in_array($confirmed, ['false', '0', 'no'], true)) {
            throw new RowTransformException("Baris tidak terkonfirmasi (Confirmed = {$mapped['confirmed']}).");
        }

        // Frame sudah dikirim → grup gagal (tidak bisa merge)
        $frame = (string) ($mapped['frame_number'] ?? '');
        if ($frame !== '') {
            $existing = Shopping::where('frame_number', $frame)->first();
            if ($existing && $existing->status !== 'draft') {
                throw new RowTransformException("Frame {$frame} sudah dikirim — tidak bisa digabung.");
            }
        }

        // Part number → product
        $productId = $this->resolveForeignKey(Product::class, 'part_number', $mapped['part_number'] ?? null, true);
        $mapped['product_id'] = $productId;

        // Modify Date (d/m/Y H:i:s) → shopping_date; kosong/gagal → hari ini
        $mapped['shopping_date'] = now();
        if (! empty($mapped['modify_date'])) {
            $parsed = Carbon::createFromFormat('d/m/Y H:i:s', trim((string) $mapped['modify_date']));
            if ($parsed !== false) {
                $mapped['shopping_date'] = $parsed;
            }
        }

        return $mapped;
    }

    public function isDuplicate(array $data): bool
    {
        $frame = (string) $data['frame_number'];

        // Masih grup frame yang sama — lanjut tambah item
        if ($frame === $this->currentFrameNumber) {
            return false;
        }

        // Merge ke frame draft yang sudah ada: part duplikat di-skip
        $existing = Shopping::where('frame_number', $frame)->first();
        if ($existing && $existing->status === 'draft'
            && $existing->items()->where('product_id', $data['product_id'])->exists()) {
            return true;
        }

        return false;
    }

    public function insertRow(array $data): void
    {
        $frame = (string) $data['frame_number'];

        if ($frame !== $this->currentFrameNumber) {
            $existing = Shopping::where('frame_number', $frame)->first();
            if ($existing) {
                $this->currentShopping = $existing; // merge ke draft yang sudah ada
            } else {
                $this->currentShopping = Shopping::create([
                    'shopping_location_id' => $this->shoppingLocationId,
                    'shopping_date' => $data['shopping_date'],
                    'frame_number' => $frame,
                    'status' => 'draft',
                ]);
            }
            $this->currentFrameNumber = $frame;
        }

        $product = Product::find($data['product_id']);

        $this->currentShopping->items()->create([
            'product_id' => $data['product_id'],
            'rack_id' => $product?->default_rack_id,
            'quantity' => $data['quantity'],
        ]);
    }
}
```

- [ ] **Step 2: Verifikasi sintaks**

Run: `php -l app/Services/ImportExport/Imports/ShoppingImporter.php`
Expected: `No syntax errors detected`.

---

### Task 3: Routes + ShoppingController (GREEN)

**Files:**
- Modify: `routes/web.php` (blok Shopping, sekitar baris 396–398)
- Modify: `app/Http/Controllers/ShoppingController.php`

**Interfaces:**
- Consumes: `ShoppingImporter` (Task 2), `ImportManager`, `ImportFormat`.

- [ ] **Step 1: Tambah route static di group Create**

Cari di `routes/web.php`:

```php
    // Create (static paths first)
    Route::middleware(PermissionMiddleware::using('create shoppings'))->group(function () {
        Route::get('shoppings/create', [ShoppingController::class, 'create'])->name('shoppings.create');
        Route::post('shoppings', [ShoppingController::class, 'store'])->name('shoppings.store');
    });
```

Ganti dengan:

```php
    // Create (static paths first)
    Route::middleware(PermissionMiddleware::using('create shoppings'))->group(function () {
        Route::get('shoppings/create', [ShoppingController::class, 'create'])->name('shoppings.create');
        Route::post('shoppings', [ShoppingController::class, 'store'])->name('shoppings.store');
        Route::post('shoppings/import/preview', [ShoppingController::class, 'importPreview'])->name('shoppings.import.preview');
        Route::post('shoppings/import', [ShoppingController::class, 'import'])->name('shoppings.import');
        Route::get('shoppings/import-template', [ShoppingController::class, 'importTemplate'])->name('shoppings.import-template');
    });
```

- [ ] **Step 2: Import di ShoppingController**

Cari baris import di bagian atas file:

```php
use App\Events\StockChanged;
use App\Models\Product;
```

Ganti dengan:

```php
use App\Events\StockChanged;
use App\Models\Product;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Imports\ShoppingImporter;
use App\Services\ImportExport\Managers\ImportManager;
```

- [ ] **Step 3: Tambah 3 method controller**

Cari method `private function mergeDuplicateItems(array $items): array` dan tambahkan TIGA method persis di atasnya:

```php
    public function importPreview(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $validated = $request->validate([
            'shopping_location_id' => 'required|exists:shopping_locations,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = app(ImportManager::class)->preview(
            new ShoppingImporter((int) $validated['shopping_location_id']),
            $request->file('file')
        );

        return response()->json($result);
    }

    public function import(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $validated = $request->validate([
            'shopping_location_id' => 'required|exists:shopping_locations,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
        ]);

        $importLog = app(ImportManager::class)->start(
            new ShoppingImporter((int) $validated['shopping_location_id']),
            $request->file('file'),
            $request->input('column_mapping'),
            auth()->id(),
        );

        return response()->json([
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }

    public function importTemplate(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $format = ImportFormat::from($request->query('format', 'xlsx'));

        return (new ShoppingImporter(0))->downloadTemplate($format);
    }

    private function mergeDuplicateItems(array $items): array
```

- [ ] **Step 4: Jalankan test — pastikan GREEN**

Run: `php artisan test tests/Feature/ImportExport/ShoppingImportExportTest.php`
Expected: semua 7 test PASS. Jika ada yang gagal, periksa pesan sebelum lanjut.

---

### Task 4: Ekstensi ImportModal

**Files:**
- Modify: `resources/js/Components/ImportExport/ImportModal.tsx`

**Interfaces:**
- Consumes: tidak ada.
- Produces: props opsional baru `extraNode?: React.ReactNode` dan `extraParams?: () => Record<string, string>` — dipakai Task 5.

- [ ] **Step 1: Tambah props**

Cari:

```tsx
interface ImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onComplete?: () => void;
  importUrl: string;
  previewUrl: string;
  templateUrl: string;
  fields: ImportField[];
  title: string;
}
```

Ganti dengan:

```tsx
interface ImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onComplete?: () => void;
  importUrl: string;
  previewUrl: string;
  templateUrl: string;
  fields: ImportField[];
  title: string;
  extraNode?: React.ReactNode;
  extraParams?: () => Record<string, string>;
}
```

- [ ] **Step 2: Destructure props**

Cari:

```tsx
export default function ImportModal({ isOpen, onClose, onComplete, importUrl, previewUrl, templateUrl, fields, title }: ImportModalProps) {
```

Ganti dengan:

```tsx
export default function ImportModal({ isOpen, onClose, onComplete, importUrl, previewUrl, templateUrl, fields, title, extraNode, extraParams }: ImportModalProps) {
```

- [ ] **Step 3: Append extraParams ke FormData (preview & import)**

Cari (di dalam `handleFileChange`, setelah `formData.append('file', f);`):

```tsx
    const formData = new FormData();
    formData.append('file', f);
```

Ganti dengan:

```tsx
    const formData = new FormData();
    formData.append('file', f);
    if (extraParams) {
      Object.entries(extraParams()).forEach(([key, value]) => formData.append(key, value));
    }
```

Cari (di dalam `handleImport`, setelah `formData.append('file', file);`):

```tsx
    const formData = new FormData();
    formData.append('file', file);
    Object.entries(columnMapping).forEach(([key, value]) => {
```

Ganti dengan:

```tsx
    const formData = new FormData();
    formData.append('file', file);
    if (extraParams) {
      Object.entries(extraParams()).forEach(([key, value]) => formData.append(key, value));
    }
    Object.entries(columnMapping).forEach(([key, value]) => {
```

- [ ] **Step 4: Render extraNode di step upload**

Cari (di dalam blok `step === 'upload'`):

```tsx
          {step === 'upload' && (
            <div>
              <div
                className="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-brand-500 transition-colors"
```

Ganti dengan:

```tsx
          {step === 'upload' && (
            <div>
              {extraNode && <div className="mb-4">{extraNode}</div>}
              <div
                className="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-brand-500 transition-colors"
```

- [ ] **Step 5: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 5: Wire Import di Shopping/Index.tsx

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Index.tsx`
- Modify: `app/Http/Controllers/ShoppingController.php` (index() tambah `shoppingLocations`)

**Interfaces:**
- Consumes: `ImportModal` dengan props baru (Task 4); prop halaman `shoppingLocations`.

- [ ] **Step 1: index() kirim shoppingLocations**

Cari di `ShoppingController::index()`:

```php
        return Inertia::render('Transactions/Shopping/Index', [
            'shoppings' => $shoppings,
            'filters' => $request->only(['status', 'search']),
        ]);
```

Ganti dengan:

```php
        return Inertia::render('Transactions/Shopping/Index', [
            'shoppings' => $shoppings,
            'filters' => $request->only(['status', 'search']),
            'shoppingLocations' => ShoppingLocation::orderBy('name')->get(),
        ]);
```

(Pastikan `use App\Models\ShoppingLocation;` sudah ada di atas file controller — tambahkan bila belum.)

- [ ] **Step 2: Import state & modal di Index.tsx**

Cari:

```tsx
export default function Index({ shoppings, filters }: any) {
```

Ganti dengan:

```tsx
export default function Index({ shoppings, filters, shoppingLocations = [] }: any) {
```

Cari:

```tsx
    const handleDelete = (id: number) => {
```

Ganti dengan:

```tsx
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [importLocationId, setImportLocationId] = useState('');

    const handleDelete = (id: number) => {
```

- [ ] **Step 3: Tambah import di baris import file**

Cari:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
```

Ganti dengan:

```tsx
import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import ImportModal from '../../../Components/ImportExport/ImportModal';
```

- [ ] **Step 4: Tombol Import di header**

Cari blok tombol (bagian `{canCreate && (<Link href={route('shoppings.create')}><Button>Tambah Shopping</Button></Link>)}` di dalam header):

```tsx
                    {canCreate && (
                        <Link href={route('shoppings.create')}><Button>Tambah Shopping</Button></Link>
                    )}
                </div>
```

Ganti dengan:

```tsx
                    <div className="flex flex-wrap items-center gap-2">
                        {canCreate && (
                            <Link href={route('shoppings.create')}><Button>Tambah Shopping</Button></Link>
                        )}
                        {canCreate && (
                            <Button variant="outline" onClick={() => setImportModalOpen(true)}>Import</Button>
                        )}
                    </div>
                </div>
```

- [ ] **Step 5: Render ImportModal (setelah ComponentCard, sebelum penutup fragment)**

Cari:

```tsx
                )}
            </ComponentCard>
        </>
    );
}
```

Ganti dengan:

```tsx
                )}
            </ComponentCard>

            {canCreate && (
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('shoppings.import')}
                    previewUrl={route('shoppings.import.preview')}
                    templateUrl={route('shoppings.import-template')}
                    title="Shopping"
                    fields={[
                        { key: 'frame_number', label: 'Frame Number', required: true },
                        { key: 'part_number', label: 'Part Number', required: true },
                        { key: 'quantity', label: 'Quantity', required: true },
                        { key: 'confirmed', label: 'Confirmed', required: false },
                        { key: 'modify_date', label: 'Modify Date', required: false },
                    ]}
                    extraNode={
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Lokasi Tujuan *</label>
                            <SearchableSelect
                                options={shoppingLocations.map((l: any) => ({ value: l.id, label: l.name }))}
                                value={importLocationId}
                                onChange={(v) => setImportLocationId(v as string)}
                                placeholder="Pilih lokasi tujuan..."
                            />
                        </div>
                    }
                    extraParams={() => ({ shopping_location_id: importLocationId })}
                />
            )}
        </>
    );
}
```

Catatan: `SearchableSelect` sudah diimpor di Index.tsx (dipakai filter status) ✓. Tombol "Start Import" di modal akan gagal dengan pesan error validasi bila lokasi belum dipilih (controller memvalidasi) — perilaku yang dapat diterima.

- [ ] **Step 6: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 6: Verifikasi akhir keseluruhan

**Files:** (tidak ada perubahan kode)

- [ ] **Step 1: Test import hijau**

Run: `php artisan test tests/Feature/ImportExport/ShoppingImportExportTest.php`
Expected: 7/7 PASS.

- [ ] **Step 2: Build bersih**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

- [ ] **Step 3: Tes manual (diserahkan ke user)**

1. Buka Shopping Index → klik **Import** → pilih Lokasi Tujuan → upload file Excel/CSV sample.
2. Cek daftar Shopping: satu Shopping per Frame Number, item sesuai, tanggal dari Modify Date.
3. Ulangi import file yang sama → item frame draft bertambah tanpa duplikat part.
4. Import frame yang sudah shipped → baris gagal dengan pesan error (cek di riwayat import).
5. Download template → kolom Frame Number, Part Number, Quantity, Confirmed, Modify Date.
