<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClientProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('client.profile.show', ['borrower' => $request->user()->borrower()->firstOrFail()]);
    }

    public function update(UpdateClientProfileRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $borrower = $user->borrower()->firstOrFail();
        $data = $request->validated();

        DB::transaction(function () use ($user, $borrower, $data, $auditLogger, $request): void {
            $borrower->fill($data);
            $changedFields = array_keys($borrower->getDirty());
            $borrower->save();

            if (in_array('full_name', $changedFields, true)) {
                $user->forceFill(['name' => $borrower->full_name])->save();
            }

            if ($changedFields !== []) {
                $auditLogger->record($user, 'client.profile_updated', $borrower, [
                    'changed_fields' => implode(',', $changedFields),
                ], $request);
            }
        });

        return to_route('client.profile.show')->with('success', 'Your profile has been updated.');
    }
}
