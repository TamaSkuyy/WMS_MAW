<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Menu;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with('parent')->orderBy('sort_order')->get();
        $permissions = Permission::all();
        $parentMenus = Menu::whereNull('parent_id')->get();

        return Inertia::render('Menus/Index', [
            'menuItems' => $menus,
            'permissions' => $permissions,
            'parentMenus' => $parentMenus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'sort_order' => 'required|integer',
            'permission_name' => 'nullable|string|exists:permissions,name',
            'group' => 'required|string|in:main,others',
        ]);

        Menu::create($validated);

        return redirect()->route('menus.index')->with('success', 'Menu created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $menu = Menu::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'sort_order' => 'required|integer',
            'permission_name' => 'nullable|string|exists:permissions,name',
            'group' => 'required|string|in:main,others',
        ]);

        $menu->update($validated);

        return redirect()->route('menus.index')->with('success', 'Menu updated successfully.');
    }

    public function destroy(string $id)
    {
        $menu = Menu::findOrFail($id);
        $menu->delete();

        return redirect()->route('menus.index')->with('success', 'Menu deleted successfully.');
    }
}
