<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staple_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type', 16);
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cadence_days')->nullable();
            $table->boolean('prediction_enabled')->default(true);
            $table->boolean('reminder_enabled')->default(true);
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'product_id']);
        });

        Schema::create('recommendation_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 16);
            $table->timestamp('dismissed_until')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_dismissals');
        Schema::dropIfExists('staple_rules');
    }
};
