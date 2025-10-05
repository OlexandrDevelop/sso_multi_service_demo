<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_USER_EMAIL', 'admin@example.com');
        $password = env('SEED_USER_PASSWORD', 'password');
        $name = env('SEED_USER_NAME', 'Admin');

        $exists = User::query()->where('email', $email)->exists();
        if (!$exists) {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        }
    }
} 