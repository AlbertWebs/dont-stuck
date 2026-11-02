<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorizeStaff($request);

        return view('admin.profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateAdminProfileRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email']);
        $data['email'] = mb_strtolower($data['email']);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->fill($data);
        $changedFields = array_keys($user->getDirty());
        $user->save();

        if ($changedFields !== []) {
            $auditLogger->record($user, 'admin.profile_updated', $user, [
                'changed_fields' => implode(',', $changedFields),
            ], $request);
        }

        return to_route('admin.profile.edit')->with('success', 'Your admin profile has been updated.');
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'loan_officer', 'finance_officer', 'management']), 403);
    }
}
