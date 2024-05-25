<?php

namespace App\Entity;

readonly class SystemArchitecturePopularityList implements PopularityListInterface
{
    /**
     * @param SystemArchitecturePopularity[] $systemArchitecturePopularities
     */
    public function __construct(
        private array $systemArchitecturePopularities,
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
        return count($this->getSystemArchitecturePopularities());
    }

    /**
     * @return SystemArchitecturePopularity[]
     */
    public function getSystemArchitecturePopularities(): array
    {
        return $this->systemArchitecturePopularities;
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
