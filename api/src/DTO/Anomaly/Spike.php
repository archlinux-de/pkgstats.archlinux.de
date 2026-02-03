<?php

namespace App\DTO\Anomaly;

readonly class Spike
{
    public function __construct(
        public string $identifier,
        public int $count
    ) {
    }
}
