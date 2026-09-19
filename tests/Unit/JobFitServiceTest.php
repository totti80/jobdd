<?php

use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\ContextRoleClassifier;
use App\Services\JobFactDictionary;
use App\Services\JobFitService;
use App\Services\OccupationNormalizer;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

function fitService(): JobFitService
{
    return new JobFitService(new ContextRoleClassifier, new OccupationNormalizer);
}

function fitJob(array $attributes = []): JobPosting
{
    $job = new JobPosting;
    $job->setRawAttributes(array_replace(['id' => 1, 'title' => '機械設計', 'occupation' => '機械設計', 'region' => '兵庫県神戸市',
        'salary_min' => 650, 'salary_max' => 850, 'description' => '【仕事内容】AutoCADを使用して設計します。',
        'source_url' => 'https://example.test/jobs/1', 'last_seen_at' => '2026-09-19 01:02:03'], $attributes));

    return $job;
}

function fitFact(string $key = 'autocad', array $attributes = []): JobFact
{
    $definition = array_column(JobFactDictionary::definitions(), null, 'fact_key')[$key];
    $fact = new JobFact;
    $fact->setRawAttributes(array_replace(['id' => 1, 'job_posting_id' => 1, 'source_id' => null,
        'fact_key' => $key, 'fact_category' => $definition['fact_category'], 'fact_value' => $definition['normalized_value'],
        'normalized_value' => $definition['normalized_value'], 'evidence_text' => $definition['normalized_value'],
        'extraction_method' => 'rule', 'verification_status' => 'verified', 'observed_at' => '2026-09-19 01:02:03'], $attributes));

    return $fact;
}

function fitRequest(string $key = 'autocad', string $action = 'use'): array
{
    return ['desired' => [['fact_key' => $key, 'action' => $action]]];
}

test('compares explicit Tier 1 values without filling missing evidence', function ($query, $job, $status, $reason) {
    $result = fitService()->evaluate(new UserQuery($query), fitJob($job), []);
    expect($result['axes'])->toHaveCount(1)
        ->and($result['axes'][0]['status'])->toBe($status)
        ->and($result['axes'][0]['reason_code'])->toBe($reason);
})->with([
    'region match' => [['region' => '兵庫県'], [], 'match', 'same_prefecture'],
    'region mismatch' => [['region' => '大阪府'], [], 'mismatch', 'different_prefecture'],
    'region missing' => [['region' => '兵庫県'], ['region' => null], 'unknown', 'missing_job_value'],
    'multiple prefectures' => [['region' => '兵庫県'], ['region' => '兵庫県・大阪府'], 'unknown', 'invalid_job_value'],
    'outside prefecture mixed' => [['region' => '兵庫県'], ['region' => '兵庫県/東京都'], 'unknown', 'invalid_job_value'],
    'national' => [['region' => '兵庫県'], ['region' => '兵庫県を含む全国'], 'unknown', 'invalid_job_value'],
    'undecided' => [['region' => '兵庫県'], ['region' => '兵庫県（勤務地未定）'], 'unknown', 'invalid_job_value'],
    'user region unsupported' => [['region' => '関西'], [], 'unknown', 'unsupported_user_value'],
    'occupation match' => [['occupation' => '機械設計'], [], 'match', 'same_occupation'],
    'occupation mismatch' => [['occupation' => '電気設計'], [], 'mismatch', 'different_occupation'],
    'occupation conflict' => [['occupation' => '機械設計'], ['title' => 'ハード（電気）設計'], 'unknown', 'conflicting_job_evidence'],
    'occupation missing' => [['occupation' => '機械設計'], ['occupation' => null], 'unknown', 'missing_job_value'],
    'user occupation unsupported' => [['occupation' => '営業'], [], 'unknown', 'unsupported_user_value'],
    'normalizer abstention' => [['occupation' => '機械設計'], ['title' => '', 'description' => ''], 'match', 'same_occupation'],
    'salary match' => [['salary_min' => 600], [], 'match', 'salary_floor_meets'],
    'salary mismatch' => [['salary_min' => 900], [], 'mismatch', 'salary_ceiling_below'],
    'salary overlap' => [['salary_min' => 700], [], 'unknown', 'salary_overlap'],
    'salary missing' => [['salary_min' => 600], ['salary_min' => null, 'salary_max' => null], 'unknown', 'missing_job_value'],
    'salary zero' => [['salary_min' => 600], ['salary_min' => 0], 'unknown', 'invalid_job_value'],
    'salary inverted' => [['salary_min' => 600], ['salary_min' => 900, 'salary_max' => 800], 'unknown', 'invalid_job_value'],
    'salary user upper' => [['salary_min' => 600, 'salary_max' => 900], [], 'unknown', 'unsupported_salary_request'],
    'salary min only match' => [['salary_min' => 600], ['salary_max' => null], 'match', 'salary_floor_meets'],
    'salary min only unknown' => [['salary_min' => 700], ['salary_max' => null], 'unknown', 'missing_job_value'],
    'salary max only mismatch' => [['salary_min' => 900], ['salary_min' => null], 'mismatch', 'salary_ceiling_below'],
    'salary max only unknown' => [['salary_min' => 700], ['salary_min' => null], 'unknown', 'missing_job_value'],
    'source missing' => [['region' => '兵庫県'], ['source_url' => null], 'unknown', 'evidence_missing'],
    'non http source' => [['region' => '大阪府'], ['source_url' => 'ftp://example.test/job'], 'unknown', 'evidence_missing'],
    'last seen optional' => [['region' => '兵庫県'], ['last_seen_at' => null], 'match', 'same_prefecture'],
]);

test('only a supported explicit tool use can match', function ($text, $key, $action, $status, $reason) {
    $result = fitService()->evaluate(new UserQuery, fitJob(['description' => $text]), [fitFact($key)], fitRequest($key, $action));
    expect($result['axes'][0]['status'])->toBe($status)
        ->and($result['axes'][0]['reason_code'])->toBe($reason)
        ->and($result['axes'][0]['evidence'][0]['context']['matched_contexts'])->not->toBeEmpty();
})->with([
    ['【仕事内容】AutoCADを使用して設計します。', 'autocad', 'use', 'match', 'confirmed_tool_use'],
    ['【仕事内容】NXを用いて設計します。', 'nx', 'use', 'match', 'confirmed_tool_use'],
    ['【使用ツール】SolidWorks', 'solidworks', 'use', 'match', 'confirmed_tool_use'],
    ['【ツール/開発環境】Excel、AutoCAD、Creo', 'creo', 'use', 'match', 'confirmed_tool_use'],
    ['【必須】AutoCAD経験。', 'autocad', 'use', 'unknown', 'context_not_responsibility'],
    ['【歓迎】AutoCAD経験。', 'autocad', 'use', 'unknown', 'context_not_responsibility'],
    ['【会社概要】当社使用CAD：AutoCAD。', 'autocad', 'use', 'unknown', 'context_not_responsibility'],
    ['【製品】AutoCAD対応製品。', 'autocad', 'use', 'unknown', 'context_not_responsibility'],
    ['《開発例》AutoCADを使用して設計します。', 'autocad', 'use', 'unknown', 'context_not_responsibility'],
    ['【仕事内容】AutoCAD担当者と連携します。', 'autocad', 'use', 'unknown', 'context_not_responsibility'],
    ['【仕事内容】他部署がAutoCADを使用します。', 'autocad', 'use', 'unknown', 'context_not_responsibility'],
    ['AutoCAD。', 'autocad', 'use', 'unknown', 'context_unknown'],
    ['【仕事内容】AutoCADを使用します。【その他】AutoCAD。', 'autocad', 'use', 'unknown', 'context_unknown'],
    ['【仕事内容】AUTOCAD ELECTRICALを用いて設計します。', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【使用ツール】AutoCAD Electrical', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【使用ツール】AutoCAD LT', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【仕事内容】AutoCADの購入を担当します。', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【使用ツール】AutoCAD又はCATIA', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【使用ツール】AutoCAD OR CATIA', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【使用ツール】移行元AutoCAD', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【使用ツール】AutoCADは未使用', 'autocad', 'use', 'unknown', 'action_unconfirmed'],
    ['【仕事内容】制御盤の組立を担当します。', 'control_panel', 'design', 'unknown', 'unsupported_tier2_requirement'],
    ['【仕事内容】CAEを使用します。', 'cae', 'analyze', 'unknown', 'unsupported_tier2_requirement'],
    ['【仕事内容】PLCを使用します。', 'plc', 'use', 'unknown', 'unsupported_tier2_requirement'],
    ['【仕事内容】AutoCADを使用します。【必須】AutoCAD経験。', 'autocad', 'use', 'match', 'confirmed_tool_use'],
]);

test('accepts each of the seven tool keys without adding aliases', function ($key, $alias) {
    $result = fitService()->evaluate(new UserQuery, fitJob(['description' => '【使用ツール】'.$alias]), [fitFact($key)], fitRequest($key));
    expect($result['axes'][0]['status'])->toBe('match');
})->with(['autocad' => ['autocad', 'AutoCAD'], 'inventor' => ['inventor', 'Inventor'], 'solidworks' => ['solidworks', 'SolidWorks'], 'catia' => ['catia', 'CATIA'], 'creo' => ['creo', 'Creo'], 'nx' => ['nx', 'NX'], 'electrical_cad' => ['electrical_cad', '電気CAD']]);

test('missing presence is not a negative claim', function () {
    $axis = fitService()->evaluate(new UserQuery, fitJob(), [], fitRequest())['axes'][0];
    expect($axis['status'])->toBe('unknown')->and($axis['reason_code'])->toBe('presence_not_found')
        ->and($axis['notes'])->toContain('absence_does_not_prove_non_use')->and($axis['evidence'])->toBe([]);
});

test('rejects unverified and incomplete fact evidence safely', function ($attrs, $reason) {
    $axis = fitService()->evaluate(new UserQuery, fitJob(), [fitFact('autocad', $attrs)], fitRequest())['axes'][0];
    expect($axis['status'])->toBe('unknown')->and($axis['reason_code'])->toBe($reason);
})->with([
    [['verification_status' => 'unverified'], 'fact_unverified'],
    [['extraction_method' => 'ai'], 'fact_unverified'],
    [['normalized_value' => ''], 'evidence_missing'],
    [['evidence_text' => ''], 'evidence_missing'],
    [['observed_at' => null], 'evidence_missing'],
    [['observed_at' => 'not-a-date'], 'evidence_missing'],
]);

test('missing raw never matches', function () {
    $axis = fitService()->evaluate(new UserQuery, fitJob(['description' => null]), [fitFact()], fitRequest())['axes'][0];
    expect($axis['status'])->toBe('unknown')->and($axis['reason_code'])->toBe('evidence_missing');
});

test('invalid call contracts throw safe exceptions', function ($requirements) {
    expect(fn () => fitService()->evaluate(new UserQuery(['region' => '兵庫県']), fitJob(), [], $requirements))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [null], ['secret-user-text'], [true], [['desired' => null]], [['desired' => 'autocad']],
    [['desired' => ['autocad']]], [['desired' => [['fact_key' => 'fake', 'action' => 'use']]]],
    [['desired' => [['fact_key' => 'autocad', 'action' => 'buy']]]],
    [['desired' => [['fact_key' => 'autocad']]]], [['hard_axes' => ['salary']]],
    [['hard_axes' => 'region']], [['hard_axes' => null]], [['score' => 100]],
]);

test('invalid or foreign facts are caller errors', function ($change) {
    $fact = fitFact('autocad', $change);
    expect(fn () => fitService()->evaluate(new UserQuery, fitJob(), [$fact], fitRequest()))->toThrow(InvalidArgumentException::class);
})->with([[['job_posting_id' => 2]], [['id' => null]], [['fact_key' => 'fake']], [['fact_category' => 'fake']]]);

test('rejects duplicate fact identity and non-model facts', function () {
    foreach ([[fitFact(), fitFact()], [new stdClass], [null]] as $facts) {
        expect(fn () => fitService()->evaluate(new UserQuery, fitJob(), $facts, fitRequest()))->toThrow(InvalidArgumentException::class);
    }
});

test('duplicate keys preserve every evidence in stable order without inflating counts', function () {
    $facts = [fitFact('autocad', ['id' => 2]), fitFact('autocad', ['id' => 1])];
    $service = fitService();
    $first = $service->evaluate(new UserQuery, fitJob(), $facts, fitRequest());
    expect($first)->toBe($service->evaluate(new UserQuery, fitJob(), array_reverse($facts), fitRequest()))
        ->and($first['summary']['confirmed_matches'])->toBe(1)
        ->and(array_column($first['axes'][0]['evidence'], 'job_fact_id'))->toBe([1, 2]);
    $facts[0]->evidence_text = null;
    $axis = $service->evaluate(new UserQuery, fitJob(), $facts, fitRequest())['axes'][0];
    expect($axis['reason_code'])->toBe('conflicting_contexts')->and($axis['status'])->toBe('unknown');
});

test('multiple Fact role conflicts cannot become a positive match', function ($otherRole, $reason) {
    $classifier = $this->createMock(ContextRoleClassifier::class);
    $real = (new ContextRoleClassifier)->classify(fitJob(), fitFact());
    $classifier->expects($this->exactly(2))->method('classify')->willReturnCallback(function ($job, $fact) use ($real, $otherRole) {
        return $fact->id === 1 ? $real : [...$real, 'role' => $otherRole];
    });
    $service = new JobFitService($classifier, new OccupationNormalizer);
    $axis = $service->evaluate(new UserQuery, fitJob(), [fitFact(), fitFact('autocad', ['id' => 2])], fitRequest())['axes'][0];
    expect($axis['reason_code'])->toBe($reason)->and($axis['evidence'])->toHaveCount(2);
})->with([
    ['required_experience', 'confirmed_tool_use'], ['preferred_experience', 'confirmed_tool_use'],
    ['project_example', 'conflicting_contexts'], ['company_context', 'conflicting_contexts'],
    ['product_context', 'conflicting_contexts'], ['collaboration', 'conflicting_contexts'],
    ['other_department', 'conflicting_contexts'], ['unknown', 'conflicting_contexts'],
]);

test('priorities only change display order while hard axes only mark confirmed differences', function () {
    $query = new UserQuery(['occupation' => '機械設計', 'region' => '大阪府', 'salary_min' => 700]);
    $req = [...fitRequest(), 'hard_axes' => ['region']];
    $before = fitService()->evaluate($query, fitJob(), [], $req);
    $query->priorities = ['tool_use:autocad', 'salary', 'region', 'occupation'];
    $after = fitService()->evaluate($query, fitJob(), [], $req);
    expect($after['summary'])->toBe($before['summary'])
        ->and($after['summary'])->toBe(['evaluated_axes' => 4, 'confirmed_matches' => 1, 'confirmed_mismatches' => 1, 'unknowns' => 2, 'hard_mismatch_keys' => ['region']])
        ->and(array_column($after['axes'], 'key'))->toBe(['tool_use:autocad', 'salary', 'region', 'occupation']);
});

test('unconfirmed skills and unknown priorities never invent requirements', function () {
    $query = new UserQuery(['detailed_skills' => ['AutoCAD経験'], 'priorities' => ['salary' => 100]]);
    $result = fitService()->evaluate($query, fitJob(), []);
    expect($result['axes'])->toBe([])->and($result['input_notes'])->toBe(['user_intent_unconfirmed', 'unsupported_priorities_format'])
        ->and($result)->not->toHaveKeys(['score', 'overall_status', 'rank', 'ranking']);
});

test('empty and unknown results have counts but no implicit score or verdict', function () {
    $r = fitService()->evaluate(new UserQuery, fitJob(), [], fitRequest());
    expect($r['summary'])->toBe(['evaluated_axes' => 1, 'confirmed_matches' => 0, 'confirmed_mismatches' => 0, 'unknowns' => 1, 'hard_mismatch_keys' => []])
        ->and($r)->not->toHaveKeys(['score', 'overall_status', 'rank', 'ranking']);
});

test('returns source dates and full context while leaving models and database untouched', function () {
    $query = new UserQuery(['occupation' => '機械設計']);
    $job = fitJob();
    $fact = fitFact();
    $before = [$query->getAttributes(), $job->getAttributes(), $fact->getAttributes()];
    $old = Model::getConnectionResolver();
    $resolver = $this->createMock(ConnectionResolverInterface::class);
    $resolver->expects($this->never())->method('connection');
    Model::setConnectionResolver($resolver);
    try {
        $result = fitService()->evaluate($query, $job, [$fact], fitRequest());
        expect($result)->toBe(fitService()->evaluate($query, $job, [$fact], fitRequest()));
    } finally {
        $old ? Model::setConnectionResolver($old) : Model::unsetConnectionResolver();
    }
    expect([$query->getAttributes(), $job->getAttributes(), $fact->getAttributes()])->toBe($before)
        ->and($result['source']['last_seen_at'])->toBe('2026-09-19T01:02:03+00:00')
        ->and($result['axes'][1]['evidence'][0]['context'])->toHaveKeys(['role', 'reason', 'matched_contexts', 'notes'])
        ->and($result['axes'][1]['evidence'][0]['context'])->not->toHaveKey('normalized_text')
        ->and($result['axes'][1]['evidence'][0]['source_id'])->toBeNull();
});

test('classifier version label is pinned to the evaluated source', function () {
    expect(JobFitService::CLASSIFIER_VERSION)->toBe('batch43:'.hash_file('sha256', __DIR__.'/../../app/Services/ContextRoleClassifier.php'));
});

test('reproduces the eight real snapshot examples without a database', function ($case) {
    $job = new JobPosting;
    $job->setRawAttributes($case['job']);
    $facts = array_map(function ($attributes) {
        $fact = new JobFact;
        $fact->setRawAttributes($attributes);

        return $fact;
    }, $case['facts']);
    $result = fitService()->evaluate(new UserQuery($case['query']), $job, $facts, $case['requirements']);
    expect(array_map(fn ($a) => array_intersect_key($a, array_flip(['key', 'status', 'reason_code'])), $result['axes']))
        ->toBe($case['expected_axes'])->and($result['summary'])->toBe($case['expected_summary']);
})->with(function () {
    $data = json_decode(file_get_contents(__DIR__.'/../Fixtures/job_fit/manual_examples.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($data['cases'] as $case) {
        yield 'Job '.$case['job']['id'] => [$case];
    }
});

test('relative or impossible evidence dates cannot become confirmed use', function ($date) {
    $axis = fitService()->evaluate(new UserQuery, fitJob(), [fitFact('autocad', ['observed_at' => $date])], fitRequest())['axes'][0];
    expect($axis['status'])->toBe('unknown')->and($axis['reason_code'])->toBe('evidence_missing');
})->with(['tomorrow', '2026-02-31 00:00:00', 'now']);

test('negative assertions are never upgraded even with duty verbs nearby', function ($text) {
    $axis = fitService()->evaluate(new UserQuery, fitJob(['description' => $text]), [fitFact()], fitRequest())['axes'][0];
    expect($axis['status'])->toBe('unknown');
})->with([
    '【仕事内容】AutoCADを使用しません。',
    '【仕事内容】AutoCADを使用できない環境で設計を担当します。',
    '【仕事内容】AutoCADを使用せず設計を担当します。',
]);

test('malformed matched offsets cannot be accepted as tool evidence', function () {
    $result = (new ContextRoleClassifier)->classify(fitJob(), fitFact());
    $result['matched_contexts'][0]['offset'] = 999;
    $classifier = $this->createMock(ContextRoleClassifier::class);
    $classifier->method('classify')->willReturn($result);
    $service = new JobFitService($classifier, new OccupationNormalizer);
    expect($service->evaluate(new UserQuery, fitJob(), [fitFact()], fitRequest())['axes'][0]['status'])->toBe('unknown');
});

test('reuses each classification across desired actions and deduplicates requests', function () {
    $classifier = $this->createMock(ContextRoleClassifier::class);
    $classifier->expects($this->once())->method('classify')->willReturn((new ContextRoleClassifier)->classify(fitJob(), fitFact()));
    $service = new JobFitService($classifier, new OccupationNormalizer);
    $result = $service->evaluate(new UserQuery, fitJob(), [fitFact()], ['desired' => [
        ['fact_key' => 'autocad', 'action' => 'use'], ['fact_key' => 'autocad', 'action' => 'use'],
        ['fact_key' => 'autocad', 'action' => 'design'],
    ]]);
    expect(array_column($result['axes'], 'status'))->toBe(['match', 'unknown'])
        ->and($result['summary']['evaluated_axes'])->toBe(2);
});
