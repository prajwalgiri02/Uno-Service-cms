<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@najik.app')],
            [
                'name' => 'Najik Admin',
                'password' => env('ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole(UserRole::ADMIN->value);
    }
}
