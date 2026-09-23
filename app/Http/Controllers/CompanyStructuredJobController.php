<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyStructuredJobRequest;
use App\Models\JobPosting;
use App\Services\StructuredProfileCompletionService;
use App\Support\StructuredJobOptions as Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyStructuredJobController extends Controller
{
    public function edit(JobPosting $jobPosting, int $step, StructuredProfileCompletionService $completion): View
    {
        Gate::authorize('view', $jobPosting);
        $jobPosting->load(['structuredProfile', 'toolUsages' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'), 'typicalDayItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);

        return view('company.jobs.structured', ['job' => $jobPosting, 'step' => $step, 'completion' => $completion->calculate($jobPosting)]);
    }

    public function update(CompanyStructuredJobRequest $request, JobPosting $jobPosting, int $step): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($jobPosting, $step, $data) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($jobPosting->id);
            Gate::authorize('update', $job);
            abort_unless($job->authoringEditable(), 409, '公開Snapshotのない既存求人は編集できません。');
            $profile = [];
            foreach (Options::FIELDS[$step] as $field => $label) {
                $profile[$field] = $data[$field] ?? (in_array($field, ['design_phases', 'collaborators']) ? [] : null);
            }
            $job->structuredProfile()->updateOrCreate([], $profile);
            if (in_array($step, [2, 5])) {
                $relation = $step === 2 ? $job->toolUsages() : $job->typicalDayItems();
                $rows = array_values(array_filter($data[$step === 2 ? 'tools' : 'typical_day'] ?? [], fn ($row) => collect($row)->contains(fn ($value) => filled($value))));
                $relation->delete();
                foreach ($rows as $index => $row) {
                    $relation->create([...$row, 'sort_order' => $index]);
                }
            }
            $job->touch();
        });
        $navigation = $data['navigation'] ?? 'save';
        if ($navigation === 'back' && $step === 1) {
            return redirect()->route('company.jobs.basic.edit', $jobPosting)->with('status', 'STEP 1を保存しました。');
        }
        if ($step === 5 && $navigation === 'next') {
            return redirect()->route('company.jobs.preview', $jobPosting)->with('status', 'STEP 5を保存しました。');
        }
        $target = $navigation === 'next' ? min(5, $step + 1) : ($navigation === 'back' ? $step - 1 : $step);

        return redirect()->route('company.jobs.structured.edit', [$jobPosting, $target])->with('status', "STEP {$step}を保存しました。");
    }
}
