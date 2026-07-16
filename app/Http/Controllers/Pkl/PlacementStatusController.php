<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pkl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pkl\CancelPlacementRequest;
use App\Models\Placement;
use App\Services\PlacementService;
use Illuminate\Http\RedirectResponse;

/**
 * Status transitions live apart from PlacementController on purpose: they carry
 * their own permissions (placements.activate / .complete / .cancel), their own
 * rules, and their own audit entries. Folding them into a status dropdown on the
 * edit form would collapse all three into a generic "updated".
 *
 * PlacementRuleException renders itself as a flash message.
 */
class PlacementStatusController extends Controller
{
    public function __construct(private readonly PlacementService $placements) {}

    public function activate(Placement $placement): RedirectResponse
    {
        $this->authorize('activate', $placement);

        $this->placements->activate($placement);

        return back()->with('success', 'Penempatan diaktifkan. Siswa dapat mulai absensi dan mengisi jurnal.');
    }

    public function complete(Placement $placement): RedirectResponse
    {
        $this->authorize('complete', $placement);

        $this->placements->complete($placement);

        return back()->with('success', 'Penempatan ditandai selesai.');
    }

    public function cancel(CancelPlacementRequest $request, Placement $placement): RedirectResponse
    {
        $this->placements->cancel($placement, (string) $request->string('cancel_reason'));

        return back()->with('success', 'Penempatan dibatalkan.');
    }
}
