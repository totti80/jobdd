<x-company-layout title="新規企業登録">
    <section class="grid overflow-hidden rounded-2xl bg-blue-50 md:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 p-6 sm:p-8 md:px-6 xl:px-8">
            <h1 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl">新規企業登録</h1>
            <div class="mt-3 max-w-2xl leading-7 text-slate-600">JobDDで、仕事の中身を伝える求人を作成しましょう。<p class="mt-2 text-sm">企業登録後、すぐに求人の作成を始められます。</p></div>
        </div>
        <div class="flex min-w-0 items-center justify-end md:min-w-80">
            <img src="{{ asset('images/jobdd/jobdd-hero-kinki.png') }}" alt="" aria-hidden="true" class="pointer-events-none block h-auto w-full object-contain opacity-100 md:h-full md:object-cover">
        </div>
    </section>
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <section class="min-w-0 rounded-xl border border-blue-100 bg-white p-5 sm:p-7">
        <form method="POST" action="{{ route('company.register.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="account_name" label="会社名またはユーザーID" :value="old('account_name')"
                type="text" required autofocus maxlength="255" autocomplete="organization" placeholder="株式会社マプリイ / maply-design" />
            <flux:input name="email" label="メールアドレス" :value="old('email')"
                type="email" required maxlength="255" autocomplete="email" placeholder="email@example.com" />
            <flux:input name="password" label="パスワード" type="password" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" viewable />
            <p class="-mt-4 text-xs leading-5 text-slate-500">12文字以上で、大文字・小文字・数字・記号を各1文字以上含めてください。</p>
            <flux:input name="password_confirmation" label="パスワード（確認）" type="password" required autocomplete="new-password" viewable />
            <flux:button type="submit" variant="primary" class="w-full bg-blue-700! hover:bg-blue-800! text-white!">企業アカウントを作成</flux:button>
        </form>

            <p class="mt-6 text-sm text-slate-600">すでにアカウントをお持ちの方 → <a href="{{ route('login') }}" class="text-blue-800 underline">ログイン</a></p>
        </section>
        <aside class="min-w-0 space-y-5"><section class="rounded-xl border border-blue-100 bg-white p-5"><h2 class="text-lg font-bold text-blue-950">登録するとできること</h2><ul class="mt-4 list-inside list-disc space-y-3 text-sm text-slate-600">@foreach(['求人Draftを作成', 'Level 1 基本情報を登録', 'Level 2 仕事の中身を構造化', '求職者向けPreviewを確認', '公開申請'] as $item)<li>{{ $item }}</li>@endforeach</ul><p class="mt-5 rounded-lg bg-blue-50 p-3 text-sm leading-6 text-blue-950">求職者向けへの公開にはJobDDの公開確認があります。</p></section></aside>
    </div>
</x-company-layout>
