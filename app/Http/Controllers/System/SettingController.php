<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpdateSettingRequest;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function edit(): Response
    {
        Gate::authorize('settings.view');

        $weights = $this->settings->weights();

        return Inertia::render('system/settings/index', [
            'weights' => [
                'industry' => $weights['industry'],
                'teacher' => $weights['teacher'],
            ],
            // Admin PKL may read the weights; only Super Admin may change them.
            'editable' => Auth::user()->can('settings.update'),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        $this->settings->setWeights(
            (float) $request->input('industry_weight'),
            (float) $request->input('teacher_weight'),
        );

        return back()->with('success', 'Bobot penilaian diperbarui. Nilai baru memakai bobot ini; nilai yang sudah ada tidak berubah.');
    }
}
