<?php

namespace App\Entity;

readonly class PackagePopularityList implements PopularityListInterface
{
    /**
     * @param PackagePopularity[] $packagePopularities
     */
    public function __construct(
        private array $packagePopularities,
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
        return count($this->getPackagePopularities());
    }

    /**
     * @return PackagePopularity[]
     */
    public function getPackagePopularities(): array
    {
        return $this->packagePopularities;
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
