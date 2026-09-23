@php
    use App\Support\StructuredJobOptions as Options;
    $readOnly = ! $job->authoringEditable();
    $profile = $job->structuredProfile;
@endphp
<x-company-layout :title="'Level 2 STEP '.$step">
    <a href="{{ route('company.dashboard') }}" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline">企業ダッシュボードへ戻る</a>
    <h1 class="mt-3 text-2xl font-bold text-blue-950">Level 2 仕事の実像</h1>
    <p class="mt-2 break-words text-slate-600">{{ $job->title }}</p>
    <ol class="mt-5 grid grid-cols-5 gap-1 text-center text-sm" aria-label="入力ステップ">
        @foreach(Options::TITLES as $number => $title)<li class="rounded-lg border p-3 {{ $step === $number ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white' }}" @if($step === $number) aria-current="step" @endif>STEP {{ $number }}</li>@endforeach
    </ol>
    @if($readOnly)<p role="status" class="mt-5 rounded-xl bg-amber-50 p-4">公開Snapshotのない既存求人は閲覧のみ可能です。</p>@endif
    @if($errors->any())<div role="alert" class="mt-5 rounded-xl bg-red-50 p-4 text-red-900"><p class="font-bold">入力内容を確認してください</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <form method="POST" action="{{ route('company.jobs.structured.update', [$job, $step]) }}" class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-7">
            @csrf @method('PATCH')
            <fieldset @disabled($readOnly) class="min-w-0 space-y-6">
                <legend class="mb-4 text-xl font-bold text-blue-950">STEP {{ $step }}：{{ Options::TITLES[$step] }}</legend>
                <p class="text-sm leading-7 text-slate-600">すべて途中の状態で保存できます。分からない項目は空欄にできます。保存だけでは公開されません。</p>
                @if($step === 4)<p class="rounded-lg bg-blue-50 p-4 text-sm leading-7">人の属性ではなく、仕事の進め方との相性を記載してください。年齢・性別・国籍・家族構成による適性判断は記載しないでください。</p>@endif
                @foreach(Options::FIELDS[$step] as $field => $label)
                    @if($field !== 'representative_project')
                        @include('company.jobs.partials.structured-field', ['value' => session()->hasOldInput() ? old($field) : $profile?->$field])
                    @else
                        <section class="space-y-5 rounded-xl border border-slate-200 p-4"><h2 class="font-bold">代表的な案件（任意）</h2>
                            @foreach(['what_made'=>'何を作ったか','phases'=>'担当工程','duration'=>'期間','team'=>'チーム構成','difficult_point'=>'難しかった点'] as $key => $projectLabel)
                                @include('company.jobs.partials.structured-field', ['field'=>'representative_project.'.$key, 'label'=>$projectLabel, 'value'=>session()->hasOldInput() ? old('representative_project.'.$key) : ($profile?->representative_project[$key] ?? null)])
                            @endforeach
                        </section>
                    @endif
                @endforeach
                @if(in_array($step, [2, 5]))
                    @php
                        $rowName = $step === 2 ? 'tools' : 'typical_day';
                        $rowFields = $step === 2 ? ['tool_key'=>'ツールの候補', 'tool_name'=>'ツール名（自由入力可）', 'usage_context'=>'使用場面', 'experience_expectation'=>'経験の期待', 'usage_notes'=>'補足'] : ['time_label'=>'時間帯（例：午前・出社後）', 'activity'=>'作業内容'];
                        $savedRows = ($step === 2 ? $job->toolUsages : $job->typicalDayItems)->toArray();
                        $rows = session()->hasOldInput() ? old($rowName, []) : ($savedRows ?: [[]]);
                        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
                    @endphp
                    <section data-authoring-rows data-next-index="{{ count($rows) }}" class="min-w-0 space-y-4">
                        <h2 class="text-lg font-bold">{{ $step === 2 ? '使用ツール' : 'ある一日の流れ' }}</h2>
                        <p class="text-sm text-slate-600">表示順に保存します。最大30行。空欄の行は保存されません。</p>
                        <div data-rows class="space-y-4">@foreach($rows as $index => $row)@include('company.jobs.partials.structured-row')@endforeach</div>
                        <template>@include('company.jobs.partials.structured-row', ['index'=>'__INDEX__','row'=>[]])</template>
                        <button type="button" data-add-row class="min-h-12 rounded-lg border border-blue-700 px-5 text-blue-800">＋ 行を追加</button>
                        <p data-row-status role="status" class="text-sm text-slate-600"></p>
                    </section>
                @endif
                @if(!$readOnly)
                    <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:flex-wrap">
                        <button name="navigation" value="save" class="min-h-12 rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white">下書き保存</button>
                        <button name="navigation" value="back" class="min-h-12 rounded-lg border border-slate-400 px-5 py-3">保存して前へ</button>
                        <button name="navigation" value="next" class="min-h-12 rounded-lg border border-blue-700 px-5 py-3 text-blue-800">{{ $step === 5 ? '保存してPreviewへ' : '保存して次へ' }}</button>
                    </div>
                @endif
            </fieldset>
        </form>
        <aside class="min-w-0 space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-bold text-blue-950">このSTEPの保存済み内容</h2>
                <p class="mt-2 text-xs text-slate-500">入力支援用の要約です。求職者向けプレビューではありません。</p>
                <dl class="mt-4 space-y-3 text-sm">
                    @foreach(Options::FIELDS[$step] as $summaryField => $summaryLabel)
                        @php($summaryValue = $profile?->$summaryField)
                        @if($summaryField !== 'representative_project')
                            <div><dt class="font-semibold">{{ $summaryLabel }}</dt><dd class="mt-1 break-words text-slate-600">{{ is_array($summaryValue) ? implode('、', array_map(fn ($key) => Options::options($summaryField)[$key] ?? $key, $summaryValue)) ?: '未入力' : (Options::options($summaryField)[$summaryValue ?? ''] ?? $summaryValue ?: '未入力') }}</dd></div>
                        @endif
                    @endforeach
                    @if($step === 2)<div><dt class="font-semibold">使用ツール</dt><dd>{{ $job->toolUsages->count() }}行</dd></div>@endif
                    @if($step === 5)<div><dt class="font-semibold">ある一日の流れ</dt><dd>{{ $job->typicalDayItems->count() }}行</dd></div>@endif
                </dl>
            </section>
            <section class="rounded-xl border border-blue-100 bg-blue-50 p-5"><h2 class="font-bold text-blue-950">保存済みの入力充足率</h2><p class="mt-3 text-3xl font-bold text-blue-800">{{ $completion['percentage'] }}%</p><p class="mt-2 text-sm">{{ $completion['completed'] }} / {{ $completion['total'] }}項目</p><p class="mt-3 text-sm leading-7">Level 1の8項目とLevel 2 Coreの13項目を集計しています。入力を進めるための目安で、Fitや公開可否の判定には使用しません。</p></section>
            <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-bold text-blue-950">仕事の具体像を伝える</h2><p class="mt-3 text-sm leading-7">工程、相手、頻度、具体的な作業を記載しましょう。保存後はログインし直しても続きから編集できます。</p><p class="mt-3 text-sm leading-7">Previewで内容を確認して公開申請できます。</p></section>
            <a href="{{ route('company.jobs.basic.edit', $job) }}" class="inline-flex min-h-11 items-center text-blue-800 underline">Level 1 基本情報を確認</a>
        </aside>
    </div>
</x-company-layout>
