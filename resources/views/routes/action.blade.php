<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>次の行動 | JobDD</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

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

    <div class="mb-8 flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">

      <div>

        <h1 class="text-3xl font-bold tracking-tight text-blue-950 md:text-4xl">
          次の行動を選択してください
        </h1>

        <p class="mt-4 text-base leading-7 text-slate-600">
          比較結果を確認したうえで、
          あなたが最も納得できる方法で進めましょう。
        </p>

      </div>

      <div class="flex items-start gap-3 text-sm text-slate-500">

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-600">
            ✓
          </div>
          <span class="mt-2">入力</span>
        </div>

        <div class="mt-4 h-px w-10 bg-blue-200"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-600">
            ✓
          </div>
          <span class="mt-2">調査</span>
        </div>

        <div class="mt-4 h-px w-10 bg-blue-200"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-600">
            ✓
          </div>
          <span class="mt-2">比較</span>
        </div>

        <div class="mt-4 h-px w-10 bg-blue-200"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 font-bold text-white">
            4
          </div>
          <span class="mt-2 font-semibold text-blue-600">
            行動
          </span>
        </div>

      </div>

    </div>

    <section class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

      <p class="text-sm font-semibold text-blue-600">
        対象求人
      </p>

      <h2 class="mt-2 text-2xl font-bold text-blue-950">
        {{ $jobPosting->title }}
      </h2>

      <p class="mt-2 font-semibold text-slate-700">
        {{ $jobPosting->company->name }}
      </p>

    </section>

    <section class="grid gap-5 md:grid-cols-3">

      @foreach ($jobPosting->applicationRoutes as $route)

      @php
      $isDirect = $route->route_type === 'direct';
      $isAgent = $route->route_type === 'agent';
      $isPlatform = $route->route_type === 'platform';
      @endphp

      <article class="flex flex-col rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

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
          Directで進む
          @elseif ($isAgent)
          Agentで進む
          @elseif ($isPlatform)
          Platformで進む
          @endif

        </h2>

        <p class="mt-3 text-sm leading-6 text-slate-600">

          @if ($isDirect)
          企業の採用ページから直接応募します。
          @elseif ($isAgent)
          人材紹介会社に相談して、転職活動を進めます。
          @elseif ($isPlatform)
          求人媒体を通じて応募します。
          @endif

        </p>

        <div class="mt-auto pt-6">

          @if ($route->application_url)

          <a
            href="{{ $route->application_url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="block w-full rounded-xl bg-blue-600 px-5 py-3 text-center font-semibold text-white transition hover:bg-blue-700">

            @if ($isDirect)
            企業サイトを見る
            @elseif ($isAgent)
            紹介会社に相談する
            @elseif ($isPlatform)
            求人媒体を見る
            @endif

          </a>

          @else

          <button
            type="button"
            disabled
            class="w-full cursor-not-allowed rounded-xl bg-slate-100 px-5 py-3 text-center font-semibold text-slate-400">

            @if ($isAgent)
            紹介会社に相談する
            @else
            リンク準備中
            @endif

          </button>

          @endif

        </div>

      </article>

      @endforeach

    </section>

    <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

        <div>

          <p class="font-semibold text-blue-950">
            まだ迷っている方へ
          </p>

          <p class="mt-2 text-sm leading-6 text-slate-600">
            この結果を保存して、あとで比較し直すことができます。
          </p>

        </div>

        <button
          type="button"
          disabled
          class="rounded-xl border border-slate-300 bg-white px-6 py-3 font-semibold text-slate-400">
          結果を保存する
        </button>

      </div>

      <p class="mt-3 text-xs text-slate-400">
        ※ β版では保存機能は準備中です。
      </p>

    </section>

    <section class="mt-6 rounded-2xl bg-blue-50 px-6 py-5 ring-1 ring-blue-100">

      <p class="font-semibold text-blue-950">
        最終判断はあなた自身が行います。
      </p>

      <p class="mt-2 text-sm leading-6 text-slate-600">
        JobDDは応募先や応募経路を決定するサービスではありません。
        比較材料を整理し、次の行動を選ぶためのDecision Supportを提供します。
      </p>

    </section>

    <div class="mt-6">

      <a
        href="{{ route('routes.show', [
          'jobPosting' => $jobPosting->id,
          'userQuery' => request('userQuery'),
        ]) }}"
        class="text-sm font-semibold text-blue-600 hover:underline">
        ← 比較画面に戻る
      </a>

    </div>

  </main>

</body>

</html>