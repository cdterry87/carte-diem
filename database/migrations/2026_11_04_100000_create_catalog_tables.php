<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('icon', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['scope_type', 'scope_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id');
            $table->string('canonical_name');
            $table->string('normalized_name');
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('default_quantity', 12, 3)->nullable();
            $table->string('default_unit_code', 16)->nullable();
            $table->boolean('is_staple')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['scope_type', 'scope_id', 'normalized_name']);
            $table->index(['scope_type', 'scope_id', 'last_used_at']);
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_alias');
            $table->string('source', 32)->default('entry');
            $table->timestamps();
            $table->unique(['product_id', 'normalized_alias']);
            $table->index('normalized_alias');
        });

        Schema::create('product_favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'product_id']);
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id');
            $table->string('name');
            $table->string('location')->nullable();
            $table->timestamps();
            $table->index(['scope_type', 'scope_id']);
        });

        $defaults = ['Produce', 'Dairy & Eggs', 'Meat & Seafood', 'Bakery', 'Frozen', 'Pantry', 'Beverages', 'Household', 'Personal Care', 'Pets', 'Other'];

        foreach ($defaults as $index => $name) {
            DB::table('categories')->insert([
                'uuid' => (string) Str::uuid7(),
                'name' => $name,
                'slug' => Str::slug($name),
                'scope_type' => 'system',
                'sort_order' => ($index + 1) * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('product_aliases');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
