@php
    use App\Support\StructuredJobOptions as Options;
    $basic = $data['level_one'];
    $profile = $data['structured_profile'];
@endphp
<div class="space-y-6">
    <x-company-card>
        <p class="break-words text-slate-600">{{ $data['company']['name'] }}</p>
        <h1 class="mt-2 break-words text-2xl font-bold text-blue-950">{{ $basic['title'] ?: '求人タイトル未入力' }}</h1>
        <dl class="mt-6 grid min-w-0 gap-5 sm:grid-cols-2">
            @foreach(['occupation'=>'職種','region'=>'勤務地','employment_type'=>'雇用形態'] as $field=>$label)
                <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words">{{ $basic[$field] ?: '未入力' }}</dd></div>
            @endforeach
            <div><dt class="text-sm text-slate-500">想定年収（万円）</dt><dd class="mt-1">{{ $basic['salary_min'] ?? '未入力' }} 〜 {{ $basic['salary_max'] ?? '未入力' }}</dd></div>
        </dl>
        @foreach(['description'=>'仕事内容','application_requirements'=>'最低限の応募条件','source_url'=>'応募URL'] as $field=>$label)
            <h2 class="mt-6 font-bold">{{ $label }}</h2><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-7">{{ $basic[$field] ?: '未入力' }}</p>
        @endforeach
    </x-company-card>
    @foreach(Options::FIELDS as $step=>$fields)
        <x-company-card>
            <h2 class="text-xl font-bold text-blue-950">{{ Options::TITLES[$step] }}</h2>
            <dl class="mt-5 space-y-5">
                @foreach($fields as $field=>$label)
                    @if($field === 'representative_project')
                        <div><dt class="font-semibold">{{ $label }}</dt><dd class="mt-3"><dl class="space-y-3 border-l-2 border-blue-100 pl-4">
                            @foreach(['what_made'=>'何を作ったか','phases'=>'担当工程','duration'=>'期間','team'=>'チーム構成','difficult_point'=>'難しかった点'] as $key=>$projectLabel)
                                @php($value = $profile[$field][$key] ?? null)
                                <div><dt class="text-sm text-slate-500">{{ $projectLabel }}</dt><dd class="whitespace-pre-wrap break-words leading-7">{{ is_array($value) ? implode('、', array_map(fn ($k)=>Options::PHASES[$k] ?? $k, $value)) ?: '未入力' : ($value ?: '未入力') }}</dd></div>
                            @endforeach
                        </dl></dd></div>
                    @else
                        @php($value = $profile[$field] ?? null)
                        <div><dt class="font-semibold">{{ $label }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ is_array($value) ? implode('、', array_map(fn ($k)=>Options::options($field)[$k] ?? $k, $value)) ?: '未入力' : (Options::options($field)[$value ?? ''] ?? $value ?: '未入力') }}</dd></div>
                    @endif
                @endforeach
            </dl>
            @if($step === 2)
                <h3 class="mt-6 font-bold">使用ツール</h3><div class="mt-3 grid min-w-0 gap-4 sm:grid-cols-2">
                    @forelse($data['tool_usages'] as $tool)<div class="min-w-0 rounded-lg bg-slate-50 p-4"><h4 class="break-words font-semibold">{{ $tool['tool_name'] ?: '名称未入力' }}</h4><p class="mt-2 text-sm">使用場面：{{ Options::USAGES[$tool['usage_context'] ?? ''] ?? '未入力' }}</p><p class="mt-2 text-sm">経験要件：{{ Options::EXPECTATIONS[$tool['experience_expectation'] ?? ''] ?? '未入力' }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-7">{{ $tool['usage_notes'] ?? '' }}</p></div>@empty<p class="text-sm text-slate-500">未入力</p>@endforelse
                </div>
            @endif
            @if($step === 5)
                <h3 class="mt-6 font-bold">ある一日の流れ</h3><ol class="mt-3 space-y-3">
                    @forelse($data['typical_day_items'] as $item)<li class="min-w-0 rounded-lg bg-slate-50 p-4"><p class="break-words font-semibold">{{ $item['time_label'] ?: '時間帯未入力' }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-7">{{ $item['activity'] ?: '作業内容未入力' }}</p></li>@empty<li class="text-sm text-slate-500">未入力</li>@endforelse
                </ol>
            @endif
        </x-company-card>
    @endforeach
</div>
