<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->boolean('in_app');
            $table->boolean('email');
            $table->boolean('push')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });

        Schema::create('staple_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staple_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->unique(['staple_rule_id', 'user_id']);
        });

        Schema::create('list_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 16);
            $table->string('item_name');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->index(['processed_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_activity_events');
        Schema::dropIfExists('staple_reminder_deliveries');
        Schema::dropIfExists('notification_preferences');
    }
};
