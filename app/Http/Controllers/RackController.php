<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RackController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Master/Racks/Index', [
            'racks' => Rack::orderBy('zone')->orderBy('code')
                ->when($request->search, function ($query, $search) {
                    $query->where('code', 'like', "%{$search}%")
                          ->orWhere('zone', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/Racks/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:racks',
            'zone' => 'required|string|max:50',
        ]);
        Rack::create($validated);
        return redirect()->route('racks.index')->with('success', 'Rack created.');
    }

    public function show(Rack $rack)
    {
        return Inertia::render('Master/Racks/Show', [
            'rack' => $rack->load('stocks.product'),
        ]);
    }

    public function edit(Rack $rack)
    {
        return Inertia::render('Master/Racks/Edit', ['rack' => $rack]);
    }

    public function update(Request $request, Rack $rack)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:racks,code,' . $rack->id,
            'zone' => 'required|string|max:50',
        ]);
        $rack->update($validated);
        return redirect()->route('racks.show', $rack)->with('success', 'Rack updated.');
    }

    public function destroy(Rack $rack)
    {
        $rack->delete();
        return redirect()->route('racks.index')->with('success', 'Rack deleted.');
    }
}
