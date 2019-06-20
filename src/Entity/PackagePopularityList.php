<?php

namespace App\Entity;

class PackagePopularityList implements \JsonSerializable
{
    /**
     * @var PackagePopularity[]
     */
    private $packagePopularities;

    /**
     * @var int
     */
    private $total = 0;

    /**
     * @param PackagePopularity[] $packagePopularities
     * @param int $total
     */
    public function __construct(array $packagePopularities, int $total)
    {
        $this->packagePopularities = $packagePopularities;
        $this->total = $total;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'total' => $this->getTotal(),
            'count' => $this->getCount(),
            'packagePopularities' => $this->getPackagePopularities()
        ];
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return int
     */
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
}
