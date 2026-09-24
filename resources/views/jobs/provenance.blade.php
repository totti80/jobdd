<x-public-layout title="情報の出所 | JobDD">
<div class="mx-auto max-w-3xl px-4 py-10"><x-company-card><h1 class="text-2xl font-bold">情報の出所</h1><p class="mt-5 break-words">{{ $data['level_one']['title'] }}</p><p class="mt-3 break-words">{{ $data['provenance']['publisher'] }}がJobDDへ登録した情報です。</p><p class="mt-3">企業提供情報 / JobDD公開確認済み</p><p class="mt-3 text-sm leading-7">公開確認は、企業が申告した内容の真偽を保証するものではありません。</p><p class="mt-3 text-sm">公開版更新：{{ $data['provenance']['published_at'] }}</p></x-company-card></div>
</x-public-layout>
