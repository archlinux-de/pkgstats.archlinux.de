<?php

namespace App\Controller;

use App\Entity\Month;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\OperatingSystemArchitecturePopularity;
use App\Entity\OperatingSystemArchitecturePopularityList;
use App\Request\OperatingSystemArchitectureQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\OperatingSystemArchitecturePopularityCalculator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiOperatingSystemArchitectureController extends AbstractController
{
    public function __construct(
        private readonly OperatingSystemArchitecturePopularityCalculator $calculator,
        private readonly string $environment
    ) {
    }

    #[Route(
        path: '/api/operating-system-architectures/{name}',
        name: 'app_api_operating_system_architecture',
        requirements: ['name' => OperatingSystemArchitecture::NAME_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'operating-system-architectures')]
    #[OA\Parameter(
        name: 'name',
        description: 'Name of the operating system architecture',
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
        description: 'Returns popularity of given operating system architecture',
        content: new OA\JsonContent(ref: new Model(type: OperatingSystemArchitecturePopularity::class))
    )]
    public function operatingSystemArchitectureAction(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->calculator->getOperatingSystemArchitecturePopularity($name, $statisticsRangeRequest)
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
        path: '/api/operating-system-architectures/{name}/series',
        name: 'app_api_operating_system_architecture_series',
        requirements: ['name' => OperatingSystemArchitecture::NAME_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'operating-system-architectures')]
    #[OA\Parameter(
        name: 'name',
        description: 'Name of the operating system architecture',
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
        description: 'Returns popularities of given operating system architecture in a monthly series',
        content: new OA\JsonContent(ref: new Model(type: OperatingSystemArchitecturePopularityList::class))
    )]
    public function operatingSystemArchitectureSeriesAction(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->calculator->getOperatingSystemArchitecturePopularitySeries(
                    $name,
                    $statisticsRangeRequest,
                    $paginationRequest
                )
            )
        );
    }

    #[Route(
        path: '/api/operating-system-architectures',
        name: 'app_api_operating_system_architectures',
        methods: ['GET']
    )]
    #[OA\Tag(name: 'operating-system-architectures')]
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
        description: 'Search by operating system architecture name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', maxLength: 15)
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of operating system architecture popularities',
        content: new OA\JsonContent(ref: new Model(type: OperatingSystemArchitecturePopularityList::class))
    )]
    public function operatingSystemArchitecturesAction(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        OperatingSystemArchitectureQueryRequest $operatingSystemArchitectureQueryRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->calculator->findOperatingSystemArchitecturesPopularity(
                    $statisticsRangeRequest,
                    $paginationRequest,
                    $operatingSystemArchitectureQueryRequest
                )
            )
        );
    }
}
