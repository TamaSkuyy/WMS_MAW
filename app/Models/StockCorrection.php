<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'correctable_type',
        'correctable_id',
        'user_id',
        'reason',
        'before',
        'after',
        'deltas',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'deltas' => 'array',
        ];
    }

    public function correctable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
