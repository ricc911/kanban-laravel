<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_shared_workspaces')->nullable()->change();
            $table->unsignedInteger('max_members_per_workspace')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->whereNull('max_shared_workspaces')->update(['max_shared_workspaces' => 0]);
        DB::table('plans')->whereNull('max_members_per_workspace')->update(['max_members_per_workspace' => 1]);

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_shared_workspaces')->default(0)->nullable(false)->change();
            $table->unsignedInteger('max_members_per_workspace')->default(1)->nullable(false)->change();
        });
    }
};
