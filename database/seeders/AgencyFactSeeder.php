<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyFact;
use App\Models\Source;
use Illuminate\Database\Seeder;

class AgencyFactSeeder extends Seeder
{
    public function run(): void
    {
        $agencyA = Agency::where('name', 'A社')->firstOrFail();
        $agencyB = Agency::where('name', 'B社')->firstOrFail();
        $agencyC = Agency::where('name', 'C社')->firstOrFail();

        $sourceA = Source::where('publisher', 'A社')->firstOrFail();
        $sourceB = Source::where('publisher', 'B社')->firstOrFail();
        $sourceC = Source::where('publisher', 'C社')->firstOrFail();

        $this->createFacts($agencyA->id, $sourceA->id, [
            ['occupation', 'supported_occupation', '機械設計'],
            ['region', 'supported_region', '兵庫県'],
            ['experience', 'experience_range', '5〜20年'],
            ['salary', 'salary_range', '500〜800万円'],
            ['job_count', 'public_job_count', '520件'],
        ]);

        $this->createFacts($agencyB->id, $sourceB->id, [
            ['occupation', 'supported_occupation', '機械設計'],
            ['region', 'supported_region', '大阪府'],
            ['experience', 'experience_range', '3〜15年'],
            ['salary', 'salary_range', '450〜700万円'],
            ['job_count', 'public_job_count', '360件'],
        ]);

        $this->createFacts($agencyC->id, $sourceC->id, [
            ['occupation', 'supported_occupation', 'CADオペレーター'],
            ['region', 'supported_region', '兵庫県'],
            ['experience', 'experience_range', '0〜10年'],
            ['salary', 'salary_range', '350〜600万円'],
            ['job_count', 'public_job_count', '240件'],
        ]);
    }

    private function createFacts(
        int $agencyId,
        int $sourceId,
        array $facts
    ): void {
        foreach ($facts as [$factType, $factKey, $factValue]) {
            AgencyFact::create([
                'agency_id' => $agencyId,
                'source_id' => $sourceId,
                'fact_type' => $factType,
                'fact_key' => $factKey,
                'fact_value' => $factValue,
                'verification_status' => 'verified',
                'observed_at' => now(),
            ]);
        }
    }
}
