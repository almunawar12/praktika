<?php

declare(strict_types=1);

use App\Enums\PlacementStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Placement;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
});

test('draft to active to completed is the happy path', function (): void {
    $placement = Placement::factory()->create();

    $this->actingAs($this->admin)->put("/pkl/placements/{$placement->id}/activate");
    expect($placement->refresh()->status)->toBe(PlacementStatus::Active);

    $this->actingAs($this->admin)->put("/pkl/placements/{$placement->id}/complete");
    expect($placement->refresh()->status)->toBe(PlacementStatus::Completed);
});

test('completed and cancelled are terminal', function (PlacementStatus $terminal): void {
    $placement = Placement::factory()->state(['status' => $terminal])->create();

    // Re-opening a finished PKL would silently change what its attendance and
    // assessment records mean.
    foreach (['activate', 'complete'] as $action) {
        $this->actingAs($this->admin)
            ->put("/pkl/placements/{$placement->id}/{$action}")
            ->assertForbidden();
    }

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$placement->id}/cancel", ['cancel_reason' => 'apa pun'])
        ->assertForbidden();

    expect($placement->refresh()->status)->toBe($terminal);
})->with([
    'completed' => PlacementStatus::Completed,
    'cancelled' => PlacementStatus::Cancelled,
]);

test('a draft can not skip straight to completed', function (): void {
    $placement = Placement::factory()->create();

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$placement->id}/complete")
        ->assertForbidden();

    expect($placement->refresh()->status)->toBe(PlacementStatus::Draft);
});

test('cancelling requires a reason and records it', function (): void {
    $placement = Placement::factory()->active()->create();

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$placement->id}/cancel", ['cancel_reason' => ''])
        ->assertSessionHasErrors('cancel_reason');

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$placement->id}/cancel", ['cancel_reason' => 'Siswa pindah sekolah'])
        ->assertSessionHasNoErrors();

    $placement->refresh();

    expect($placement->status)->toBe(PlacementStatus::Cancelled)
        ->and($placement->cancel_reason)->toBe('Siswa pindah sekolah');
});

test('only a draft can be deleted', function (): void {
    $draft = Placement::factory()->create();
    $active = Placement::factory()->active()->create();

    $this->actingAs($this->admin)
        ->delete("/pkl/placements/{$draft->id}")
        ->assertRedirect('/pkl/placements');
    $this->assertSoftDeleted($draft);

    // An active PKL has history behind it; cancelling is the way to retire it.
    $this->actingAs($this->admin)
        ->delete("/pkl/placements/{$active->id}")
        ->assertForbidden();
    $this->assertNotSoftDeleted($active);
});

test('a completed placement can no longer be edited', function (): void {
    $placement = Placement::factory()->completed()->create();

    $this->actingAs($this->admin)
        ->get("/pkl/placements/{$placement->id}/edit")
        ->assertForbidden();
});

test('every transition is written to the audit trail', function (): void {
    $placement = Placement::factory()->create();

    $this->actingAs($this->admin)->put("/pkl/placements/{$placement->id}/activate");
    $this->actingAs($this->admin)->put("/pkl/placements/{$placement->id}/complete");

    $entries = Activity::where('log_name', 'placement')
        ->where('subject_id', $placement->id)
        ->where('event', 'updated')
        ->get();

    // Separate endpoints mean separate entries, rather than one generic "updated".
    expect($entries)->toHaveCount(2);

    $statuses = $entries->map(fn ($a) => $a->properties['attributes']['status'])->all();

    expect($statuses)->toContain('active', 'completed');
});
