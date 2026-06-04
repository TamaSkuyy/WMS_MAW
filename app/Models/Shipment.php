<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = ['partner_name', 'shipment_date', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'shipment_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }
}
