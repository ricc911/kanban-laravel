<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'created_at']);
            $table->index(['board_id', 'created_at']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
