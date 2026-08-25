<?php

namespace App\Models;

use App\Models\Concerns\AuditableBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosition extends Model
{
    use HasFactory;
    use AuditableBy;

    protected $fillable = ['name', 'level', 'role_name', 'created_by', 'updated_by'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all valid role names for dropdown options.
     */
    public static function roleOptions(): array
    {
        return \Spatie\Permission\Models\Role::pluck('name')->toArray();
    }
}
