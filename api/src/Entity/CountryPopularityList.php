<?php

namespace App\Entity;

readonly class CountryPopularityList implements PopularityListInterface
{
    /**
     * @param CountryPopularity[] $countryPopularities
     */
    public function __construct(
        private array $countryPopularities,
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
        return count($this->getCountryPopularities());
    }

    /**
     * @return CountryPopularity[]
     */
    public function getCountryPopularities(): array
    {
        return $this->countryPopularities;
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
