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
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('subscription_id')->nullable()->nullOnDelete()->constrained();
            $table->foreignId('workspace_id')->nullable()->nullOnDelete()->constrained();
            $table->foreignId('user_id')->nullable()->nullOnDelete()->constrained();
            $table->string('feature');
            $table->string('reasoning_level');
            $table->string('provider')->default('openai');
            $table->string('model');
            $table->string('status')->default('pending');
            $table->unsignedInteger('reserved_credits')->default(0);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedBigInteger('provider_cost_micros')->nullable();
            $table->unsignedInteger('credits_used')->default(0);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'status', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
