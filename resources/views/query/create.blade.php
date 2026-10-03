<!DOCTYPE html>
<html lang="ja">

<head>
    @include('partials.favicon')
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>転職相談 | JobDD</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="jobdd min-h-screen bg-slate-50 text-slate-900">

  {{-- ヘッダー --}}
  <x-site-header />

  <main class="mx-auto max-w-6xl px-6 py-10">
    <a href="{{ route('jobs.start') }}" class="mb-6 inline-block rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white">新しい求人比較を試す</a>

    {{-- ページ上部 --}}
    <div class="mb-8 flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">

      <div>

        <h1 class="text-3xl font-bold tracking-tight text-blue-950 md:text-4xl">
          転職について教えてください
        </h1>

        <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
          希望条件をもとに、JobDDが応募経路を整理します。
          分からない項目は空欄でも大丈夫です。
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

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

      {{-- 入力フォーム --}}
      <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-8">

        <div class="mb-7">

          <p class="text-sm font-semibold text-blue-600">
            希望条件
          </p>

          <h2 class="mt-2 text-2xl font-bold text-blue-950">
            転職についての希望・相談内容
          </h2>

          <p class="mt-2 text-sm leading-6 text-slate-500">
            決まっている条件だけ入力してください。
            言葉にしにくい希望は、一番下の「その他の希望」に自由に書けます。
          </p>

        </div>

        @if ($errors->any())

        <div class="mb-6 rounded-xl bg-red-50 px-5 py-4 text-sm text-red-700 ring-1 ring-red-100">

          <p class="font-semibold">
            入力内容を確認してください。
          </p>

          <ul class="mt-2 list-disc pl-5">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
          </ul>

        </div>

        @endif

        <form
          id="query-form"
          method="POST"
          action="{{ route('query.store') }}"
          class="space-y-6">

          @csrf

          {{-- 現行バックエンド互換用 --}}
          <input
            type="hidden"
            id="raw_text"
            name="raw_text"
            value="{{ old('raw_text') }}">

          {{-- 希望職種 --}}
          <div>

            <label
              for="occupation"
              class="block text-sm font-semibold text-slate-800">
              希望職種
              <span class="ml-1 text-red-500">*</span>
            </label>

            <p class="mt-1 text-xs text-slate-500">
              例：機械設計、施工管理、Webエンジニア
            </p>

            <input
              type="text"
              id="occupation"
              name="occupation"
              required
              autocomplete="off"
              placeholder="例：機械設計"
              class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-50">

          </div>

          {{-- 希望業界 --}}
          <div>

            <label
              for="industry"
              class="block text-sm font-semibold text-slate-800">
              希望業界
              <span class="ml-1 text-xs font-normal text-slate-400">
                任意
              </span>
            </label>

            <p class="mt-1 text-xs text-slate-500">
              決まっていなければ空欄で構いません。
            </p>

            <input
              type="text"
              id="industry"
              name="industry"
              autocomplete="off"
              placeholder="例：製造・プラント、IT・ソフトウェア"
              class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-50">

          </div>

          {{-- 勤務地 --}}
          <div>

            <label class="block text-sm font-semibold text-slate-800">
              希望勤務地
              <span class="ml-1 text-red-500">*</span>
            </label>

            <p class="mt-1 text-xs text-slate-500">
              都道府県と、希望があれば市区町村まで入力してください。
            </p>

            <div class="mt-2 grid gap-3 sm:grid-cols-2">

              <input
                type="text"
                id="prefecture"
                name="prefecture"
                required
                autocomplete="off"
                placeholder="例：兵庫県"
                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-50">

              <input
                type="text"
                id="city"
                name="city"
                autocomplete="off"
                placeholder="例：加古川市"
                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-50">

            </div>

          </div>

          {{-- 希望年収 --}}
          <div>

            <label
              for="salary"
              class="block text-sm font-semibold text-slate-800">
              希望年収
              <span class="ml-1 text-xs font-normal text-slate-400">
                任意
              </span>
            </label>

            <select
              id="salary"
              name="salary"
              class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-50">

              <option value="">
                選択してください
              </option>

              <option value="こだわらない">
                こだわらない
              </option>

              <option value="300万円以上">
                300万円以上
              </option>

              <option value="400万円以上">
                400万円以上
              </option>

              <option value="500万円以上">
                500万円以上
              </option>

              <option value="600万円以上">
                600万円以上
              </option>

              <option value="700万円以上">
                700万円以上
              </option>

              <option value="800万円以上">
                800万円以上
              </option>

              <option value="1000万円以上">
                1,000万円以上
              </option>

            </select>

          </div>

          {{-- 経験年数 --}}
          <div>

            <label
              for="experience"
              class="block text-sm font-semibold text-slate-800">
              経験年数
              <span class="ml-1 text-xs font-normal text-slate-400">
                任意
              </span>
            </label>

            <select
              id="experience"
              name="experience"
              class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-50">

              <option value="">
                選択してください
              </option>

              <option value="未経験">
                未経験
              </option>

              <option value="1年">
                1年程度
              </option>

              <option value="3年">
                3年程度
              </option>

              <option value="5年">
                5年程度
              </option>

              <option value="10年">
                10年程度
              </option>

              <option value="15年">
                15年程度
              </option>

              <option value="20年">
                20年以上
              </option>

            </select>

          </div>

          {{-- その他の希望 --}}
          <div>

            <label
              for="additional_preferences"
              class="block text-sm font-semibold text-slate-800">
              その他の希望・相談したいこと
              <span class="ml-1 text-xs font-normal text-slate-400">
                任意
              </span>
            </label>

            <p class="mt-1 text-xs leading-5 text-slate-500">
              働き方、会社の雰囲気、学びたいことなど、検索条件だけでは表せない希望を書いてください。
            </p>

            <textarea
              id="additional_preferences"
              name="additional_preferences"
              rows="5"
              placeholder="例：コーディングの達人がいて、技術をよく学べる会社が良いです。"
              class="mt-2 w-full resize-y rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-50"></textarea>

          </div>

          {{-- 送信 --}}
          <div class="border-t border-slate-200 pt-6">

            <button
              type="submit"
              class="w-full rounded-xl bg-blue-600 px-6 py-4 text-base font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">
              応募経路を調べる
            </button>

            <p class="mt-3 text-center text-xs leading-5 text-slate-500">
              入力内容をもとに、JobDDが比較に必要な情報を整理します。
            </p>

          </div>

        </form>

      </section>

      {{-- 右側ガイド --}}
      <aside class="space-y-4">

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">

          <div class="flex items-start gap-4">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 font-bold text-blue-600">
              1
            </div>

            <div>

              <p class="font-semibold text-blue-950">
                入力は約1分
              </p>

              <p class="mt-1 text-sm leading-6 text-slate-500">
                分かる範囲だけ入力すれば大丈夫です。
              </p>

            </div>

          </div>

        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">

          <div class="flex items-start gap-4">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 font-bold text-blue-600">
              2
            </div>

            <div>

              <p class="font-semibold text-blue-950">
                個人名は不要
              </p>

              <p class="mt-1 text-sm leading-6 text-slate-500">
                比較に必要な希望条件だけ入力してください。
              </p>

            </div>

          </div>

        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">

          <div class="flex items-start gap-4">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 font-bold text-blue-600">
              3
            </div>

            <div>

              <p class="font-semibold text-blue-950">
                定性的な希望もOK
              </p>

              <p class="mt-1 text-sm leading-6 text-slate-500">
                「技術を学べる会社」など、数字にしにくい希望も自由に書けます。
              </p>

            </div>

          </div>

        </section>

        {{-- Decision Support --}}
        <section class="rounded-2xl bg-blue-50 p-5 ring-1 ring-blue-100">

          <p class="font-semibold text-blue-950">
            RecommendではなくDecision Support。
          </p>

          <p class="mt-2 text-sm leading-6 text-slate-600">
            JobDDが転職先を決めるのではなく、比較材料を整理して意思決定を支援します。
          </p>

        </section>

      </aside>

    </div>

  </main>

  {{-- 現行バックエンド用：
       構造化入力を raw_text にまとめて送信する --}}
  <script>
    const queryForm = document.getElementById('query-form');

    queryForm.addEventListener('submit', () => {

      const occupation =
        document.getElementById('occupation').value.trim();

      const industry =
        document.getElementById('industry').value.trim();

      const prefecture =
        document.getElementById('prefecture').value.trim();

      const city =
        document.getElementById('city').value.trim();

      const salary =
        document.getElementById('salary').value;

      const experience =
        document.getElementById('experience').value;

      const additionalPreferences =
        document.getElementById('additional_preferences').value.trim();

      const lines = [];

      if (occupation) {
        lines.push(`希望職種は${occupation}です。`);
      }

      if (industry) {
        lines.push(`希望業界は${industry}です。`);
      }

      if (prefecture || city) {
        lines.push(
          `希望勤務地は${prefecture}${city}です。`
        );
      }

      if (salary) {

        if (salary === 'こだわらない') {
          lines.push('年収にはこだわりません。');
        } else {
          lines.push(`希望年収は${salary}です。`);
        }

      }

      if (experience) {

        if (experience === '未経験') {
          lines.push('希望職種は未経験です。');
        } else {
          lines.push(`経験年数は${experience}です。`);
        }

      }

      if (additionalPreferences) {
        lines.push(additionalPreferences);
      }

      document.getElementById('raw_text').value =
        lines.join('\n');

    });
  </script>

<x-site-footer />
</body>

</html>