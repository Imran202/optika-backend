<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Method 1: Import from SQL file
        $this->importFromSql();
        
        // Method 2: Manual data insertion (as backup)
        // $this->insertManualData();
    }
    
    private function importFromSql()
    {
        $sqlFile = '../loyalty.sql';
        
        if (file_exists($sqlFile)) {
            $this->command->info('Importing data from SQL file...');
            
            try {
                $sql = file_get_contents($sqlFile);
                
                // Extract only INSERT statements for users and transactions
                $lines = explode("\n", $sql);
                $insertStatements = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'INSERT INTO `users`') || 
                        str_starts_with($line, 'INSERT INTO `transactions`')) {
                        $insertStatements[] = $line;
                    }
                }
                
                foreach ($insertStatements as $statement) {
                    try {
                        DB::unprepared($statement);
                    } catch (\Exception $e) {
                        $this->command->warn("Skipping: " . substr($statement, 0, 50) . "...");
                    }
                }
                
                $this->command->info('SQL data imported successfully!');
                
            } catch (\Exception $e) {
                $this->command->error("Error importing SQL: " . $e->getMessage());
            }
        } else {
            $this->command->warn('SQL file not found, using manual data insertion...');
            $this->insertManualData();
        }
    }
    
    private function insertManualData()
    {
        // Sample data - you can add more from the SQL file
        $users = [
            [
                'username' => 'Sefer Dauti',
                'useremail' => 'dauti_sefer@hotmail.com',
                'userphone' => '062424900',
                'rfid' => 12926067,
                'points' => 233,
                'count' => 2,
                'dioptrija' => 1,
                'dsph' => '-5,00',
                'dcyl' => '',
                'daxa' => '',
                'lsph' => '-5,00',
                'lcyl' => '',
                'laxa' => '',
                'ldadd' => '',
                'bonus_status' => 0,
                'password' => bcrypt('password123'), // Default password
            ],
            // Add more users here...
        ];
        
        foreach ($users as $user) {
            DB::table('users')->insert($user);
        }
    }
}
