<!DOCTYPE html>
<html lang="ja">

<head>
    @include('partials.favicon')
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>応募経路比較 | JobDD</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="jobdd min-h-screen bg-slate-50 text-slate-900">

  {{-- ヘッダー --}}
  <x-site-header />

  <main class="mx-auto max-w-6xl px-6 py-10">

    {{-- ページ上部 --}}
    <div class="mb-8 flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">

      <div>
        <h1 class="text-3xl font-bold tracking-tight text-blue-950 md:text-4xl">
          あなたに合う応募経路を比較
        </h1>

        <p class="mt-4 text-base leading-7 text-slate-600">
          対象求人について、利用できる応募経路とその違いを整理しました。<br class="hidden md:block">
          比較材料を確認して、ご自身で応募方法を選べます。
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

    {{-- 対象求人 --}}
    <section class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

      <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">

        <div>

          <p class="text-sm font-semibold text-blue-600">
            対象求人
          </p>

          <h2 class="mt-2 text-2xl font-bold text-blue-950">
            {{ $jobPosting->title }}
          </h2>

          <p class="mt-2 font-semibold text-slate-700">
            <x-company-name :name="($jobPosting->published_company_name ?? $jobPosting->company?->name)" />
          </p>

        </div>

        <div class="flex flex-wrap gap-2 text-sm">

          <span class="rounded-full bg-blue-50 px-4 py-2 text-slate-700 ring-1 ring-blue-100">
            職種：
            <strong>{{ $jobPosting->occupation ?? '未指定' }}</strong>
          </span>

          <span class="rounded-full bg-blue-50 px-4 py-2 text-slate-700 ring-1 ring-blue-100">
            地域：
            <strong>{{ $jobPosting->region ?? '未指定' }}</strong>
          </span>

          <span class="rounded-full bg-blue-50 px-4 py-2 text-slate-700 ring-1 ring-blue-100">
            年収：
            <strong>
              @php
              $salaryMin = $jobPosting->salary_min && $jobPosting->salary_min > 0
              ? $jobPosting->salary_min
              : null;

              $salaryMax = $jobPosting->salary_max && $jobPosting->salary_max > 0
              ? $jobPosting->salary_max
              : null;
              @endphp

              @if ($salaryMin && $salaryMax)
              {{ $salaryMin }}〜{{ $salaryMax }}万円

              @elseif ($salaryMin)
              {{ $salaryMin }}万円以上

              @elseif ($salaryMax)
              {{ $salaryMax }}万円以下

              @else
              非公開
              @endif
            </strong>
          </span>

        </div>

      </div>

    </section>

    {{-- 今回JobDDが比較したこと --}}
    <section class="mb-6 rounded-2xl bg-blue-50 px-6 py-5 ring-1 ring-blue-100">

      <p class="text-sm font-semibold text-blue-600">
        今回JobDDが比較したこと
      </p>

      <h2 class="mt-2 text-xl font-bold text-blue-950">
        3つの応募経路を同じ項目で整理しました
      </h2>

      <p class="mt-2 text-sm leading-6 text-slate-600">
        確認できた応募経路について、
        <strong class="text-slate-800">
          応募可否・応募方法・情報源
        </strong>
        を整理しています。未確認の経路は「未確認」と表示します。
      </p>

    </section>

    {{-- 3経路サマリー --}}
    <section class="grid gap-5 md:grid-cols-3">

      @foreach ($routeGroups as $routeType => $routes)

      @continue($routes->isEmpty())

      @php
      $isDirect = $routeType === 'direct';
      $isAgent = $routeType === 'agent';
      $isPlatform = $routeType === 'platform';
      @endphp

      <article class="flex flex-col rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

        <div>

          <p
            class="
            text-sm font-bold uppercase tracking-wide
            {{ $isDirect ? 'text-blue-600' : '' }}
            {{ $isAgent ? 'text-emerald-600' : '' }}
            {{ $isPlatform ? 'text-violet-600' : '' }}
          ">
            @if ($isDirect)
            Direct
            @elseif ($isAgent)
            Agent
            @elseif ($isPlatform)
            Platform
            @endif
          </p>

          <h2 class="mt-2 text-2xl font-bold text-blue-950">
            @if ($isDirect)
            企業へ直接応募
            @elseif ($isAgent)
            人材紹介会社経由
            @elseif ($isPlatform)
            求人媒体経由
            @endif
          </h2>

          <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">

            @if ($isDirect)

            @foreach ($routes as $route)
            <div class="rounded-xl bg-blue-50 px-4 py-3">
              <p class="font-semibold text-blue-950">
                企業公式採用ページ
              </p>

              <p class="mt-1">
                {{ $route->availability_status === 'available' ? '応募可能' : '応募状況を確認' }}
              </p>
            </div>
            @endforeach

            @elseif ($isAgent)

            @foreach ($routes as $route)
            <div class="rounded-xl bg-emerald-50 px-4 py-3">
              <p class="font-semibold text-emerald-950">
                {{ $route->agency?->name ?? '人材紹介会社' }}
              </p>

              <p class="mt-1">
                {{ $route->availability_status === 'available' ? '応募可能' : '応募状況を確認' }}
              </p>
            </div>
            @endforeach

            @elseif ($isPlatform)

            @foreach ($routes as $route)
            <div class="rounded-xl bg-violet-50 px-4 py-3">

              <p class="font-semibold text-violet-950">
                {{ $route->platform?->name ?? '求人媒体' }}
              </p>

              <p class="mt-1">
                {{ $route->availability_status === 'available' ? '応募可能' : '応募状況を確認' }}
              </p>

              @if ($route->notes)
              <p class="mt-1 text-xs text-slate-500">
                {{ $route->notes }}
              </p>
              @endif

            </div>
            @endforeach

            @endif

          </div>

        </div>

        <div class="mt-auto pt-6">

          @foreach ($routes as $route)

          <div class="{{ !$loop->first ? 'mt-4 border-t border-slate-100 pt-4' : '' }}">

            <button
              type="button"
              class="route-select-button w-full rounded-xl border border-blue-600 px-5 py-3 text-center font-semibold text-blue-600 transition hover:bg-blue-50"
              data-user-query-id="{{ request('userQuery') }}"
              data-application-route-id="{{ $route->id }}">
              @if ($isPlatform && $route->platform)
              {{ $route->platform->name }}を選ぶ
              @elseif ($isAgent && $route->agency)
              {{ $route->agency->name }}を選ぶ
              @else
              この経路を選ぶ
              @endif
            </button>

            @if (\App\Support\JobDecisionPresenter::safeUrl($route->application_url))

            <a
              href="{{ $route->application_url }}"
              target="_blank"
              rel="noopener noreferrer"
              class="contact-link mt-3 inline-block w-full rounded-xl bg-blue-600 px-5 py-3 text-center font-semibold text-white transition hover:bg-blue-700"
              data-user-query-id="{{ request('userQuery') }}"
              data-application-route-id="{{ $route->id }}">
              @if ($isPlatform && $route->platform)
              {{ $route->platform->name }}の応募ページを見る
              @else
              応募ページを見る
              @endif
              <span class="ml-2">›</span>
            </a>

            @else

            <div class="mt-3 rounded-xl bg-slate-100 px-5 py-3 text-center text-sm text-slate-500">
              応募URL未登録
            </div>

            @endif

          </div>

          @endforeach

        </div>

      </article>

      @endforeach

    </section>

    {{-- 詳細比較 --}}
    <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

      <div class="mb-5">

        <p class="text-sm font-semibold text-blue-600">
          詳細比較
        </p>

        <h2 class="mt-2 text-xl font-bold text-blue-950">
          今回確認できた事実と、経路ごとの一般的な特徴
        </h2>

        <p class="mt-2 text-sm leading-6 text-slate-600">
          上段はこの求人について確認できた内容、
          下段は応募経路そのものの一般的な特徴です。
        </p>

      </div>

      <div class="overflow-x-auto">

        <table class="w-full min-w-[820px] border-collapse text-sm">

          <thead>

            <tr class="border-b border-slate-200">

              <th class="px-4 py-3 text-left font-semibold text-slate-500">
                比較項目
              </th>

              <th class="px-4 py-3 text-center font-bold text-blue-600">
                Direct
              </th>

              <th class="px-4 py-3 text-center font-bold text-emerald-600">
                Agent
              </th>

              <th class="px-4 py-3 text-center font-bold text-violet-600">
                Platform
              </th>

            </tr>

          </thead>

          <tbody class="divide-y divide-slate-100">

            {{-- 今回確認できたFACT --}}
            <tr class="bg-slate-50">

              <td
                colspan="4"
                class="px-4 py-3 font-bold text-slate-700">
                今回の求人で確認できたこと
              </td>

            </tr>

            {{-- 確認状況 --}}
            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                確認状況
              </td>

              @foreach (['direct', 'agent', 'platform'] as $type)

              @php
              $comparisonRoutes = $jobPosting->applicationRoutes
              ->where('route_type', $type)
              ->values();
              @endphp

              <td class="px-4 py-4 text-center">

                @if ($comparisonRoutes->isEmpty())

                <span class="text-slate-400">
                  未確認
                </span>

                @else

                <span class="font-semibold text-emerald-700">
                  {{ $comparisonRoutes->count() }}件確認済み
                </span>

                @endif

              </td>

              @endforeach

            </tr>

            {{-- 応募可否 --}}
            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                応募可否
              </td>

              @foreach (['direct', 'agent', 'platform'] as $type)

              @php
              $comparisonRoutes = $jobPosting->applicationRoutes
              ->where('route_type', $type)
              ->values();

              $availableCount = $comparisonRoutes
              ->where('availability_status', 'available')
              ->count();
              @endphp

              <td class="px-4 py-4 text-center">

                @if ($comparisonRoutes->isEmpty())

                未確認

                @elseif ($availableCount === $comparisonRoutes->count())

                ○ {{ $availableCount }}件とも応募可能

                @elseif ($availableCount > 0)

                ○ {{ $availableCount }}件応募可能

                @else

                応募可能な経路は未確認

                @endif

              </td>

              @endforeach

            </tr>

            {{-- 確認できた経路 --}}
            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                確認できた経路
              </td>

              @foreach (['direct', 'agent', 'platform'] as $type)

              @php
              $comparisonRoutes = $jobPosting->applicationRoutes
              ->where('route_type', $type)
              ->values();
              @endphp

              <td class="px-4 py-4 text-center">

                @if ($comparisonRoutes->isEmpty())

                <span class="text-slate-400">
                  ―
                </span>

                @else

                <div class="space-y-1">

                  @foreach ($comparisonRoutes as $comparisonRoute)

                  <div>

                    @if ($type === 'direct')

                    企業公式採用ページ

                    @elseif ($type === 'agent')

                    {{ $comparisonRoute->agency?->name ?? '人材紹介会社' }}

                    @elseif ($type === 'platform')

                    {{ $comparisonRoute->platform?->name ?? '求人媒体' }}

                    @endif

                  </div>

                  @endforeach

                </div>

                @endif

              </td>

              @endforeach

            </tr>

            {{-- 応募URL --}}
            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                応募URL
              </td>

              @foreach (['direct', 'agent', 'platform'] as $type)

              @php
              $comparisonRoutes = $jobPosting->applicationRoutes
              ->where('route_type', $type)
              ->values();
              @endphp

              <td class="px-4 py-4 text-center">

                @if ($comparisonRoutes->isEmpty())

                <span class="text-slate-400">
                  未確認
                </span>

                @else

                <div class="space-y-1">

                  @foreach ($comparisonRoutes as $comparisonRoute)

                  @if (\App\Support\JobDecisionPresenter::safeUrl($comparisonRoute->application_url))

                  <div>
                    <a
                      href="{{ $comparisonRoute->application_url }}"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="font-semibold text-blue-600 underline hover:text-blue-800">
                      @if ($type === 'platform')
                      {{ $comparisonRoute->platform?->name ?? '求人媒体' }}
                      @elseif ($type === 'agent')
                      {{ $comparisonRoute->agency?->name ?? '人材紹介会社' }}
                      @else
                      企業公式
                      @endif
                    </a>
                  </div>

                  @else

                  <div class="text-slate-500">
                    URL未登録
                  </div>

                  @endif

                  @endforeach

                </div>

                @endif

              </td>

              @endforeach

            </tr>

            {{-- 一般説明 --}}
            <tr class="bg-slate-50">

              <td
                colspan="4"
                class="px-4 py-3 font-bold text-slate-700">
                経路ごとの一般的な特徴
              </td>

            </tr>

            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                一般的な応募方法
              </td>

              <td class="px-4 py-4 text-center">
                企業公式サイト等から直接応募
              </td>

              <td class="px-4 py-4 text-center">
                人材紹介会社を通じて応募
              </td>

              <td class="px-4 py-4 text-center">
                求人媒体の仕組みに沿って応募
              </td>

            </tr>

            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                一般的なサポート
              </td>

              <td class="px-4 py-4 text-center">
                原則として自分で進める
              </td>

              <td class="px-4 py-4 text-center">
                面談・応募・選考支援を受けられる場合がある
              </td>

              <td class="px-4 py-4 text-center">
                媒体・サービスによる
              </td>

            </tr>

            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                企業とのやり取り
              </td>

              <td class="px-4 py-4 text-center">
                企業と直接やり取りすることが多い
              </td>

              <td class="px-4 py-4 text-center">
                紹介会社が間に入ることが多い
              </td>

              <td class="px-4 py-4 text-center">
                媒体経由で進める場合がある
              </td>

            </tr>

          </tbody>

        </table>

      </div>

      {{-- 今回確認できた情報源 --}}
      <div class="mt-5 border-t border-slate-200 pt-5">

        <p class="text-sm font-semibold text-slate-700">
          今回確認できた情報源
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm text-slate-600">

          @forelse ($jobPosting->applicationRoutes as $route)

          <span class="rounded-full bg-slate-50 px-4 py-2">

            @if ($route->route_type === 'direct')

            企業公式採用ページ

            @elseif ($route->route_type === 'agent')

            {{ $route->agency?->name ?? '人材紹介会社' }}

            @elseif ($route->route_type === 'platform')

            {{ $route->platform?->name ?? '求人媒体' }}

            @endif

          </span>

          @empty

          <span class="text-slate-400">
            現在確認できた応募経路はありません。
          </span>

          @endforelse

        </div>

      </div>

    </section>

    {{-- β版 / Decision Support --}}
    <section class="mt-6 rounded-2xl bg-blue-50 px-6 py-5 ring-1 ring-blue-100">

      <div class="grid gap-5 md:grid-cols-2">

        <div>

          <p class="font-semibold text-blue-950">
            β版のため、現在は参考情報として提供しています。
          </p>

          <p class="mt-2 text-sm leading-6 text-slate-600">
            応募経路そのものの適合Scoreはまだ算定していません。
            現時点で確認できる情報を比較材料として整理しています。
          </p>

        </div>

        <div class="border-t border-blue-100 pt-5 md:border-l md:border-t-0 md:pl-6 md:pt-0">

          <p class="font-semibold text-blue-950">
            RecommendではなくDecision Support。
          </p>

          <p class="mt-2 text-sm leading-6 text-slate-600">
            JobDDは応募経路を決定しません。
            比較材料を整理し、最終判断はご本人が行います。
          </p>

        </div>

      </div>

    </section>

  </main>

  {{-- 経路選択ログ --}}
  <script>
    document.querySelectorAll('.route-select-button').forEach((button) => {
      button.addEventListener('click', async () => {

        if (button.dataset.logged === 'true') {
          return;
        }

        try {

          const response = await fetch('{{ route("interaction.route-selected") }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              user_query_id: Number(button.dataset.userQueryId) || null,
              application_route_id: Number(button.dataset.applicationRouteId),
            }),
          });

          if (response.ok) {

            button.dataset.logged = 'true';
            button.textContent = '選択しました';
            button.disabled = true;

            console.log('route_selected saved', {
              userQueryId: button.dataset.userQueryId,
              applicationRouteId: button.dataset.applicationRouteId,
            });

          } else {

            console.error(
              'route_selected failed:',
              response.status
            );

          }

        } catch (error) {

          console.error(
            'route_selected fetch error:',
            error
          );

        }
      });
    });
  </script>

  {{-- 外部応募ページクリックログ --}}
  <script>
    document.querySelectorAll('.contact-link').forEach((link) => {

      link.addEventListener('click', async (event) => {
        const payload = JSON.stringify({
          user_query_id: Number(link.dataset.userQueryId) || null,
          application_route_id: Number(link.dataset.applicationRouteId),
        });

        try {
          await fetch('{{ route("interaction.contact-clicked") }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'Accept': 'application/json',
            },
            body: payload,
            keepalive: true,
          });
        } catch (error) {
          console.error('contact_clicked best effort failed:', error);
        }

        // Let the anchor open its destination once, independently of logging.
      });
    });
  </script>

<x-site-footer />
</body>

</html>