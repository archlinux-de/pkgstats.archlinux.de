<?php

namespace App\DTO\Anomaly;

readonly class CountCorrelation
{
    /** @param list<string> $packages */
    public function __construct(
        public int $delta,
        public int $packageCount,
        public array $packages
    ) {
    }
}
