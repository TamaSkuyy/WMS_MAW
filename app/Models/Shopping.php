<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Shopping extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['shopping_location_id', 'shopping_date', 'status', 'notes', 'frame_number'];

    protected function casts(): array
    {
        return [
            'shopping_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['shopping_location_id', 'shopping_date', 'status', 'notes', 'frame_number'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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
