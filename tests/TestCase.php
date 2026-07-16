<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles and permissions are reference data: 52 permissions across 5 roles that
     * no test mutates and every test needs, since `can:` middleware and policies
     * have nothing to resolve against without them.
     *
     * Declaring it here rather than seeding in a `beforeEach` is what makes it
     * cheap. RefreshDatabase hands this to `migrate:fresh --seeder=`, which runs
     * once per process and *before* the per-test transaction opens — so the rows
     * are committed and outlive every rollback. Seeding inside `beforeEach` put
     * them inside that transaction instead, so each test paid a full cold seed
     * (326 queries, ~1.6s) to rebuild rows identical to the ones just discarded.
     *
     * @var class-string<Seeder>
     */
    protected $seeder = RolePermissionSeeder::class;
}
