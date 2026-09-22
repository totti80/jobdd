<x-layouts::auth title="企業マイページ">
    <div class="flex flex-col gap-6">
        <x-auth-header title="企業マイページ" description="企業向けの機能は準備中です。" />
        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ auth()->user()->name }} さんでログインしています。</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" class="w-full">ログアウト</flux:button>
        </form>
    </div>
</x-layouts::auth>
