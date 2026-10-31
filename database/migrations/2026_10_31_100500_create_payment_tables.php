<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider event ids we have already handled; the primary key makes replays a no-op.
        Schema::create('processed_webhook_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->timestamp('processed_at');
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount'); // signed cents: credits positive, debits negative
            $table->char('currency', 3);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'type']);
            // A retried listener must not book the same sale or commission twice.
            $table->unique(['vendor_order_id', 'type']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->unsignedBigInteger('total');
            $table->char('currency', 3);
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('processed_webhook_events');
    }
};
