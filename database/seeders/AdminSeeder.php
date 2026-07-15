<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@bblsdm.id'],
            [
                'name' => 'Admin BBLSDM',
                'password' => Hash::make('admin123'), // ganti setelah login pertama kali
                'role' => 'admin',
            ]
        );
    }
}
