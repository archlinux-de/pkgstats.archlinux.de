<?php

namespace App\Service;

use App\Entity\CountryPopularity;
use App\Entity\CountryPopularityList;
use App\Repository\CountryRepository;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Request\CountryQueryRequest;

readonly class CountryPopularityCalculator
{
    public function __construct(private CountryRepository $countryRepository)
    {
    }

    public function getCountryPopularity(
        string $code,
        StatisticsRangeRequest $statisticsRangeRequest
    ): CountryPopularity {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $countryCount = $this->countryRepository->getCountByCodeAndRange(
            $code,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );

        return new CountryPopularity(
            $code,
            $rangeCount,
            $countryCount,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    private function getRangeCount(StatisticsRangeRequest $statisticsRangeRequest): int
    {
        return $this->countryRepository->getSumCountByRange(
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    public function findCountriesPopularity(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        CountryQueryRequest $countryQueryRequest
    ): CountryPopularityList {
        $rangeCount = $this->getRangeCount($statisticsRangeRequest);
        $countries = $this->countryRepository->findCountriesCountByRange(
            $countryQueryRequest->getQuery(),
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $countryPopularities = iterator_to_array(
            (function () use ($countries, $rangeCount, $statisticsRangeRequest) {
                foreach ($countries['countries'] as $country) {
                    $countryPopularity = new CountryPopularity(
                        $country['code'],
                        $rangeCount,
                        $country['count'],
                        $statisticsRangeRequest->getStartMonth(),
                        $statisticsRangeRequest->getEndMonth()
                    );
                    if ($countryPopularity->getPopularity() > 0) {
                        yield $countryPopularity;
                    }
                }
            })()
        );

        return new CountryPopularityList(
            $countryPopularities,
            $countries['total'],
            $paginationRequest->getLimit(),
            $paginationRequest->getOffset(),
            $countryQueryRequest->getQuery()
        );
    }

    public function getCountryPopularitySeries(
        string $code,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): CountryPopularityList {
        $rangeCountSeries = $this->getRangeCountSeries($statisticsRangeRequest);
        $countries = $this->countryRepository->findMonthlyByCodeAndRange(
            $code,
            $statisticsRangeRequest->getStartMonth(),
            $statisticsRangeRequest->getEndMonth(),
            $paginationRequest->getOffset(),
            $paginationRequest->getLimit()
        );

        $countryPopularities = iterator_to_array(
            (function () use (
                $countries,
                $rangeCountSeries
            ) {
                foreach ($countries['countries'] as $country) {
                    $countryPopularity = new CountryPopularity(
                        $country['code'],
                        $rangeCountSeries[$country['month']],
                        $country['count'],
                        $country['month'],
                        $country['month']
                    );
                    if ($countryPopularity->getPopularity() > 0) {
                        yield $countryPopularity;
                    }
                }
            })()
        );

        return new CountryPopularityList(
            $countryPopularities,
            $countries['total'],
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
        $monthlyCount = $this->countryRepository->getMonthlySumCountByRange(
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
