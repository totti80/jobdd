<x-public-layout title="詳細条件入力 | JobDD">
    <div class="site-container py-10 sm:py-16">
        <x-page-hero title="詳細条件入力">担当工程や、顧客・製造・現場との関わり、仕事の進め方などを追加すると、求人の仕事の中身をより詳しく比較できます。</x-page-hero>
        <section class="jobdd-card max-w-3xl">
            <h2 class="text-xl font-bold text-blue-950">まずは4つの基本条件を入力してください</h2>
            <p class="mt-4 leading-8">詳細条件を入力するには、まず4つの基本条件（希望職種・希望勤務地・希望年収・CAD / Tool）を入力してください。</p>
            <div class="site-actions">
                <a href="{{ route('jobs.start') }}" class="jobdd-button">かんたん入力をする</a>
            </div>
        </section>
    </div>
</x-public-layout>
