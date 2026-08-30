<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>応募経路比較 | JobDD</title>

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

  <main class="mx-auto max-w-6xl px-6 py-12">

    <div class="mb-10">
      <p class="mb-3 text-sm font-semibold text-blue-600">
        STEP 2 / 応募経路比較
      </p>

      <h1 class="text-3xl font-bold text-blue-950">
        {{ $jobPosting->title }}
      </h1>

      <p class="mt-3 text-lg text-slate-700">
        {{ $jobPosting->company->name }}
      </p>

      <div class="mt-5 flex flex-wrap gap-3 text-sm">
        <span class="rounded-full bg-blue-50 px-4 py-2">
          職種：{{ $jobPosting->occupation ?? '未指定' }}
        </span>

        <span class="rounded-full bg-blue-50 px-4 py-2">
          地域：{{ $jobPosting->region ?? '未指定' }}
        </span>

        <span class="rounded-full bg-blue-50 px-4 py-2">
          年収：
          @if ($jobPosting->salary_min || $jobPosting->salary_max)
          {{ $jobPosting->salary_min ?? '?' }}
          〜
          {{ $jobPosting->salary_max ?? '?' }}
          万円
          @else
          未指定
          @endif
        </span>
      </div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">

      @foreach ($jobPosting->applicationRoutes as $route)

      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

        <div class="mb-5">
          <div class="text-sm font-semibold uppercase tracking-wide text-blue-600">
            {{ $route->route_type }}
          </div>

          <h2 class="mt-2 text-2xl font-bold text-blue-950">
            @if ($route->route_type === 'direct')
            企業へ直接応募
            @elseif ($route->route_type === 'agent')
            {{ $route->agency?->name ?? '人材紹介会社' }}
            @elseif ($route->route_type === 'platform')
            {{ $route->platform?->name ?? '転職プラットフォーム' }}
            @endif
          </h2>
        </div>

        <div class="space-y-3 text-sm text-slate-700">

          <p>
            <span class="font-semibold">応募可否：</span>
            {{ $route->availability_status }}
          </p>

          <p>
            <span class="font-semibold">説明：</span>
            {{ $route->notes ?? '情報なし' }}
          </p>

        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 text-sm">

          <div class="rounded-xl bg-slate-50 p-3">
            <div class="text-xs text-slate-500">
              サポート
            </div>

            <div class="mt-1 font-semibold">
              @if ($route->route_type === 'agent')
              あり
              @elseif ($route->route_type === 'platform')
              サービスによる
              @else
              なし
              @endif
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 p-3">
            <div class="text-xs text-slate-500">
              企業との直接性
            </div>

            <div class="mt-1 font-semibold">
              @if ($route->route_type === 'direct')
              高い
              @elseif ($route->route_type === 'agent')
              低い
              @else
              中程度
              @endif
            </div>
          </div>

        </div>

        <button
          type="button"
          class="route-select-button mt-6 w-full rounded-xl border border-blue-600 px-5 py-3 text-center font-semibold text-blue-600 hover:bg-blue-50"
          data-user-query-id="{{ request('userQuery') }}"
          data-application-route-id="{{ $route->id }}">
          この経路を選ぶ
        </button>

        @if ($route->application_url)
        <a
          href="{{ $route->application_url }}"
          target="_blank"
          rel="noopener noreferrer"
          class="contact-link mt-3 inline-block w-full rounded-xl bg-blue-600 px-5 py-3 text-center font-semibold text-white hover:bg-blue-700"
          data-user-query-id="{{ request('userQuery') }}"
          data-application-route-id="{{ $route->id }}">
          応募ページを見る
        </a>
        @else
        <div class="mt-3 rounded-xl bg-slate-100 px-5 py-3 text-center text-sm text-slate-500">
          応募URL未登録
        </div>
        @endif

      </div>

      @endforeach

    </div>

    <div class="mt-10 rounded-2xl bg-blue-50 p-6">
      <p class="font-semibold text-blue-950">
        JobDDは応募経路を決定しません。
      </p>

      <p class="mt-2 text-sm text-slate-600">
        各経路の違いを整理し、最終判断はご本人が行います。
      </p>
    </div>

  </main>

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
            console.error('route_selected failed:', response.status);
          }
        } catch (error) {
          console.error('route_selected fetch error:', error);
        }
      });
    });
  </script>

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
            console.error('contact_clicked failed:', response.status);
          }
        } catch (error) {
          console.error('contact_clicked fetch error:', error);
        }
      });
    });
  </script>

</body>

</html>