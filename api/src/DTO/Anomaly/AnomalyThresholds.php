<?php

namespace App\DTO\Anomaly;

readonly class AnomalyThresholds
{
    public function __construct(
        public int $lookbackMonths = 6,
        public int $minBaselineCount = 100,
        public int $minCorrelationCount = 1000,
        public float $growthThreshold = 300.0,
        public float $extremeGrowthThreshold = 1000.0,
        public float $basePackageDeviationThreshold = 1.5
    ) {
    }
}
