<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!User::count()) {

            User::create([
                'name' => 'SuperAdmin',
                'password' => 123123123,
                'email' => 'super_admin@mail.com'
            ])->assignRole('Super Admin');


            User::create([
                'name' => 'Admin',
                'password' => 123123123,
                'email' => 'admin@mail.com'
            ])->assignRole('Admin');


        }

    }
}
