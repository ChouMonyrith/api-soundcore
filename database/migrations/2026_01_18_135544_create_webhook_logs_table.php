<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('bakong')->index();
            $table->string('event_type')->index();
            $table->string('event_id')->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('payload');
            $table->string('signature')->nullable();
            $table->string('status')->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_type']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
