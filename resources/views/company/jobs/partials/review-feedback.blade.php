<p class="text-sm">公開状態：{{ \App\Models\JobPosting::STATUS_LABELS[$job->status] ?? $job->status }} ／ 公開審査：{{ \App\Models\JobPosting::REVIEW_STATUS_LABELS[$job->review_status] ?? $job->review_status }}</p>
@if($job->review_note)<p class="mt-3 whitespace-pre-wrap break-words rounded-lg bg-amber-50 p-4 text-sm leading-7">差戻し理由：{{ $job->review_note }}</p>@endif
@if($errors->any())<ul role="alert" class="mt-4 list-inside list-disc rounded-lg bg-red-50 p-4 text-red-900">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@if($publishErrors)<div class="mt-4 rounded-lg bg-amber-50 p-4"><h2 class="font-bold">公開申請に必要な入力</h2><ul class="mt-2 list-inside list-disc text-sm leading-7">@foreach($publishErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
