<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_token')->nullable()->unique()->after('number');
            $table->timestamp('expires_at')->nullable()->index()->after('paid_at');
            $table->index(['payment_method', 'payment_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['checkout_token']);
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['payment_method', 'payment_reference']);
            $table->dropColumn(['checkout_token', 'expires_at']);
        });
    }
};
