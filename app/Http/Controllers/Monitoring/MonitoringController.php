<?php

declare(strict_types=1);

namespace App\Http\Controllers\Monitoring;

use App\DataTransferObjects\MonitoringData;
use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\StoreMonitoringRequest;
use App\Http\Requests\Monitoring\UpdateMonitoringRequest;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Services\AttachmentService;
use App\Services\MonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MonitoringRuleException renders itself as a flash message.
 */
class MonitoringController extends Controller
{
    public function __construct(
        private readonly MonitoringService $monitorings,
        private readonly AttachmentService $attachments,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Monitoring::class);

        $table = TableRequest::fromRequest($request, ['placement_id']);

        return Inertia::render('monitorings/index', [
            'monitorings' => $this->monitorings->paginate($table, $request->user()),
            'table' => $table->toArray(),
            'canCreate' => $request->user()->can('create', Monitoring::class),
            'stats' => $this->monitorings->stats($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Monitoring::class);

        return Inertia::render('monitorings/create', [
            'placements' => $this->placementOptions($request),
            // Surfaces the placements that have gone longest without a visit, so the
            // form opens on the work that actually needs doing.
            'needingVisit' => $request->user()->teacher === null ? [] : $this->monitorings
                ->needingVisit($request->user()->teacher)
                ->map(fn (Placement $p): array => [
                    'id' => $p->id,
                    'label' => "{$p->student->name} — {$p->company->name}",
                ])
                ->all(),
        ]);
    }

    public function store(StoreMonitoringRequest $request): RedirectResponse
    {
        $placement = Placement::findOrFail($request->string('placement_id')->toString());

        // `create` has no model to inspect, so the per-placement check lands here:
        // a teacher may only file visits for their own supervision.
        $this->authorize('recordFor', [Monitoring::class, $placement]);

        $monitoring = DB::transaction(function () use ($request, $placement): Monitoring {
            $monitoring = $this->monitorings->record(
                $placement,
                $request->user()->teacher,
                MonitoringData::fromRequest($request),
            );

            $this->storeAttachments($request, $monitoring);

            return $monitoring;
        });

        return redirect()
            ->route('monitorings.show', $monitoring)
            ->with('success', 'Laporan kunjungan tersimpan.');
    }

    public function show(Request $request, Monitoring $monitoring): Response
    {
        $this->authorize('view', $monitoring);

        $monitoring->load([
            'placement:id,student_id,company_id,teacher_id,start_date,end_date,status',
            'placement.student:id,name,nis',
            'placement.company:id,name,address',
            'teacher:id,name,nip',
            'attachments',
        ]);

        $user = $request->user();

        return Inertia::render('monitorings/show', [
            'monitoring' => [
                ...$monitoring->toArray(),
                'has_location' => $monitoring->hasLocation(),
                'attachments' => $this->attachmentPayload($monitoring),
            ],
            'can' => [
                'update' => $user->can('update', $monitoring),
                'delete' => $user->can('delete', $monitoring),
            ],
        ]);
    }

    public function edit(Request $request, Monitoring $monitoring): Response
    {
        $this->authorize('update', $monitoring);

        $monitoring->load(['placement.student:id,name', 'placement.company:id,name', 'attachments']);

        return Inertia::render('monitorings/edit', [
            'monitoring' => [
                ...$monitoring->toArray(),
                'attachments' => $this->attachmentPayload($monitoring),
            ],
            'placementLabel' => sprintf(
                '%s — %s',
                $monitoring->placement->student->name,
                $monitoring->placement->company->name,
            ),
        ]);
    }

    public function update(UpdateMonitoringRequest $request, Monitoring $monitoring): RedirectResponse
    {
        DB::transaction(function () use ($request, $monitoring): void {
            $this->monitorings->update($monitoring, MonitoringData::fromRequest($request));

            $this->storeAttachments($request, $monitoring);
        });

        return redirect()
            ->route('monitorings.show', $monitoring)
            ->with('success', 'Laporan kunjungan diperbarui.');
    }

    public function destroy(Monitoring $monitoring): RedirectResponse
    {
        $this->authorize('delete', $monitoring);

        $this->monitorings->delete($monitoring);

        return redirect()
            ->route('monitorings.index')
            ->with('success', 'Laporan kunjungan dihapus.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachmentPayload(Monitoring $monitoring): array
    {
        return $monitoring->attachments->map(fn ($a): array => [
            'id' => $a->id,
            'original_name' => $a->original_name,
            'caption' => $a->caption,
            'is_image' => $a->isImage(),
            'url' => route('attachments.show', $a),
        ])->all();
    }

    private function storeAttachments(Request $request, Monitoring $monitoring): void
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('attachments', []);

        foreach ($files as $file) {
            $this->attachments->store($monitoring, $file, $request->user());
        }
    }

    /**
     * Only placements this teacher supervises, and only ones a visit may be filed
     * against.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function placementOptions(Request $request): array
    {
        $teacher = $request->user()->teacher;

        if ($teacher === null) {
            return [];
        }

        return Placement::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('status', array_column(MonitoringService::VISITABLE, 'value'))
            ->with(['student:id,name,nis', 'company:id,name'])
            ->get()
            ->map(fn (Placement $p): array => [
                'value' => $p->id,
                'label' => "{$p->student->name} ({$p->student->nis}) — {$p->company->name}",
            ])
            ->all();
    }
}
