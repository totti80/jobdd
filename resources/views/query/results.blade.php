<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>紹介会社TOP3 | JobDD</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

  <header class="border-b bg-white">
    <div class="mx-auto max-w-6xl px-6 py-5">
      <div class="text-2xl font-bold text-blue-950">
        JobDD
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-5xl px-6 py-12">

    <div class="mb-10 text-center">
      <p class="mb-3 text-sm font-semibold text-blue-600">
        STEP 3 / 比較
      </p>

      <h1 class="text-3xl font-bold text-blue-950">
        あなたに合う人材紹介会社 TOP3
      </h1>

      <p class="mt-4 text-slate-600">
        入力内容と紹介会社データをもとに比較しています。
      </p>

      <div class="mx-auto mt-6 max-w-3xl rounded-xl bg-blue-50 px-5 py-4 text-left text-sm text-slate-700">
        <div class="mb-2 font-semibold text-blue-950">
          今回の入力条件
        </div>

        <div class="flex flex-wrap gap-x-6 gap-y-2">
          <span>
            職種：
            <strong>{{ $userQuery->occupation ?? '未指定' }}</strong>
          </span>

          <span>
            地域：
            <strong>{{ $userQuery->region ?? '未指定' }}</strong>
          </span>

          <span>
            経験：
            <strong>
              {{ $userQuery->experience_years !== null ? $userQuery->experience_years . '年' : '未指定' }}
            </strong>
          </span>

          <span>
            希望年収：
            <strong>
              {{ $userQuery->salary_min !== null ? $userQuery->salary_min . '万円以上' : '未指定' }}
            </strong>
          </span>
        </div>
      </div>
    </div>

    <div class="space-y-6">

      @foreach ($results as $index => $result)

      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

        <div class="flex items-start justify-between gap-6">

          <div>
            <div class="mb-2 text-sm font-bold text-blue-600">
              {{ $index + 1 }}位
            </div>

            <h2 class="text-2xl font-bold text-blue-950">
              {{ $result->agency->name }}
            </h2>

            <div class="mt-4 space-y-2 text-sm text-slate-700">
              <p>
                <span class="font-semibold">得意職種：</span>
                {{ $result->agency->occupation ?? '情報なし' }}
              </p>

              <p>
                <span class="font-semibold">対応地域：</span>
                {{ $result->agency->region ?? '情報なし' }}
              </p>

              <p>
                <span class="font-semibold">適合理由：</span>
                {{ $result->reason ?: '現在の公開情報から算定しています。' }}
              </p>

              <p>
                <span class="font-semibold">Evidence Level：</span>
                {{ $result->agency->evidence_level }}
              </p>
            </div>
          </div>


          <div class="text-right">
            <div class="text-sm text-slate-500">
              JobDD Score
            </div>

            <div class="text-5xl font-bold text-blue-600">
              {{ $result->score }}
            </div>

            <div class="text-sm text-slate-400">
              / 100
            </div>
          </div>

        </div>

        <div class="mt-4">
          <details class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <summary class="cursor-pointer font-semibold text-blue-600">
              根拠を見る
            </summary>

            <div class="mt-4 space-y-4">

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
                  <span class="text-slate-500">出典：</span>

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
          </details>
        </div>

        <div class="mt-6 flex gap-3">

          <div class="flex-1 rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">職種適合</div>
            <div class="mt-1 font-bold">{{ $result->occupation_score }}</div>
          </div>

          <div class="flex-1 rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">地域適合</div>
            <div class="mt-1 font-bold">{{ $result->region_score }}</div>
          </div>

          <div class="flex-1 rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">経験適合</div>
            <div class="mt-1 font-bold">{{ $result->experience_score }}</div>
          </div>

          <div class="flex-1 rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">年収適合</div>
            <div class="mt-1 font-bold">{{ $result->salary_score }}</div>
          </div>

          <div class="flex-1 rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">求人適合</div>
            <div class="mt-1 font-bold">{{ $result->job_score }}</div>
          </div>

        </div>

      </div>

      @endforeach

    </div>

    <div class="mt-10 text-center">
      <a
        href="{{ route('query.create') }}"
        class="inline-block rounded-xl border border-blue-600 px-6 py-3 font-semibold text-blue-600 hover:bg-blue-50">
        条件を変えてもう一度試す
      </a>
    </div>

    <p class="mt-8 text-center text-sm text-slate-500">
      RecommendではなくDecision Support。
      最終判断はご本人が行います。
    </p>

  </main>

</body>

</html>