<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>相談内容を入力 | JobDD</title>

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
    <div class="mb-10 flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">

      {{-- 見出し --}}
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-blue-950 md:text-4xl">
          まずは相談内容を入力してください
        </h1>

        <p class="mt-4 text-base leading-7 text-slate-600">
          転職について希望していることを自由に入力してください。<br class="hidden md:block">
          あなたに合う応募経路を整理します。
        </p>
      </div>

      {{-- ステップ --}}
      <div class="flex items-start gap-3 text-sm text-slate-500">

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 font-bold text-white">
            1
          </div>
          <span class="mt-2 font-semibold text-blue-600">
            入力
          </span>
        </div>

        <div class="mt-4 h-px w-10 bg-slate-300"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white font-semibold">
            2
          </div>
          <span class="mt-2">
            調査
          </span>
        </div>

        <div class="mt-4 h-px w-10 bg-slate-300"></div>

        <div class="flex flex-col items-center">
          <div class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white font-semibold">
            3
          </div>
          <span class="mt-2">
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

    {{-- メイン2カラム --}}
    <div class="grid gap-8 lg:grid-cols-3">

      {{-- 左：入力フォーム --}}
      <section class="lg:col-span-2">

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-7">

          @if (session('success'))
          <div class="mb-6 rounded-lg bg-green-50 px-4 py-3 text-green-700">
            {{ session('success') }}
          </div>
          @endif

          @if ($errors->any())
          <div class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-red-700">
            {{ $errors->first() }}
          </div>
          @endif

          <form method="POST" action="{{ route('query.store') }}">
            @csrf

            <label
              for="raw_text"
              class="mb-3 block font-semibold text-slate-800">
              転職についての希望・相談内容
            </label>

            <textarea
              id="raw_text"
              name="raw_text"
              rows="8"
              maxlength="2000"
              placeholder="例：機械設計の経験が10年あります。兵庫県で、年収600万円以上の仕事を探しています。自分に合う人材紹介会社を比較したいです。"
              class="w-full resize-y rounded-xl border border-slate-300 bg-white p-4 leading-7 text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('raw_text') }}</textarea>

            <button
              type="submit"
              class="mt-6 w-full rounded-xl bg-blue-600 px-6 py-4 text-base font-bold text-white transition hover:bg-blue-700">
              応募経路を調べる
              <span class="ml-2">›</span>
            </button>

          </form>

        </div>

      </section>

      {{-- 右：補助情報 --}}
      <aside class="space-y-3">

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
          <div class="flex gap-4">

            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-lg font-bold text-blue-600">
              1
            </div>

            <div>
              <h2 class="font-bold text-blue-950">
                入力は約1分
              </h2>

              <p class="mt-2 text-sm leading-6 text-slate-600">
                希望や状況を自由に入力するだけ。かんたんに相談を始められます。
              </p>
            </div>

          </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
          <div class="flex gap-4">

            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-lg font-bold text-emerald-600">
              ✓
            </div>

            <div>
              <h2 class="font-bold text-blue-950">
                個人名は不要
              </h2>

              <p class="mt-2 text-sm leading-6 text-slate-600">
                氏名や連絡先を入力せずに利用できます。
              </p>
            </div>

          </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
          <div class="flex gap-4">

            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-violet-50 text-lg font-bold text-violet-600">
              i
            </div>

            <div>
              <h2 class="font-bold text-blue-950">
                結果は参考情報です
              </h2>

              <p class="mt-2 text-sm leading-6 text-slate-600">
                複数の情報を整理して比較材料を提示します。最終判断はご本人が行います。
              </p>
            </div>

          </div>
        </div>

        <div class="rounded-2xl bg-blue-50 p-5 ring-1 ring-blue-100">
          <p class="text-sm font-semibold leading-6 text-blue-900">
            RecommendではなくDecision Support。
          </p>

          <p class="mt-1 text-sm leading-6 text-blue-800">
            JobDDは意思決定を支援するサービスです。
          </p>
        </div>

      </aside>

    </div>

  </main>

</body>

</html>