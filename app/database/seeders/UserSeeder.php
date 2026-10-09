<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@sinar.com'],
            [
                'name' => 'Admin PT Sinar Nusantara',
                'password' => 'admin123',
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'operator@sinar.com'],
            [
                'name' => 'Operator PT Sinar Nusantara',
                'password' => 'operator123',
                'role' => 'operator',
                'is_active' => true,
            ],
        );
    }
}
