<?php

use App\Models\UserQuery;
use App\Services\JobDiscoveryService;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

test('discovery rejects unsupported inputs before any database access', function ($attributes, $limit, $offset) {
    $query = new UserQuery(array_replace(['occupation' => '機械設計', 'region' => '兵庫県', 'raw_text' => 'PRIVATE'], $attributes));
    $before = $query->getAttributes();
    $old = Model::getConnectionResolver();
    $resolver = $this->createMock(ConnectionResolverInterface::class);
    $resolver->expects($this->never())->method('connection');
    Model::setConnectionResolver($resolver);
    try {
        try {
            (new JobDiscoveryService)->discover($query, $limit, $offset);
            $this->fail('Expected contract exception.');
        } catch (InvalidArgumentException $e) {
            expect($e->getMessage())->not->toContain('PRIVATE')->and($query->getAttributes())->toBe($before);
        }
    } finally {
        $old ? Model::setConnectionResolver($old) : Model::unsetConnectionResolver();
    }
})->with([
    'null occupation' => [['occupation' => null], 20, 0],
    'empty occupation' => [['occupation' => ''], 20, 0],
    'other occupation' => [['occupation' => '施工管理'], 20, 0],
    'occupation alias' => [['occupation' => '機械'], 20, 0],
    'occupation space' => [['occupation' => '機械設計 '], 20, 0],
    'empty region' => [['region' => ''], 20, 0],
    'city region' => [['region' => '兵庫県西宮市'], 20, 0],
    'multi region' => [['region' => '東京都 / 兵庫県'], 20, 0],
    'outside region' => [['region' => '東京都'], 20, 0],
    'region alias' => [['region' => '兵庫'], 20, 0],
    'region space' => [['region' => ' 兵庫県'], 20, 0],
    'region unknown' => [['region' => '未定'], 20, 0],
    'limit zero' => [[], 0, 0],
    'limit negative' => [[], -1, 0],
    'limit above max' => [[], 51, 0],
    'offset negative' => [[], 20, -1],
]);
