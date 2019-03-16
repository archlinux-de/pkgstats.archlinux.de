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

    /**
     * @param string $name
     * @param int $samples
     * @param int $count
     */
    public function __construct(string $name, int $samples, int $count)
    {
        $this->name = $name;
        $this->samples = $samples;
        $this->count = $count;
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
            'popularity' => $this->getPopularity()
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
}
