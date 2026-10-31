<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('total'); // cents
            $table->string('idempotency_key', 100);
            $table->string('payment_intent_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // The same key from the same customer always maps to one order.
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('subtotal');
            // Rate is copied at checkout so later rate changes don't rewrite history.
            $table->unsignedSmallInteger('commission_rate_bps');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('vendor_earnings');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'vendor_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of what was bought, independent of later catalog edits.
            $table->string('product_name');
            $table->string('variant_name');
            $table->string('sku', 64);
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('vendor_orders');
        Schema::dropIfExists('orders');
    }
};
