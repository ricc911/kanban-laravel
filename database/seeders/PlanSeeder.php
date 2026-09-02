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
                'price_cents' => 0,
                'max_shared_workspaces' => 0,
                'max_members_per_workspace' => 1,
                'max_projects' => 1,
                'ai_enabled' => false,
                'ai_monthly_credits' => 0,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'price_cents' => 499,
                'max_shared_workspaces' => 0,
                'max_members_per_workspace' => 1,
                'max_projects' => 10,
                'ai_enabled' => true,
                'ai_monthly_credits' => 500,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'team'],
            [
                'name' => 'Team',
                'price_cents' => 1499,
                'max_shared_workspaces' => 3,
                'max_members_per_workspace' => 10,
                'max_projects' => 50,
                'ai_enabled' => true,
                'ai_monthly_credits' => 3000,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'business'],
            [
                'name' => 'Business',
                'price_cents' => 3999,
                'max_shared_workspaces' => 10,
                'max_members_per_workspace' => 40,
                'max_projects' => null,
                'ai_enabled' => true,
                'ai_monthly_credits' => 10000,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'unlimited'],
            [
                'name' => 'Unlimited',
                'price_cents' => 0,
                'max_shared_workspaces' => null,
                'max_members_per_workspace' => null,
                'max_projects' => null,
                'ai_enabled' => true,
                'ai_monthly_credits' => 1000000,
            ]
        );
    }
}
