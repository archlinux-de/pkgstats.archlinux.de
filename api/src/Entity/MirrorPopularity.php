<?php

namespace App\Entity;

readonly class MirrorPopularity implements PopularityInterface
{
    public function __construct(
        private string $url,
        private int $samples,
        private int $count,
        private int $startMonth,
        private int $endMonth
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getSamples(): int
    {
        return $this->samples;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getPopularity(): float
    {
        if ($this->getSamples() < 1 || $this->getCount() < 0) {
            return 0;
        }
        if ($this->getCount() >= $this->getSamples()) {
            return 100;
        }
        return round($this->getCount() / $this->getSamples() * 100, 2);
    }

    public function getStartMonth(): int
    {
        return $this->startMonth;
    }

    public function getEndMonth(): int
    {
        return $this->endMonth;
    }
}
