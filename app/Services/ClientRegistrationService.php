<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientRegistrationService
{
    /**
     * @param  array{full_name: string, national_id: string, phone: string, email: string}  $data
     */
    public function register(array $data): void
    {
        $email = Str::lower(trim($data['email']));
        $nationalId = trim($data['national_id']);

        DB::transaction(function () use ($data, $email, $nationalId): void {
            $existingUser = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if ($existingUser !== null && ($existingUser->role !== 'client' || ! $existingUser->is_active)) {
                throw ValidationException::withMessages([
                    'email' => 'This email cannot be used to create a client account.',
                ]);
            }

            if ($existingUser !== null && $existingUser->borrower()->exists()) {
                $borrower = $existingUser->borrower()->first();

                if ($borrower?->national_id !== $nationalId || Str::lower((string) $borrower->email) !== $email) {
                    throw ValidationException::withMessages([
                        'email' => 'The details provided do not match the existing client account.',
                    ]);
                }

                return;
            }

            $borrowersWithEmail = Borrower::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            if ($borrowersWithEmail->isNotEmpty()) {
                $borrower = $borrowersWithEmail->first();

                if ($borrowersWithEmail->count() !== 1 || $borrower->national_id !== $nationalId) {
                    throw ValidationException::withMessages([
                        'email' => 'The details provided do not match the existing borrower record.',
                    ]);
                }

                if ($borrower->user_id !== null) {
                    throw ValidationException::withMessages([
                        'email' => 'This borrower record is already linked to a different client account.',
                    ]);
                }

                return;
            }

            $borrowerWithNationalId = Borrower::query()
                ->where('national_id', $nationalId)
                ->lockForUpdate()
                ->exists();

            if ($borrowerWithNationalId) {
                throw ValidationException::withMessages([
                    'national_id' => 'This national ID is already associated with a different email address.',
                ]);
            }

            Borrower::query()->create([
                'full_name' => trim($data['full_name']),
                'national_id' => $nationalId,
                'phone' => trim($data['phone']),
                'email' => $email,
            ]);
        });
    }
}
