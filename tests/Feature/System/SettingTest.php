<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Services\SettingService;

test('a super admin updates the assessment weights', function (): void {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->put('/system/settings', ['industry_weight' => 0.7, 'teacher_weight' => 0.3])
        ->assertSessionHasNoErrors();

    $weights = app(SettingService::class)->weights();

    expect($weights['industry'])->toBe(0.7)
        ->and($weights['teacher'])->toBe(0.3);
});

test('weights that do not sum to 100% are refused', function (): void {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->put('/system/settings', ['industry_weight' => 0.7, 'teacher_weight' => 0.7])
        ->assertSessionHasErrors('teacher_weight');
});

test('an admin pkl may read the settings but not change them', function (): void {
    $this->actingAs(userWithRole(RoleName::AdminPkl))->get('/system/settings')->assertOk();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->put('/system/settings', ['industry_weight' => 0.5, 'teacher_weight' => 0.5])
        ->assertForbidden();
});

test('a guru can not reach the settings at all', function (): void {
    $this->actingAs(userWithRole(RoleName::GuruPembimbing))->get('/system/settings')->assertForbidden();
});
