<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;

test('super admin and admin pkl can read the audit trail; a siswa can not', function (): void {
    // Something to show: creating a user writes an activity entry.
    User::factory()->create(['name' => 'Terpantau']);

    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->get('/system/activity')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activities.data'));

    $this->actingAs(userWithRole(RoleName::AdminPkl))->get('/system/activity')->assertOk();

    $this->actingAs(userWithRole(RoleName::Siswa))->get('/system/activity')->assertForbidden();
});

test('the log can be filtered by category', function (): void {
    User::factory()->create();

    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->get('/system/activity?log_name=user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('table.filters.log_name', 'user'));
});
