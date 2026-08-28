<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'max_shared_workspaces' => 0,
                'max_members_per_workspace' => 1,
                'max_projects' => 5,
                'ai_enabled' => false,
                'ai_monthly_credits' => null,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'team'],
            [
                'name' => 'Team',
                'max_shared_workspaces' => 3,
                'max_members_per_workspace' => 5,
                'max_projects' => null,
                'ai_enabled' => false,
                'ai_monthly_credits' => null,
            ]
        );
    }
}
