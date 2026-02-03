<?php

namespace App\DTO\Anomaly;

readonly class BasePackageResult
{
    /**
     * @param list<PackageRatio> $outliers
     * @param list<PackageRatio> $packagesAboveThreshold
     */
    public function __construct(
        public int $median,
        public array $outliers,
        public array $packagesAboveThreshold
    ) {
    }

    public function hasAnomalies(): bool
    {
        return !empty($this->outliers) || !empty($this->packagesAboveThreshold);
    }
}
