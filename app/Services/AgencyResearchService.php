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

  private function fetchPublicJobCount(Agency $agency): ?AgencyFact
  {
    if ($agency->id !== 7) {
      return null;
    }

    $url = 'https://www.m-next.jp/job/s/';

    $html = file_get_contents($url);

    if ($html === false) {
      return null;
    }

    if (
      !preg_match(
        '/<section id="results_number">.*?<strong>([0-9,]+)<\/strong>件/s',
        $html,
        $matches
      )
    ) {
      return null;
    }

    $jobCount = str_replace(',', '', $matches[1]);

    return $this->savePendingFact(
      $agency,
      [
        'source_type' => 'official_site',
        'title' => '検索結果－エンジニアの求人検索',
        'publisher' => $agency->name,
        'url' => $url,
        'fetched_at' => now(),
      ],
      [
        'fact_type' => 'public_job_count',
        'fact_key' => 'public_job_count',
        'fact_value' => $jobCount,
        'observed_at' => now(),
      ]
    );
  }

  private function buildResearchPrompt(
    Agency $agency,
    string $factKey
  ): string {
    return match ($factKey) {

      'public_job_count' => <<<PROMPT
人材紹介会社「{$agency->name}」について調査してください。
公式サイトURLは {$agency->website_url} です。

JobDDにおける public_job_count は、
「この人材紹介会社が公式サイト上で現在公開している求人の総件数」
を意味します。

公式サイトまたは同社が運営する求人検索ページで、
公開求人の総件数を第三者が数値として直接確認できる場合のみ回答してください。

FACT_VALUEには整数だけを記載してください。

例:
120
487
1532

根拠が複数見つかった場合は、次の優先順位で採用してください。

1. 公式求人検索ページ等に明示された「全○件」「求人○件」などの総件数
2. 公式サイト上で明示された現在の公開求人総数
3. その他、総件数を直接確認できる公式情報

禁止:
- 求人一覧1ページに表示された件数を総件数として扱う
- ページ数 × 1ページ件数から推測する
- 数件の求人を数えて総数を推定する
- 「求人多数」「豊富な求人」等の表現から件数を推測する
- 過去の求人件数を現在件数として扱う
- 検索エンジンのヒット件数を使用する
- 根拠に存在しない件数を生成する

現在の公開求人総数を直接確認できない場合は、
必ず NOT_FOUND としてください。

FACT_VALUEには public_job_count に該当する整数だけを記載し、
「件」等の単位や、地域・職種・年収など他の情報は含めないでください。

次の形式だけで回答してください。

STATUS: FOUND または NOT_FOUND
FACT_VALUE: <公開求人総数。NOT_FOUNDなら空欄>
SOURCE_URL: <根拠URL。NOT_FOUNDなら空欄>
SOURCE_TITLE: <ページタイトル。NOT_FOUNDなら空欄>
PUBLISHER: <運営主体。NOT_FOUNDなら空欄>
EVIDENCE: <そのページの何がpublic_job_countの根拠になるか短く説明>
PROMPT,

      'supported_occupation' => <<<PROMPT
人材紹介会社「{$agency->name}」について調査してください。
公式サイトURLは {$agency->website_url} です。

JobDDにおける supported_occupation は、
「この会社が得意そうな職種」ではなく、
「求職者に紹介可能な職種、または職業紹介事業として取り扱う職種」
を意味します。

公式サイトまたは法定・公的性質の強い情報から、
supported_occupation を第三者が確認できる場合のみ回答してください。

根拠が複数見つかった場合は、次の優先順位で最も直接的・公式性の高い情報を採用してください。

1. 職業安定法に基づく明示、許可情報、取扱業務範囲などの法定・公的性質の強い情報
2. 公式サイト上で明示された取扱職種・転職支援対象職種
3. 同社が運営する求人情報から確認できる職種
4. その他の公式ページ

「○○業界に強い」「エンジニア専門」「豊富な求人」などの
マーケティング表現だけでは supported_occupation と判定しないでください。

複数職種が確認できる場合は、
情報源で明示されている範囲を簡潔にまとめてください。

禁止:
- 会社名やブランドイメージから職種を推測する
- 求人1件だけから取扱職種全体を断定する
- 「○○に強い」等の自己申告表現だけで判定する
- 一般論による補完

十分な根拠が見つからない場合は NOT_FOUND としてください。

次の形式だけで回答してください。

STATUS: FOUND または NOT_FOUND
FACT_VALUE: <対応職種。NOT_FOUNDなら空欄>
SOURCE_URL: <根拠URL。NOT_FOUNDなら空欄>
SOURCE_TITLE: <ページタイトル。NOT_FOUNDなら空欄>
PUBLISHER: <運営主体。NOT_FOUNDなら空欄>
EVIDENCE: <そのページの何がsupported_occupationの根拠になるか短く説明>

FACT_VALUEには supported_occupation に該当する職種情報だけを記載し、
地域・拠点・年収・求人件数など他のFact情報は含めないでください。

PROMPT,

      'experience_range' => <<<PROMPT
人材紹介会社「{$agency->name}」について調査してください。
公式サイトURLは {$agency->website_url} です。

JobDDにおける experience_range は、
「この人材紹介会社が転職支援・求人紹介の対象としている経験年数の範囲」
を意味します。

公式サイトまたは同社が運営する求人情報から、
経験年数の下限と上限を第三者が数値で確認できる場合のみ回答してください。

FACT_VALUEは必ず次の形式にしてください。

<最小経験年数>〜<最大経験年数>

例:
0〜5
3〜10
5〜20

根拠が複数見つかった場合は、次の優先順位で採用してください。

1. 公式サイト上で明示された転職支援対象者の経験年数
2. 公式に明示された対象経験層・応募条件
3. 同社が運営する複数の求人情報から明確に確認できる経験年数範囲
4. その他の公式情報

禁止:
- 「経験者歓迎」だけから経験年数を推測する
- 「未経験可」だけから上限年数を推測する
- 年齢から経験年数を推測する
- 求人1件だけから会社全体の経験年数範囲を断定する
- 一般論や職種の慣習から補完する
- 根拠に存在しない最小値・最大値を生成する

重要:
単一求人、個別求人、特定職種だけの求人条件は、
人材紹介会社全体の experience_range の根拠として使用しないでください。

FOUND としてよいのは、
人材紹介会社そのものについて「転職支援対象者の経験年数」または
「紹介対象となる経験年数の範囲」が公式情報で直接明示されている場合のみです。

複数求人から最小値・最大値を拾って会社全体のレンジを合成することも禁止します。

会社全体の経験年数範囲が直接確認できない場合は、
必ず NOT_FOUND としてください。

下限と上限の両方を数値として確認できない場合は、
無理にレンジを作らず NOT_FOUND としてください。

FACT_VALUEには experience_range に該当する数値レンジだけを記載し、
年齢・年収・地域・職種・求人件数など他のFact情報は含めないでください。

次の形式だけで回答してください。

STATUS: FOUND または NOT_FOUND
FACT_VALUE: <経験年数範囲。NOT_FOUNDなら空欄>
SOURCE_URL: <根拠URL。NOT_FOUNDなら空欄>
SOURCE_TITLE: <ページタイトル。NOT_FOUNDなら空欄>
PUBLISHER: <運営主体。NOT_FOUNDなら空欄>
EVIDENCE: <そのページの何がexperience_rangeの根拠になるか短く説明>
PROMPT,

      'salary_range' => <<<PROMPT
人材紹介会社「{$agency->name}」について調査してください。
公式サイトURLは {$agency->website_url} です。

JobDDにおける salary_range は、
「この人材紹介会社が公式に公開・取り扱っている求人について確認できる年収帯」
を意味します。

会社の平均年収や社員給与ではありません。
また、求職者本人が必ず紹介を受けられる年収を保証するものでもありません。

公式サイトまたは同社が運営する求人情報から、
公開求人全体または十分に広い求人集合について、
年収の下限と上限を第三者が数値で確認できる場合のみ回答してください。

FACT_VALUEは万円単位で、必ず次の形式にしてください。

<最低年収>〜<最高年収>

例:
400〜800
500〜1200
600〜2000

根拠が複数見つかった場合は、次の優先順位で採用してください。

1. 公式サイト上で明示された取扱求人全体の年収帯
2. 公式の求人検索・求人一覧等で明示された広い求人集合の年収帯
3. その他、会社全体の取扱求人年収帯を直接確認できる公式情報

禁止:
- 人材紹介会社自身の社員平均年収を使用する
- 求人1件だけから会社全体のsalary_rangeを断定する
- 数件の求人から最小値と最大値を拾ってレンジを合成する
- 「高年収求人多数」等の表現から数値を推測する
- 年齢・職種・経験年数等から年収を推測する
- 根拠に存在しない最小値・最大値を生成する

単一求人・個別求人の年収情報だけしか確認できない場合は、
必ず NOT_FOUND としてください。

下限と上限の両方を確認できない場合も、
無理にレンジを作らず NOT_FOUND としてください。

FACT_VALUEには salary_range に該当する万円単位の数値レンジだけを記載し、
「万円」等の単位や、職種・地域・経験年数・求人件数など他の情報は含めないでください。

次の形式だけで回答してください。

STATUS: FOUND または NOT_FOUND
FACT_VALUE: <年収範囲。NOT_FOUNDなら空欄>
SOURCE_URL: <根拠URL。NOT_FOUNDなら空欄>
SOURCE_TITLE: <ページタイトル。NOT_FOUNDなら空欄>
PUBLISHER: <運営主体。NOT_FOUNDなら空欄>
EVIDENCE: <そのページの何がsalary_rangeの根拠になるか短く説明>
PROMPT,

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

if ($factKey === 'public_job_count') {
  $jobCountFact = $this->fetchPublicJobCount($agency);

  if ($jobCountFact !== null) {
    return $jobCountFact;
  }
}

    $prompt = $this->buildResearchPrompt(
      $agency,
      $factKey
    );

    $response = \Illuminate\Support\Facades\Http::withToken(
      config('services.openai.key')
    )
      ->timeout(90)
      ->connectTimeout(10)
      ->post('https://api.openai.com/v1/responses', [
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

    if (
      $factKey === 'experience_range' &&
      (
        str_contains($parsed['source_url'], '/job/') ||
        str_contains($parsed['source_url'], ',')
      )
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
