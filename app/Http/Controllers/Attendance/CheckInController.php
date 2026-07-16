<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\DataTransferObjects\CheckInData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CheckInRequest;
use App\Http\Requests\Attendance\CheckOutRequest;
use App\Http\Requests\Attendance\StoreAbsenceRequest;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\GeolocationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's own attendance actions. The placement is always resolved from the
 * authenticated user, never from the request — a student can only ever act on
 * their own PKL.
 *
 * AttendanceRuleException renders itself as a flash message.
 */
class CheckInController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendances,
        private readonly GeolocationService $geo,
    ) {}

    public function create(Request $request): Response
    {
        $this->authorize('checkIn', Attendance::class);

        $student = $request->user()->student;
        $placement = $student?->activePlacement()->with('company')->first();
        $today = $placement === null ? null : $this->attendances->todayFor($placement);

        return Inertia::render('attendances/check-in', [
            'placement' => $placement === null ? null : [
                'id' => $placement->id,
                'company' => $placement->company->name,
                'start_date' => $placement->start_date->toDateString(),
                'end_date' => $placement->end_date->toDateString(),
                'geofence' => $this->geo->hasGeofence($placement->company) ? [
                    'latitude' => (float) $placement->company->latitude,
                    'longitude' => (float) $placement->company->longitude,
                    'radius_m' => (int) $placement->company->geofence_radius_m,
                ] : null,
            ],
            'today' => $today === null ? null : [
                'status' => $today->status->value,
                'status_label' => $today->status->label(),
                'check_in_at' => $today->check_in_at?->toIso8601String(),
                'check_out_at' => $today->check_out_at?->toIso8601String(),
                'verification_status' => $today->verification_status->value,
                'verification_label' => $today->verification_status->label(),
            ],
            'withinPeriod' => $placement !== null
                && CarbonImmutable::now()->startOfDay()->betweenIncluded(
                    $placement->start_date,
                    $placement->end_date,
                ),
        ]);
    }

    public function store(CheckInRequest $request): RedirectResponse
    {
        $this->attendances->checkIn(
            $request->user()->student,
            CheckInData::fromRequest($request),
        );

        return back()->with('success', 'Check in berhasil. Menunggu verifikasi pembimbing.');
    }

    public function checkOut(CheckOutRequest $request): RedirectResponse
    {
        $this->attendances->checkOut(
            $request->user()->student,
            CheckInData::fromRequest($request),
        );

        return back()->with('success', 'Check out berhasil. Selamat beristirahat.');
    }

    public function storeAbsence(StoreAbsenceRequest $request): RedirectResponse
    {
        $status = $request->status();

        $this->attendances->requestAbsence(
            $request->user()->student,
            $status,
            (string) $request->string('notes'),
        );

        return back()->with(
            'success',
            sprintf('Pengajuan %s terkirim. Menunggu verifikasi pembimbing.', $status->label()),
        );
    }
}
