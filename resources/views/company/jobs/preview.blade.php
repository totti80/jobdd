<x-company-layout title="求人Preview">
    <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5"><p class="text-sm font-bold tracking-wider">PREVIEW</p><p class="mt-2 font-semibold">未公開または編集中の内容です</p><p class="mt-2 text-sm leading-7">公開済み求人も、再申請が承認されるまでは前回の公開内容が維持されます。</p></div>
    <x-company-card class="mb-6">
        @include('company.jobs.partials.review-feedback')
        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <x-company-action :href="route('company.jobs.basic.edit', $job)">Level 1を編集</x-company-action>
            <x-company-action :href="route('company.jobs.structured.edit', [$job, 1])">Level 2を編集</x-company-action>
            <form method="POST" action="{{ route('company.jobs.review-request', $job) }}">@csrf<x-company-action type="submit" :disabled="$job->review_status === 'pending_review' || count($publishErrors) > 0">{{ $job->review_status === 'pending_review' ? '審査中' : '公開申請する' }}</x-company-action></form>
        </div>
    </x-company-card>
    @include('company.jobs.partials.preview-content')
</x-company-layout>
