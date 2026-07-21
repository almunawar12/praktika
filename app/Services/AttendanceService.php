<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\CheckInData;
use App\DataTransferObjects\TableRequest;
use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\AttendanceRuleException;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AttendanceService
{
    public const SORTABLE = ['date', 'status', 'verification_status', 'created_at'];

    public function __construct(private readonly GeolocationService $geo) {}

    /**
     * Totals for the overview cards, scoped to what the viewer may see.
     *
     * @return array{total: int, verified: int, pending: int, rejected: int}
     */
    public function stats(User $viewer): array
    {
        $counts = Attendance::query()
            ->visibleTo($viewer)
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where verification_status = '".VerificationStatus::Verified->value."') as verified")
            ->selectRaw("count(*) filter (where verification_status = '".VerificationStatus::Pending->value."') as pending")
            ->selectRaw("count(*) filter (where verification_status = '".VerificationStatus::Rejected->value."') as rejected")
            ->first();

        return [
            'total' => (int) ($counts?->getAttribute('total') ?? 0),
            'verified' => (int) ($counts?->getAttribute('verified') ?? 0),
            'pending' => (int) ($counts?->getAttribute('pending') ?? 0),
            'rejected' => (int) ($counts?->getAttribute('rejected') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Attendance>
     */
    public function paginate(TableRequest $table, User $viewer): LengthAwarePaginator
    {
        return Attendance::query()
            ->visibleTo($viewer)
            ->with([
                'placement:id,student_id,company_id,teacher_id',
                'placement.student:id,name,nis',
                'placement.company:id,name,geofence_radius_m',
                'verifier:id,name',
            ])
            ->when($table->search, fn ($query, string $search) => $query
                ->whereHas('placement.student', fn ($q) => $q
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%")))
            ->when($table->filter('status'), fn ($query, string $s) => $query->where('status', $s))
            ->when($table->filter('verification_status'), fn ($query, string $s) => $query
                ->where('verification_status', $s))
            ->when($table->filter('date'), fn ($query, string $date) => $query->whereDate('date', $date))
            ->orderBy(...$table->sortOrder(self::SORTABLE))
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * The student's active placement, or a failure they can act on.
     *
     * @throws AttendanceRuleException
     */
    public function activePlacementFor(Student $student): Placement
    {
        $placement = $student->activePlacement()->with('company')->first();

        if ($placement === null) {
            throw AttendanceRuleException::noActivePlacement();
        }

        return $placement;
    }

    public function todayFor(Placement $placement): ?Attendance
    {
        return Attendance::query()
            ->where('placement_id', $placement->id)
            ->whereDate('date', CarbonImmutable::now()->toDateString())
            ->first();
    }

    /**
     * @throws AttendanceRuleException
     */
    public function checkIn(Student $student, CheckInData $data): Attendance
    {
        $placement = $this->activePlacementFor($student);

        $this->assertWithinPlacementPeriod($placement);
        $this->assertNoRecordToday($placement);

        $distance = $this->assertWithinGeofence($placement->company, $data);

        return DB::transaction(function () use ($placement, $data, $distance): Attendance {
            // Stored only after every rule has passed, so a rejected attempt does not
            // leave an orphan file behind.
            $path = $this->storePhoto($placement, $data, 'check-in');

            try {
                return Attendance::create([
                    'placement_id' => $placement->id,
                    'date' => CarbonImmutable::now()->toDateString(),
                    'status' => AttendanceStatus::Present,
                    'check_in_at' => CarbonImmutable::now(),
                    'check_in_latitude' => $data->latitude,
                    'check_in_longitude' => $data->longitude,
                    'check_in_photo_path' => $path,
                    'check_in_distance_m' => $distance,
                    'verification_status' => VerificationStatus::Pending,
                ]);
            } catch (QueryException $exception) {
                Storage::disk($this->disk())->delete($path);

                // Two taps on a flaky connection both pass assertNoRecordToday; the
                // unique index is what actually stops the second.
                if ($this->isDuplicateDay($exception)) {
                    throw AttendanceRuleException::alreadyCheckedIn();
                }

                throw $exception;
            }
        });
    }

    /**
     * @throws AttendanceRuleException
     */
    public function checkOut(Student $student, CheckInData $data): Attendance
    {
        $placement = $this->activePlacementFor($student);
        $attendance = $this->todayFor($placement);

        if ($attendance === null || $attendance->check_in_at === null) {
            throw AttendanceRuleException::notCheckedIn();
        }

        if ($attendance->hasCheckedOut()) {
            throw AttendanceRuleException::alreadyCheckedOut();
        }

        $distance = $this->assertWithinGeofence($placement->company, $data);

        return DB::transaction(function () use ($placement, $attendance, $data, $distance): Attendance {
            $path = $this->storePhoto($placement, $data, 'check-out');

            $attendance->update([
                'check_out_at' => CarbonImmutable::now(),
                'check_out_latitude' => $data->latitude,
                'check_out_longitude' => $data->longitude,
                'check_out_photo_path' => $path,
                'check_out_distance_m' => $distance,
            ]);

            return $attendance;
        });
    }

    /**
     * A student declaring izin or sakit for today.
     *
     * Recorded as pending: the claim is the student's, but the decision is the
     * supervisor's — otherwise anyone could opt out of `absent` at will.
     *
     * @throws AttendanceRuleException
     */
    public function requestAbsence(Student $student, AttendanceStatus $status, string $notes): Attendance
    {
        $placement = $this->activePlacementFor($student);

        $this->assertWithinPlacementPeriod($placement);
        $this->assertNoRecordToday($placement);

        try {
            return Attendance::create([
                'placement_id' => $placement->id,
                'date' => CarbonImmutable::now()->toDateString(),
                'status' => $status,
                'notes' => $notes,
                'verification_status' => VerificationStatus::Pending,
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateDay($exception)) {
                throw AttendanceRuleException::alreadyCheckedIn();
            }

            throw $exception;
        }
    }

    public function verify(Attendance $attendance, User $verifier, ?string $note): Attendance
    {
        return $this->decide($attendance, $verifier, VerificationStatus::Verified, $note);
    }

    public function reject(Attendance $attendance, User $verifier, string $note): Attendance
    {
        return $this->decide($attendance, $verifier, VerificationStatus::Rejected, $note);
    }

    /**
     * Attendance summary for a placement, counting only records that survived
     * verification.
     *
     * @return array<string, int>
     */
    public function recapFor(Placement $placement): array
    {
        $counts = Attendance::query()
            ->where('placement_id', $placement->id)
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status')
            ->all();

        $recap = [];

        foreach (AttendanceStatus::cases() as $status) {
            $recap[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $recap;
    }

    private function decide(
        Attendance $attendance,
        User $verifier,
        VerificationStatus $status,
        ?string $note,
    ): Attendance {
        $attendance->update([
            'verification_status' => $status,
            'verified_by' => $verifier->id,
            'verified_at' => CarbonImmutable::now(),
            'verification_note' => $note,
        ]);

        return $attendance;
    }

    /**
     * @throws AttendanceRuleException
     */
    private function assertWithinPlacementPeriod(Placement $placement): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        if ($today->lt($placement->start_date) || $today->gt($placement->end_date)) {
            throw AttendanceRuleException::outsidePlacementPeriod();
        }
    }

    /**
     * @throws AttendanceRuleException
     */
    private function assertNoRecordToday(Placement $placement): void
    {
        if ($this->todayFor($placement) !== null) {
            throw AttendanceRuleException::alreadyCheckedIn();
        }
    }

    /**
     * Returns the recorded distance, or null when the company has no geofence.
     *
     * @throws AttendanceRuleException
     */
    private function assertWithinGeofence(Company $company, CheckInData $data): ?int
    {
        $distance = $this->geo->distanceToCompany($company, $data->latitude, $data->longitude);

        if ($distance === null) {
            return null;
        }

        // Distance is recorded even where no radius is set, so a supervisor can still
        // see where the student was.
        if (! $this->geo->hasGeofence($company)) {
            return $distance;
        }

        $radius = (int) $company->geofence_radius_m;

        if ($distance > $radius) {
            throw AttendanceRuleException::outsideGeofence($company, $distance, $radius);
        }

        return $distance;
    }

    /**
     * @throws RuntimeException
     */
    private function storePhoto(Placement $placement, CheckInData $data, string $kind): string
    {
        $path = $data->photo->store(
            sprintf('attendances/%s/%s', $placement->id, $kind),
            $this->disk(),
        );

        // `store()` returns false when the disk write fails. Carrying on would record
        // attendance whose evidence does not exist, so this aborts the transaction.
        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan foto absensi.');
        }

        return $path;
    }

    private function disk(): string
    {
        return (string) config('filesystems.default');
    }

    private function isDuplicateDay(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'attendances_placement_id_date_unique');
    }
}
