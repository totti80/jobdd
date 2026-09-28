<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Support\JobMapLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function mapFixture(array $regions): UserQuery
{
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'map-secret-token',
        'raw_text' => 'map-private-input', 'occupation' => '機械設計', 'region' => '兵庫県']);
    $company = Company::create(['name' => '会社<script>alert(1)</script>']);
    foreach ($regions as $i => $region) {
        JobPosting::create(['company_id' => $company->id, 'title' => '機械設計<script>alert(2)</script>'.$i,
            'occupation' => '機械設計', 'region' => $region, 'source_url' => 'https://careers.map-fixture.jp/jobs/'.$i,
            'description' => '大阪府の詳細住所は推定しない']);
    }

    return $query;
}

function mapDom(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    return new DOMXPath($document);
}

test('map dictionary matches the six official sample coordinate rows and has no workplace precision', function () {
    $expected = ['滋賀県' => [35.003792, 135.867828], '京都府' => [35.02124, 135.755615],
        '大阪府' => [34.68639, 135.520004], '兵庫県' => [34.691257, 135.183075],
        '奈良県' => [34.68528, 135.832779], '和歌山県' => [34.226112, 135.167496]];
    expect(JobMapLocation::POINTS)->toHaveCount(6);
    foreach ($expected as $region => [$latitude, $longitude]) {
        $point = JobMapLocation::point($region);
        expect($point['latitude'])->toBe($latitude)->toBeGreaterThan(-90)->toBeLessThan(90)
            ->and($point['longitude'])->toBe($longitude)->toBeGreaterThan(-180)->toBeLessThan(180)
            ->and($point['is_workplace_coordinate'])->toBeFalse()
            ->and($point['precision'])->toBe('prefecture_representative')
            ->and($point['coordinate_source']['license'])->toBe('PDL-1.0');
    }
});

test('map dictionary never normalizes or infers missing and unsupported regions', function ($region) {
    expect(JobMapLocation::point($region))->toBe([
        'latitude' => null, 'longitude' => null, 'point_key' => null, 'precision' => 'unknown',
        'coordinate_source' => null, 'is_workplace_coordinate' => false,
    ]);
})->with([null, '', 'unknown', '大阪', ' 大阪府', '大阪府大阪市', '東京都', '大阪府・兵庫県']);

test('map view model preserves every candidate and order without queries or fake offsets', function () {
    $items = [];
    foreach (['兵庫県', null, '兵庫県', '大阪府大阪市', '滋賀県'] as $i => $region) {
        $items[] = ['job' => new JobPosting(['id' => 100 + $i, 'title' => '設計'.$i, 'region' => $region]), 'company_name' => '会社'.$i];
    }
    // Set IDs directly because the model guards ID on mass assignment.
    foreach ($items as $i => $item) {
        $item['job']->id = 100 + $i;
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $map = JobMapLocation::viewModel($items, ['public_id' => 'map-public-id'], 2, ['nx', 'autocad']);
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
    expect(array_column($map['jobs'], 'job_id'))->toBe([100, 101, 102, 103, 104])
        ->and(array_column($map['jobs'], 'region'))->toBe(['兵庫県', null, '兵庫県', '大阪府大阪市', '滋賀県'])
        ->and($map['mapped_count'])->toBe(3)->and($map['missing_count'])->toBe(2)
        ->and($map['markers'][0]['count'])->toBe(2)
        ->and($map['jobs'][0]['latitude'])->toBe($map['jobs'][2]['latitude'])
        ->and($map['jobs'][0]['longitude'])->toBe($map['jobs'][2]['longitude']);
    foreach ($map['jobs'] as $job) {
        expect($job['detail_url'])->toBe(route('query.jobs.show', ['userQuery' => 'map-public-id', 'job' => $job['job_id'], 'page' => 2, 'tools' => ['nx', 'autocad']]))
            ->and($job['location_evidence'])->toBe(['field' => 'region', 'value' => $job['region']]);
    }
    foreach ($map['markers'] as $marker) {
        expect($marker['x'])->toBeGreaterThan(0)->toBeLessThan(100)
            ->and($marker['y'])->toBeGreaterThan(0)->toBeLessThan(100);
    }
});

test('authorized map uses the current page only with five reads no writes and escaped accessible markup', function () {
    $query = mapFixture(array_merge(array_fill(0, 21, '兵庫県'), ['大阪府']));
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->get(route('query.jobs', ['userQuery' => $query->public_id, 'page' => 2, 'tools' => ['nx']]));
    $sql = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();
    expect($sql)->toHaveCount(6)->and(array_filter($sql, fn ($sql) => ! preg_match('/^select\b/i', $sql)))->toBe([]);
    $response->assertOk()->assertSee('地図（都道府県の目安）')->assertSee('簡易位置図')
        ->assertSee('都道府県の代表点です。実際の勤務地を示すものではありません。')
        ->assertSee('地図表示対象 2求人 / 位置表示未設定 0求人')->assertSee('地図を表示できませんでした')
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false)
        ->assertDontSee('map-secret-token')->assertDontSee('map-private-input')->assertDontSee('javascript:alert(3)');
    $dom = mapDom($response->getContent());
    expect($dom->query('//button[@data-jobdd-view="list" and @aria-pressed="true"]')->length)->toBe(1)
        ->and($dom->query('//button[@data-jobdd-view="map" and @aria-pressed="false"]')->length)->toBe(1)
        ->and($dom->query('//*[@data-map-view and @hidden]')->length)->toBe(1)
        ->and($dom->query('//button[@data-map-marker and @aria-pressed="false" and @aria-controls="map-results"]')->length)->toBe(6)
        ->and($dom->query('//*[@data-map-job]')->length)->toBe(2)
        ->and($dom->query('//input[@name="jobs[]"]')->length)->toBe(2)
        ->and($dom->query('//*[@data-map-view]//input')->length)->toBe(0)
        ->and($dom->query('//button[@data-map-marker="hyogo" and @data-count="1"]')->length)->toBe(1);
    $ids = [];
    foreach ($dom->query('//*[@data-map-job]') as $card) {
        $ids[] = (int) $card->getAttribute('data-map-job');
        $href = $dom->query('.//a', $card)->item(0)->getAttribute('href');
        expect($href)->toContain('page=2', 'tools%5B0%5D=nx');
    }
    expect($ids)->toBe(array_map(fn ($item) => $item['job']->id, $response->viewData('items')));
    foreach (['ranking', 'score', 'おすすめ', 'best area', 'geolocation', 'iframe'] as $forbidden) {
        expect($dom->query('//*[@data-map-view]')->item(0)->textContent)->not->toContain($forbidden);
    }
    if (getenv('JOBDD_UI_CAPTURE_DIR')) {
        file_put_contents(getenv('JOBDD_UI_CAPTURE_DIR').'/map.html', $response->getContent());
    }
});

test('map partial exposes missing region jobs without dropping candidates and empty page fallback', function () {
    foreach ([[null, '東京都', '大阪府大阪市'], ['兵庫県', null], []] as $regions) {
        $items = [];
        foreach ($regions as $i => $region) {
            $job = new JobPosting(['region' => $region, 'title' => '求人']);
            $job->id = $i + 1;
            $items[] = ['job' => $job, 'company_name' => '会社'];
        }
        $map = JobMapLocation::viewModel($items, ['public_id' => 'public-id'], 1, []);
        $html = view('query.partials.map-view', compact('map'))->render();
        $dom = mapDom($html);
        expect($dom->query('//*[@data-map-job]')->length)->toBe(count($items));
        if ($regions === []) {
            expect($html)->toContain('このページに表示できる求人候補はありません。');
        } elseif ($map['mapped_count'] === 0) {
            expect($html)->toContain('このページの求人の地図上の位置を表示できていません');
        } else {
            expect($html)->toContain('地図表示対象 1求人 / 位置表示未設定 1求人');
        }
    }
});

test('map does not bypass session authorization', function () {
    $query = mapFixture(['兵庫県']);
    $this->get(route('query.jobs', ['userQuery' => $query->public_id]))->assertNotFound()->assertDontSee('data-map-marker');
});
