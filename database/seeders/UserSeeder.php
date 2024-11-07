<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Define the users to be seeded
        $users = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'date_of_birth' => '1990-01-01',
                'email' => 'admin@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08012345678',
                'country' => 'Nigeria',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'staff@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08098765432',
                'country' => 'Nigeria',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'Olumide',
                'last_name' => 'Sanni',
                'email' => 'olumide@example.com',
                'user_role_id' => 1,  // Admin
                'phone_number' => '08056789123',
                'country' => 'Nigeria',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'User',
                'last_name' => 'One',
                'email' => 'user@example.com',
                'user_role_id' => 2,  // Regular User
                'phone_number' => '08011112222',
                'country' => 'Nigeria',
                'referral_code' => 'REF-123456',
                'referral_count' => 1,
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'User',
                'last_name' => 'Two',
                'email' => 'doe@example.com',
                'user_role_id' => 2,  // Regular User
                'phone_number' => '08033334444',
                'country' => 'Nigeria',
                'referral_code' => 'REF-654321',
                'referral_by' => 4,
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'created_at' => now(),
                'updated_at' => now()
            ],
            // [
            //     'first_name' => 'Moderator',
            //     'last_name' => 'One',
            //     'email' => 'moderator@example.com',
            //     'user_role_id' => 3,  // Moderator
            //     'phone_number' => '08022223333',
            //     'country' => 'Nigeria',
            //     'status' => 'active',
            //     'created_at' => now(),
            //     'updated_at' => now()
            // ],
        ];

        // Create or update users
        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']], // Unique field to check
                $user
            );
        }
    }
}
