<?php

namespace App\Service;

use App\Entity\PackagePopularity;
use App\Entity\PackagePopularityList;
use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use App\Request\PackageQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;

class PackagePopularityCalculator
{
    /** @var PackageRepository */
    private $packageRepository;

    /** @var UserRepository */
    private $userRepository;

    /**
     * @param PackageRepository $packageRepository
     * @param UserRepository $userRepository
     */
    public function __construct(
        PackageRepository $packageRepository,
        UserRepository $userRepository
    ) {
        $this->packageRepository = $packageRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param string $name
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @return PackagePopularity
     */
    public function getPackagePopularity(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest
    ): PackagePopularity {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $packageCount = $this->packageRepository->getCountByNameAndRange(
            $name,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return new PackagePopularity($name, $rangeCount, $packageCount);
    }

    /**
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @return int
     */
    private function getRangeCount(StatisticsRangeRequest $statisticsRangeRequest): int
    {
        return $this->userRepository->getCountByRange(
            (int)strtotime(
                $statisticsRangeRequest->getStartMonth() .
                '01 00:00:00'
            ),
            (int)strtotime(
                $statisticsRangeRequest->getEndMonth() .
                date('t', $statisticsRangeRequest->getEndMonth()) . ' 23:59:59'
            )
        );
    }

    /**
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @param PaginationRequest $paginationRequest
     * @param PackageQueryRequest $packageQueryRequest
     * @return PackagePopularityList
     */
    public function findPackagesPopularity(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        PackageQueryRequest $packageQueryRequest
    ): PackagePopularityList {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $packages = $this->packageRepository->findPackagesCountByRange(
            $packageQueryRequest->getQuery(),
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $packagePopularities = iterator_to_array((function ($packages, $rangeCount) {
            foreach ($packages['packages'] as $package) {
                $packagePopularity = new PackagePopularity($package['name'], $rangeCount, $package['count']);
                if ($packagePopularity->getPopularity() > 0) {
                    yield $packagePopularity;
                }
            }
        })($packages, $rangeCount));

        return new PackagePopularityList($packagePopularities, $packages['total']);
    }
}
