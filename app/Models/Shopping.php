<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shopping extends Model
{
    use HasFactory;

    protected $fillable = ['shopping_location_id', 'shopping_date', 'status', 'notes', 'frame_number'];

    protected function casts(): array
    {
        return [
            'shopping_date' => 'date',
        ];
    }

    public function shoppingLocation(): BelongsTo
    {
        return $this->belongsTo(ShoppingLocation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingItem::class);
    }
}
