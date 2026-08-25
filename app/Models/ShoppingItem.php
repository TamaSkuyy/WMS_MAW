<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingItem extends Model
{
    use HasFactory;
    use AuditableBy;

    protected $fillable = ['shopping_id', 'product_id', 'rack_id', 'quantity', 'created_by', 'updated_by'];

    public function shopping(): BelongsTo
    {
        return $this->belongsTo(Shopping::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
