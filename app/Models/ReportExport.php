<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use Carbon\CarbonImmutable;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $requested_by
 * @property ReportType $type
 * @property ReportFormat $format
 * @property ReportStatus $status
 * @property array<string, mixed> $filters
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $row_count
 * @property CarbonImmutable|null $completed_at
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $requester
 */
#[Fillable([
    'requested_by', 'type', 'format', 'status', 'filters',
    'file_path', 'file_name', 'row_count', 'completed_at', 'error',
])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'format' => ReportFormat::class,
            'status' => ReportStatus::class,
            'filters' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('report-export')
            ->logOnly(['type', 'format', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status->isDownloadable() && $this->file_path !== null;
    }

    /**
     * Exports are private to whoever asked for them — even an admin sees only their
     * own (ADD § 9). Restricting by role would leak one user's filtered extract of
     * student data to another.
     *
     * @param  Builder<ReportExport>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('requested_by', $user->id);
    }
}
