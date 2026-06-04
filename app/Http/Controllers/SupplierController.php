<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index()
    {
        $suppliers = Supplier::with('primaryAddress')
                             ->paginate(15);

        return Inertia::render('Master/Suppliers/Index', [
            'suppliers' => $suppliers,
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
