<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_otvaranje', function (Blueprint $table) {
            $table->integer('id_otvaranja')->primary();
            $table->string('poslovnica', 255);
            $table->date('datum');
            $table->time('vrijeme');
        });

        // Dodajemo ENGINE i CHARSET kao što je u SQL-u
        DB::statement('ALTER TABLE admin_otvaranje ENGINE = InnoDB');
        DB::statement('ALTER TABLE admin_otvaranje CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_otvaranje');
    }
};
