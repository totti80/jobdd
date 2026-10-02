@props(['title' => '求人公開までの流れ'])
<section class="rounded-xl border border-blue-100 bg-white p-5 sm:p-6">
    <h2 class="text-lg font-bold text-blue-950">{{ $title }}</h2>
    <ol class="mt-4 space-y-4 text-sm leading-6">
        @foreach(['Level 1 Basic' => '職種・勤務地・年収などの基本情報を登録', 'Level 2 Structured Job Profile' => '工程・Tool・仕事の進め方・Typical Dayなどを構造化', 'Preview' => '求職者から実際にどう見えるか確認', '公開申請' => '入力内容をJobDDへ公開申請', '公開審査' => 'JobDD運営が公開可能な状態か確認', '求職者向けDecision View' => '承認後、求職者が仕事内容を理解・比較できる状態へ'] as $label => $description)
        <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-800">{{ $loop->iteration }}</span><div><p class="font-semibold text-blue-950">{{ $label }}</p><p class="text-slate-600">{{ $description }}</p></div></li>
        @endforeach
    </ol>
</section>
