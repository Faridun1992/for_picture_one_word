<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::query()->firstOrCreate(
            ['email' => 'super_admin@mail.com'],
            [
                'name' => 'SuperAdmin',
                'password' => '123123123',
            ]
        )->assignRole('Super Admin');

        User::query()->firstOrCreate(
            ['email' => 'admin@mail.com'],
            [
                'name' => 'Admin',
                'password' => '123123123',
            ]
        )->assignRole('Admin');
    }
}
