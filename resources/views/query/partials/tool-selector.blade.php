<fieldset aria-describedby="{{ $idPrefix }}-help{{ !empty($toolErrors) ? ' '.$idPrefix.'-error' : '' }}">
    <legend class="font-semibold">仕事で使いたいCAD・ツール <span class="text-sm font-normal text-slate-600">任意</span></legend>
    <p id="{{ $idPrefix }}-help" class="mt-2 text-sm leading-6 text-slate-600">複数選択できます。求人本文に担当業務として記載があるかを確認します。</p>
    <div class="mt-3 grid gap-3 min-[360px]:grid-cols-2 sm:grid-cols-3 {{ ($wide ?? false) ? 'lg:grid-cols-4' : '' }}">
        @foreach ($tools as $key => $name)
            <label class="jobdd-choice" for="{{ $idPrefix }}-{{ $key }}">
                <input id="{{ $idPrefix }}-{{ $key }}" type="checkbox" name="tools[]" value="{{ $key }}" @checked(in_array($key, $selected, true))
                    @if (!empty($toolErrors)) aria-invalid="true" @endif
                    aria-describedby="{{ $idPrefix }}-help{{ !empty($toolErrors) ? ' '.$idPrefix.'-error' : '' }}">
                <span>{{ $name }}</span>
            </label>
        @endforeach
    </div>
    @if (!empty($toolErrors))
        <p id="{{ $idPrefix }}-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($toolErrors)) }}</p>
    @endif
</fieldset>
