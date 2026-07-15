<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeliverySlot extends Model
{
    use HasFactory;

    protected $fillable = ['slot_number', 'time_start', 'time_end', 'label'];

    public function scheduledSuppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_delivery_schedules')->withTimestamps();
    }

    public static function currentForTime(Carbon $time): ?self
    {
        $slots = static::orderBy('slot_number')->get();

        if ($slots->isEmpty()) {
            return null;
        }

        $nowTime = $time->format('H:i:s');

        foreach ($slots as $slot) {
            if ($nowTime >= $slot->time_start && $nowTime < $slot->time_end) {
                return $slot;
            }
        }

        return $nowTime < $slots->first()->time_start
            ? $slots->first()
            : $slots->last();
    }
}
