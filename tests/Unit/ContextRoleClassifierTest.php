<?php

use App\Models\JobFact;
use App\Models\JobPosting;
use App\Services\ContextRoleClassifier;
use App\Services\JobFactExtractor;

function classifyContextText(string $text, string $key): array
{
    $job = new JobPosting(['description' => $text]);
    $facts = (new JobFactExtractor)->extract($job);
    $attributes = array_values(array_filter($facts, fn ($f) => $f['fact_key'] === $key))[0];

    return (new ContextRoleClassifier)->classify($job, new JobFact($attributes));
}

test('derives roles from sections and the subject of the assertion', function ($text, $key, $role) {
    $result = classifyContextText($text, $key);

    expect($result['role'])->toBe($role)
        ->and($result['reason'])->not->toBe('')
        ->and($result['matched_contexts'])->not->toBeEmpty();
})->with([
    ['【仕事内容】PLCを使用して設計を行います。', 'plc', 'responsibility'],
    ['【必須】CATIAの利用経験3年以上。', 'catia', 'required_experience'],
    ['【必須】機械の知識。【歓迎】CAEの利用経験。', 'cae', 'preferred_experience'],
    ['CATIA利用経験歓迎。', 'catia', 'preferred_experience'],
    ['【仕事内容】詳細設計部門と連携し、工程管理を担当します。', 'detail_design', 'collaboration'],
    ['【仕事内容】他部署が回路設計を担当します。', 'circuit_design', 'other_department'],
    ['【仕事内容】回路設計は他部署が担当します。', 'circuit_design', 'other_department'],
    ['【仕事内容】当社使用CAD：SolidWorks。', 'solidworks', 'company_context'],
    ['【企業概要】製品はFAシステムです。', 'fa', 'product_context'],
    ['【案件例】設備設計。', 'facility_design', 'project_example'],
    ['回路設計。', 'circuit_design', 'unknown'],
    ['【仕事内容】関係者と連携しながら構想設計を担当します。', 'concept_design', 'responsibility'],
    ['【仕事内容】回路設計は担当しません。', 'circuit_design', 'unknown'],
    ['【仕事内容】回路設計...ソフト開発もあります。', 'circuit_design', 'unknown'],
    ['【歓迎】PLC経験。【休日休暇】回路設計。', 'circuit_design', 'unknown'],
    ['<h2>仕事内容</h2><p>回路<b>設計</b>を担当します。</p><h2>歓迎</h2><p>CAE経験。</p>', 'circuit_design', 'responsibility'],
]);

test('keeps every alias occurrence when a duty also requires experience', function () {
    $result = classifyContextText('【仕事内容】PLCを使用して設計します。【必須】シーケンサーの使用経験。', 'plc');

    expect($result['role'])->toBe('responsibility')
        ->and(array_column($result['matched_contexts'], 'role'))->toBe(['responsibility', 'required_experience'])
        ->and($result['notes'])->not->toBeEmpty();
    foreach ($result['matched_contexts'] as $context) {
        expect(mb_substr($result['normalized_text'], $context['offset'], $context['length']))->toBe($context['match']);
    }
});

test('abstains when a duty and an unresolved or unrelated occurrence coexist', function () {
    $result = classifyContextText('【仕事内容】PLCを使用します。【その他】PLC。', 'plc');

    expect($result['role'])->toBe('unknown')
        ->and($result['matched_contexts'])->toHaveCount(2)
        ->and($result['notes'])->not->toBeEmpty();
});

test('keeps required and preferred contexts without claiming responsibility', function () {
    $result = classifyContextText('【必須】機構設計経験。【歓迎】機構設計経験5年以上。', 'mechanism_design');

    expect($result['role'])->toBe('required_experience')
        ->and($result['matched_contexts'])->toHaveCount(2);
});

test('missing raw or evidence never becomes a responsibility', function () {
    $classifier = new ContextRoleClassifier;
    $fact = new JobFact(['fact_category' => 'tool_system', 'fact_key' => 'plc', 'evidence_text' => 'PLCを使用します。']);

    expect($classifier->classify(new JobPosting, $fact)['role'])->toBe('unknown');
    $fact->evidence_text = null;
    expect($classifier->classify(new JobPosting(['description' => 'PLCを使用します。']), $fact)['role'])->toBe('unknown');
});

test('classification is reproducible and does not mutate input models', function () {
    $job = new JobPosting(['description' => '【仕事内容】NXを使用して設計します。']);
    $fact = new JobFact((new JobFactExtractor)->extract($job)[0]);
    $before = [$job->getAttributes(), $fact->getAttributes()];
    $classifier = new ContextRoleClassifier;

    expect($classifier->classify($job, $fact))->toBe($classifier->classify($job, $fact))
        ->and([$job->getAttributes(), $fact->getAttributes()])->toBe($before);
});

test('does not accept a fact attached to another job or absent from raw', function () {
    $job = new JobPosting(['description' => 'PLCを使用します。']);
    $job->id = 5;
    $fact = new JobFact(['job_posting_id' => 6, 'fact_category' => 'tool_system', 'fact_key' => 'plc', 'evidence_text' => 'PLC']);
    $classifier = new ContextRoleClassifier;
    expect($classifier->classify($job, $fact)['role'])->toBe('unknown');
    $fact->job_posting_id = 5;
    $job->description = 'PLConstruction';
    expect($classifier->classify($job, $fact)['role'])->toBe('unknown');
});

test('ends duty inheritance at decorated example headings', function ($heading) {
    $result = classifyContextText('【仕事内容】設計業務を担当します。'.$heading.'FAシステムの設計を行います。', 'fa');

    expect($result['role'])->toBe('project_example')
        ->and($result['matched_contexts'][0]['section_role'])->toBe('project_example');
})->with(['【開発例】', '《開発例》', '＜開発例＞', '■開発例', '◆開発例', '●開発例', '▼開発例', '[開発例]', '「開発例」', '【案件例】', '■プロジェクト例', '<h3>開発例</h3>', '<b>《開発例》</b>', '<開発例>']);

test('preserves safety across headings and local experience qualifiers', function ($text, $key, $role) {
    expect(classifyContextText($text, $key)['role'])->toBe($role);
})->with([
    ["【必須】機械の設計経験\n歓迎：CAE経験", 'cae', 'preferred_experience'],
    ['【必須】設備設計経験は歓迎します。', 'facility_design', 'preferred_experience'],
    ['<応募条件>制御盤の設計経験。', 'control_panel', 'required_experience'],
    ['【必須】機械の知識。◆歓迎：CAE経験。', 'cae', 'preferred_experience'],
    ["仕事内容\n設計を担当します。\n使用ツール：NX", 'nx', 'responsibility'],
    ['【仕事内容】設計を担当します。＜会社概要＞CAE環境を使用します。', 'cae', 'company_context'],
    ['【仕事内容】設計を担当します。●製品群：FAシステム。', 'fa', 'product_context'],
    ['【案件例】PLCを使用します。【必須】シーケンサー経験。', 'plc', 'unknown'],
    ['【会社概要】CAE環境があります。【歓迎】CAE経験。', 'cae', 'unknown'],
    ['【仕事内容】設計を担当します。機械...CAEを使用します。', 'cae', 'unknown'],
    ['【仕事内容】設計を担当します。\n《未定の配属》CAE。', 'cae', 'unknown'],
    ['【仕事内容】設計を担当します。家電・FA機器の設計開発業務《開発例》FA装置。', 'fa', 'project_example'],
]);

test('keeps mixed-role aggregation conservative and independent of occurrence order', function () {
    foreach ([
        ['【仕事内容】PLCを使用します。', '【必須】シーケンサ経験。', 'responsibility'],
        ['【案件例】PLCを使用します。', '【必須】シーケンサ経験。', 'unknown'],
        ['【会社概要】PLCを使用します。', '【歓迎】シーケンサ経験。', 'unknown'],
        ['【仕事内容】PLCを使用します。', '【案件例】シーケンサを使用します。', 'unknown'],
    ] as [$first, $second, $role]) {
        foreach ([$first.$second, $second.$first] as $text) {
            $result = classifyContextText($text, 'plc');
            expect($result['role'])->toBe($role)
                ->and($result['matched_contexts'])->toHaveCount(2)
                ->and($result['notes'])->not->toBeEmpty();
        }
    }
});

test('preserves specified known cases and fixes the real development example failure', function () {
    foreach (['batch41.json', 'independent.json'] as $file) {
        $data = json_decode(file_get_contents(__DIR__.'/../Fixtures/context_roles/'.$file), true, 512, JSON_THROW_ON_ERROR);
        $ids = $file === 'batch41.json' ? [43, 1103, 24, 21, 8, 20, 39, 9, 35, 36, 37] : [324];
        foreach ($data['cases'] as $case) {
            if (! in_array($case['fact']['job_posting_id'], $ids, true)) {
                continue;
            }
            $job = new JobPosting;
            $job->setRawAttributes($data['jobs'][$case['fact']['job_posting_id']]);
            $fact = new JobFact;
            $fact->setRawAttributes($case['fact']);
            expect((new ContextRoleClassifier)->classify($job, $fact)['role'])
                ->toBe($case['expected_role'], 'Job '.$job->id.' / '.$fact->fact_key);
        }
    }
});
