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
            $table->string('transaction_id')->nullable()->unique()->after('id');
            $table->string('md5')->nullable()->index()->after('total');
            $table->json('payment_metadata')->nullable()->after('md5');
            $table->timestamp('paid_at')->nullable()->after('payment_metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'md5', 'payment_metadata', 'paid_at']);
        });
    }
};
