<?php

namespace App\DTO\Anomaly;

readonly class GrowthAnomaly
{
    public function __construct(
        public string $identifier,
        public int $count,
        public float $baselineAvg,
        public float $growthPercent
    ) {
    }
}
