<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Fetch menus and filter by user's permissions
        $menus = [];
        if ($user) {
            $allMenus = \App\Models\Menu::with('subMenus')->whereNull('parent_id')->orderBy('sort_order')->get();
            $menus = $allMenus->filter(function ($menu) use ($user) {
                // If it has a permission name, check if user has it
                if ($menu->permission_name && !$user->can($menu->permission_name)) {
                    return false;
                }
                return true;
            })->map(function ($menu) use ($user) {
                // Filter submenus
                $menu->subMenus = $menu->subMenus->filter(function ($subMenu) use ($user) {
                    if ($subMenu->permission_name && !$user->can($subMenu->permission_name)) {
                        return false;
                    }
                    return true;
                })->values();
                return $menu;
            })->values();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'unreadNotifications' => $user->unreadNotifications()->take(10)->get(),
                ]) : null,
            ],
            'menus' => $menus,
        ];
    }
}
