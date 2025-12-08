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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_profile_id')->constrained('producer_profiles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->onDelete('restrict'); // Don't delete category if it has products
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->decimal('rating', 3, 2)->default(0.00); // Max 5.00
            $table->integer('download_count')->default(0);
            $table->string('image_path')->nullable(); // Cover art
            $table->string('audio_preview_path')->nullable(); // Public preview
            $table->string('file_path'); // Secure storage path
            $table->string('format')->default('WAV'); // WAV, MP3, etc
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
