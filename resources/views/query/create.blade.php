<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>相談内容を入力 | JobDD</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

  <header class="border-b bg-white">
    <div class="mx-auto flex max-w-6xl items-center px-6 py-5">
      <div class="text-2xl font-bold text-blue-950">
        JobDD
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-3xl px-6 py-16">

    <div class="mb-10 text-center">
      <p class="mb-3 text-sm font-semibold text-blue-600">
        STEP 1 / 入力
      </p>

      <h1 class="text-3xl font-bold text-blue-950">
        相談内容を入力してください
      </h1>

      <p class="mt-4 text-slate-600">
        転職について希望していることを、自由に入力してください。
      </p>
    </div>

    <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">

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
          class="mb-2 block font-semibold text-slate-800">
          転職についての希望・相談内容
        </label>

        <textarea
          id="raw_text"
          name="raw_text"
          rows="8"
          maxlength="2000"
          placeholder="例：機械設計の経験が10年あります。兵庫県で、年収600万円以上の仕事を探しています。自分に合う人材紹介会社を比較したいです。"
          class="w-full rounded-xl border border-slate-300 p-4 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('raw_text') }}</textarea>

        <button
          type="submit"
          class="mt-6 w-full rounded-xl bg-blue-600 px-6 py-4 font-bold text-white hover:bg-blue-700">
          応募経路を調べる
        </button>
      </form>

    </div>

    <p class="mt-6 text-center text-sm text-slate-500">
      RecommendではなくDecision Support。
      最終判断はご本人が行います。
    </p>

  </main>

</body>

</html>