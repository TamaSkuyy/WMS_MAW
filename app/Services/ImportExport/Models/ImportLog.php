<?php

namespace App\Services\ImportExport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    use HasFactory;

    protected $table = 'import_logs';

    protected $fillable = [
        'user_id',
        'model_type',
        'file_name',
        'file_format',
        'status',
        'column_mapping',
        'total_rows',
        'processed_rows',
        'skipped_rows',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'errors' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'skipped_rows' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\ImportLogFactory::new();
    }
}
