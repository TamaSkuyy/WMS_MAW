<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends Model
{
    use HasFactory;
    use AuditableBy;

    protected $fillable = ['code', 'zone', 'capacity', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
