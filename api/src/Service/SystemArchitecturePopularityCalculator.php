<?php

namespace App\Service;

use App\Entity\SystemArchitecturePopularity;
use App\Entity\SystemArchitecturePopularityList;
use App\Repository\SystemArchitectureRepository;
use App\Request\SystemArchitectureQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;

class SystemArchitecturePopularityCalculator
{
    public function __construct(private SystemArchitectureRepository $systemArchitectureRepository)
    {
    }

    public function getSystemArchitecturePopularity(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest
    ): SystemArchitecturePopularity {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $systemArchitectureCount = $this->systemArchitectureRepository->getCountByNameAndRange(
            $name,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return new SystemArchitecturePopularity(
            $name,
            $rangeCount,
            $systemArchitectureCount,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    private function getRangeCount(StatisticsRangeRequest $statisticsRangeRequest): int
    {
        return $this->systemArchitectureRepository->getSumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    public function findSystemArchitecturesPopularity(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        SystemArchitectureQueryRequest $systemArchitectureQueryRequest
    ): SystemArchitecturePopularityList {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $systemArchitectures = $this->systemArchitectureRepository->findSystemArchitecturesCountByRange(
            $systemArchitectureQueryRequest->getQuery(),
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $systemArchitecturePopularities = iterator_to_array(
            (function () use ($systemArchitectures, $rangeCount, $statisticsRangeRequest) {
                foreach ($systemArchitectures['systemArchitectures'] as $systemArchitecture) {
                    $systemArchitecturePopularity = new SystemArchitecturePopularity(
                        $systemArchitecture['name'],
                        $rangeCount,
                        $systemArchitecture['count'],
                        $statisticsRangeRequest->getStartMonth(),
                        $statisticsRangeRequest->getEndMonth()
                    );
                    if ($systemArchitecturePopularity->getPopularity() > 0) {
                        yield $systemArchitecturePopularity;
                    }
                }
            })()
        );

        return new SystemArchitecturePopularityList(
            $systemArchitecturePopularities,
            $systemArchitectures['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            $systemArchitectureQueryRequest->getQuery()
        );
    }

    public function getSystemArchitecturePopularitySeries(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): SystemArchitecturePopularityList {
        $rangeCountSeries = $this->getRangeCountSeries($statisticsRangeRequest);
        $systemArchitectures = $this->systemArchitectureRepository->findMonthlyByNameAndRange(
            $name,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $systemArchitecturePopularities = iterator_to_array(
            (function () use (
                $systemArchitectures,
                $rangeCountSeries
            ) {
                foreach ($systemArchitectures['systemArchitectures'] as $systemArchitecture) {
                    $systemArchitecturePopularity = new SystemArchitecturePopularity(
                        $systemArchitecture['name'],
                        $rangeCountSeries[$systemArchitecture['month']],
                        $systemArchitecture['count'],
                        $systemArchitecture['month'],
                        $systemArchitecture['month']
                    );
                    if ($systemArchitecturePopularity->getPopularity() > 0) {
                        yield $systemArchitecturePopularity;
                    }
                }
            })()
        );

        return new SystemArchitecturePopularityList(
            $systemArchitecturePopularities,
            $systemArchitectures['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            null
        );
    }

    private function getRangeCountSeries(StatisticsRangeRequest $statisticsRangeRequest): array
    {
        $monthlyCount = $this->systemArchitectureRepository->getMonthlySumCountByRange(
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
