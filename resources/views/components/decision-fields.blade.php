@props(['fields'])
<dl class="grid min-w-0 gap-5 sm:grid-cols-2">
    @foreach ($fields as $label => $value)
        <div class="min-w-0">
            <dt class="text-sm font-semibold leading-6 text-slate-600">{{ $label }}</dt>
            <dd class="mt-1 break-words leading-7">
                @if (mb_strlen($value) > 400)
                    <details class="jobdd-details"><summary>{{ $label }}の全文を見る</summary><p class="whitespace-pre-wrap p-4 pt-2">{{ $value }}</p></details>
                @else
                    <p class="whitespace-pre-wrap">{{ $value }}</p>
                @endif
            </dd>
        </div>
    @endforeach
</dl>
