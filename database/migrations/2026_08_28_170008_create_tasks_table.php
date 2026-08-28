<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('board_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('board_column_id')
                ->constrained('board_columns')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->string('priority')->nullable();
            $table->timestamp('due_at')->nullable();

            $table->boolean('archived')->default(false);

            $table->timestamps();

            $table->index(['board_column_id', 'position']);
            $table->index(['board_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
