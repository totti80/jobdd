<x-layouts::app :title="'新規アイコン登録'">
    <div class="mx-auto w-full max-w-md rounded-lg border border-neutral-200 bg-white px-6 py-8 shadow dark:border-neutral-700 dark:bg-neutral-900">
        <h1 class="mb-8 text-center text-2xl font-bold">
            新規アイコン登録
        </h1>

        @if ($errors->any())
            <div class="mb-4 rounded bg-red-100 px-3 py-3 text-red-600">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('icon.store') }}"
            method="POST"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="mb-6">
                <label class="mb-2 block text-sm font-bold" for="image">
                    アイコン画像
                </label>

                <input
                    id="image"
                    name="image"
                    type="file"
                >
            </div>

            <div class="mb-6">
                <label class="mb-2 block text-sm font-bold" for="title">
                    タイトル
                </label>

                <input
                    class="w-full rounded border border-gray-300 px-3 py-2 dark:border-neutral-600 dark:bg-neutral-800"
                    id="title"
                    type="text"
                    name="title"
                    value="{{ old('title') }}"
                >
            </div>

            <div class="mb-6">
                <label class="mb-2 block text-sm font-bold" for="description">
                    説明
                </label>

                <textarea
                    class="w-full rounded border border-gray-300 px-3 py-2 dark:border-neutral-600 dark:bg-neutral-800"
                    id="description"
                    name="description"
                    rows="4"
                >{{ old('description') }}</textarea>
            </div>

            <button
                class="w-full rounded-lg bg-blue-500 px-4 py-3 font-bold text-white hover:bg-blue-600"
                type="submit"
            >
                登録する
            </button>
        </form>
    </div>
</x-layouts::app>