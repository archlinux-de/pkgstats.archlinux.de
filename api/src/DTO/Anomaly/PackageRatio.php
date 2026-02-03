<?php

namespace App\DTO\Anomaly;

readonly class PackageRatio
{
    public function __construct(
        public string $name,
        public int $count,
        public float $ratio
    ) {
    }
}
