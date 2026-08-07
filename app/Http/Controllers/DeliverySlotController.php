<?php

namespace App\Http\Controllers;

use App\Models\DeliverySlot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliverySlotController extends Controller
{
    /**
     * List the 6 fixed daily slots. There is no create/delete — the set of
     * slots is fixed; only their time windows and labels are editable.
     */
    public function index()
    {
        return Inertia::render('Master/DeliverySlots/Index', [
            'slots' => DeliverySlot::orderBy('slot_number')->get(),
        ]);
    }

    public function edit(DeliverySlot $deliverySlot)
    {
        abort_unless(auth()->user()->can('edit delivery slots'), 403);
        return Inertia::render('Master/DeliverySlots/Edit', [
            'slot' => $deliverySlot,
        ]);
    }

    public function update(Request $request, DeliverySlot $deliverySlot)
    {
        abort_unless(auth()->user()->can('edit delivery slots'), 403);
        $validated = $request->validate([
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
            'label' => 'nullable|string|max:255',
        ]);

        $deliverySlot->update($validated);

        return redirect()->route('delivery-slots.index')->with('success', 'Jadwal slot berhasil diupdate.');
    }
}
