<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\DataTransferObjects\TableRequest;
use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendances) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $table = TableRequest::fromRequest($request, ['status', 'verification_status', 'date']);

        return Inertia::render('attendances/index', [
            'attendances' => $this->attendances->paginate($table, $request->user()),
            'table' => $table->toArray(),
            'statuses' => $this->options(AttendanceStatus::cases()),
            'verificationStatuses' => $this->options(VerificationStatus::cases()),
            'canVerify' => $request->user()->can('attendances.verify'),
        ]);
    }

    public function show(Request $request, Attendance $attendance): Response
    {
        $this->authorize('view', $attendance);

        $attendance->load([
            'placement:id,student_id,company_id,teacher_id',
            'placement.student:id,name,nis',
            'placement.company:id,name,latitude,longitude,geofence_radius_m',
            'verifier:id,name',
        ]);

        return Inertia::render('attendances/show', [
            'attendance' => [
                ...$attendance->toArray(),
                // Resolved here: the raw path is not reachable from the browser, and
                // "outside the geofence" is a comparison the frontend has no radius for.
                'check_in_photo_url' => $this->photoUrl($attendance->check_in_photo_path),
                'check_out_photo_url' => $this->photoUrl($attendance->check_out_photo_path),
                'check_in_outside_geofence' => $attendance->checkInOutsideGeofence(),
            ],
            'can' => [
                'verify' => $request->user()->can('verify', $attendance),
            ],
        ]);
    }

    /**
     * @param  array<int, AttendanceStatus|VerificationStatus>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(
            fn (AttendanceStatus|VerificationStatus $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }

    private function photoUrl(?string $path): ?string
    {
        return $path === null ? null : route('attendances.photo', ['path' => $path]);
    }
}
