<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\VerifyAttendanceRequest;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;

class AttendanceVerificationController extends Controller
{
    public function __construct(private readonly AttendanceService $attendances) {}

    public function __invoke(VerifyAttendanceRequest $request, Attendance $attendance): RedirectResponse
    {
        $note = $request->filled('note') ? (string) $request->string('note') : null;

        if ($request->isRejection()) {
            $this->attendances->reject($attendance, $request->user(), (string) $note);

            return back()->with('success', 'Absensi ditolak. Siswa dapat melihat alasannya.');
        }

        $this->attendances->verify($attendance, $request->user(), $note);

        return back()->with('success', 'Absensi terverifikasi.');
    }
}
