<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAddress extends Model
{
    use HasFactory;
    use AuditableBy;

    protected $table = 'supplier_addresses';

    protected $fillable = [
        'supplier_id',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'address_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the supplier that owns this address
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
