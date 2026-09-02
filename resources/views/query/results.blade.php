<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>紹介会社TOP3 | JobDD</title>

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
          あなたに合う人材紹介会社 TOP3
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

    {{-- TOP3 --}}
    <div class="space-y-5">

      @foreach ($results as $index => $result)

      <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-7">

        {{-- 上部 --}}
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">

          <div class="min-w-0">

            <div class="mb-2 inline-flex rounded-full bg-blue-50 px-3 py-1 text-sm font-bold text-blue-600">
              {{ $index + 1 }}位
            </div>

            <h2 class="text-2xl font-bold tracking-tight text-blue-950">
              {{ $result->agency->name }}
            </h2>

            <div class="mt-4 space-y-2 text-sm leading-6 text-slate-700">

              <p>
                <span class="font-semibold text-slate-900">
                  得意職種：
                </span>
                {{ $result->agency->occupation ?? '情報なし' }}
              </p>

              <p>
                <span class="font-semibold text-slate-900">
                  対応地域：
                </span>
                {{ $result->agency->region ?? '情報なし' }}
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

          </div>

        </div>

        {{-- 5軸 --}}
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              職種適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->occupation_score }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              地域適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->region_score }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              経験適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->experience_score }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              年収適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->salary_score }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3 text-center">
            <div class="text-xs text-slate-500">
              求人適合
            </div>
            <div class="mt-1 font-bold text-blue-950">
              {{ $result->job_score }}
            </div>
          </div>

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

    {{-- 次の行動 --}}
    <div class="mt-8">

      <a
        href="{{ route('routes.show', [
          'jobPosting' => 1,
          'userQuery' => $userQuery->id,
        ]) }}"
        class="block w-full rounded-xl bg-blue-600 px-6 py-4 text-center font-bold text-white transition hover:bg-blue-700">
        この条件に合う求人の応募経路を比較する
        <span class="ml-2">›</span>
      </a>

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
          const response = await fetch('{{ route("interaction.evidence-opened") }}', {
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
          });

          console.log('evidence response status:', response.status);

          if (response.ok) {
            details.dataset.logged = 'true';
            console.log('evidence log saved');
          } else {
            console.error('evidence log failed');
          }
        } catch (error) {
          console.error('evidence fetch error:', error);
        }
      });
    });
  </script>

</body>

</html>