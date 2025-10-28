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
        Schema::create('transactions', function (Blueprint $table) {
            $table->integer('transcation_id')->primary()->autoIncrement();
            $table->string('poslovnica', 255);
            $table->integer('rfid');
            $table->string('user', 255);
            $table->timestamp('date')->useCurrent()->useCurrentOnUpdate();
            $table->integer('points');
            $table->string('action', 7);
            $table->string('vrsta', 255)->default('Redovna kupovina');
        });

        // Dodajemo ENGINE i CHARSET kao što je u SQL-u
        DB::statement('ALTER TABLE transactions ENGINE = InnoDB');
        DB::statement('ALTER TABLE transactions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
