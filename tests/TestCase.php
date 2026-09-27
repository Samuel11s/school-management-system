<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles and permissions whenever a test refreshes the database.
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    protected function teacher(array $attributes = []): User
    {
        return User::factory()->teacher()->create($attributes);
    }

    protected function studentUser(array $attributes = []): User
    {
        return User::factory()->student()->create($attributes);
    }
}
