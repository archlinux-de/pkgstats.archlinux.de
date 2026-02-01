<?php

namespace App\Service;

use App\Entity\OperatingSystemIdPopularity;
use App\Entity\OperatingSystemIdPopularityList;
use App\Repository\OperatingSystemIdRepository;
use App\Request\OperatingSystemIdQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;

readonly class OperatingSystemIdPopularityCalculator
{
    public function __construct(private OperatingSystemIdRepository $operatingSystemIdRepository)
    {
    }

    public function getOperatingSystemIdPopularity(
        string $id,
        StatisticsRangeRequest $statisticsRangeRequest
    ): OperatingSystemIdPopularity {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $operatingSystemIdCount = $this->operatingSystemIdRepository->getCountByIdAndRange(
            $id,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return new OperatingSystemIdPopularity(
            $id,
            $rangeCount,
            $operatingSystemIdCount,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    private function getRangeCount(StatisticsRangeRequest $statisticsRangeRequest): int
    {
        return $this->operatingSystemIdRepository->getSumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    public function findOperatingSystemIdsPopularity(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        OperatingSystemIdQueryRequest $operatingSystemIdQueryRequest
    ): OperatingSystemIdPopularityList {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $operatingSystemIds = $this->operatingSystemIdRepository->findOperatingSystemIdsCountByRange(
            $operatingSystemIdQueryRequest->getQuery(),
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $operatingSystemIdPopularities = iterator_to_array(
            (function () use ($operatingSystemIds, $rangeCount, $statisticsRangeRequest) {
                foreach ($operatingSystemIds['operatingSystemIds'] as $operatingSystemId) {
                    $operatingSystemIdPopularity = new OperatingSystemIdPopularity(
                        $operatingSystemId['id'],
                        $rangeCount,
                        $operatingSystemId['count'],
                        $statisticsRangeRequest->getStartMonth(),
                        $statisticsRangeRequest->getEndMonth()
                    );
                    if ($operatingSystemIdPopularity->getPopularity() > 0) {
                        yield $operatingSystemIdPopularity;
                    }
                }
            })()
        );

        return new OperatingSystemIdPopularityList(
            $operatingSystemIdPopularities,
            $operatingSystemIds['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            $operatingSystemIdQueryRequest->getQuery()
        );
    }

    public function getOperatingSystemIdPopularitySeries(
        string $id,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): OperatingSystemIdPopularityList {
        $rangeCountSeries = $this->getRangeCountSeries($statisticsRangeRequest);
        $operatingSystemIds = $this->operatingSystemIdRepository->findMonthlyByIdAndRange(
            $id,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $operatingSystemIdPopularities = iterator_to_array(
            (function () use (
                $operatingSystemIds,
                $rangeCountSeries
            ) {
                foreach ($operatingSystemIds['operatingSystemIds'] as $operatingSystemId) {
                    $operatingSystemIdPopularity = new OperatingSystemIdPopularity(
                        $operatingSystemId['id'],
                        $rangeCountSeries[$operatingSystemId['month']],
                        $operatingSystemId['count'],
                        $operatingSystemId['month'],
                        $operatingSystemId['month']
                    );
                    if ($operatingSystemIdPopularity->getPopularity() > 0) {
                        yield $operatingSystemIdPopularity;
                    }
                }
            })()
        );

        return new OperatingSystemIdPopularityList(
            $operatingSystemIdPopularities,
            $operatingSystemIds['total'],
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
        $monthlyCount = $this->operatingSystemIdRepository->getMonthlySumCountByRange(
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
