<x-layouts::auth title="企業アカウント登録">
    <div class="flex flex-col gap-6">
        <x-auth-header title="企業アカウント登録" description="会社名またはユーザーIDで登録できます。" />

        <form method="POST" action="{{ route('company.register.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="account_name" label="会社名またはユーザーID" :value="old('account_name')"
                type="text" required autofocus maxlength="255" autocomplete="organization" placeholder="株式会社マプリイ / maply-design" />
            <flux:input name="email" label="メールアドレス" :value="old('email')"
                type="email" required maxlength="255" autocomplete="email" placeholder="email@example.com" />
            <flux:input name="password" label="パスワード" type="password" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" viewable />
            <flux:input name="password_confirmation" label="パスワード（確認）" type="password" required autocomplete="new-password" viewable />
            <flux:button type="submit" variant="primary" class="w-full">企業アカウントを作成</flux:button>
        </form>

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            登録済みの方は <flux:link :href="route('login')">ログイン</flux:link>
        </p>
    </div>
</x-layouts::auth>
