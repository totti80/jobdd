<?php

use App\Services\DiscoveryChangeClassifier;

test('daily classifier ignores observation clock but tracks meaningful changes', function () {
    $classifier = new DiscoveryChangeClassifier;
    $before = ['title' => '機械設計', 'description' => 'AutoCAD', 'last_seen_at' => '2026-09-20'];
    expect($classifier->classify(null, $before))->toBe('new')
        ->and($classifier->classify($before, [...$before, 'last_seen_at' => '2026-09-21']))->toBe('unchanged');
    foreach (DiscoveryChangeClassifier::FIELDS as $field) {
        expect($classifier->classify($before, [...$before, $field => 'changed']))->toBe('updated');
    }
});
