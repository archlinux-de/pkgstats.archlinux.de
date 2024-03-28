<?php

namespace App\Controller;

use App\Entity\Month;
use App\Entity\Package;
use App\Entity\PackagePopularity;
use App\Entity\PackagePopularityList;
use App\Request\PackageQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\PackagePopularityCalculator;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiPackagesController extends AbstractController
{
    public function __construct(
        private readonly PackagePopularityCalculator $packagePopularityCalculator,
        private readonly string $environment
    ) {
    }

    #[Route(
        path: '/api/packages/{name}',
        name: 'app_api_package',
        requirements: ['name' => Package::NAME_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'packages')]
    #[OA\Parameter(
        name: 'name',
        description: 'Name of the package',
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
        description: 'Returns popularity of given package',
        content: new OA\JsonContent(ref: new Model(type: PackagePopularity::class))
    )]
    public function packageAction(string $name, StatisticsRangeRequest $statisticsRangeRequest): Response
    {
        return $this->applyCacheHeaders(
            $this->json(
                $this->packagePopularityCalculator->getPackagePopularity($name, $statisticsRangeRequest)
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
        path: '/api/packages/{name}/series',
        name: 'app_api_package_series',
        requirements: ['name' => Package::NAME_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'packages')]
    #[OA\Parameter(
        name: 'name',
        description: 'Name of the package',
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
        description: 'Returns popularities of given package in a monthly series',
        content: new OA\JsonContent(ref: new Model(type: PackagePopularityList::class))
    )]
    public function packageSeriesAction(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->packagePopularityCalculator->getPackagePopularitySeries(
                    $name,
                    $statisticsRangeRequest,
                    $paginationRequest
                )
            )
        );
    }

    #[Route(
        path: '/api/packages',
        name: 'app_api_packages',
        methods: ['GET']
    )]
    #[OA\Tag(name: 'packages')]
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
        description: 'Search by package name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', maxLength: 191)
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of package popularities',
        content: new OA\JsonContent(ref: new Model(type: PackagePopularityList::class))
    )]
    public function packageJsonAction(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        PackageQueryRequest $packageQueryRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->packagePopularityCalculator->findPackagesPopularity(
                    $statisticsRangeRequest,
                    $paginationRequest,
                    $packageQueryRequest
                )
            )
        );
    }
}
