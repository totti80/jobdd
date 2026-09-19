<?php

// Offline evaluation: no Laravel bootstrap, database connection, or model saves.
require __DIR__.'/../../vendor/autoload.php';

use App\Models\JobFact;
use App\Models\JobPosting;
use App\Services\ContextRoleClassifier;

$path = $argv[1] ?? __DIR__.'/../Fixtures/context_roles/batch41.json';
$data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$classifier = new ContextRoleClassifier;
$matrix = array_fill_keys(ContextRoleClassifier::ROLES, array_fill_keys(ContextRoleClassifier::ROLES, 0));
$results = [];
$correct = $unsafe = $tp = $predictedResponsibility = $expectedResponsibility = $unknown = 0;
foreach ($data['cases'] as $case) {
    $attributes = $data['jobs'][(string) $case['fact']['job_posting_id']];
    $job = new JobPosting;
    $job->setRawAttributes($attributes);
    $fact = new JobFact;
    $fact->setRawAttributes($case['fact']);
    $result = $classifier->classify($job, $fact);
    $expected = $case['expected_role'];
    $predicted = $result['role'];
    $matrix[$expected][$predicted]++;
    $correct += (int) ($expected === $predicted);
    $unsafe += (int) ($expected !== 'responsibility' && $predicted === 'responsibility');
    $tp += (int) ($expected === 'responsibility' && $predicted === 'responsibility');
    $predictedResponsibility += (int) ($predicted === 'responsibility');
    $expectedResponsibility += (int) ($expected === 'responsibility');
    $unknown += (int) ($predicted === 'unknown');
    $results[] = ['job_id' => $job->id, 'fact_key' => $fact->fact_key, 'expected' => $expected, 'predicted' => $predicted, 'result' => $result];
}
$count = count($results);
echo json_encode([
    'set' => $data['name'], 'classifier_sha256' => hash_file('sha256', __DIR__.'/../../app/Services/ContextRoleClassifier.php'),
    'fixture_sha256' => hash_file('sha256', $path),
    'metrics' => [
        'count' => $count, 'correct' => $correct, 'incorrect' => $count - $correct,
        'accuracy' => $count ? $correct / $count : null,
        'responsibility_true_positive' => $tp, 'responsibility_predicted' => $predictedResponsibility,
        'responsibility_expected' => $expectedResponsibility,
        'responsibility_precision' => $predictedResponsibility ? $tp / $predictedResponsibility : null,
        'responsibility_recall' => $expectedResponsibility ? $tp / $expectedResponsibility : null,
        'unsafe_responsibility' => $unsafe, 'unknown_count' => $unknown,
        'unknown_rate' => $count ? $unknown / $count : null,
    ], 'confusion_matrix' => $matrix, 'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
