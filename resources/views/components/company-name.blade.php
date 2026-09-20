@props(['name' => null])
{{ \App\Support\AnonymousCompany::display($name) }}
@if (\App\Support\AnonymousCompany::isAnonymous($name))
    <span class="block text-xs font-normal text-slate-500">{{ \App\Support\AnonymousCompany::NOTE }}</span>
@endif
