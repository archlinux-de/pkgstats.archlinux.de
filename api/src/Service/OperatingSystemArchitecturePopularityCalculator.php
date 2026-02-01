<?php

namespace App\Service;

use App\Entity\OperatingSystemArchitecturePopularity;
use App\Entity\OperatingSystemArchitecturePopularityList;
use App\Repository\OperatingSystemArchitectureRepository;
use App\Request\OperatingSystemArchitectureQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;

readonly class OperatingSystemArchitecturePopularityCalculator
{
    public function __construct(private OperatingSystemArchitectureRepository $operatingSystemArchitectureRepository)
    {
    }

    public function getOperatingSystemArchitecturePopularity(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest
    ): OperatingSystemArchitecturePopularity {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $operatingSystemArchitectureCount = $this->operatingSystemArchitectureRepository->getCountByNameAndRange(
            $name,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return new OperatingSystemArchitecturePopularity(
            $name,
            $rangeCount,
            $operatingSystemArchitectureCount,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    private function getRangeCount(StatisticsRangeRequest $statisticsRangeRequest): int
    {
        return $this->operatingSystemArchitectureRepository->getSumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    public function findOperatingSystemArchitecturesPopularity(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        OperatingSystemArchitectureQueryRequest $operatingSystemArchitectureQueryRequest
    ): OperatingSystemArchitecturePopularityList {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $operatingSystemArchitectures = $this->operatingSystemArchitectureRepository
            ->findOperatingSystemArchitecturesCountByRange(
                $operatingSystemArchitectureQueryRequest->getQuery(),
                $statisticsRangeRequest->getStartMonth(),
                $statisticsRangeRequest->getEndMonth(),
                $paginationRequest->getOffset(),
                $paginationRequest->getLimit()
            );

        $operatingSystemArchitecturePopularities = iterator_to_array(
            (function () use ($operatingSystemArchitectures, $rangeCount, $statisticsRangeRequest) {
                foreach ($operatingSystemArchitectures['operatingSystemArchitectures'] as $arch) {
                    $popularity = new OperatingSystemArchitecturePopularity(
                        $arch['name'],
                        $rangeCount,
                        $arch['count'],
                        $statisticsRangeRequest->getStartMonth(),
                        $statisticsRangeRequest->getEndMonth()
                    );
                    if ($popularity->getPopularity() > 0) {
                        yield $popularity;
                    }
                }
            })()
        );

        return new OperatingSystemArchitecturePopularityList(
            $operatingSystemArchitecturePopularities,
            $operatingSystemArchitectures['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            $operatingSystemArchitectureQueryRequest->getQuery()
        );
    }

    public function getOperatingSystemArchitecturePopularitySeries(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): OperatingSystemArchitecturePopularityList {
        $rangeCountSeries = $this->getRangeCountSeries($statisticsRangeRequest);
        $operatingSystemArchitectures = $this->operatingSystemArchitectureRepository->findMonthlyByNameAndRange(
            $name,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $operatingSystemArchitecturePopularities = iterator_to_array(
            (function () use ($operatingSystemArchitectures, $rangeCountSeries) {
                foreach ($operatingSystemArchitectures['operatingSystemArchitectures'] as $arch) {
                    $popularity = new OperatingSystemArchitecturePopularity(
                        $arch['name'],
                        $rangeCountSeries[$arch['month']],
                        $arch['count'],
                        $arch['month'],
                        $arch['month']
                    );
                    if ($popularity->getPopularity() > 0) {
                        yield $popularity;
                    }
                }
            })()
        );

        return new OperatingSystemArchitecturePopularityList(
            $operatingSystemArchitecturePopularities,
            $operatingSystemArchitectures['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            null
        );
    }

    /**
     * @return array<int, int>
     */
    private function getRangeCountSeries(StatisticsRangeRequest $statisticsRangeRequest): array
    {
        $monthlyCount = $this->operatingSystemArchitectureRepository->getMonthlySumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return iterator_to_array(
            (function () use ($monthlyCount) {
                foreach ($monthlyCount as $month) {
                    yield $month['month'] => $month['count'];
                }
            })()
        );
    }
}
