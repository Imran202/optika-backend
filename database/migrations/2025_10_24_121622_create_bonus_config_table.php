<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bonus_config', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->integer('bonus_points')->default(200);
            $table->string('bonus_title')->default('Dobrodošli!');
            $table->text('bonus_message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_config');
    }
};
