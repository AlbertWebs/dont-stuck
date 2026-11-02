<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    private const ROLES = ['administrator', 'loan_officer', 'finance_officer', 'management'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->role === 'administrator', 403);

        return view('admin.users.index', ['users' => User::query()->whereIn('role', self::ROLES)->orderBy('name')->paginate(25)]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
        $auditLogger->record($request->user(), 'user.created', $user, ['role' => $user->role], $request);

        return back()->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->role === 'administrator', 403);
        abort_unless(in_array($user->role, self::ROLES, true), 404);
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->is($request->user()) && (! $data['is_active'] || $data['role'] !== 'administrator')) {
            return back()->withErrors(['user' => 'You cannot deactivate or demote your own administrator account.']);
        }

        if ($user->role === 'administrator' && (! $data['is_active'] || $data['role'] !== 'administrator')) {
            $hasAnotherAdministrator = User::query()->where('role', 'administrator')->where('is_active', true)->where('id', '!=', $user->id)->exists();
            abort_unless($hasAnotherAdministrator, 422, 'At least one active administrator must remain.');
        }

        $before = ['role' => $user->role, 'is_active' => $user->is_active];
        $user->update($data);
        $auditLogger->record($request->user(), 'user.access_updated', $user, ['before' => $before, 'after' => $data], $request);

        return back()->with('success', 'Staff access updated.');
    }
}
