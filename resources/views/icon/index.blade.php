<x-layouts::app :title="'アイコン一覧'">
    <h1 class="mb-6 text-2xl font-bold">アイコン一覧</h1>

    <div class="rounded-lg border border-neutral-200 bg-white shadow dark:border-neutral-700 dark:bg-neutral-900">
        <ul class="divide-y divide-gray-200 dark:divide-neutral-700">
            @forelse ($icons as $icon)
            <li class="px-6 py-4">
                @if ($icon->image_path)
                <img
                    src="{{ asset('storage/' . $icon->image_path) }}"
                    alt="{{ $icon->title }}"
                    class="mb-3 h-20 w-20 rounded-lg object-cover">
                @endif

                <h2 class="text-lg font-semibold">
                    {{ $icon->title }}
                </h2>

                <p class="text-sm text-gray-500">
                    {{ $icon->description }}
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ $icon->created_at }} / {{ $icon->user->name }}
                </p>

                <a
                    href="{{ route('icon.edit', ['id' => $icon->id]) }}"
                    class="font-medium text-blue-500 hover:text-blue-600">
                    編集
                </a>
                
                <form
                    action="{{ route('icon.destroy', ['id' => $icon->id]) }}"
                    method="POST"
                    class="mt-2"
                    onsubmit="return window.confirm('このアイコンを本当に削除しますか？')">
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="font-medium text-red-500 hover:text-red-600">
                        削除
                    </button>
                </form>

            </li>
            @empty
            <li class="px-6 py-8 text-center text-gray-500">
                まだアイコンが登録されていません。
            </li>
            @endforelse
        </ul>
    </div>
</x-layouts::app>