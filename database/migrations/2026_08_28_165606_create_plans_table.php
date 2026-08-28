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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('slug')->unique();

            $table->unsignedInteger('max_shared_workspaces')->default(0);
            $table->unsignedInteger('max_members_per_workspace')->default(1);
            $table->unsignedInteger('max_projects')->nullable();

            $table->boolean('ai_enabled')->default(false);
            $table->unsignedInteger('ai_monthly_credits')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
