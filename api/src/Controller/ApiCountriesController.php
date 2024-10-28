<?php

namespace App\Controller;

use App\Entity\Country;
use App\Entity\CountryPopularity;
use App\Entity\CountryPopularityList;
use App\Entity\Month;
use App\Request\CountryQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\CountryPopularityCalculator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiCountriesController extends AbstractController
{
    public function __construct(
        private readonly CountryPopularityCalculator $countryPopularityCalculator,
        private readonly string $environment
    ) {
    }

    #[Route(
        path: '/api/countries/{code}',
        name: 'app_api_country',
        requirements: ['code' => Country::CODE_REGEXP],
        methods: ['GET'],
        priority: 1
    )]
    #[OA\Tag(name: 'countries')]
    #[OA\Parameter(
        name: 'code',
        description: 'ISO 3166-1 alpha-2 code of the country',
        in: 'path',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'startMonth',
        description: 'Specify start month in the form of \'Ym\', e.g. 201901. Defaults to last month.',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'endMonth',
        description: 'Specify end month in the format of \'Ym\', e.g. 201901. Defaults to last month.',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns popularity of given country',
        content: new OA\JsonContent(ref: new Model(type: CountryPopularity::class))
    )]
    public function countryAction(string $code, StatisticsRangeRequest $statisticsRangeRequest): Response
    {
        return $this->applyCacheHeaders(
            $this->json(
                $this->countryPopularityCalculator->getCountryPopularity($code, $statisticsRangeRequest)
            )
        );
    }

    private function applyCacheHeaders(Response $response): Response
    {
        if ($this->environment !== 'prod') {
            return $response;
        }

        return $response
            ->setMaxAge(5 * 60)
            ->setSharedMaxAge(Month::create(1)->getTimestamp() - time());
    }

    #[Route(
        path: '/api/countries/{code}/series',
        name: 'app_api_country_series',
        requirements: ['code' => Country::CODE_REGEXP],
        methods: ['GET'],
        priority: 2
    )]
    #[OA\Tag(name: 'countries')]
    #[OA\Parameter(
        name: 'code',
        description: 'ISO 3166-1 alpha-2 code of the country',
        in: 'path',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'startMonth',
        description: 'Specify start month in the form of \'Ym\', e.g. 201901. Defaults to last month.',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'endMonth',
        description: 'Specify end month in the format of \'Ym\', e.g. 201901. Defaults to last month.',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Limit the result set',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 100, maximum: 10000, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'offset',
        description: 'Offset the result set',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 0, maximum: 100000, minimum: 0)
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns popularities of given country in a monthly series',
        content: new OA\JsonContent(ref: new Model(type: CountryPopularityList::class))
    )]
    public function countrieseriesAction(
        string $code,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->countryPopularityCalculator->getCountryPopularitySeries(
                    $code,
                    $statisticsRangeRequest,
                    $paginationRequest
                )
            )
        );
    }

    #[Route(
        path: '/api/countries',
        name: 'app_api_countries',
        methods: ['GET']
    )]
    #[OA\Tag(name: 'countries')]
    #[OA\Parameter(
        name: 'startMonth',
        description: 'Specify start month in the format of \'Ym\', e.g. 201901. Defaults to last month.',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', format: 'Ym')
    )]
    #[OA\Parameter(
        name: 'endMonth',
        description: 'Specify end month in the format of \'Ym\', e.g. 201901. Defaults to last month.',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Limit the result set',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 100, maximum: 10000, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'offset',
        description: 'Offset the result set',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 0, maximum: 100000, minimum: 0)
    )]
    #[OA\Parameter(
        name: 'query',
        description: 'Search by country code',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', maxLength: 191)
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of country popularities',
        content: new OA\JsonContent(ref: new Model(type: CountryPopularityList::class))
    )]
    public function countryJsonAction(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        CountryQueryRequest $countryQueryRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->countryPopularityCalculator->findCountriesPopularity(
                    $statisticsRangeRequest,
                    $paginationRequest,
                    $countryQueryRequest
                )
            )
        );
    }
}
