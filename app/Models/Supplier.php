<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
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
}
