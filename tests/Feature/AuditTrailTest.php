<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Str;

test('a successful login is written to the audit trail', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $activity = Activity::where('log_name', 'auth')->where('event', 'login')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->description)->toBe('Pengguna berhasil masuk');
});

test('a successful login stamps last_login_at and the ip', function (): void {
    $user = userWithRole(RoleName::Siswa);

    expect($user->last_login_at)->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $user->refresh();

    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();
});

test('a logout is written to the audit trail', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $this->actingAs($user)->post('/logout');

    $activity = Activity::where('log_name', 'auth')->where('event', 'logout')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id);
});

test('a failed login is recorded without ever storing the submitted password', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $this->post('/login', ['email' => $user->email, 'password' => 'hunter2-secret']);

    $activity = Activity::where('event', 'login_failed')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['email'])->toBe($user->email);

    // The audit log is readable by admins, so a leaked password there would be worse
    // than no log at all.
    expect(json_encode($activity->properties))->not->toContain('hunter2-secret');
});

test('user changes are logged, and the password hash is never among them', function (): void {
    $user = userWithRole(RoleName::AdminPkl);

    $user->update(['name' => 'Nama Baru', 'password' => 'a-brand-new-password']);

    // Filtered by event, not by `latest()`: creating the user already wrote a
    // `created` entry in the same second, and ordering on created_at alone ties.
    $activity = Activity::where('log_name', 'user')->where('event', 'updated')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['attributes']['name'])->toBe('Nama Baru')
        ->and($activity->properties['attributes'])->not->toHaveKey('password')
        ->and(json_encode($activity->properties))->not->toContain('a-brand-new-password');
});

test('activity log ids and causer ids are uuids', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $this->actingAs($user)->post('/logout');

    $activity = Activity::where('event', 'logout')->firstOrFail();

    expect(Str::isUuid($activity->id))->toBeTrue()
        ->and(Str::isUuid($activity->causer_id))->toBeTrue();
});

test('the causer of an audit entry resolves back to the user', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $this->actingAs($user)->post('/logout');

    $causer = Activity::where('event', 'logout')->firstOrFail()->causer;

    expect($causer)->toBeInstanceOf(User::class)
        ->and($causer->id)->toBe($user->id);
});
