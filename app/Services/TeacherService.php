<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TableRequest;
use App\DataTransferObjects\TeacherData;
use App\Enums\RoleName;
use App\Exceptions\ResourceInUseException;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TeacherService
{
    public const SORTABLE = ['nip', 'name', 'created_at'];

    public function __construct(private readonly UserAccountService $accounts) {}

    /**
     * Totals for the overview cards.
     *
     * @return array{total: int, active: int, inactive: int, supervising: int}
     */
    public function stats(): array
    {
        $counts = Teacher::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where exists (select 1 from users where users.id = teachers.user_id and users.is_active)) as active')
            ->selectRaw('count(*) filter (where exists (select 1 from placements where placements.teacher_id = teachers.id and placements.deleted_at is null)) as supervising')
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $active = (int) ($counts?->getAttribute('active') ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'supervising' => (int) ($counts?->getAttribute('supervising') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Teacher>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return Teacher::query()
            ->with('user:id,email,is_active')
            ->when($table->search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('nip', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('email', 'ilike', "%{$search}%"));
                });
            })
            ->when($table->filter('is_active'), function ($query, string $value): void {
                $query->whereHas('user', fn ($query) => $query->where('is_active', $value === 'active'));
            })
            ->orderBy(...$table->sortOrder(self::SORTABLE))
            ->paginate($table->perPage)
            ->withQueryString();
    }

    public function create(TeacherData $data): Teacher
    {
        // One transaction: a teacher row without its account, or an account without
        // its teacher row, is a broken state neither screen can repair.
        return DB::transaction(function () use ($data): Teacher {
            $user = $this->accounts->createFor(
                RoleName::GuruPembimbing,
                $data->name,
                $data->email,
                $data->phone,
            );

            return Teacher::create([
                'user_id' => $user->id,
                'nip' => $data->nip,
                'name' => $data->name,
                'phone' => $data->phone,
            ]);
        });
    }

    public function update(Teacher $teacher, TeacherData $data): Teacher
    {
        return DB::transaction(function () use ($teacher, $data): Teacher {
            $teacher->update([
                'nip' => $data->nip,
                'name' => $data->name,
                'phone' => $data->phone,
            ]);

            $this->accounts->syncFromProfile(
                $teacher->user,
                $data->name,
                $data->email,
                $data->phone,
            );

            return $teacher;
        });
    }

    /**
     * @throws ResourceInUseException
     */
    public function delete(Teacher $teacher): void
    {
        // placements.teacher_id is RESTRICT. Reassign the placements first, or the
        // PKL record loses the supervisor it names.
        if ($teacher->placements()->exists()) {
            throw new ResourceInUseException(
                'Guru tidak dapat dihapus karena masih tercatat sebagai pembimbing pada penempatan PKL.'
            );
        }

        DB::transaction(function () use ($teacher): void {
            // The account is deactivated, not deleted: it is the causer on audit-log
            // entries that must remain attributable.
            $this->accounts->deactivate($teacher->user);

            $teacher->delete();
        });
    }
}
