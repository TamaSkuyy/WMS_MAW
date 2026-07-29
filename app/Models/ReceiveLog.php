<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiveLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['cycle_item_id', 'quantity', 'rack_id', 'user_id', 'notes', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function cycleItem(): BelongsTo
    {
        return $this->belongsTo(CycleItem::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
