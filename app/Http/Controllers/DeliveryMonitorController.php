<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DeliveryMonitorController extends Controller
{
    public function index()
    {
        return Inertia::render('DeliveryMonitor/Index');
    }
}
