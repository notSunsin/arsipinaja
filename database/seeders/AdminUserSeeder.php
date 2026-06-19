<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@arsipin.id'],
            [
                'name' => 'Administrator',
                'username' => 'Admin ARSIPIN',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role_type' => 'admin',
            ]
        );
        $admin->assignRole('admin');

        // Create Intern Users (Mahasiswa Magang)
        $intern1 = User::firstOrCreate(
            ['email' => 'intern@arsipin.id'],
            [
                'name' => 'Muhammad Rizky H',
                'username' => 'Intern ARSIPIN',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role_type' => 'intern',
            ]
        );
        $intern1->assignRole('intern');

        $intern2 = User::firstOrCreate(
            ['email' => 'intern1@arsipin.id'],
            [
                'name' => 'Dhimas Valentino',
                'username' => 'Intern ARSIPIN 2',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role_type' => 'intern',
            ]
        );
        $intern2->assignRole('intern');

        $this->command->info('Demo users created successfully!');
        $this->command->table(['Name', 'Username', 'Email', 'Role', 'Password'], [
            ['Administrator', 'Admin ARSIPIN', 'admin@arsipin.id', 'Admin', 'password'],
            ['Muhammad Rizky H', 'Intern ARSIPIN', 'intern@arsipin.id', 'Mahasiswa Magang', 'password'],
            ['Dhimas Valentino', 'Intern ARSIPIN 2', 'intern1@arsipin.id', 'Mahasiswa Magang', 'password'],
        ]);
    }
}
