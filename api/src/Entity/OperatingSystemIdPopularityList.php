<?php

namespace App\Entity;

readonly class OperatingSystemIdPopularityList implements PopularityListInterface
{
    /**
     * @param OperatingSystemIdPopularity[] $operatingSystemIdPopularities
     */
    public function __construct(
        private array $operatingSystemIdPopularities,
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
        return count($this->getOperatingSystemIdPopularities());
    }

    /**
     * @return OperatingSystemIdPopularity[]
     */
    public function getOperatingSystemIdPopularities(): array
    {
        return $this->operatingSystemIdPopularities;
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
