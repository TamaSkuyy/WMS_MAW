<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\DeliverySlot;
use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\SupplierExporter;
use App\Services\ImportExport\Imports\SupplierImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new SupplierImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new SupplierExporter();
    }

    protected function exportFileName(): string
    {
        return 'suppliers-export';
    }

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request)
    {
        $suppliers = Supplier::with('primaryAddress')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Master/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create()
    {
        return Inertia::render('Master/Suppliers/Create');
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'required|email|unique:suppliers',
            'phone' => 'nullable|string|max:20',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
        ]);

        $supplier = Supplier::create([
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        $supplier->addresses()->create([
            'street' => $validated['street'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'postal_code' => $validated['postal_code'],
            'country' => $validated['country'],
            'address_type' => 'primary',
        ]);

        return redirect()->route('suppliers.show', $supplier)
                       ->with('success', 'Supplier created successfully.');
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier)
    {
        return Inertia::render('Master/Suppliers/Show', [
            'supplier' => $supplier->load('addresses'),
        ]);
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Supplier $supplier)
    {
        return Inertia::render('Master/Suppliers/Edit', [
            'supplier' => $supplier->load('primaryAddress'),
            'deliverySlots' => DeliverySlot::orderBy('slot_number')->get(['id', 'slot_number', 'time_start', 'time_end', 'label']),
            'scheduledSlotIds' => $supplier->scheduledSlots()->pluck('delivery_slots.id'),
        ]);
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name,' . $supplier->id,
            'contact_person' => 'nullable|string|max:255',
            'email' => 'required|email|unique:suppliers,email,' . $supplier->id,
            'phone' => 'nullable|string|max:20',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'delivery_slot_ids' => 'nullable|array',
            'delivery_slot_ids.*' => 'exists:delivery_slots,id',
        ]);

        $supplier->update([
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        $primaryAddress = $supplier->primaryAddress;
        if ($primaryAddress) {
            $primaryAddress->update([
                'street' => $validated['street'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
            ]);
        } else {
            $supplier->addresses()->create([
                'street' => $validated['street'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
                'address_type' => 'primary',
            ]);
        }

        $supplier->scheduledSlots()->sync($validated['delivery_slot_ids'] ?? []);

        return redirect()->route('suppliers.show', $supplier)
                       ->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')
                       ->with('success', 'Supplier deleted successfully.');
    }
}
