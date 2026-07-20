<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
    $this->company = Company::factory()->create();
});

function supervisorPayload(Company $company, array $overrides = []): array
{
    return array_merge([
        'company_id' => $company->id,
        'name' => 'Rina Wijaya',
        'email' => 'rina@dudi.test',
        'position' => 'HRD',
        'phone' => '081200000001',
    ], $overrides);
}

test('creating a supervisor also creates a pembimbing industri login account', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/supervisors', supervisorPayload($this->company))
        ->assertRedirect();

    $user = CompanySupervisor::firstOrFail()->user;

    expect($user->email)->toBe('rina@dudi.test')
        ->and($user->hasRole(RoleName::PembimbingIndustri->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

test('show and edit return json for the modal', function (): void {
    $supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->getJson("/master/supervisors/{$supervisor->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $supervisor->id)
        ->assertJsonPath('data.company.id', $this->company->id);

    $this->actingAs($this->admin)
        ->getJson("/master/supervisors/{$supervisor->id}/edit")
        ->assertOk()
        ->assertJsonPath('data.user.email', $supervisor->user->email);
});

test('the supervisor index carries overview stats', function (): void {
    CompanySupervisor::factory()->count(2)->create(['company_id' => $this->company->id]);
    $other = Company::factory()->create();
    $inactive = CompanySupervisor::factory()->create(['company_id' => $other->id]);
    $inactive->user->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->get('/master/supervisors')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.total', 3)
            ->where('stats.active', 2)
            ->where('stats.inactive', 1)
            ->where('stats.companies', 2)
            ->etc()
        );
});

test('the supervisor create page route is removed', function (): void {
    $this->actingAs($this->admin)->get('/master/supervisors/create')->assertNotFound();
});

test('supervisor create and update redirect to the index', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/supervisors', supervisorPayload($this->company))
        ->assertRedirect('/master/supervisors');

    $supervisor = CompanySupervisor::firstOrFail();

    $this->actingAs($this->admin)
        ->put("/master/supervisors/{$supervisor->id}", supervisorPayload($this->company, ['name' => 'Baru']))
        ->assertRedirect('/master/supervisors');
});

test('a failed create leaves no orphan user behind', function (): void {
    User::factory()->create(['email' => 'taken@dudi.test']);
    $before = User::count();

    $this->actingAs($this->admin)
        ->post('/master/supervisors', supervisorPayload($this->company, ['email' => 'taken@dudi.test']))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe($before)
        ->and(CompanySupervisor::count())->toBe(0);
});

test('editing a supervisor keeps its account in step', function (): void {
    $supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->put("/master/supervisors/{$supervisor->id}", supervisorPayload($this->company, [
            'name' => 'Nama Diubah',
            'email' => 'diubah@dudi.test',
        ]))
        ->assertRedirect();

    $supervisor->refresh();

    expect($supervisor->name)->toBe('Nama Diubah')
        ->and($supervisor->user->name)->toBe('Nama Diubah')
        ->and($supervisor->user->email)->toBe('diubah@dudi.test');
});

test('deleting a supervisor deactivates the account rather than removing it', function (): void {
    $supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);
    $userId = $supervisor->user_id;

    $this->actingAs($this->admin)
        ->delete("/master/supervisors/{$supervisor->id}")
        ->assertRedirect('/master/supervisors');

    $this->assertSoftDeleted($supervisor);
    // The account is the causer on verification entries that must stay attributable.
    $this->assertDatabaseHas('users', ['id' => $userId, 'is_active' => false]);
});

test('an email must not collide with any existing account', function (): void {
    $guru = userWithRole(RoleName::GuruPembimbing);

    $this->actingAs($this->admin)
        ->post('/master/supervisors', supervisorPayload($this->company, ['email' => $guru->email]))
        ->assertSessionHasErrors('email');
});

test('a supervisor can view their own profile but not delete it', function (): void {
    $supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);

    expect($supervisor->user->can('view', $supervisor))->toBeTrue()
        // Would deactivate the account they are acting from.
        ->and($supervisor->user->can('delete', $supervisor))->toBeFalse();
});

test('the module is closed to guru and siswa', function (): void {
    foreach ([RoleName::GuruPembimbing, RoleName::Siswa] as $role) {
        $this->actingAs(userWithRole($role))
            ->get('/master/supervisors')
            ->assertForbidden();
    }
});
