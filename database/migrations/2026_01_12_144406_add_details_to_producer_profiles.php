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
        Schema::table('producer_profiles', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('website')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('social_links')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producer_profiles', function (Blueprint $table) {
            $table->dropColumn('location');
            $table->dropColumn('website');
            $table->dropColumn('cover_path');
            $table->dropColumn('social_links');
        });
    }
};
