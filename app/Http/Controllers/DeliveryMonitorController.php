<?php

namespace App\Http\Controllers;

use App\Services\DeliveryMonitor\DeliveryMonitorSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryMonitorController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : now();

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build($date);

        return Inertia::render('DeliveryMonitor/Index', array_merge($snapshot, [
            'selectedDate' => $date->toDateString(),
        ]));
    }
}
