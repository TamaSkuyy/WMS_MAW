<?php

namespace App\Http\Controllers;

use App\Models\ShoppingLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShoppingLocationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Master/ShoppingLocations/Index', [
            'locations' => ShoppingLocation::orderBy('name')
                ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('create shopping locations'), 403);
        return Inertia::render('Master/ShoppingLocations/Create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create shopping locations'), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:shopping_locations',
        ]);

        ShoppingLocation::create($validated);

        return redirect()->route('shopping-locations.index')->with('success', 'Lokasi tujuan berhasil dibuat.');
    }

    public function edit(ShoppingLocation $shoppingLocation)
    {
        abort_unless(auth()->user()->can('edit shopping locations'), 403);
        return Inertia::render('Master/ShoppingLocations/Edit', [
            'location' => $shoppingLocation,
        ]);
    }

    public function update(Request $request, ShoppingLocation $shoppingLocation)
    {
        abort_unless(auth()->user()->can('edit shopping locations'), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:shopping_locations,name,' . $shoppingLocation->id,
        ]);

        $shoppingLocation->update($validated);

        return redirect()->route('shopping-locations.index')->with('success', 'Lokasi tujuan berhasil diupdate.');
    }

    public function destroy(ShoppingLocation $shoppingLocation)
    {
        abort_unless(auth()->user()->can('delete shopping locations'), 403);
        $shoppingLocation->delete();

        return redirect()->route('shopping-locations.index')->with('success', 'Lokasi tujuan berhasil dihapus.');
    }
}
