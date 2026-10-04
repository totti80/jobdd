<div data-row class="min-w-0 space-y-4 rounded-xl border border-slate-300 bg-slate-50 p-4">
    @foreach($rowFields as $field => $label)
        @php
            $options = \App\Support\StructuredJobOptions::options($field);
            $rowValue = is_scalar($row[$field] ?? null) ? $row[$field] : null;
        @endphp
        <div class="min-w-0"><label for="{{ $rowName }}-{{ $index }}-{{ $field }}" class="block text-sm font-semibold">{{ $label }} @include('company.jobs.partials.publication-requirement', ['publicationPath' => ($rowName === 'tools' ? 'tool_usages' : 'typical_day_items').'.*.'.$field])</label>
        @if($options)
            <select id="{{ $rowName }}-{{ $index }}-{{ $field }}" name="{{ $rowName }}[{{ $index }}][{{ $field }}]" class="mt-1 min-h-12 w-full rounded-lg border border-slate-400 bg-white p-3"><option value="">未入力</option>@foreach($options as $key => $option)<option value="{{ $key }}" @selected($rowValue === $key)>{{ $option }}</option>@endforeach</select>
        @elseif(in_array($field, ['tool_name', 'time_label']))
            <input id="{{ $rowName }}-{{ $index }}-{{ $field }}" name="{{ $rowName }}[{{ $index }}][{{ $field }}]" value="{{ $rowValue }}" maxlength="255" class="mt-1 min-h-12 w-full min-w-0 rounded-lg border border-slate-400 p-3">
        @else
            <textarea id="{{ $rowName }}-{{ $index }}-{{ $field }}" name="{{ $rowName }}[{{ $index }}][{{ $field }}]" rows="3" maxlength="10000" class="mt-1 w-full min-w-0 rounded-lg border border-slate-400 p-3">{{ $rowValue }}</textarea>
        @endif
        </div>
    @endforeach
    <button type="button" data-remove-row class="min-h-11 rounded-lg border border-slate-400 bg-white px-4 text-sm text-red-800">この行を削除</button>
</div>
