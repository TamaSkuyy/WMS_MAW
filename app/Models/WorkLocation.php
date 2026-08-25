<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkLocation extends Model
{
    use HasFactory;
    use AuditableBy;

    protected $fillable = ['name', 'created_by', 'updated_by'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
