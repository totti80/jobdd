@php
    $statusKey = array_key_exists($status, \App\Support\JobDecisionPresenter::STATUSES) ? $status : 'unknown';
@endphp
<span data-status-badge="{{ $statusKey }}" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold leading-5 ring-1 {{ \App\Support\JobDecisionPresenter::BADGECLASSES[$statusKey] }}">
    <x-jobdd-icon :name="$statusKey" class="size-4" />
    {{ \App\Support\JobDecisionPresenter::STATUSES[$statusKey] }}
</span>
