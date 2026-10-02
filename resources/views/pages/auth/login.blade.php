<x-company-layout title="企業ログイン">
    <x-company-hero title="企業ログイン">仕事の中身を構造化して、<br class="hidden sm:block">求職者に正しく伝えましょう。</x-company-hero>
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="min-w-0 rounded-xl border border-blue-100 bg-white p-5 sm:p-7 flex flex-col gap-6">


        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                label="メールアドレス"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    label="パスワード"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="パスワード"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="mt-2 inline-flex min-h-11 items-center text-sm" :href="route('password.request')">
                        パスワードを忘れた方
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" label="ログイン状態を保持" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full bg-blue-700! hover:bg-blue-800! text-white!" data-test="login-button">
                    ログイン
                </flux:button>
            </div>
        </form>

        <div class="border-t border-blue-100 pt-5 text-sm text-slate-600"><p>はじめてJobDDをご利用の企業はこちら</p><a href="{{ route('company.register') }}" class="mt-2 inline-flex min-h-11 items-center font-semibold text-blue-800 underline">新規企業登録</a></div>
    </div>
    <aside class="min-w-0"><x-company-guide title="JobDDでできること" /></aside>
    </div>
</x-company-layout>
