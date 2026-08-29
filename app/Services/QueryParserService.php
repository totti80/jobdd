<?php

namespace App\Services;

class QueryParserService
{
  public function parse(string $rawText): array
  {
    $data = [
      'occupation' => null,
      'industry' => null,
      'region' => null,
      'experience_years' => null,
      'salary_min' => null,
      'salary_max' => null,
      'validation_domain' => null,
    ];

    if (str_contains($rawText, '機械設計')) {
      $data['occupation'] = '機械設計';
      $data['validation_domain'] = '製造・プラント系';
    }

    if (str_contains($rawText, '兵庫県')) {
      $data['region'] = '兵庫県';
    }

    if (preg_match('/(\d+)年/', $rawText, $matches)) {
      $data['experience_years'] = (int) $matches[1];
    }

    if (preg_match('/(\d+)万円以上/', $rawText, $matches)) {
      $data['salary_min'] = (int) $matches[1];
    }

    return $data;
  }
}
