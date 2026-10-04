@php($requirement = \App\Support\CompanyJobPublicationRequirements::requirement($publicationPath))
<span class="ml-1 text-xs font-normal {{ str_starts_with($requirement, '必須') ? 'text-red-800' : 'text-slate-600' }}">{{ $requirement }}</span>
