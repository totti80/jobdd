<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Reserved storage identity, never an inferred employer. */
class AnonymousCompany
{
    public const NAME = '[jobdd:anonymous:careerjet]';

    public const LABEL = 'Careerjet掲載・企業名未確認';

    public const NOTE = '企業名は掲載情報から確認できていません';

    public static function name(string $provider): string
    {
        if ($provider !== 'careerjet') {
            throw new InvalidArgumentException('UnsupportedAnonymousProvider');
        }

        return self::NAME;
    }

    public static function isAnonymous(?string $name): bool
    {
        return $name === self::NAME;
    }

    public static function display(?string $name): string
    {
        return self::isAnonymous($name) ? self::LABEL : ($name ?? '企業名未確認');
    }

    /** Caller owns the import transaction; the existing platform row serializes creation. */
    public static function resolve(string $provider): Company
    {
        $name = self::name($provider);
        if (DB::transactionLevel() === 0) {
            throw new InvalidArgumentException('AnonymousCompanyRequiresTransaction');
        }
        if (! DB::table('platforms')->where('name', 'Careerjet')->lockForUpdate()->first()) {
            throw new InvalidArgumentException('MissingPlatform');
        }

        // Locking read sees the latest committed row even under MySQL repeatable read.
        return Company::where('name', $name)->lockForUpdate()->first()
            ?? Company::create(['name' => $name]);
    }
}
