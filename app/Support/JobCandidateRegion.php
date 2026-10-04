<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** The supported prefectures, optionally followed by a single municipality/address. */
class JobCandidateRegion
{
    public static function apply(Builder $query): Builder
    {
        return $query->whereRaw('region REGEXP ?', [
            '^(兵庫県|大阪府|京都府|滋賀県|奈良県|和歌山県)([^/／,、;；|｜]*[市区町村][^/／,、;；|｜]*)?$',
        ]);
    }
}
