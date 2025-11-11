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
        Schema::table('notifications', function (Blueprint $table) {
            $table->boolean('has_post')->default(false)->after('message');
            $table->string('post_title')->nullable()->after('has_post');
            $table->text('post_description')->nullable()->after('post_title');
            $table->string('post_image')->nullable()->after('post_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['has_post', 'post_title', 'post_description', 'post_image']);
        });
    }
};

