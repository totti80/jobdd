<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>入力内容の確認 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 p-6 text-slate-900">
<main class="mx-auto max-w-2xl rounded-2xl bg-white p-6 ring-1 ring-slate-200">
    <p class="font-bold text-blue-950">JobDD</p>
    <h1 class="mt-4 text-2xl font-bold">入力内容を確認してください</h1>
    <p role="alert" class="mt-4 leading-7">{{ $message }}</p>
    <a href="{{ route('query.jobs', ['userQuery' => $public_id]) }}" class="mt-5 inline-block py-2 font-semibold text-blue-800 underline">求人一覧へ戻る</a>
</main>
</body>
</html>
