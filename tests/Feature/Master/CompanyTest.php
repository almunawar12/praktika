<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Company;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
});

function companyPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'PT Praktika Jaya',
        'business_field' => 'Teknologi Informasi',
        'address' => 'Jl. Merdeka No. 1',
        'pic_name' => 'Budi',
        'pic_phone' => '081234567890',
        'quota' => 5,
        'is_active' => true,
    ], $overrides);
}

test('admin can create a company and is redirected to the index', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/companies', companyPayload())
        ->assertRedirect('/master/companies');

    $this->assertDatabaseHas('companies', ['name' => 'PT Praktika Jaya']);
});

test('show and edit return json for the modal', function (): void {
    $company = Company::factory()->create(['name' => 'PT Contoh']);

    $this->actingAs($this->admin)
        ->getJson("/master/companies/{$company->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'PT Contoh')
        ->assertJsonPath('data.is_active', $company->is_active);

    $this->actingAs($this->admin)
        ->getJson("/master/companies/{$company->id}/edit")
        ->assertOk()
        ->assertJsonPath('data.id', $company->id);
});

test('the company index carries overview stats', function (): void {
    Company::factory()->count(2)->create(['quota' => 5]);
    Company::factory()->create(['is_active' => false, 'quota' => 3]);

    $this->actingAs($this->admin)
        ->get('/master/companies')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.total', 3)
            ->where('stats.active', 2)
            ->where('stats.inactive', 1)
            ->where('stats.quota', 13)
            ->etc()
        );
});

test('the company create page route is removed', function (): void {
    $this->actingAs($this->admin)->get('/master/companies/create')->assertNotFound();
});

test('company update redirects to the index', function (): void {
    $company = Company::factory()->create();

    $this->actingAs($this->admin)
        ->put("/master/companies/{$company->id}", companyPayload(['name' => 'PT Baru']))
        ->assertRedirect('/master/companies');

    expect($company->refresh()->name)->toBe('PT Baru');
});

test('a siswa can not reach the companies module for writes', function (): void {
    $siswa = userWithRole(RoleName::Siswa);

    $this->actingAs($siswa)
        ->post('/master/companies', companyPayload())
        ->assertForbidden();
});
