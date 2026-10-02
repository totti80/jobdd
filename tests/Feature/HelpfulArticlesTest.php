<?php

use Illuminate\Support\Facades\DB;

function helpfulArticles(): array
{
    return [
        ['cad-experience', 'CAD・設計職', '機械設計の転職で、CAD経験をどう伝える？ ― ソフト名だけでは伝わらない5つのポイント', 'cad_eyecatch_1200x675.jpg', 'CADを使った機械設計のイメージ'],
        ['job-change-preparation', '転職ノウハウ', '転職活動を始める前に整理しておきたい5つの条件', 'career_eyecatch_1200x675.jpg', '転職条件を整理するビジネスパーソンのイメージ'],
        ['how-to-read-job-postings', '求人の読み方', '求人票の「仕事内容」だけでは分からない5つのポイント', 'job-reading_eyecatch_1200x675.jpg', 'パソコンとメモを使って情報を確認するイメージ'],
    ];
}

test('homepage and resource listing link all three illustrated articles', function (string $name) {
    $response = $this->get(route($name))->assertOk()->assertDontSee('準備中');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    foreach (helpfulArticles() as [$slug, $category, $title, $filename, $alt]) {
        $url = route('public.resources.'.$slug);
        $response->assertSee($category)->assertSee($title)->assertSee(asset('images/helpful/'.$filename), false)->assertSee($alt);
        $links = $xpath->query('//main//a[@href="'.$url.'"]');
        expect($links->length)->toBe(2);
        $imageLink = $links->item(0);
        expect($imageLink->getAttribute('aria-label'))->toContain($title);
        expect($xpath->query('./img', $imageLink)->length)->toBe(1);
        expect($xpath->query('./*', $imageLink)->length)->toBe(1);
        expect($xpath->query('.//h3', $imageLink)->length)->toBe(0);
        expect($imageLink->firstElementChild->getAttribute('alt'))->toBe($alt);
        expect(trim($links->item(1)->textContent))->toContain('記事を読む');
        $this->get($url)->assertOk();
    }
})->with(['home', 'public.resources']);

test('static articles render complete content images shared navigation and return links', function (string $slug, string $category, string $title, string $filename, string $alt) {
    $response = $this->get('/helpful/'.$slug)->assertOk()->assertViewIs('public.helpful.'.$slug)
        ->assertSee($category)->assertSee($title)->assertSee($alt)->assertDontSee('準備中')->assertDontSee('<form', false)
        ->assertSee('お役立ち情報へ戻る')->assertSee('トップへ戻る');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//main//h1')->length)->toBe(1);
    expect(trim($xpath->query('//main//h1')->item(0)->textContent))->toBe($title);
    $image = $xpath->query('//main//img')->item(0);
    expect($image->getAttribute('src'))->toBe(asset('images/helpful/'.$filename));
    expect($image->getAttribute('alt'))->toBe($alt);
    expect($image->getAttribute('width'))->toBe('1200');
    expect($image->getAttribute('height'))->toBe('675');
    expect($image->getAttribute('class'))->toContain('aspect-video', 'object-cover', 'w-full');
    expect(getimagesize(public_path('images/helpful/'.$filename)))->toMatchArray([0 => 1200, 1 => 675]);
    $body = $xpath->query('//*[@data-helpful-body]')->item(0);
    $length = mb_strlen(preg_replace('/\s+/u', '', $body->textContent));
    expect($length)->toBeGreaterThanOrEqual(800)->toBeLessThanOrEqual(1500);
    expect($xpath->query('//main//h2')->length)->toBe(6);
    expect($xpath->query('//main//a[@href="'.route('public.resources').'"]')->length)->toBe(1);
    expect($xpath->query('//main//a[@href="'.route('home').'"]')->length)->toBe(1);
    foreach (['header', 'footer'] as $area) {
        expect($xpath->query('//'.$area.'//nav/a')->length)->toBe(6);
    }
    $active = $xpath->query('//header//nav/a[@aria-current="page"]');
    expect($active->length)->toBe(1);
    expect(trim($active->item(0)->textContent))->toBe('お役立ち情報');
})->with(helpfulArticles());

test('helpful content keeps the public read only session and database boundaries', function () {
    config(['session.driver' => 'database']);
    DB::enableQueryLog();
    try {
        $this->get(route('public.resources'))->assertOk();
        foreach (helpfulArticles() as [$slug]) {
            $this->get(route('public.resources.'.$slug))->assertOk();
        }
        $writes = collect(DB::getQueryLog())->filter(fn ($entry) => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $entry['query']));
        expect($writes)->toBeEmpty();
    } finally {
        DB::disableQueryLog();
    }
});

test('unknown articles do not fall back to arbitrary templates', function () {
    $this->get('/helpful/unknown')->assertNotFound();
});
