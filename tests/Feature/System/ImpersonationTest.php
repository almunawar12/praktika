<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Http\Controllers\System\ImpersonationController;
use App\Models\Student;

test('a super admin can impersonate another user and step back out', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $target = Student::factory()->create()->user;

    $this->actingAs($admin)
        ->post("/system/users/{$target->id}/impersonate")
        ->assertRedirect(route('dashboard'));

    // Now acting as the target, with the admin parked in the session.
    $this->assertAuthenticatedAs($target);
    expect(session(ImpersonationController::SESSION_KEY))->toBe($admin->id);

    $this->delete('/system/impersonation')->assertRedirect(route('system.users.index'));

    $this->assertAuthenticatedAs($admin);
    expect(session()->has(ImpersonationController::SESSION_KEY))->toBeFalse();
});

test('the impersonation banner is shared while impersonating', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $target = Student::factory()->create()->user;

    $this->actingAs($admin)->post("/system/users/{$target->id}/impersonate");

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('impersonating.name', $target->name));
});

test('you can not impersonate yourself', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);

    $this->actingAs($admin)
        ->post("/system/users/{$admin->id}/impersonate")
        ->assertForbidden();
});

test('an inactive account can not be impersonated', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $target = Student::factory()->create()->user;
    $target->update(['is_active' => false]);

    $this->actingAs($admin)
        ->post("/system/users/{$target->id}/impersonate")
        ->assertSessionHas('error');

    // Still the admin: impersonation was refused, not silently started.
    $this->assertAuthenticatedAs($admin);
});

test('only a super admin may impersonate', function (): void {
    $actor = userWithRole(RoleName::AdminPkl);
    $target = Student::factory()->create()->user;

    $this->actingAs($actor)
        ->post("/system/users/{$target->id}/impersonate")
        ->assertForbidden();
});
