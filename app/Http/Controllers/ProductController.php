<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $products = Product::with(['vehicleModel', 'supplier', 'category'])
            ->when($request->search, function ($query, $search) {
                $query->where('part_number', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->supplier_id, function ($query, $supplierId) {
                $query->where('supplier_id', $supplierId);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Master/Products/Index', [
            'products' => $products,
            'categories' => ProductCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'filters' => $request->only(['search', 'category_id', 'supplier_id']),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        return Inertia::render('Master/Products/Create', [
            'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_number' => 'required|string|max:50|unique:products',
            'name' => 'required|string|max:255',
            'vehicle_model_id' => 'required|exists:vehicle_models,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'category_id' => 'required|exists:product_categories,id',
            'unit' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
            'base_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')
                       ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return Inertia::render('Master/Products/Show', [
            'product' => $product->load(['vehicleModel', 'supplier', 'category']),
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        return Inertia::render('Master/Products/Edit', [
            'product' => $product,
            'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'part_number' => 'required|string|max:50|unique:products,part_number,' . $product->id,
            'name' => 'required|string|max:255',
            'vehicle_model_id' => 'required|exists:vehicle_models,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'category_id' => 'required|exists:product_categories,id',
            'unit' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
            'base_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return redirect()->route('products.show', $product)
                       ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')
                       ->with('success', 'Product deleted successfully.');
    }
}
