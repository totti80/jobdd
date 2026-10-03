<!DOCTYPE html>
<html lang="ja">

<head>
    @include('partials.favicon')
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Agency Fact Review | JobDD</title>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">

  <header class="border-b bg-white">
    <div class="mx-auto max-w-6xl px-6 py-5">
      <div class="text-2xl font-bold text-blue-950">
        JobDD Admin
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-6 py-10">

    <div class="mb-8">
      <p class="text-sm font-semibold text-blue-600">
        Slice 5 / Verification
      </p>

      <h1 class="mt-2 text-3xl font-bold text-blue-950">
        未確認の調査情報
      </h1>

      <p class="mt-3 text-slate-600">
        AI等で取得した情報を確認し、verified / rejected を判断します。
      </p>
    </div>

    @if ($facts->isEmpty())

    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
      <p class="text-slate-500">
        現在、pending の情報はありません。
      </p>
    </div>

    @else

    <div class="space-y-5">

      @foreach ($facts as $fact)

      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">

          <div class="space-y-3">

            <div>
              <span class="text-sm text-slate-500">紹介会社</span>
              <div class="text-xl font-bold text-blue-950">
                {{ $fact->agency?->name ?? '不明' }}
              </div>
            </div>

            <div>
              <span class="text-sm text-slate-500">Fact</span>
              <div class="font-semibold text-slate-800">
                {{ $fact->fact_value }}
              </div>
            </div>

            <div class="text-sm text-slate-600">
              {{ $fact->fact_type }}
              /
              {{ $fact->fact_key }}
            </div>

            @if ($fact->source)
            <div class="text-sm">
              <span class="text-slate-500">出典：</span>

              <a
                href="{{ $fact->source->url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-blue-600 underline hover:text-blue-800">
                {{ $fact->source->title ?? $fact->source->publisher ?? '出典を見る' }}
              </a>
            </div>
            @endif

          </div>

          <div class="flex min-w-44 flex-col gap-3">

            <span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-center text-sm font-semibold text-amber-700">
              pending
            </span>

            <form
              method="POST"
              action="{{ route('admin.agency-facts.verify', $fact) }}">
              @csrf
              @method('PATCH')

              <button
                type="submit"
                class="w-full rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                verified にする
              </button>
            </form>

            <form
              method="POST"
              action="{{ route('admin.agency-facts.reject', $fact) }}">
              @csrf
              @method('PATCH')

              <button
                type="submit"
                class="w-full rounded-xl border border-red-300 px-4 py-3 font-semibold text-red-600 hover:bg-red-50">
                rejected にする
              </button>
            </form>

          </div>

        </div>

      </div>

      @endforeach

    </div>

    @endif

  </main>

</body>

</html>