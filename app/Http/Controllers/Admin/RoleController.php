<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception; 

class RoleController extends Controller
{
    private function getAdminId()
    {
        return auth()->id() ?? 'guest';
    }

    public function index(Request $request)
    {
        $query = Role::withCount('admins')->orderBy('name');

        if ($request->has('search') && !empty($request->query('search'))) {
            $searchTerm = $request->query('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        $roles = $query->get();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $role = Role::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
            ]);
            

            return response()->json($role->loadCount('admins'), 201);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while creating the role.'], 500);
        }
    }
    
    public function update(Request $request, Role $role)
    {
        if ($role->slug === 'super-admin') {
            Log::warning('Forbidden attempt to edit Super Admin role.', ['role_id' => $role->id, 'admin_id' => $this->getAdminId()]);
            return response()->json(['message' => 'Super Admin role cannot be edited.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $oldName = $role->name;
            $role->update([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
            ]);
            

            return response()->json($role->loadCount('admins'));
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the role.'], 500);
        }
    }

    public function destroy(Role $role)
    {
        if ($role->slug === 'super-admin') {
            return response()->json(['message' => 'Super Admin role cannot be deleted.'], 403);
        }

        if ($role->admins()->count() > 0) {
            return response()->json(['message' => 'Cannot delete role. It is currently assigned to one or more admins.'], 422);
        }

        try {
            $roleName = $role->name;
            $roleId = $role->id;
            $role->delete();
            return response()->json(['message' => 'Role deleted successfully.'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while deleting the role.'], 500);
        }
    }
    
    public function getAssignedAdmins(Role $role)
    {
        return response()->json($role->admins()->get(['admins.id', 'admins.name']));
    }


    public function syncAdmins(Request $request, Role $role)
    {
        $validated = $request->validate([
            'admin_ids' => 'present|array',
            'admin_ids.*' => 'exists:admins,id' 
        ]);

        try {
            $role->admins()->sync($validated['admin_ids']);
            return response()->json(['message' => 'Personnel updated successfully.']);

        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while updating personnel.'], 500);
        }
    }
}
