<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>応募経路比較 | JobDD</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">
  <header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-6 py-5">
      <p class="text-xl font-bold tracking-tight text-blue-950 md:text-3xl">JobDD | 応募経路Decision Support</p>
      <p class="mt-2 text-sm text-slate-600 md:text-base">公開情報とEvidenceを整理し、最終判断はあなた自身が行います。</p>
    </div>
  </header>
  <main class="mx-auto max-w-6xl px-6 py-10">
    <div class="mb-8">
      <h1 class="text-3xl font-bold tracking-tight text-blue-950 md:text-4xl">応募経路を比較</h1>
      <p class="mt-4 text-base leading-7 text-slate-600">Direct / Agent / Platformを同じ基準で確認できます。件数やScoreだけで優劣を決めません。</p>
    </div>
    <section class="mb-8 rounded-2xl bg-blue-50 px-6 py-5 ring-1 ring-blue-100">
      <div class="mb-3 text-sm font-bold text-blue-950">今回の入力条件</div>
      <div class="flex flex-wrap gap-3 text-sm"><span class="rounded-full bg-white px-4 py-2 ring-1 ring-blue-100">職種：<strong>{{ $userQuery->occupation ?? '未指定' }}</strong></span><span class="rounded-full bg-white px-4 py-2 ring-1 ring-blue-100">地域：<strong>{{ $userQuery->region ?? '未指定' }}</strong></span><span class="rounded-full bg-white px-4 py-2 ring-1 ring-blue-100">経験：<strong>{{ $userQuery->experience_years !== null ? $userQuery->experience_years . '年' : '未指定' }}</strong></span><span class="rounded-full bg-white px-4 py-2 ring-1 ring-blue-100">希望年収：<strong>{{ $userQuery->salary_min !== null ? $userQuery->salary_min . '万円以上' : '未指定' }}</strong></span></div>
    </section>
    <section class="grid gap-5 md:grid-cols-3">
      @foreach ($routeSummaries as $summary)
      <article class="flex flex-col rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-bold uppercase tracking-wide text-blue-600">{{ $summary['label'] }}</p>
        <h2 class="mt-2 text-2xl font-bold text-blue-950">{{ $summary['route_type'] === 'direct' ? '企業へ直接応募' : ($summary['route_type'] === 'agent' ? '人材紹介会社経由' : '求人媒体経由') }}</h2>
        <p class="mt-4 text-sm leading-6 text-slate-600">{{ $summary['summary_reason'] }}</p>
        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
          <div class="rounded-xl bg-slate-50 p-3">
            <dt class="text-slate-500">確認済み</dt>
            <dd class="mt-1 font-bold">{{ $summary['evidence_count'] }}件</dd>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <dt class="text-slate-500">候補数</dt>
            <dd class="mt-1 font-bold">{{ $summary['candidate_count'] }}件</dd>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <dt class="text-slate-500">職種</dt>
            <dd class="mt-1 font-bold">{{ $summary['occupation_match'] === true ? '一致' : '未確認' }}</dd>
          </div>
          <div class="rounded-xl bg-slate-50 p-3">
            <dt class="text-slate-500">地域</dt>
            <dd class="mt-1 font-bold">{{ $summary['region_match'] === true ? '一致' : '未確認' }}</dd>
          </div>
        </dl>
        <div class="mt-5 text-sm">
          <p class="font-semibold text-slate-800">根拠</p>
          <p class="mt-1 text-slate-600">応募可能URL、情報源、最終確認日を候補ごとに表示します。</p>
          <p class="mt-3 font-semibold text-slate-800">未確認</p>
          <ul class="mt-1 list-disc pl-5 text-slate-600">@foreach ($summary['missing_items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
        </div>
        @if (count($summary['representative_candidates']) > 0)<div class="mt-5 space-y-3">
          <p class="font-semibold text-blue-950">代表候補</p>@foreach ($summary['representative_candidates'] as $candidate)<div class="rounded-xl border border-slate-200 p-3 text-sm">
            <p class="font-semibold"><x-company-name :name="$candidate['company_name'] ?? ($candidate['agency_name'] ?? $candidate['platform_name'] ?? '候補')" /></p>
            <p class="mt-1 text-slate-700">{{ $candidate['title'] }}</p>
            <p class="mt-1 text-slate-500">{{ $candidate['region'] ?: '地域未確認' }}</p>@if (\App\Support\JobDecisionPresenter::safeUrl($candidate['application_url']))<a class="mt-2 inline-block font-semibold text-blue-600 underline" href="{{ $candidate['application_url'] }}" target="_blank" rel="noopener noreferrer">外部リンクを確認</a>@endif
          </div>@endforeach
        </div>@endif
        <a href="#candidates-{{ $summary['route_type'] }}" class="mt-6 inline-flex justify-center rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">この経路の候補を見る</a>
      </article>
      @endforeach
    </section>
    @foreach ($routeSummaries as $summary)
    <section id="candidates-{{ $summary['route_type'] }}" class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="text-xl font-bold text-blue-950">{{ $summary['label'] }}の今回条件に近い公開求人</h2>
      <p class="mt-2 text-sm text-slate-600">Evidence Level: 公開URLと確認日がある候補のみ表示</p>@if (count($summary['representative_candidates']) === 0)<p class="mt-4 text-sm text-slate-600">現在確認できるEvidenceがありません。求人が存在しないことを意味しません。</p>@else<div class="mt-4 grid gap-4 md:grid-cols-3">@foreach ($summary['representative_candidates'] as $candidate)<article class="rounded-xl border border-slate-200 p-4">
          <p class="font-semibold text-slate-900"><x-company-name :name="$candidate['company_name'] ?? ($candidate['agency_name'] ?? $candidate['platform_name'] ?? '候補')" /></p>
          <h3 class="mt-1 font-bold">{{ $candidate['title'] }}</h3>
          <p class="mt-2 text-sm text-slate-600">地域：{{ $candidate['region'] ?: '未確認' }}</p>
          <p class="text-sm text-slate-600">年収：{{ $candidate['salary_min'] ? $candidate['salary_min'] . '万円以上' : '未確認' }}</p>
          <p class="mt-2 text-xs text-slate-500">確認日：{{ optional($candidate['confirmed_at'])->format('Y-m-d') ?: '未確認' }}</p>@if (\App\Support\JobDecisionPresenter::safeUrl($candidate['application_url']))<a class="mt-3 inline-block text-sm font-semibold text-blue-600 underline" href="{{ $candidate['application_url'] }}" target="_blank" rel="noopener noreferrer">応募可能URLを開く</a>@endif
        </article>@endforeach</div>@endif
    </section>
    @endforeach
    <div class="mt-8 rounded-2xl bg-blue-50 px-6 py-5 ring-1 ring-blue-100">
      <p class="font-semibold text-blue-950">RecommendではなくDecision Support</p>
      <p class="mt-1 text-sm leading-6 text-slate-600">JobDDは比較材料とEvidenceを整理します。最終判断はご本人が行います。</p>
    </div>
  </main>
</body>

</html>
<!-- Legacy Agent-only result screen retained for reference but excluded from public rendering.
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Legacy result screen | JobDD</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

  {{-- ヘッダー --}}
  <header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-6 py-5">

      <p class="text-xl font-bold tracking-tight text-blue-950 md:text-3xl">
        あなた専用 転職コンシェルジュ | JobDD
      </p>

      <p class="mt-2 text-sm text-slate-600 md:text-base">
        求人・企業・人材紹介会社・求人媒体を横断して、あなたに合う応募経路を整理します。
      </p>

    </div>
  </header>

  <main class="mx-auto max-w-6xl px-6 py-10">

    {{-- ページ上部 --}}
    <div class="mb-8 flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">

      {{-- 見出し --}}
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-blue-950 md:text-4xl">
          Legacy agent result screen
        </h1>

        <p class="mt-4 text-base leading-7 text-slate-600">
          入力内容と公開情報をもとに、候補を比較しています。<br class="hidden md:block">
          Scoreだけでなく、適合理由と根拠も確認できます。
        </p>
      </div>

      {{-- ステップ --}}
      <div class="flex items-start gap-3 text-sm text-slate-500">

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-600">
            ✓
          </div>
          <span class="mt-2">
            入力
          </span>
        </div>

        <div class="mt-4 h-px w-10 bg-blue-200"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-600">
            ✓
          </div>
          <span class="mt-2">
            調査
          </span>
        </div>

        <div class="mt-4 h-px w-10 bg-blue-200"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 font-bold text-white">
            3
          </div>
          <span class="mt-2 font-semibold text-blue-600">
            比較
          </span>
        </div>

        <div class="mt-4 h-px w-10 bg-slate-300"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white font-semibold">
            4
          </div>
          <span class="mt-2">
            行動
          </span>
        </div>

      </div>
    </div>

    {{-- 今回の入力条件 --}}
    <section class="mb-8 rounded-2xl bg-blue-50 px-6 py-5 ring-1 ring-blue-100">

      <div class="mb-3 text-sm font-bold text-blue-950">
        今回の入力条件
      </div>

      <div class="flex flex-wrap gap-3 text-sm">

        <span class="rounded-full bg-white px-4 py-2 text-slate-700 ring-1 ring-blue-100">
          職種：
          <strong>{{ $userQuery->occupation ?? '未指定' }}</strong>
        </span>

        <span class="rounded-full bg-white px-4 py-2 text-slate-700 ring-1 ring-blue-100">
          地域：
          <strong>{{ $userQuery->region ?? '未指定' }}</strong>
        </span>

        <span class="rounded-full bg-white px-4 py-2 text-slate-700 ring-1 ring-blue-100">
          経験：
          <strong>
            {{ $userQuery->experience_years !== null ? $userQuery->experience_years . '年' : '未指定' }}
          </strong>
        </span>

        <span class="rounded-full bg-white px-4 py-2 text-slate-700 ring-1 ring-blue-100">
          希望年収：
          <strong>
            {{ $userQuery->salary_min !== null ? $userQuery->salary_min . '万円以上' : '未指定' }}
          </strong>
        </span>

      </div>

    </section>

    {{-- 紹介会社比較 --}}
    <div class="space-y-5">

      @foreach ($results as $index => $result)

      <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-7">

        {{-- 上部 --}}
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">

          <div class="min-w-0">

            <div class="mb-2 inline-flex rounded-full bg-blue-50 px-3 py-1 text-sm font-bold text-blue-600">
              比較候補
            </div>

            <h2 class="text-2xl font-bold tracking-tight text-blue-950">
              {{ $result->agency->name }}
            </h2>

            <div class="mt-4 space-y-2 text-sm leading-6 text-slate-700">

              @php
              $verifiedOccupation = $result->agency->facts
              ->where('fact_key', 'supported_occupation')
              ->where('verification_status', 'verified')
              ->sortByDesc('observed_at')
              ->first()?->fact_value;

              $verifiedRegion = $result->agency->facts
              ->where('fact_key', 'supported_region')
              ->where('verification_status', 'verified')
              ->sortByDesc('observed_at')
              ->first()?->fact_value;
              @endphp

              <p>
                <span class="font-semibold text-slate-900">
                  得意職種：
                </span>
                {{ $verifiedOccupation ?? '情報なし' }}
              </p>

              <p>
                <span class="font-semibold text-slate-900">
                  対応地域：
                </span>
                {{ $verifiedRegion ?? '情報なし' }}
              </p>

              <p>
                <span class="font-semibold text-slate-900">
                  適合理由：
                </span>
                {{ $result->reason ?: '現在の公開情報から算定しています。' }}
              </p>

              <p>
                <span class="font-semibold text-slate-900">
                  Evidence Level：
                </span>
                {{ $result->agency->evidence_level }}
              </p>

            </div>

          </div>

          {{-- Score --}}
          @php
          $verifiedAxisCount = collect([
          $result->occupation_score,
          $result->region_score,
          $result->experience_score,
          $result->salary_score,
          ])->filter(fn ($score) => $score !== null)->count();
          @endphp

          <div class="shrink-0 rounded-2xl bg-blue-50 px-6 py-5 text-center ring-1 ring-blue-100">

            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              JobDD Score
            </div>

            <div class="mt-1 text-5xl font-bold tracking-tight text-blue-600">
              {{ $result->score }}
            </div>

            <div class="mt-1 text-sm text-slate-400">
              / 100
            </div>

            <div class="mt-2 text-xs font-semibold text-slate-500">
              確認済み {{ $verifiedAxisCount }} / 4軸
            </div>

          </div>

        </div>

        {{-- 4軸 --}}
        <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              職種適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->occupation_score ?? '未確認' }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              地域適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->region_score ?? '未確認' }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              経験適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->experience_score ?? '未確認' }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              年収適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->salary_score ?? '未確認' }}
            </div>
          </div>

        </div>

        {{-- Agent求人Evidence --}}
        @php
        $evidenceJobs = $agentJobEvidence->get(
        $result->agency->id,
        collect()
        );
        @endphp

        <div class="mt-5 overflow-hidden rounded-xl border border-emerald-200 bg-white">

          {{-- Evidenceヘッダー --}}
          <div class="bg-emerald-50 px-5 py-4">

            <div class="flex items-center justify-between gap-4">

              <div>
                <div class="text-sm font-semibold text-emerald-900">
                  今回条件に近い公開求人
                </div>

                <div class="mt-1 text-xs text-emerald-700">
                  @if ($evidenceJobs->isNotEmpty())
                  条件一致した公開求人のうち、代表3件を表示
                  @else
                  現在確認できた公開求人
                  @endif
                </div>
              </div>

              <div class="shrink-0 rounded-full bg-white px-3 py-1 text-sm font-bold text-emerald-700 ring-1 ring-emerald-200">
                {{ $evidenceJobs->count() }}件確認
              </div>

            </div>

          </div>

          {{-- Evidence求人一覧 --}}
          @if ($evidenceJobs->isNotEmpty())

          <div class="divide-y divide-slate-100">

            @foreach ($evidenceJobs->take(3) as $job)

            <div class="px-5 py-5">

              <div class="font-semibold leading-6 text-slate-800">
                {{ $job->title }}
              </div>

              @if ($job->company)
              <div class="mt-1 text-sm text-slate-500">
                <x-company-name :name="($job->published_company_name ?? $job->company?->name)" />
              </div>
              @endif

              <div class="mt-3 flex flex-wrap gap-2 text-xs">

                @if ($job->region)
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">
                  {{ $job->region }}
                </span>
                @endif

                @php
                $salaryMin = $job->salary_min && $job->salary_min > 0
                ? $job->salary_min
                : null;

                $salaryMax = $job->salary_max && $job->salary_max > 0
                ? $job->salary_max
                : null;
                @endphp

                @if ($salaryMin || $salaryMax)
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">
                  年収
                  @if ($salaryMin && $salaryMax)
                  {{ $salaryMin }}〜{{ $salaryMax }}万円
                  @elseif ($salaryMin)
                  {{ $salaryMin }}万円以上
                  @elseif ($salaryMax)
                  {{ $salaryMax }}万円以下
                  @endif
                </span>
                @endif

              </div>

              <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold text-emerald-700">
                <span>✓ 職種一致</span>
                <span>✓ 地域一致</span>
                <span>✓ 年収条件一致</span>
              </div>

              @if (\App\Support\JobDecisionPresenter::safeUrl($job->source_url))
              <div class="mt-3">
                <a
                  href="{{ $job->source_url }}"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-sm font-semibold text-blue-600 underline hover:text-blue-800">
                  公開求人を確認
                </a>
              </div>
              @endif

            </div>

            @endforeach

          </div>

          @else

          <div class="px-5 py-5">
            <p class="text-sm leading-6 text-slate-600">
              JobDDでは、今回の条件に近い公開求人をまだ確認できていません。
              求人が存在しないことを意味するものではありません。
            </p>
          </div>

          @endif

        </div>

        {{-- Evidence --}}
        <div class="mt-5">

          <details
            class="evidence-details overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
            data-user-query-id="{{ $userQuery->id }}"
            data-agency-id="{{ $result->agency->id }}">

            <summary class="cursor-pointer px-5 py-4 font-semibold text-blue-600 hover:bg-blue-50">
              根拠を見る
            </summary>

            <div class="border-t border-slate-200 bg-white px-5 py-4">

              <div class="space-y-4">

                @forelse ($result->agency->facts as $fact)

                <div class="border-b border-slate-200 pb-3 last:border-b-0">

                  <div class="font-semibold text-slate-800">
                    {{ $fact->fact_value }}
                  </div>

                  <div class="mt-1 text-sm text-slate-500">
                    {{ $fact->fact_type }}
                    /
                    {{ $fact->verification_status }}
                  </div>

                  @if ($fact->source)
                  <div class="mt-2 text-sm">

                    <span class="text-slate-500">
                      出典：
                    </span>

                    <a
                      href="{{ $fact->source->url }}"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-blue-600 underline hover:text-blue-800">
                      {{ $fact->source->title ?? $fact->source->publisher }}
                    </a>

                  </div>
                  @endif

                </div>

                @empty

                <p class="text-sm text-slate-500">
                  根拠情報はまだ登録されていません。
                </p>

                @endforelse

              </div>

            </div>

          </details>

        </div>

      </article>

      @endforeach

    </div>

    {{-- 条件に合う求人候補 --}}
    <div class="mt-8">

      <h2 class="text-xl font-bold text-slate-900">
        この条件に合う求人候補
      </h2>

      <p class="mt-2 text-sm text-slate-500">
        実在求人から、条件に合う候補を表示しています。
      </p>

      <div class="mt-4 space-y-4">

        @forelse ($jobPostings as $jobPosting)

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

          <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

            <div class="min-w-0">

              <div class="text-sm font-semibold text-blue-600">
                <x-company-name :name="($jobPosting->published_company_name ?? $jobPosting->company?->name) ?? '企業名未確認'" />
              </div>

              <h3 class="mt-1 text-lg font-bold leading-7 text-slate-900">
                {{ $jobPosting->title }}
              </h3>

              <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600">

                @if ($jobPosting->region)
                <span>
                  勤務地：{{ $jobPosting->region }}
                </span>
                @endif

                @php
                $salaryMin = $jobPosting->salary_min && $jobPosting->salary_min > 0
                ? $jobPosting->salary_min
                : null;

                $salaryMax = $jobPosting->salary_max && $jobPosting->salary_max > 0
                ? $jobPosting->salary_max
                : null;
                @endphp

                @if ($salaryMin || $salaryMax)
                <span>
                  年収目安：
                  @if ($salaryMin && $salaryMax)
                  {{ $salaryMin }}〜{{ $salaryMax }}万円
                  @elseif ($salaryMin)
                  {{ $salaryMin }}万円以上
                  @elseif ($salaryMax)
                  {{ $salaryMax }}万円以下
                  @endif
                </span>
                @endif

                @if ($jobPosting->employment_type)
                <span>
                  雇用形態：{{ $jobPosting->employment_type }}
                </span>
                @endif

              </div>

            </div>

            <div class="shrink-0">

              <a
                href="{{ route('routes.show', [
                  'jobPosting' => $jobPosting->id,
                  'userQuery' => $userQuery->id,
                ]) }}"
                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 font-bold text-white transition hover:bg-blue-700 md:w-auto">
                応募経路を比較
                <span class="ml-2">›</span>
              </a>

            </div>

          </div>

        </article>

        @empty

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center">

          <p class="text-sm text-slate-600">
            現在、この条件に合う求人候補は登録されていません。
          </p>

        </div>

        @endforelse

      </div>

    </div>

    <div class="mt-5 text-center">

      <a
        href="{{ route('query.create') }}"
        class="inline-block rounded-xl border border-blue-600 px-6 py-3 font-semibold text-blue-600 transition hover:bg-blue-50">
        条件を変えてもう一度試す
      </a>

    </div>

    {{-- Decision Support --}}
    <div class="mt-8 rounded-2xl bg-blue-50 px-6 py-5 ring-1 ring-blue-100">

      <p class="font-semibold text-blue-950">
        RecommendではなくDecision Support。
      </p>

      <p class="mt-1 text-sm leading-6 text-slate-600">
        JobDDは比較材料と根拠を整理します。最終判断はご本人が行います。
      </p>

    </div>

  </main>

  <script>
    console.log('evidence log script loaded');

    document.querySelectorAll('.evidence-details').forEach((details) => {
      details.addEventListener('toggle', async () => {

        console.log('evidence toggled', {
          open: details.open,
          userQueryId: details.dataset.userQueryId,
          agencyId: details.dataset.agencyId,
        });

        if (!details.open) {
          return;
        }

        if (details.dataset.logged === 'true') {
          return;
        }

        try {

          const response = await fetch(
            '{{ route("interaction.evidence-opened") }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
              },
              body: JSON.stringify({
                user_query_id: Number(details.dataset.userQueryId),
                agency_id: Number(details.dataset.agencyId),
              }),
            }
          );

          console.log(
            'evidence response status:',
            response.status
          );

          if (response.ok) {

            details.dataset.logged = 'true';

            console.log(
              'evidence log saved'
            );

          } else {

            console.error(
              'evidence log failed'
            );

          }

        } catch (error) {

          console.error(
            'evidence fetch error:',
            error
          );

        }
      });
    });
  </script>

</body>

</html>
-->