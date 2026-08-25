<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CycleItem extends Model
{
    use HasFactory;
    use AuditableBy;

    protected $fillable = ['cycle_id', 'product_id', 'quantity', 'received_quantity', 'rack_id', 'notes', 'created_by', 'updated_by'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function receiveLogs(): HasMany
    {
        return $this->hasMany(ReceiveLog::class);
    }

    public function latestReceiveLog(): HasOne
    {
        return $this->hasOne(ReceiveLog::class)->latestOfMany('created_at');
    }
}
