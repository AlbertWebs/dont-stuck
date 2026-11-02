<?php

namespace App\Services;

use App\Mail\ClientLoginCodeMail;
use App\Models\Borrower;
use App\Models\ClientLoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClientLoginCodeService
{
    public function requestCode(string $email): void
    {
        $normalizedEmail = Str::lower(trim($email));
        $user = DB::transaction(function () use ($normalizedEmail): ?User {
            $borrowers = Borrower::query()
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($borrowers->isEmpty()) {
                return null;
            }

            $linkedBorrower = $borrowers->first(fn (Borrower $borrower): bool => $borrower->user_id !== null);
            $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if ($existingUser !== null) {
                if ($existingUser->role !== 'client' || ! $existingUser->is_active) {
                    return null;
                }

                if ($linkedBorrower !== null) {
                    return $linkedBorrower->user_id === $existingUser->id ? $existingUser : null;
                }

                if ($borrowers->count() !== 1 || Borrower::query()->where('user_id', $existingUser->id)->exists()) {
                    return null;
                }

                $borrowers->first()->user()->associate($existingUser)->save();

                return $existingUser;
            }

            if ($borrowers->count() !== 1 || $linkedBorrower !== null) {
                return null;
            }

            $borrower = $borrowers->first();
            $user = User::create([
                'name' => $borrower->full_name,
                'email' => $normalizedEmail,
                'password' => Str::random(64),
                'role' => 'client',
                'is_active' => true,
            ]);
            $borrower->user()->associate($user)->save();

            return $user;
        });

        if ($user === null) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        ClientLoginCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
                'consumed_at' => null,
            ],
        );

        Mail::to($normalizedEmail)->send(new ClientLoginCodeMail($code));
    }

    public function verifyCode(string $email, string $code): ?User
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])
            ->where('role', 'client')
            ->where('is_active', true)
            ->whereHas('borrower')
            ->first();

        if ($user === null) {
            return null;
        }

        $verified = DB::transaction(function () use ($user, $code): bool {
            $loginCode = ClientLoginCode::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($loginCode === null || $loginCode->consumed_at !== null || $loginCode->expires_at->isPast() || $loginCode->attempts >= 5) {
                return false;
            }

            if (! Hash::check($code, $loginCode->code_hash)) {
                $loginCode->attempts++;

                if ($loginCode->attempts >= 5) {
                    $loginCode->consumed_at = now();
                }

                $loginCode->save();

                return false;
            }

            $loginCode->consumed_at = now();
            $loginCode->save();

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return true;
        });

        return $verified ? $user : null;
    }
}
