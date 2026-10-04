@php
    $options = \App\Support\StructuredJobOptions::options($field);
    $multiple = in_array($field, ['design_phases', 'collaborators', 'representative_project.phases']);
    $value = $multiple ? array_filter((array) $value, 'is_string') : (is_scalar($value) ? $value : null);
    $id = str_replace(['.', '[', ']'], '-', $field);
    $name = str_contains($field, '.') ? preg_replace('/\.([^.]*)/', '[$1]', $field) : $field;
@endphp
<div class="min-w-0">
    @if($multiple)
        <fieldset><legend class="font-semibold">{{ $label }} @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'structured_profile.'.$field])</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach($options as $key => $option)<label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 p-3"><input type="checkbox" name="{{ $name }}[]" value="{{ $key }}" @checked(in_array($key, (array) $value))>{{ $option }}</label>@endforeach
            </div>
        </fieldset>
    @else
        <label for="{{ $id }}" class="block font-semibold">{{ $label }} @include('company.jobs.partials.publication-requirement', ['publicationPath' => 'structured_profile.'.$field])</label>
        @if($options)
            <select id="{{ $id }}" name="{{ $name }}" class="mt-2 min-h-12 w-full rounded-lg border border-slate-400 bg-white p-3"><option value="">未入力</option>@foreach($options as $key => $option)<option value="{{ $key }}" @selected($value === $key)>{{ $option }}</option>@endforeach</select>
        @else
            <textarea id="{{ $id }}" name="{{ $name }}" rows="3" maxlength="10000" class="mt-2 w-full min-w-0 rounded-lg border border-slate-400 p-3">{{ $value }}</textarea>
        @endif
    @endif
    @error($field)<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
</div>
