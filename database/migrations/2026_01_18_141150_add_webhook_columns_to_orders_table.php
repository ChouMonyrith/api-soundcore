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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_confirmed_at')->nullable()->after('paid_at');
            $table->boolean('webhook_processed')->default(false)->after('payment_confirmed_at');
            $table->string('last_webhook_event_id')->nullable()->after('webhook_processed');
            $table->index('last_webhook_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['last_webhook_event_id']);
            $table->dropColumn(['payment_confirmed_at', 'webhook_processed', 'last_webhook_event_id']);
        });
    }
};
