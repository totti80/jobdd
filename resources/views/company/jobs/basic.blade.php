@php($readOnly = $job->exists && ! $job->authoringEditable())
<x-company-layout :title="$job->exists ? 'Level 1 基本情報' : '求人新規作成'">
    <a href="{{ route('company.dashboard') }}" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline">企業ダッシュボードへ戻る</a>
    <h1 class="mt-3 text-2xl font-bold text-blue-950 sm:text-3xl">{{ $job->exists ? 'Level 1 基本情報' : '求人新規作成' }}</h1>
    <p class="mt-2 break-words text-slate-600">{{ $company->name }}</p>
    <p class="mt-3 text-sm text-slate-600">基本情報を入力して下書き保存できます。保存だけでは公開されません。</p>
    @if ($job->exists)
        <p class="mt-3 text-sm">公開状態：{{ \App\Models\JobPosting::STATUS_LABELS[$job->status] ?? $job->status }} ／ 公開審査：{{ \App\Models\JobPosting::REVIEW_STATUS_LABELS[$job->review_status] ?? $job->review_status }}</p>
    @endif
    @if ($readOnly)
        <p role="status" class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950">この既存求人には公開Snapshotがないため、現在は確認のみ可能です。</p>
    @endif
    @if ($errors->any())
        <section role="alert" class="mt-5 rounded-xl border border-red-300 bg-red-50 p-4 text-red-900">
            <h2 class="font-bold">入力内容を確認してください</h2>
            <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <form method="POST" action="{{ $job->exists ? route('company.jobs.basic.update', $job) : route('company.jobs.store') }}" class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-7">
            @csrf
            @if ($job->exists) @method('PATCH') @endif
            <fieldset @disabled($readOnly) class="min-w-0 space-y-5">
                <legend class="mb-4 text-xl font-bold text-blue-950">Level 1 Basic</legend>
                <p class="text-sm text-slate-600">下書き保存には求人タイトルが必要です。他の項目はあとから追記できます。</p>
                <div>
                    <label for="title" class="block font-semibold">求人タイトル <span class="text-sm text-red-800">必須</span></label>
                    <input id="title" name="title" type="text" value="{{ old('title', $job->title) }}" required maxlength="255" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3" @if($errors->has('title')) aria-invalid="true" @endif>
                </div>
                <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="occupation" class="block font-semibold">職種</label>
                        <select id="occupation" name="occupation" class="mt-2 min-h-12 w-full rounded-lg border border-slate-400 bg-white px-3 py-3">
                            <option value="">未入力</option>
                            @foreach(['機械設計', '電気設計'] as $occupation)<option value="{{ $occupation }}" @selected(old('occupation', $job->occupation) === $occupation)>{{ $occupation }}</option>@endforeach
                            @if($readOnly && $job->occupation && !in_array($job->occupation, ['機械設計', '電気設計']))<option selected>{{ $job->occupation }}</option>@endif
                        </select>
                    </div>
                    <div><label for="region" class="block font-semibold">勤務地</label><input id="region" name="region" type="text" value="{{ old('region', $job->region) }}" maxlength="255" placeholder="例：兵庫県神戸市" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3"></div>
                </div>
                <fieldset class="min-w-0">
                    <legend class="font-semibold">想定年収（万円）</legend>
                    <div class="mt-2 grid grid-cols-2 gap-4">
                        @foreach(['salary_min' => '下限', 'salary_max' => '上限'] as $field => $label)
                            <div><label for="{{ $field }}" class="text-sm text-slate-600">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="number" min="1" max="10000" step="1" inputmode="numeric" value="{{ old($field, $job->$field) }}" class="mt-1 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3"></div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-sm text-slate-500">未確定の場合は空欄にしてください。</p>
                </fieldset>
                <div><label for="employment_type" class="block font-semibold">雇用形態</label><input id="employment_type" name="employment_type" type="text" value="{{ old('employment_type', $job->employment_type) }}" maxlength="255" placeholder="例：正社員" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3"></div>
                @foreach(['description' => '仕事内容（概要）', 'application_requirements' => '最低限の応募条件'] as $field => $label)
                    <div><label for="{{ $field }}" class="block font-semibold">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" rows="5" maxlength="10000" class="mt-2 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3">{{ old($field, $job->$field) }}</textarea></div>
                @endforeach
                <div>
                    <label for="source_url" class="block font-semibold">応募URL</label>
                    <input id="source_url" name="source_url" type="url" value="{{ old('source_url', $job->source_url) }}" maxlength="2048" placeholder="https://" aria-describedby="application-url-help" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3">
                    <p id="application-url-help" class="mt-2 text-sm text-slate-500">自社の採用ページや応募フォームのURLを入力してください。</p>
                </div>
                @if (!$readOnly)
                    <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row">
                        <button type="submit" class="min-h-12 rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800">下書き保存</button>
                        <button type="submit" name="navigation" value="next" class="min-h-12 rounded-lg border border-blue-700 bg-white px-6 py-3 text-blue-800">保存してSTEP 1へ進む</button>
                    </div>
                @endif
            </fieldset>
        </form>
        <aside class="min-w-0 space-y-5">
            <section class="rounded-xl border border-blue-100 bg-blue-50 p-5">
                <h2 class="font-bold text-blue-950">まずは基本情報から</h2>
                <p class="mt-3 text-sm leading-7 text-slate-700">求人の概要や応募条件を整理しましょう。下書きは何度でも保存・再編集できます。</p>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-bold text-blue-950">公開までの流れ</h2>
                <p class="mt-3 text-sm leading-7 text-slate-700">求職者向け公開には、情報の入力後に公開申請と運営の承認が必要です。Level 2の5STEPまで下書き保存できます。入力後、Previewから公開申請できます。</p>
            </section>
        </aside>
    </div>
</x-company-layout>
