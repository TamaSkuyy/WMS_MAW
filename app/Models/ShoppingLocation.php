<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingLocation extends Model
{
    use HasFactory;
    use AuditableBy;

    protected $fillable = ['name', 'barcode', 'created_by', 'updated_by'];

    public function shoppings(): HasMany
    {
        return $this->hasMany(Shopping::class);
    }
}
