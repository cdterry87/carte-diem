<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shopping_list_id')->constrained();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->bigInteger('total_minor')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('active_list_key')->nullable()->storedAs("if(status = 'active', shopping_list_id, null)");
            $table->unique('active_list_key');
            $table->index(['shopping_list_id', 'status', 'completed_at']);
        });

        Schema::create('shopping_trip_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shopping_trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_list_item_id')->nullable()->constrained('shopping_list_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('category_name_snapshot')->nullable();
            $table->unsignedInteger('category_sort_snapshot')->default(0);
            $table->decimal('planned_quantity', 12, 3)->nullable();
            $table->string('planned_unit', 16)->nullable();
            $table->decimal('actual_quantity', 12, 3)->nullable();
            $table->bigInteger('line_total_minor')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('purchased')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['shopping_trip_id', 'source_list_item_id']);
        });

        Schema::create('purchase_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type', 16);
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('shopping_trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('merchant_name_snapshot')->nullable();
            $table->timestamp('purchased_at');
            $table->char('currency_code', 3)->default('USD');
            $table->bigInteger('subtotal_minor')->nullable();
            $table->bigInteger('tax_minor')->nullable();
            $table->bigInteger('discount_minor')->nullable();
            $table->bigInteger('total_minor')->nullable();
            $table->string('entry_method', 16);
            $table->string('verification_status', 16);
            $table->unsignedInteger('revision')->default(1);
            $table->string('source_kind', 32);
            $table->string('source_identifier', 64);
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['source_kind', 'source_identifier']);
            $table->index(['owner_type', 'owner_id', 'purchased_at']);
        });

        Schema::create('purchase_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_trip_item_id')->nullable()->constrained('shopping_trip_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description_snapshot');
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('unit_code', 16)->nullable();
            $table->bigInteger('line_total_minor')->nullable();
            $table->bigInteger('unit_price_minor')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name_snapshot')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->unique(['purchase_transaction_id', 'source_trip_item_id'], 'purchase_items_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_transaction_items');
        Schema::dropIfExists('purchase_transactions');
        Schema::dropIfExists('shopping_trip_items');
        Schema::dropIfExists('shopping_trips');
    }
};
