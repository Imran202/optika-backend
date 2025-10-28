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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64);
            $table->string('useremail', 128);
            $table->string('userphone', 24);
            $table->timestamp('dt')->useCurrent();
            $table->integer('rfid');
            $table->integer('points')->default(0);
            $table->integer('count')->default(0);
            $table->integer('dioptrija')->default(0);
            $table->string('dsph', 10)->default('');
            $table->string('dcyl', 10)->default('');
            $table->string('daxa', 10)->default('');
            $table->string('lsph', 10)->default('');
            $table->string('lcyl', 10)->default('');
            $table->string('laxa', 10)->default('');
            $table->string('ldadd', 10)->default('');
            $table->integer('bonus_status')->default(0);
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
