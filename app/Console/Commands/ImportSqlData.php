<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportSqlData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:sql-data {file? : Path to SQL file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from SQL file into database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sqlFile = $this->argument('file') ?? '../loyalty.sql';
        
        if (!file_exists($sqlFile)) {
            $this->error("SQL file not found: {$sqlFile}");
            return 1;
        }

        $this->info("Importing data from: {$sqlFile}");
        
        try {
            // Read SQL file
            $sql = file_get_contents($sqlFile);
            
            // Extract data using regex
            $this->importUsers($sql);
            $this->importTransactions($sql);
            
            $this->info('SQL data imported successfully!');
            
        } catch (\Exception $e) {
            $this->error("Error importing data: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function importUsers($sql)
    {
        // Extract users data using regex
        preg_match_all('/INSERT INTO `users`[^;]+;/s', $sql, $matches);
        
        if (empty($matches[0])) {
            $this->warn("No user data found");
            return;
        }
        
        $this->info("Found user data, extracting...");
        
        // Parse the INSERT statement
        $insertStatement = $matches[0][0];
        
        // Extract values using regex
        preg_match_all('/\(([^)]+)\)/', $insertStatement, $valueMatches);
        
        if (empty($valueMatches[1])) {
            $this->warn("No user values found");
            return;
        }
        
        $this->info("Importing " . count($valueMatches[1]) . " users...");
        $bar = $this->output->createProgressBar(count($valueMatches[1]));
        $bar->start();
        
        foreach ($valueMatches[1] as $values) {
            try {
                $userData = $this->parseValues($values);
                
                // Add password
                $userData['password'] = bcrypt('password123');
                
                DB::table('users')->insert($userData);
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Error importing user: " . $e->getMessage());
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }
    
    private function importTransactions($sql)
    {
        // Extract transactions data using regex
        preg_match_all('/INSERT INTO `transactions`[^;]+;/s', $sql, $matches);
        
        if (empty($matches[0])) {
            $this->warn("No transaction data found");
            return;
        }
        
        $this->info("Found transaction data, extracting...");
        
        // Parse the INSERT statement
        $insertStatement = $matches[0][0];
        
        // Extract values using regex
        preg_match_all('/\(([^)]+)\)/', $insertStatement, $valueMatches);
        
        if (empty($valueMatches[1])) {
            $this->warn("No transaction values found");
            return;
        }
        
        $this->info("Importing " . count($valueMatches[1]) . " transactions...");
        $bar = $this->output->createProgressBar(count($valueMatches[1]));
        $bar->start();
        
        foreach ($valueMatches[1] as $values) {
            try {
                $transactionData = $this->parseValues($values);
                DB::table('transactions')->insert($transactionData);
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Error importing transaction: " . $e->getMessage());
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }
    
    private function parseValues($valuesString)
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        
        for ($i = 0; $i < strlen($valuesString); $i++) {
            $char = $valuesString[$i];
            
            if (($char === "'" || $char === '"') && !$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
                continue;
            }
            
            if ($char === $quoteChar && $inQuotes) {
                $inQuotes = false;
                $quoteChar = null;
                continue;
            }
            
            if ($char === ',' && !$inQuotes) {
                $values[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty($current)) {
            $values[] = trim($current);
        }
        
        // Map to column names
        $userColumns = [
            'id', 'username', 'useremail', 'userphone', 'dt', 'rfid', 
            'points', 'count', 'dioptrija', 'dsph', 'dcyl', 'daxa', 
            'lsph', 'lcyl', 'laxa', 'ldadd', 'bonus_status'
        ];
        
        $transactionColumns = [
            'transcation_id', 'poslovnica', 'rfid', 'user', 'date', 
            'points', 'action', 'vrsta'
        ];
        
        $columns = count($values) === count($userColumns) ? $userColumns : $transactionColumns;
        
        $data = [];
        foreach ($columns as $index => $column) {
            if (isset($values[$index])) {
                $value = trim($values[$index], "'\"");
                $data[$column] = $value;
            }
        }
        
        return $data;
    }
}
