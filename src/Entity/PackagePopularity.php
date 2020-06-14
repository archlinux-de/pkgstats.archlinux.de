<?php

namespace App\Entity;

class PackagePopularity implements \JsonSerializable
{
    /** @var string */
    private $name;

    /** @var int */
    private $samples;

    /** @var int */
    private $count;

    /** @var int */
    private $startMonth;

    /** @var int */
    private $endMonth;

    /**
     * @param string $name
     * @param int $samples
     * @param int $count
     * @param int $startMonth
     * @param int $endMonth
     */
    public function __construct(string $name, int $samples, int $count, int $startMonth, int $endMonth)
    {
        $this->name = $name;
        $this->samples = $samples;
        $this->count = $count;
        $this->startMonth = $startMonth;
        $this->endMonth = $endMonth;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->getName(),
            'samples' => $this->getSamples(),
            'count' => $this->getCount(),
            'popularity' => $this->getPopularity(),
            'startMonth' => $this->getStartMonth(),
            'endMonth' => $this->getEndMonth()
        ];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getSamples(): int
    {
        return $this->samples;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return float
     */
    public function getPopularity(): float
    {
        return round($this->getCount() / ($this->getSamples() ?: 1) * 100, 2);
    }

    /**
     * @return int
     */
    public function getStartMonth(): int
    {
        return $this->startMonth;
    }

    /**
     * @return int
     */
    public function getEndMonth(): int
    {
        return $this->endMonth;
    }
}
