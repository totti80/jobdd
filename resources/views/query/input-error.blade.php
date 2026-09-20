<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>入力内容の確認 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen p-4 sm:p-6" data-jobdd-root>
<main class="jobdd-card mx-auto max-w-2xl">
    <p class="font-bold text-blue-950">JobDD</p>
    <h1 class="mt-4 text-2xl font-bold">入力内容を確認してください</h1>
    <p role="alert" class="mt-4 leading-7">{{ $message }}</p>
    <a href="{{ route('query.jobs', ['userQuery' => $public_id]) }}" class="jobdd-link mt-5 inline-flex min-h-12 items-center">求人一覧へ戻る</a>
</main>
</body>
</html>
