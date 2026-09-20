<?php

namespace App\Support;

class JobMapLocation
{
    public const SOURCE = [
        'name' => '国土地理院「統計情報を円の大きさで表現する方法」公開サンプル',
        'url' => 'https://www.gsi.go.jp/CHIRIKYOUIKU/toukei_map_circle.html',
        'data_url' => 'https://www.gsi.go.jp/common/000224745.zip',
        'terms_url' => 'https://www.gsi.go.jp/kikakuchousei/kikakuchousei40182.html',
        'license' => 'PDL-1.0',
        'checked_at' => '2026-09-20',
        'definition' => '公開サンプルの府県庁代表点（2020年保存データ）。現在の庁舎位置や勤務地を保証しない。',
    ];

    // Source XLS: sheet 地理院マップシート, columns D/E/S, rows 26–31.
    // Coordinates are copied unchanged; label positions only are schematic callouts.
    public const POINTS = [
        '兵庫県' => ['key' => 'hyogo', 'latitude' => 34.691257, 'longitude' => 135.183075, 'label_x' => 18, 'label_y' => 48],
        '大阪府' => ['key' => 'osaka', 'latitude' => 34.68639, 'longitude' => 135.520004, 'label_x' => 48, 'label_y' => 62],
        '京都府' => ['key' => 'kyoto', 'latitude' => 35.02124, 'longitude' => 135.755615, 'label_x' => 40, 'label_y' => 12],
        '滋賀県' => ['key' => 'shiga', 'latitude' => 35.003792, 'longitude' => 135.867828, 'label_x' => 80, 'label_y' => 27],
        '奈良県' => ['key' => 'nara', 'latitude' => 34.68528, 'longitude' => 135.832779, 'label_x' => 82, 'label_y' => 62],
        '和歌山県' => ['key' => 'wakayama', 'latitude' => 34.226112, 'longitude' => 135.167496, 'label_x' => 25, 'label_y' => 88],
    ];

    public static function point(?string $region): array
    {
        $point = self::POINTS[$region ?? ''] ?? null;

        return [
            'latitude' => $point['latitude'] ?? null,
            'longitude' => $point['longitude'] ?? null,
            'point_key' => $point['key'] ?? null,
            'precision' => $point ? 'prefecture_representative' : 'unknown',
            'coordinate_source' => $point ? self::SOURCE : null,
            'is_workplace_coordinate' => false,
        ];
    }

    public static function viewModel(array $items, array $query, int $page, array $tools): array
    {
        $jobs = [];
        $markers = [];
        foreach (self::POINTS as $region => $point) {
            $markers[$point['key']] = $point + ['region' => $region, 'count' => 0,
                // Linear longitude/latitude diagram; north up. Not a distance map.
                'x' => ($point['longitude'] - 135.0) / 1.05 * 100,
                'y' => (35.20 - $point['latitude']) / 1.15 * 100];
        }
        $missing = 0;
        foreach ($items as $item) {
            $job = $item['job'];
            $point = self::point($job->region);
            $jobs[] = $point + [
                'job_id' => $job->id, 'company_name' => $item['company_name'], 'job_title' => $job->title,
                'region' => $job->region,
                'location_label' => ($job->region ?? '勤務地未確認').($point['point_key'] ? '（都道府県代表点）' : '（地図上の位置表示未設定）'),
                'location_evidence' => ['field' => 'region', 'value' => $job->region],
                'detail_url' => route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $job->id, 'page' => $page, 'tools' => $tools]),
            ];
            if ($point['point_key']) {
                $markers[$point['point_key']]['count']++;
            } else {
                $missing++;
            }
        }

        return ['jobs' => $jobs, 'markers' => array_values($markers), 'mapped_count' => count($jobs) - $missing, 'missing_count' => $missing];
    }
}
