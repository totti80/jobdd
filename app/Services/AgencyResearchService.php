<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencyFact;
use App\Models\Source;
use Illuminate\Support\Facades\DB;

class AgencyResearchService
{

  private function parseResearchOutput(string $outputText): array
  {
    $result = [
      'status' => null,
      'fact_value' => null,
      'source_url' => null,
      'source_title' => null,
      'publisher' => null,
      'evidence' => null,
    ];

    $patterns = [
      'status' => '/^STATUS:\s*(.+)$/mi',
      'fact_value' => '/^FACT_VALUE:\s*(.*)$/mi',
      'source_url' => '/^SOURCE_URL:\s*(.*)$/mi',
      'source_title' => '/^SOURCE_TITLE:\s*(.*)$/mi',
      'publisher' => '/^PUBLISHER:\s*(.*)$/mi',
      'evidence' => '/^EVIDENCE:\s*(.*)$/mi',
    ];

    foreach ($patterns as $key => $pattern) {
      if (preg_match($pattern, $outputText, $matches)) {
        $result[$key] = trim($matches[1]);
      }
    }

    return $result;
  }

  private function buildResearchPrompt(
    Agency $agency,
    string $factKey
  ): string {
    return match ($factKey) {
      'supported_region' => <<<PROMPT
人材紹介会社「{$agency->name}」について調査してください。
公式サイトURLは {$agency->website_url} です。

JobDDにおける supported_region は、
「会社の所在地・支社所在地」ではなく、
「求職者に紹介できる求人の勤務地、または転職支援の対象地域」
を意味します。

公式サイトまたは同社が運営する求人情報から、
supported_region を第三者が確認できる場合のみ回答してください。

根拠が複数見つかった場合は、次の優先順位で最も直接的・公式性の高い情報を採用してください。

1. 職業安定法に基づく明示、許可情報、取扱業務範囲などの法定・公的性質の強い情報
2. 公式サイト上で明示された転職支援対象地域・求人取扱地域
3. 同社が運営する求人情報から確認できる勤務地情報
4. その他の公式ページ

「○○地域を中心に」など、一部地域への注力を示す表現は、
それだけで取扱地域全体を限定する根拠として使用しないでください。

複数の情報が矛盾または範囲の異なる表現をしている場合は、
より直接的に「取扱地域」「対象地域」を定義している情報を優先してください。

禁止:
- 本社所在地や支社所在地だけから対応地域を推測する
- 根拠のない全国対応判定
- 一般論による補完

十分な根拠が見つからない場合は NOT_FOUND としてください。

次の形式だけで回答してください。

STATUS: FOUND または NOT_FOUND
FACT_VALUE: <対応地域。NOT_FOUNDなら空欄>
SOURCE_URL: <根拠URL。NOT_FOUNDなら空欄>
SOURCE_TITLE: <ページタイトル。NOT_FOUNDなら空欄>
PUBLISHER: <運営主体。NOT_FOUNDなら空欄>
EVIDENCE: <そのページの何がsupported_regionの根拠になるか短く説明>
PROMPT,

      default => throw new \InvalidArgumentException(
        "Research prompt not implemented for: {$factKey}"
      ),
    };
  }

  public function researchMissingFact(
    Agency $agency,
    string $factKey
  ): ?AgencyFact {
    if (!in_array($factKey, ScoreService::REQUIRED_FACT_KEYS, true)) {
      throw new \InvalidArgumentException(
        "Unsupported fact key: {$factKey}"
      );
    }

    $existingPendingFact = $agency->facts()
      ->where('fact_key', $factKey)
      ->where('verification_status', 'pending')
      ->latest('observed_at')
      ->first();

    if ($existingPendingFact) {
      return $existingPendingFact;
    }

    $missingKeys = $this->missingFactKeys($agency);

    if (!in_array($factKey, $missingKeys, true)) {
      return null;
    }

    $prompt = $this->buildResearchPrompt(
      $agency,
      $factKey
    );

    $response = \Illuminate\Support\Facades\Http::withToken(
      config('services.openai.key')
    )->post('https://api.openai.com/v1/responses', [
      'model' => config('services.openai.model'),
      'tools' => [
        ['type' => 'web_search_preview'],
      ],
      'input' => $prompt,
    ]);

    if (!$response->successful()) {
      throw new \RuntimeException(
        'OpenAI research request failed: ' . $response->body()
      );
    }

    $data = $response->json();

    $outputText = null;

    foreach (($data['output'] ?? []) as $item) {
      if (($item['type'] ?? null) !== 'message') {
        continue;
      }

      foreach (($item['content'] ?? []) as $content) {
        if (($content['type'] ?? null) === 'output_text') {
          $outputText = $content['text'];
          break 2;
        }
      }
    }

    $parsed = $this->parseResearchOutput($outputText);

    if (($parsed['status'] ?? null) !== 'FOUND') {
      return null;
    }

    if (
      empty($parsed['fact_value']) ||
      empty($parsed['source_url'])
    ) {
      return null;
    }

    return $this->savePendingFact(
      $agency,
      [
        'source_type' => 'official_site',
        'title' => $parsed['source_title'],
        'publisher' => $parsed['publisher'],
        'url' => $parsed['source_url'],
        'fetched_at' => now(),
      ],
      [
        'fact_type' => $factKey,
        'fact_key' => $factKey,
        'fact_value' => $parsed['fact_value'],
        'observed_at' => now(),
      ]
    );

    // 次のステップで解析・pending保存を追加する
    return null;
  }


  public function missingFactKeys(Agency $agency): array
  {
    $verifiedKeys = $agency->facts()
      ->where('verification_status', 'verified')
      ->whereIn('fact_key', ScoreService::REQUIRED_FACT_KEYS)
      ->pluck('fact_key')
      ->unique()
      ->all();

    return array_values(
      array_diff(
        ScoreService::REQUIRED_FACT_KEYS,
        $verifiedKeys
      )
    );
  }

  public function savePendingFact(
    Agency $agency,
    array $sourceData,
    array $factData
  ): AgencyFact {
    return DB::transaction(function () use ($agency, $sourceData, $factData) {
      $source = Source::create([
        'source_type' => $sourceData['source_type'],
        'title' => $sourceData['title'] ?? null,
        'publisher' => $sourceData['publisher'] ?? null,
        'url' => $sourceData['url'],
        'fetched_at' => $sourceData['fetched_at'] ?? now(),
      ]);

      return AgencyFact::create([
        'agency_id' => $agency->id,
        'source_id' => $source->id,
        'fact_type' => $factData['fact_type'],
        'fact_key' => $factData['fact_key'],
        'fact_value' => $factData['fact_value'],
        'verification_status' => 'pending',
        'observed_at' => $factData['observed_at'] ?? now(),
      ]);
    });
  }
}
