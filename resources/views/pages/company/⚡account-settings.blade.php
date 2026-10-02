<?php

use App\Concerns\PasswordValidationRules;
use App\Services\CompanyWorkspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.company-layout', ['title' => 'アカウント設定'])] #[Title('アカウント設定')] class extends Component
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $user = Auth::user();
        abort_unless($user && ($user->isPlatformOwner() || app(CompanyWorkspace::class)->companyFor($user)), 403);

        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        $user->update(['password' => $validated['password']]);
        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('password-updated', 'パスワードを変更しました。');
    }
}; ?>

<div>
    <x-company-hero title="アカウント設定">アカウント情報の確認とパスワード変更ができます。</x-company-hero>
    @php
    $user = auth()->user();
    $company = app(CompanyWorkspace::class)->companyFor($user);
    $role = $company?->pivot->role;
    @endphp
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-2">
        <section aria-labelledby="account-info-title" class="min-w-0 rounded-xl border border-blue-100 bg-white p-5 sm:p-7">
            <h2 id="account-info-title" class="text-xl font-bold text-blue-950">アカウント情報</h2>
            <dl class="mt-6 space-y-5">
                @foreach(['会社名' => $company?->name ?? '所属企業なし', '表示名' => $user->name, 'メールアドレス' => $user->email, '企業権限' => match ($role) { 'company_owner' => '企業オーナー（company_owner）', 'company_editor' => '企業編集者（company_editor）', default => '所属企業なし' }] as $label => $value)
                <div>
                    <dt class="text-sm text-slate-600">{{ $label }}</dt>
                    <dd class="mt-1 break-words font-semibold text-blue-950">{{ $value }}</dd>
                </div>
                @endforeach
            </dl>
        </section>
        <section aria-labelledby="password-title" class="min-w-0 rounded-xl border border-blue-100 bg-white p-5 sm:p-7">
            <h2 id="password-title" class="text-xl font-bold text-blue-950">パスワード変更</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">12文字以上で、大文字・小文字・数字・記号をそれぞれ1文字以上含めてください。</p>
            @if(session('password-updated'))<p role="status" class="mt-4 rounded-lg bg-emerald-50 p-3 text-emerald-900">{{ session('password-updated') }}</p>@endif
            <form wire:submit="updatePassword" class="mt-6 flex flex-col gap-5">
                @csrf
                <flux:input wire:model="current_password" label="現在のパスワード" type="password" required autocomplete="current-password">
                    <x-slot:iconTrailing>
                        <flux:input.viewable inset="left right" class="cursor-pointer" type="button" aria-label="パスワードを表示" x-bind:aria-label="open ? 'パスワードを非表示' : 'パスワードを表示'" />
                    </x-slot:iconTrailing>
                </flux:input>
                <flux:input wire:model="password" label="新しいパスワード" type="password" required autocomplete="new-password">
                    <x-slot:iconTrailing>
                        <flux:input.viewable inset="left right" class="cursor-pointer" type="button" aria-label="パスワードを表示" x-bind:aria-label="open ? 'パスワードを非表示' : 'パスワードを表示'" />
                    </x-slot:iconTrailing>
                </flux:input>
                <flux:input wire:model="password_confirmation" label="新しいパスワード（確認）" type="password" required autocomplete="new-password">
                    <x-slot:iconTrailing>
                        <flux:input.viewable inset="left right" class="cursor-pointer" type="button" aria-label="パスワードを表示" x-bind:aria-label="open ? 'パスワードを非表示' : 'パスワードを表示'" />
                    </x-slot:iconTrailing>
                </flux:input>
                <flux:button type="submit" variant="primary" class="w-full bg-blue-700! text-white! hover:bg-blue-800!" wire:loading.attr="disabled" wire:target="updatePassword">保存</flux:button>
            </form>
        </section>
    </div>
    <a href="{{ route('company.dashboard') }}" class="mt-6 inline-flex min-h-11 items-center text-blue-800 underline">企業ダッシュボードへ戻る</a>
</div>