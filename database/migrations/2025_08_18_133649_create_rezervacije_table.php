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
        Schema::create('rezervacije', function (Blueprint $table) {
            $table->integer('id_rezervacije')->primary();
            $table->string('poslovnica', 255);
            $table->date('datum');
            $table->time('vrijeme', 6);
            $table->string('ime', 255);
            $table->string('prezime', 255);
            $table->string('telefon', 255);
            $table->string('email', 255);
        });

        // Dodajemo ENGINE i CHARSET kao što je u SQL-u
        DB::statement('ALTER TABLE rezervacije ENGINE = InnoDB');
        DB::statement('ALTER TABLE rezervacije CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rezervacije');
    }
};
