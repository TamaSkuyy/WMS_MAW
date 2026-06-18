<?php
namespace App\Http\Controllers;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Master/ProductCategories/Index', [
            'categories' => ProductCategory::orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }
    public function create()
    {
        return Inertia::render('Master/ProductCategories/Create');
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:product_categories',
            'description' => 'nullable|string|max:500',
        ]);
        ProductCategory::create($validated);
        return redirect()->route('product-categories.index')->with('success', 'Kategori berhasil dibuat.');
    }
    public function edit(ProductCategory $productCategory)
    {
        return Inertia::render('Master/ProductCategories/Edit', ['category' => $productCategory]);
    }
    public function update(Request $request, ProductCategory $productCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:product_categories,name,'.$productCategory->id,
            'description' => 'nullable|string|max:500',
        ]);
        $productCategory->update($validated);
        return redirect()->route('product-categories.index')->with('success', 'Kategori berhasil diupdate.');
    }
    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();
        return redirect()->route('product-categories.index')->with('success', 'Kategori berhasil dihapus.');
    }
}
