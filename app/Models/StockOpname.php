<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'opname_date',
        'zone',
        'rack_id',
        'notes',
        'total_items',
        'diff_items',
        'new_items',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'total_items' => 'integer',
            'diff_items' => 'integer',
            'new_items' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
