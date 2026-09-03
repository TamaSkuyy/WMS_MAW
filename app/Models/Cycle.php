<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Cycle extends Model
{
    use AuditableBy;
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['supplier_id', 'carrier_id', 'created_by', 'updated_by', 'cycle_number', 'status', 'received_at', 'notes', 'delivery_date', 'delivery_slot_id'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'delivery_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['cycle_number', 'supplier_id', 'carrier_id', 'status', 'received_at', 'delivery_date', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * PIC yang membawa part / menangani cycle ini (kolom carrier_id).
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carrier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CycleItem::class);
    }

    public function corrections(): MorphMany
    {
        return $this->morphMany(StockCorrection::class, 'correctable');
    }

    public function deliverySlot(): BelongsTo
    {
        return $this->belongsTo(DeliverySlot::class);
    }
}
