<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'zone', 'capacity'];

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
