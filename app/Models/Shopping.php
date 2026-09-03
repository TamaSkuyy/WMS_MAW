<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Shopping extends Model
{
    use HasFactory;
    use LogsActivity;
    use AuditableBy;

    protected $fillable = ['shopping_location_id', 'shopping_date', 'status', 'is_cripple', 'notes', 'frame_number', 'shipped_by', 'shipped_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'shopping_date' => 'date',
            'is_cripple' => 'boolean',
            'shipped_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['shopping_location_id', 'shopping_date', 'status', 'is_cripple', 'notes', 'frame_number', 'shipped_by', 'shipped_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function shoppingLocation(): BelongsTo
    {
        return $this->belongsTo(ShoppingLocation::class);
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingItem::class);
    }

    public function corrections(): MorphMany
    {
        return $this->morphMany(StockCorrection::class, 'correctable');
    }
}
