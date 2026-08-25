<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Otomatis mengisi created_by / updated_by dari user yang sedang login
 * saat record dibuat atau diubah.
 *
 * - Nilai yang sudah di-set secara eksplisit (mis. dari importer via
 *   fixedFields()) tidak akan ditimpa saat creating.
 * - Saat dijalankan di luar konteks request (queue job, seeder, CLI),
 *   Auth::id() bernilai null sehingga kolom dibiarkan null kecuali
 *   di-set eksplisit oleh pemanggil.
 */
trait AuditableBy
{
    public static function bootAuditableBy(): void
    {
        static::creating(function ($model) {
            $userId = Auth::id();

            if (! $userId) {
                return;
            }

            if (! array_key_exists('created_by', $model->getAttributes())) {
                $model->created_by = $userId;
            }

            if (! array_key_exists('updated_by', $model->getAttributes())) {
                $model->updated_by = $userId;
            }
        });

        static::updating(function ($model) {
            if ($userId = Auth::id()) {
                $model->updated_by = $userId;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
