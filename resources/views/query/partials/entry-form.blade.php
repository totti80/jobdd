@php
    $compact = $compact ?? false;
    $occupations = $occupations ?? \App\Http\Controllers\JobSearchController::OCCUPATIONS;
    $regions = $regions ?? \App\Http\Controllers\JobSearchController::REGIONS;
    $tools = $tools ?? \App\Services\JobDecisionUseCaseService::TOOLS;
    $values = $values ?? [];
    $fieldErrors = $fieldErrors ?? [];
@endphp
    <form id="entry-form" method="POST" action="{{ route('jobs.store') }}" aria-labelledby="entry-form-title" class="jobdd-card jobdd-entry-form space-y-7{{ $compact ? ' home-entry-form' : '' }}">
        @csrf
        <div class="border-b border-slate-200 pb-5">
            <h2 id="entry-form-title" class="flex items-center gap-3 text-xl font-bold text-blue-950"><span class="jobdd-icon-tile"><x-jobdd-icon name="compare" /></span>{{ $compact ? 'かんたん入力' : '4つの条件を入力' }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">対象：近畿6府県 × 機械設計・電気設計</p>
        </div>
        <div class="{{ $compact ? 'home-entry-fields' : 'space-y-7' }}">
        <fieldset aria-describedby="occupation-help{{ isset($fieldErrors['occupation']) ? ' occupation-error' : '' }}">
            <legend class="font-semibold">職種 <span class="text-sm font-normal text-slate-600">必須</span></legend>
            <p id="occupation-help" class="mt-2 text-sm text-slate-600">希望する職種を1つ選んでください。</p>
            <div class="mt-3 grid gap-3 min-[360px]:grid-cols-2">
                @foreach ($occupations as $index => $occupation)
                    <label class="jobdd-choice" for="occupation-{{ $index }}">
                        <input id="occupation-{{ $index }}" name="occupation" type="radio" value="{{ $occupation }}" required @checked(($values['occupation'] ?? '') === $occupation)
                            @if (isset($fieldErrors['occupation'])) aria-invalid="true" @endif
                            aria-describedby="occupation-help{{ isset($fieldErrors['occupation']) ? ' occupation-error' : '' }}">
                        <span class="font-semibold">{{ $occupation }}</span>
                    </label>
                @endforeach
            </div>
            @if (isset($fieldErrors['occupation']))<p id="occupation-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['occupation'])) }}</p>@endif
        </fieldset>
        <div>
            <label for="region" class="block font-semibold">希望地域 <span class="text-sm font-normal text-slate-600">必須</span></label>
            <select id="region" name="region" required class="mt-2 min-h-12 w-full rounded-lg border border-slate-500 bg-white px-3 py-3 sm:max-w-80"
                aria-describedby="region-help{{ isset($fieldErrors['region']) ? ' region-error' : '' }}" @if (isset($fieldErrors['region'])) aria-invalid="true" @endif>
                <option value="">選んでください</option>
                @foreach ($regions as $region)<option value="{{ $region }}" @selected(($values['region'] ?? '') === $region)>{{ $region }}</option>@endforeach
            </select>
            <p id="region-help" class="mt-2 text-sm text-slate-600">近畿6府県から1つ選んでください。</p>
            @if (isset($fieldErrors['region']))<p id="region-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['region'])) }}</p>@endif
        </div>
        <div>
            <label for="salary_min" class="block font-semibold">希望年収の下限 <span class="text-sm font-normal text-slate-600">任意</span></label>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <input id="salary_min" name="salary_min" type="number" inputmode="numeric" min="1" max="10000" step="1" value="{{ $values['salary_min'] ?? '' }}" placeholder="例：600"
                    aria-describedby="salary-help{{ isset($fieldErrors['salary_min']) ? ' salary-error' : '' }}" @if (isset($fieldErrors['salary_min'])) aria-invalid="true" @endif
                    class="min-h-12 min-w-0 w-48 max-w-full rounded-lg border border-slate-500 px-3 py-3">
                <span>万円以上</span>
            </div>
            <p id="salary-help" class="mt-2 text-sm leading-6 text-slate-600">未指定の場合は空欄にしてください（1〜10,000万円）。年収による絞り込みはせず、掲載情報との違いを表示します。</p>
            @if (isset($fieldErrors['salary_min']))<p id="salary-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['salary_min'])) }}</p>@endif
        </div>
        <div data-tool-group class="jobdd-tool-group">
        @include('query.partials.tool-selector', ['tools' => $tools, 'selected' => $values['tools'] ?? [], 'idPrefix' => 'start-tools', 'toolErrors' => $fieldErrors['tools'] ?? [], 'wide' => $compact])
        @if ($compact)
        <details class="entry-custom-tools mt-5 border-t border-slate-200 pt-5" @if (isset($fieldErrors['custom_tools'])) open @endif>
            <summary class="jobdd-link cursor-pointer">その他のCAD・ツールを入力（任意）</summary>
        @else
        <div class="mt-5 border-t border-slate-200 pt-5">
        @endif
            <label for="custom_tools" class="block font-semibold">その他のCAD・ツール <span class="text-sm font-normal text-slate-600">任意</span></label>
            <textarea id="custom_tools" name="custom_tools" rows="3" maxlength="500" placeholder="iCAD SX、EPLAN、ANSYS など"
                aria-describedby="custom-tools-help{{ isset($fieldErrors['custom_tools']) ? ' custom-tools-error' : '' }}" @if (isset($fieldErrors['custom_tools'])) aria-invalid="true" @endif
                class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-500 px-3 py-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 aria-invalid:border-red-700">{{ $values['custom_tools'] ?? '' }}</textarea>
            <p id="custom-tools-help" class="mt-2 text-sm leading-6 text-slate-600">500文字以内。自由記述のツールは判定未対応です。希望条件として保持し、一致判定には使用しません。</p>
            @if (isset($fieldErrors['custom_tools']))<p id="custom-tools-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['custom_tools'])) }}</p>@endif
        @if ($compact)</details>@else</div>@endif
        </div>
        </div>
        <div class="border-t border-slate-200 pt-6">
            <button type="submit" class="jobdd-button w-full">求人候補を見る</button>
            <p class="mt-3 text-sm leading-6 text-slate-600">対象は近畿6府県の機械設計・電気設計です。条件が異なる求人や、情報が未確認の求人も表示されます。</p>
        </div>
    </form>
