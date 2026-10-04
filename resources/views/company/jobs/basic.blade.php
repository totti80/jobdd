@php($readOnly = $job->exists && (! $job->authoringEditable() || $job->review_status === 'pending_review'))
<x-company-layout :title="$job->exists ? 'Level 1 基本情報' : '求人新規作成'">
    <a href="{{ route('company.dashboard') }}" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline">企業ダッシュボードへ戻る</a>
    <x-company-hero title="基本情報" eyebrow="Level 1">まず、この求人の基本情報を入力してください。<br>仕事の詳しい中身は、次のLevel 2で登録します。<p class="mt-2 break-words text-sm">{{ $company->name }}</p></x-company-hero>
    <ol class="mt-5 flex flex-wrap gap-3 text-sm" aria-label="入力の流れ"><li aria-current="step" class="rounded-full bg-blue-700 px-5 py-2 font-semibold text-white">1 基本情報</li><li class="rounded-full bg-blue-100 px-5 py-2 text-blue-950">2 仕事の中身</li></ol>
    @if ($job->exists)
        <p class="mt-3 text-sm">公開状態：{{ ['draft' => '未公開', 'published' => '公開中'][$job->status] ?? '公開状況を確認してください' }} ／ 公開審査：{{ ['not_submitted' => '未申請', 'pending_review' => '審査中', 'changes_requested' => '修正をお願いします', 'approved' => '承認済み'][$job->review_status] ?? '審査状況を確認してください' }}</p>
    @endif
    @if ($readOnly)
        <p role="status" class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950">{{ $job->review_status === 'pending_review' && $job->authoringEditable() ? '審査中は編集できません。差戻し後に編集を再開できます。' : 'この既存求人には公開Snapshotがないため、現在は確認のみ可能です。' }}</p>
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
                <p class="text-sm text-slate-600">必須は公開申請時の条件です。途中保存できます。下書き保存には求人タイトルのみ必要です。Level 2はすべて任意です。</p>
                <div>
                    <label for="title" class="block font-semibold">求人タイトル @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.title'])</label>
                    <input aria-describedby="title-help" id="title" name="title" type="text" value="{{ old('title', $job->title) }}" required maxlength="255" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3" @if($errors->has('title')) aria-invalid="true" @endif>
                </div>
                <p id="title-help" class="text-sm leading-6 text-slate-500">求職者に表示される求人名です。職種だけでなく、担当する製品や設備が分かる名称がおすすめです。</p>
                <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="occupation" class="block font-semibold">職種 @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.occupation'])</label>
                        <select id="occupation" name="occupation" class="mt-2 min-h-12 w-full rounded-lg border border-slate-400 bg-white px-3 py-3">
                            <option value="">未入力</option>
                            @foreach(['機械設計', '電気設計'] as $occupation)<option value="{{ $occupation }}" @selected(old('occupation', $job->occupation) === $occupation)>{{ $occupation }}</option>@endforeach
                            @if($readOnly && $job->occupation && !in_array($job->occupation, ['機械設計', '電気設計']))<option selected>{{ $job->occupation }}</option>@endif
                        </select>
                    </div>
                    <div><label for="region" class="block font-semibold">勤務地 @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.region'])</label><input id="region" name="region" type="text" value="{{ old('region', $job->region) }}" maxlength="255" placeholder="例：兵庫県神戸市" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3"></div>
                </div>
                <fieldset class="min-w-0">
                    <legend class="font-semibold">想定年収（万円） @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.salary_min'])</legend>
                    <div class="mt-2 grid grid-cols-2 gap-4">
                        @foreach(['salary_min' => '下限', 'salary_max' => '上限'] as $field => $label)
                            <div><label for="{{ $field }}" class="text-sm text-slate-600">{{ $label }} @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.'.$field])</label><input id="{{ $field }}" name="{{ $field }}" type="number" min="1" max="10000" step="1" inputmode="numeric" value="{{ old($field, $job->$field) }}" class="mt-1 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3"></div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-sm text-slate-500">下書きでは空欄にできます。公開申請には下限・上限のどちらか1つ以上が必要です。</p>
                </fieldset>
                <div><label for="employment_type" class="block font-semibold">雇用形態 @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.employment_type'])</label><input id="employment_type" name="employment_type" type="text" value="{{ old('employment_type', $job->employment_type) }}" maxlength="255" placeholder="例：正社員" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3"></div>
                @foreach(['description' => '仕事内容', 'application_requirements' => '最低限の応募条件'] as $field => $label)
                    <div><label for="{{ $field }}" class="block font-semibold">{{ $label }} @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.'.$field])</label><textarea aria-describedby="{{ $field }}-help" id="{{ $field }}" name="{{ $field }}" rows="5" maxlength="10000" class="mt-2 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3">{{ old($field, $job->$field) }}</textarea><p id="{{ $field }}-help" class="mt-2 text-sm leading-6 text-slate-500">{{ $field === 'description' ? '一般的な求人情報として、仕事内容の概要を入力してください。詳しい工程・ツール・仕事の進め方は次のLevel 2で登録します。' : '応募を検討するために必要な最低条件を入力してください。' }}</p></div>
                @endforeach
                <div>
                    <label for="source_url" class="block font-semibold">応募URL @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'level_one.source_url'])</label>
                    <input id="source_url" name="source_url" type="url" value="{{ old('source_url', $job->source_url) }}" maxlength="2048" placeholder="https://" aria-describedby="application-url-help" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 px-3 py-3">
                    <p id="application-url-help" class="mt-2 text-sm text-slate-500">求職者が応募時に遷移する企業公式ページを入力してください。</p>
                </div>
                @if (!$readOnly)
                    <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row">
                        <button type="submit" class="min-h-12 rounded-lg border border-blue-700 px-6 py-3 font-semibold text-blue-800">下書き保存</button>
                        <button type="submit" name="navigation" value="next" class="min-h-12 rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800">保存してLevel 2へ</button>
                    </div>
                @endif
            </fieldset>
        </form>
        <aside class="min-w-0 space-y-5">
            <section class="rounded-xl border border-blue-100 bg-white p-5"><h2 class="font-bold text-blue-950">Level 1｜基本情報</h2><ul class="mt-4 list-inside list-disc space-y-2 text-sm text-slate-600">@foreach(['求人タイトル', '職種', '勤務地', '年収', '雇用形態', '仕事内容', '応募条件', '応募URL'] as $item)<li>{{ $item }}</li>@endforeach</ul></section>
            <section class="rounded-xl border border-blue-100 bg-blue-50 p-5"><p class="text-sm font-semibold text-blue-700">次のLevel 2では</p><h2 class="mt-2 font-bold text-blue-950">仕事の中身を詳しく登録します</h2><ul class="mt-4 list-inside list-disc space-y-2 text-sm leading-6 text-slate-600">@foreach(['何を設計するか', 'どの工程を担当するか', 'CAD / Tool', '誰と仕事をするか', '仕事の進め方', '仕事の難しさ', '代表的な1日'] as $item)<li>{{ $item }}</li>@endforeach</ul><p class="mt-4 text-sm leading-6 text-slate-600">基本情報が入力途中でもLevel 2へ進めます。公開申請時に必要な入力を確認します。</p></section>
        </aside>
    </div>
</x-company-layout>
