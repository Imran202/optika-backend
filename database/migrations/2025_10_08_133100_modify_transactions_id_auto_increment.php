<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify transcation_id to be auto-increment
        DB::statement('ALTER TABLE transactions MODIFY COLUMN transcation_id INT AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove auto-increment
        DB::statement('ALTER TABLE transactions MODIFY COLUMN transcation_id INT NOT NULL');
    }
};

