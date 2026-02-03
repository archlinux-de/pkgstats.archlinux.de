<?php

namespace App\DTO\Anomaly;

readonly class DetectionResult
{
    /**
     * @param list<CountCorrelation> $countCorrelations
     * @param list<Spike> $newPackageSpikes
     * @param list<GrowthAnomaly> $mirrorAnomalies
     * @param list<Spike> $newMirrorSpikes
     * @param list<GrowthAnomaly> $systemArchAnomalies
     * @param list<GrowthAnomaly> $osArchAnomalies
     */
    public function __construct(
        public array $countCorrelations,
        public array $newPackageSpikes,
        public array $mirrorAnomalies,
        public array $newMirrorSpikes,
        public array $systemArchAnomalies,
        public array $osArchAnomalies,
        public BasePackageResult $basePackageResult
    ) {
    }

    public function hasMirrorAnomalies(): bool
    {
        return !empty($this->mirrorAnomalies) || !empty($this->newMirrorSpikes);
    }

    public function hasArchitectureAnomalies(): bool
    {
        return !empty($this->systemArchAnomalies) || !empty($this->osArchAnomalies);
    }

    public function hasBasePackageAnomalies(): bool
    {
        return $this->basePackageResult->hasAnomalies();
    }

    public function hasExtremeMirrorGrowth(float $threshold): bool
    {
        return array_any($this->mirrorAnomalies, fn($anomaly): bool => $anomaly->growthPercent > $threshold);
    }

    public function isHighConfidence(float $extremeGrowthThreshold): bool
    {
        return $this->hasBasePackageAnomalies()
            || ($this->hasMirrorAnomalies() && $this->hasArchitectureAnomalies())
            || $this->hasExtremeMirrorGrowth($extremeGrowthThreshold);
    }
}
