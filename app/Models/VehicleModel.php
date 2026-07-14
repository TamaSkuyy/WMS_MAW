<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'suffix',
    ];

    protected function suffix(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? strtoupper($value) : null,
        );
    }

    /**
     * Get all products for this vehicle model.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
