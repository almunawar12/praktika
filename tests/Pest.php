<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// Roles and permissions are seeded once per process via `$seeder` on TestCase,
// not per test — see the note there for why that distinction costs 8 minutes.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a user carrying the given role.
 */
function userWithRole(RoleName $role): User
{
    return User::factory()->withRole($role)->create();
}
