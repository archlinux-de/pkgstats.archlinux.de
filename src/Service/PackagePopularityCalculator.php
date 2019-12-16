<?php

namespace App\Service;

use App\Entity\PackagePopularity;
use App\Entity\PackagePopularityList;
use App\Repository\PackageRepository;
use App\Request\PackageQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;

class PackagePopularityCalculator
{
    /** @var PackageRepository */
    private $packageRepository;

    /**
     * @param PackageRepository $packageRepository
     */
    public function __construct(PackageRepository $packageRepository)
    {
        $this->packageRepository = $packageRepository;
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

        return new PackagePopularity(
            $name,
            $rangeCount,
            $packageCount,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    /**
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @return int
     */
    private function getRangeCount(StatisticsRangeRequest $statisticsRangeRequest): int
    {
        return $this->packageRepository->getMaximumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
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

        $packagePopularities = iterator_to_array((function () use ($packages, $rangeCount, $statisticsRangeRequest) {
            foreach ($packages['packages'] as $package) {
                $packagePopularity = new PackagePopularity(
                    $package['name'],
                    $rangeCount,
                    $package['count'],
                    $statisticsRangeRequest->getStartMonth(),
                    $statisticsRangeRequest->getEndMonth()
                );
                if ($packagePopularity->getPopularity() > 0) {
                    yield $packagePopularity;
                }
            }
        })());

        return new PackagePopularityList(
            $packagePopularities,
            $packages['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset()
        );
    }

    /**
     * @param string $name
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @param PaginationRequest $paginationRequest
     * @return PackagePopularityList
     */
    public function getPackagePopularitySeries(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): PackagePopularityList {
        $rangeCountSeries = $this->getRangeCountSeries($statisticsRangeRequest);
        $packages = $this->packageRepository->findMonthlyByNameAndRange(
            $name,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $packagePopularities = iterator_to_array((function () use (
            $packages,
            $rangeCountSeries
        ) {
            foreach ($packages['packages'] as $package) {
                $packagePopularity = new PackagePopularity(
                    $package['name'],
                    $rangeCountSeries[$package['month']],
                    $package['count'],
                    $package['month'],
                    $package['month']
                );
                if ($packagePopularity->getPopularity() > 0) {
                    yield $packagePopularity;
                }
            }
        })());

        return new PackagePopularityList(
            $packagePopularities,
            $packages['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset()
        );
    }

    /**
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @return array<int>
     */
    private function getRangeCountSeries(StatisticsRangeRequest $statisticsRangeRequest): array
    {
        $monthlyCount = $this->packageRepository->getMonthlyMaximumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return iterator_to_array((function () use ($monthlyCount) {
            foreach ($monthlyCount as $month) {
                yield $month['month'] => $month['count'];
            }
        })());
    }
}
