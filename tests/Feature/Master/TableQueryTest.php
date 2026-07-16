<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\Student;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
});

test('pagination is server side and honours per_page', function (): void {
    Company::factory()->count(30)->create();

    $this->actingAs($this->admin)
        ->get('/master/companies?per_page=25')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('companies.data', 25)
            ->where('companies.total', 30)
            ->where('companies.last_page', 2)
        );
});

test('per_page is clamped so a client can not ask for the whole table', function (): void {
    Company::factory()->count(20)->create();

    // Without the clamp this would be an easy way to melt the server.
    $this->actingAs($this->admin)
        ->get('/master/companies?per_page=100000')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('companies.per_page', 100));
});

test('sorting is restricted to the allow-list', function (): void {
    Company::factory()->create(['name' => 'Alpha']);
    Company::factory()->create(['name' => 'Zulu']);

    $this->actingAs($this->admin)
        ->get('/master/companies?sort=name&direction=desc')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('companies.data.0.name', 'Zulu'));

    // An unknown column must fall back to the default rather than reach ORDER BY.
    $this->actingAs($this->admin)
        ->get('/master/companies?sort=password&direction=asc')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('companies.data.0.name', 'Alpha'));
});

test('a sql injection attempt through sort is ignored, not executed', function (): void {
    Company::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/master/companies?sort='.urlencode('name; drop table companies--'))
        ->assertOk();

    // The table is still there, which is the whole point.
    expect(Company::count())->toBe(3);
});

test('companies can be filtered by active status', function (): void {
    Company::factory()->count(4)->create();
    Company::factory()->count(2)->inactive()->create();

    $this->actingAs($this->admin)
        ->get('/master/companies?is_active=inactive')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 2));

    $this->actingAs($this->admin)
        ->get('/master/companies?is_active=active')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 4));
});

test('search and filter combine rather than override each other', function (): void {
    Company::factory()->create(['name' => 'Nusantara Digital']);
    Company::factory()->inactive()->create(['name' => 'Nusantara Logistik']);
    Company::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/master/companies?search=Nusantara&is_active=active')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Nusantara Digital')
        );
});

test('the index does not N+1 across relations', function (): void {
    Student::factory()->count(15)->create();

    DB::enableQueryLog();

    $this->actingAs($this->admin)->get('/master/students')->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 15 students, each with a user + class + department. Lazy loading would put
    // this in the dozens; eager loading keeps it flat.
    expect($queries)->toBeLessThan(15);
});
