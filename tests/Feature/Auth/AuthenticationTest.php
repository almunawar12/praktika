<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('login screen can be rendered', function (): void {
    $this->get('/login')->assertOk();
});

test('users can authenticate using the login screen', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('users can logout', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('deactivated users can not authenticate even with the correct password', function (): void {
    $user = User::factory()->inactive()->withRole(RoleName::Siswa)->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a user deactivated mid-session is logged out on the next request', function (): void {
    $user = userWithRole(RoleName::Siswa);

    $this->actingAs($user);
    $this->assertAuthenticated();

    $user->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('login is rate limited after five failed attempts', function (): void {
    $user = userWithRole(RoleName::Siswa);

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // Correct credentials are refused too: the lockout is on the email+IP pair,
    // not on whether the password happens to be right.
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('praktika does not expose a registration route', function (): void {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('guests are redirected to login when visiting the dashboard', function (): void {
    $this->get('/dashboard')->assertRedirect(route('login'));
});
