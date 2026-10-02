<x-public-layout title="お問い合わせ | JobDD">
    <div class="site-container py-10 sm:py-16">
        <x-page-hero title="お問い合わせ" eyebrow="JobDD">JobDDのご利用、求人掲載、不具合などについてお問い合わせください。</x-page-hero>
        <section class="jobdd-card max-w-3xl" aria-label="お問い合わせフォーム">
            @if (session('contact_sent'))
                <div role="status" class="mb-6 rounded-lg border border-teal-200 bg-teal-50 p-4 text-teal-900">
                    お問い合わせを受け付けました。内容を確認のうえ、入力いただいたメールアドレスへご連絡します。
                </div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                    <p class="font-semibold">送信できませんでした。以下をご確認ください。</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <p class="mb-6 text-sm leading-7 text-slate-600">すべての項目が必須です。入力いただいた情報は、お問い合わせへの対応に使用します。パスワードなどの機密情報は入力しないでください。</p>
            <form method="POST" action="{{ route('public.contact.store') }}" class="space-y-6">
                @csrf
                @foreach (['name' => ['お名前', 'text', 100, 'name'], 'email' => ['メールアドレス', 'email', 254, 'email']] as $field => [$label, $type, $maximum, $autocomplete])
                    <div>
                        <label for="contact-{{ $field }}" class="block font-semibold text-blue-950">{{ $label }} <span class="text-sm text-slate-600">（必須）</span></label>
                        <input id="contact-{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ is_string(old($field)) ? old($field) : '' }}" maxlength="{{ $maximum }}" autocomplete="{{ $autocomplete }}" required class="mt-2 min-h-12 w-full rounded-lg border border-slate-500 bg-white px-3 py-2" @error($field) aria-invalid="true" aria-describedby="contact-{{ $field }}-error" @enderror>
                        @error($field)<p id="contact-{{ $field }}-error" class="mt-2 text-sm text-red-800">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="contact-subject" class="block font-semibold text-blue-950">お問い合わせ種別 <span class="text-sm text-slate-600">（必須）</span></label>
                    <select id="contact-subject" name="subject" required class="mt-2 min-h-12 w-full rounded-lg border border-slate-500 bg-white px-3 py-2" @error('subject') aria-invalid="true" aria-describedby="contact-subject-error" @enderror>
                        <option value="">選択してください</option>
                        @foreach (\App\Models\ContactInquiry::CATEGORIES as $category)
                            <option value="{{ $category }}" @selected(old('subject') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    @error('subject')<p id="contact-subject-error" class="mt-2 text-sm text-red-800">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="contact-message" class="block font-semibold text-blue-950">お問い合わせ内容 <span class="text-sm text-slate-600">（必須・5,000文字まで）</span></label>
                    <textarea id="contact-message" name="message" rows="8" maxlength="5000" required class="mt-2 w-full rounded-lg border border-slate-500 bg-white px-3 py-2" @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror>{{ is_string(old('message')) ? old('message') : '' }}</textarea>
                    @error('message')<p id="contact-message-error" class="mt-2 text-sm text-red-800">{{ $message }}</p>@enderror
                </div>
                <div class="site-actions">
                    <button type="submit" class="jobdd-button">お問い合わせを送信する</button>
                    <a href="{{ route('home') }}" class="site-button-secondary">トップへ戻る</a>
                </div>
            </form>
        </section>
    </div>
</x-public-layout>
