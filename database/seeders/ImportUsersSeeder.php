<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('users')->truncate();

        // Import users data from the parsed SQL file
        $usersData = include base_path('users_data.php');
        
        // Skip the first row (column headers)
        array_shift($usersData);

        // Insert users
        foreach ($usersData as $userData) {
            if (count($userData) >= 17) {
                DB::table('users')->insert([
                    'username' => $userData[1],
                    'useremail' => $userData[2],
                    'userphone' => $userData[3],
                    'dt' => $userData[4],
                    'rfid' => $userData[5],
                    'points' => $userData[6],
                    'count' => $userData[7],
                    'dioptrija' => $userData[8],
                    'dsph' => $userData[9],
                    'dcyl' => $userData[10],
                    'daxa' => $userData[11],
                    'lsph' => $userData[12],
                    'lcyl' => $userData[13],
                    'laxa' => $userData[14],
                    'ldadd' => $userData[15],
                    'bonus_status' => $userData[16],
                    'password' => Hash::make('password123'), // Default password
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Users imported successfully! Total users: ' . count($usersData));
    }
}
