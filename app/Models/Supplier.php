<?php

namespace App\Models;

use App\Support\SupplierCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all addresses for this supplier
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    /**
     * Get the primary address for this supplier
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(SupplierAddress::class)
                    ->where('address_type', 'primary');
    }

    /**
     * Get all products supplied by this supplier.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    public function deliverySchedules(): HasMany
    {
        return $this->hasMany(SupplierDeliverySchedule::class);
    }

    public function scheduledSlots(): BelongsToMany
    {
        return $this->belongsToMany(DeliverySlot::class, 'supplier_delivery_schedules')->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier) {
            if (empty($supplier->code)) {
                $existingCodes = static::pluck('code')->filter()->all();
                $supplier->code = SupplierCodeGenerator::generate($supplier->name, $existingCodes);
            }
        });
    }
}
