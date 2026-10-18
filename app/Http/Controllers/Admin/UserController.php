<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    private const ROLES = ['dsa', 'vpa', 'adviser', 'president', 'treasurer', 'mmo', 'member'];

    public function index(Request $request): View
    {
        $search = $request->query('search');

        $users = User::with(['department', 'course'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.strtolower($search).'%'])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($search).'%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%'.strtolower($search).'%']);
            }))
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users'  => $users,
            'search' => $search,
            'roles'  => self::ROLES,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
        ]);

        if ($user->id === auth_user_id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $oldRole = $user->role;
        $newRole = $validated['role'];

        if ($oldRole === $newRole) {
            return back()->with('info', "No change — {$user->name} already has the \"{$newRole}\" role.");
        }

        $user->update(['role' => $newRole]);

        AuditLog::record('admin.user.role_changed', $user, [
            'target_email' => $user->email,
            'old_role'     => $oldRole,
            'new_role'     => $newRole,
        ]);

        return back()->with('success', "Role for {$user->name} changed from \"{$oldRole}\" to \"{$newRole}\".");
    }
}
