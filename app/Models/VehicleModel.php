<?php

namespace App\Models;

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

    /**
     * Get all products for this vehicle model.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
