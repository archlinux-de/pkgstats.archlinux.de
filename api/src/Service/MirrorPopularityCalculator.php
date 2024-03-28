<?php

namespace App\Service;

use App\Entity\MirrorPopularity;
use App\Entity\MirrorPopularityList;
use App\Repository\MirrorRepository;
use App\Request\MirrorQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;

readonly class MirrorPopularityCalculator
{
    public function __construct(private MirrorRepository $mirrorRepository)
    {
    }

    public function getMirrorPopularity(
        string $url,
        StatisticsRangeRequest $statisticsRangeRequest
    ): MirrorPopularity {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $mirrorCount = $this->mirrorRepository->getCountByUrlAndRange(
            $url,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return new MirrorPopularity(
            $url,
            $rangeCount,
            $mirrorCount,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    private function getRangeCount(StatisticsRangeRequest $statisticsRangeRequest): int
    {
        return $this->mirrorRepository->getSumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    public function findMirrorsPopularity(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        MirrorQueryRequest $mirrorQueryRequest
    ): MirrorPopularityList {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $mirrors = $this->mirrorRepository->findMirrorsCountByRange(
            $mirrorQueryRequest->getQuery(),
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $mirrorPopularities = iterator_to_array(
            (function () use ($mirrors, $rangeCount, $statisticsRangeRequest) {
                foreach ($mirrors['mirrors'] as $mirror) {
                    $mirrorPopularity = new MirrorPopularity(
                        $mirror['url'],
                        $rangeCount,
                        $mirror['count'],
                        $statisticsRangeRequest->getStartMonth(),
                        $statisticsRangeRequest->getEndMonth()
                    );
                    if ($mirrorPopularity->getPopularity() > 0) {
                        yield $mirrorPopularity;
                    }
                }
            })()
        );

        return new MirrorPopularityList(
            $mirrorPopularities,
            $mirrors['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            $mirrorQueryRequest->getQuery()
        );
    }

    public function getMirrorPopularitySeries(
        string $url,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): MirrorPopularityList {
        $rangeCountSeries = $this->getRangeCountSeries($statisticsRangeRequest);
        $mirrors = $this->mirrorRepository->findMonthlyByUrlAndRange(
            $url,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $mirrorPopularities = iterator_to_array(
            (function () use (
                $mirrors,
                $rangeCountSeries
            ) {
                foreach ($mirrors['mirrors'] as $mirror) {
                    $mirrorPopularity = new MirrorPopularity(
                        $mirror['url'],
                        $rangeCountSeries[$mirror['month']],
                        $mirror['count'],
                        $mirror['month'],
                        $mirror['month']
                    );
                    if ($mirrorPopularity->getPopularity() > 0) {
                        yield $mirrorPopularity;
                    }
                }
            })()
        );

        return new MirrorPopularityList(
            $mirrorPopularities,
            $mirrors['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            null
        );
    }

    private function getRangeCountSeries(StatisticsRangeRequest $statisticsRangeRequest): array
    {
        $monthlyCount = $this->mirrorRepository->getMonthlySumCountByRange(
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
