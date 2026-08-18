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
    private ?int $shoppingLocationId = null;
    private bool $canMerge = false;

    /** Job ProcessImport menginstansiasi ulang importer tanpa argumen — lokasi & izin diinjeksi via setContext(). */
    public function __construct(?int $shoppingLocationId = null)
    {
        $this->shoppingLocationId = $shoppingLocationId;
    }

    public function setContext(array $params): void
    {
        $this->shoppingLocationId = (int) ($params['shopping_location_id'] ?? 0) ?: null;
        $this->canMerge = (bool) ($params['can_merge'] ?? false);
    }

    public function contextParams(): array
    {
        return [
            'shopping_location_id' => $this->shoppingLocationId ?? 0,
            // Merge ke draft yang sudah ada = aksi edit — hanya untuk user dengan izin edit shoppings.
            'can_merge' => auth()->check() && auth()->user()->can('edit shoppings') ? 1 : 0,
        ];
    }

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
            'confirmed' => ['nullable'],
            'modify_date' => ['nullable'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Frame Number', 'Part Number', 'Quantity', 'Confirmed', 'Modify Date'];
    }

    public function transformRow(array $mapped): array
    {
        // Lokasi tujuan wajib tersedia sebelum memproses baris
        if (! $this->shoppingLocationId) {
            throw new RowTransformException('Lokasi tujuan tidak tersedia untuk import ini.');
        }

        // Hanya baris terkonfirmasi (kosong dianggap TRUE; Excel memparse TRUE/FALSE jadi boolean)
        $rawConfirmed = $mapped['confirmed'] ?? '';
        $confirmed = is_bool($rawConfirmed)
            ? ($rawConfirmed ? 'true' : 'false')
            : strtolower(trim((string) $rawConfirmed));
        if (in_array($confirmed, ['false', '0', 'no'], true)) {
            throw new RowTransformException("Baris tidak terkonfirmasi (Confirmed = {$confirmed}).");
        }

        // Frame sudah ada di sistem
        $frame = (string) ($mapped['frame_number'] ?? '');
        if ($frame !== '') {
            $existing = Shopping::where('frame_number', $frame)->first();
            if ($existing && $existing->status !== 'draft') {
                throw new RowTransformException("Frame {$frame} sudah dikirim — tidak bisa digabung.");
            }
            if ($existing && ! $this->canMerge) {
                throw new RowTransformException("Frame {$frame} sudah ada — tidak punya izin edit untuk menggabung.");
            }
        }

        // Part number → product
        $productId = $this->resolveForeignKey(Product::class, 'part_number', $mapped['part_number'] ?? null, true);
        $mapped['product_id'] = $productId;

        // Modify Date → shopping_date; kosong/gagal → hari ini
        // (Excel dapat memparse sel tanggal jadi objek Carbon)
        $mapped['shopping_date'] = now();
        $rawDate = $mapped['modify_date'] ?? null;
        if ($rawDate instanceof \Carbon\CarbonInterface) {
            $mapped['shopping_date'] = $rawDate;
        } elseif (is_string($rawDate) && trim($rawDate) !== '') {
            $parsed = Carbon::createFromFormat('d/m/Y H:i:s', trim($rawDate));
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
