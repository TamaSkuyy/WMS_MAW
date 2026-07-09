<?php

namespace App\Http\Controllers;

use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Support\Facades\Auth;

class ImportStatusController extends Controller
{
    public function show(ImportLog $importLog)
    {
        if ($importLog->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json([
            'id' => $importLog->id,
            'status' => $importLog->status,
            'total_rows' => $importLog->total_rows,
            'processed_rows' => $importLog->processed_rows,
            'skipped_rows' => $importLog->skipped_rows,
            'errors' => $importLog->errors,
        ]);
    }
}
