<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('lms:create-administrator {email} {name}')]
#[Description('Create or promote an LMS administrator account')]
class CreateAdministrator extends Command
{
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $name = (string) $this->argument('name');
        $password = $this->secret('Choose a strong password');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('The password must contain at least 12 characters.');

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'role' => 'administrator', 'is_active' => true],
        );

        $this->info('Administrator account created or updated.');

        return self::SUCCESS;
    }
}
