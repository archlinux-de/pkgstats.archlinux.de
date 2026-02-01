<?php

namespace App\Entity;

readonly class OperatingSystemArchitecturePopularityList implements PopularityListInterface
{
    /**
     * @param OperatingSystemArchitecturePopularity[] $operatingSystemArchitecturePopularities
     */
    public function __construct(
        private array $operatingSystemArchitecturePopularities,
        private int $total,
        private int $limit,
        private int $offset,
        private ?string $query
    ) {
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCount(): int
    {
        return count($this->getOperatingSystemArchitecturePopularities());
    }

    /**
     * @return OperatingSystemArchitecturePopularity[]
     */
    public function getOperatingSystemArchitecturePopularities(): array
    {
        return $this->operatingSystemArchitecturePopularities;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }
}
