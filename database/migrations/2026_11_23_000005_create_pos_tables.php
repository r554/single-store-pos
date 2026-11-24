<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('stock_min')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('barcode');
            $table->index('sku');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('device_id')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->unique();
            $table->string('receipt_no')->nullable()->unique();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('device_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->decimal('payment_amount', 15, 2)->default(0);
            $table->decimal('change', 15, 2)->default(0);
            $table->timestamp('transacted_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('qty')->default(1);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
        });

        // Log pergerakan stok (masuk, keluar, koreksi, opname).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->integer('qty_change');
            $table->integer('stock_after');
            $table->string('type')->default('out');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
