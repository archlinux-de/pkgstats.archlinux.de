<?php

namespace App\Entity;

readonly class MirrorPopularityList implements PopularityListInterface
{
    /**
     * @param MirrorPopularity[] $mirrorPopularities
     */
    public function __construct(
        private array $mirrorPopularities,
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
        return count($this->getMirrorPopularities());
    }

    /**
     * @return MirrorPopularity[]
     */
    public function getMirrorPopularities(): array
    {
        return $this->mirrorPopularities;
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
