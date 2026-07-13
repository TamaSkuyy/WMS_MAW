<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shopping extends Model
{
    use HasFactory;

    protected $fillable = ['partner_name', 'shopping_date', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'shopping_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingItem::class);
    }
}
