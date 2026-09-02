<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>応募経路比較 | JobDD</title>

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
            {{ $jobPosting->company->name }}
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
              @if ($jobPosting->salary_min || $jobPosting->salary_max)
              {{ $jobPosting->salary_min ?? '?' }}
              〜
              {{ $jobPosting->salary_max ?? '?' }}
              万円
              @else
              未指定
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
        企業公式・人材紹介会社・求人媒体について、
        <strong class="text-slate-800">
          応募可否・応募方法・サポート・企業との直接性
        </strong>
        を確認しています。
      </p>

    </section>

    {{-- 3経路サマリー --}}
    <section class="grid gap-5 md:grid-cols-3">

      @foreach ($jobPosting->applicationRoutes as $route)

      @php
      $isDirect = $route->route_type === 'direct';
      $isAgent = $route->route_type === 'agent';
      $isPlatform = $route->route_type === 'platform';

      $availabilityLabel = match ($route->availability_status) {
      'available' => '応募可能',
      'unavailable' => '応募不可',
      default => $route->availability_status,
      };
      @endphp

      <article class="flex flex-col rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

        {{-- 経路名 --}}
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

          {{-- カードでは要点だけ表示 --}}
          <div class="mt-4 space-y-2 text-sm leading-6 text-slate-600">

            @if ($isDirect)

            <p>✓ 企業公式ページから応募可能</p>
            <p>✓ 企業と直接やり取りする経路</p>

            @elseif ($isAgent)

            <p>✓ 人材紹介会社を通じて応募</p>
            <p>✓ 転職支援を受けながら進められる</p>

            @elseif ($isPlatform)

            <p>✓ 求人媒体を通じて応募</p>
            <p>✓ 媒体の仕組みに沿って手続きを進める</p>

            @endif

          </div>

        </div>

        {{-- 選択 --}}
        <div class="mt-auto pt-6">

          <button
            type="button"
            class="route-select-button w-full rounded-xl border border-blue-600 px-5 py-3 text-center font-semibold text-blue-600 transition hover:bg-blue-50"
            data-user-query-id="{{ request('userQuery') }}"
            data-application-route-id="{{ $route->id }}">
            この経路を選ぶ
          </button>

          @if ($route->application_url)

          <a
            href="{{ $route->application_url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="contact-link mt-3 inline-block w-full rounded-xl bg-blue-600 px-5 py-3 text-center font-semibold text-white transition hover:bg-blue-700"
            data-user-query-id="{{ request('userQuery') }}"
            data-application-route-id="{{ $route->id }}">
            応募ページを見る
            <span class="ml-2">›</span>
          </a>

          @else

          <div class="mt-3 rounded-xl bg-slate-100 px-5 py-3 text-center text-sm text-slate-500">
            応募URL未登録
          </div>

          @endif

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
          応募経路ごとの違い
        </h2>

      </div>

      <div class="overflow-x-auto">

        <table class="w-full min-w-[760px] border-collapse text-sm">

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

            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                応募可否
              </td>

              @foreach (['direct', 'agent', 'platform'] as $type)

              @php
              $comparisonRoute = $jobPosting->applicationRoutes
              ->firstWhere('route_type', $type);

              $comparisonAvailability = match ($comparisonRoute?->availability_status) {
              'available' => '○ 応募可能',
              'unavailable' => '× 応募不可',
              default => $comparisonRoute?->availability_status ?? '情報なし',
              };
              @endphp

              <td class="px-4 py-4 text-center">
                {{ $comparisonAvailability }}
              </td>

              @endforeach

            </tr>

            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                応募方法
              </td>

              <td class="px-4 py-4 text-center">
                企業公式から直接
              </td>

              <td class="px-4 py-4 text-center">
                人材紹介会社を経由
              </td>

              <td class="px-4 py-4 text-center">
                求人媒体を経由
              </td>

            </tr>

            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                サポート
              </td>

              <td class="px-4 py-4 text-center">
                なし
              </td>

              <td class="px-4 py-4 text-center">
                あり
              </td>

              <td class="px-4 py-4 text-center">
                サービスによる
              </td>

            </tr>

            <tr>

              <td class="px-4 py-4 font-semibold text-slate-700">
                企業との直接性
              </td>

              <td class="px-4 py-4 text-center">
                高い
              </td>

              <td class="px-4 py-4 text-center">
                低い
              </td>

              <td class="px-4 py-4 text-center">
                中程度
              </td>

            </tr>

          </tbody>

        </table>

      </div>

      {{-- 確認元 --}}
      <div class="mt-5 border-t border-slate-200 pt-5">

        <p class="text-sm font-semibold text-slate-700">
          今回の確認元
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm text-slate-600">

          <span class="rounded-full bg-slate-50 px-4 py-2">
            企業公式採用ページ
          </span>

          <span class="rounded-full bg-slate-50 px-4 py-2">
            人材紹介会社の公開情報
          </span>

          <span class="rounded-full bg-slate-50 px-4 py-2">
            求人媒体の公開情報
          </span>

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
              user_query_id: Number(button.dataset.userQueryId),
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

        event.preventDefault();

        const destination = link.href;

        try {

          const response = await fetch('{{ route("interaction.contact-clicked") }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              user_query_id: Number(link.dataset.userQueryId),
              application_route_id: Number(link.dataset.applicationRouteId),
            }),
          });

          if (response.ok) {

            console.log('contact_clicked saved', {
              userQueryId: link.dataset.userQueryId,
              applicationRouteId: link.dataset.applicationRouteId,
            });

            window.location.href = destination;

          } else {

            console.error(
              'contact_clicked failed:',
              response.status
            );

          }

        } catch (error) {

          console.error(
            'contact_clicked fetch error:',
            error
          );

        }
      });
    });
  </script>

</body>

</html>