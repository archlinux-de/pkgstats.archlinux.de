<?php

namespace App\Controller;

use App\Entity\Month;
use App\Entity\SystemArchitecture;
use App\Entity\SystemArchitecturePopularity;
use App\Entity\SystemArchitecturePopularityList;
use App\Request\SystemArchitectureQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\SystemArchitecturePopularityCalculator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiSystemArchitectureController extends AbstractController
{
    public function __construct(
        private readonly SystemArchitecturePopularityCalculator $systemArchitecturePopularityCalculator,
        private readonly string $environment
    ) {
    }

    #[Route(
        path: '/api/system-architectures/{name}',
        name: 'app_api_system_architecture',
        requirements: ['name' => SystemArchitecture::NAME_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'system-architectures')]
    #[OA\Parameter(
        name: 'name',
        description: 'Name of the system architecture',
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
        description: 'Returns popularity of given system architecture',
        content: new OA\JsonContent(ref: new Model(type: SystemArchitecturePopularity::class))
    )]
    public function systemArchitectureAction(string $name, StatisticsRangeRequest $statisticsRangeRequest): Response
    {
        return $this->applyCacheHeaders(
            $this->json(
                $this
                    ->systemArchitecturePopularityCalculator
                    ->getSystemArchitecturePopularity($name, $statisticsRangeRequest)
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
        path: '/api/system-architectures/{name}/series',
        name: 'app_api_system_architecture_series',
        requirements: ['name' => SystemArchitecture::NAME_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'system-architectures')]
    #[OA\Parameter(
        name: 'name',
        description: 'Name of the system architecture',
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
        description: 'Returns popularities of given system architecture in a monthly series',
        content: new OA\JsonContent(ref: new Model(type: SystemArchitecturePopularityList::class))
    )]
    public function systemArchitectureSeriesAction(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->systemArchitecturePopularityCalculator->getSystemArchitecturePopularitySeries(
                    $name,
                    $statisticsRangeRequest,
                    $paginationRequest
                )
            )
        );
    }

    #[Route(
        path: '/api/system-architectures',
        name: 'app_api_system_architectures',
        methods: ['GET']
    )]
    #[OA\Tag(name: 'system-architectures')]
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
        description: 'Search by system architecture name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', maxLength: 191)
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of system architecture popularities',
        content: new OA\JsonContent(ref: new Model(type: SystemArchitecturePopularityList::class))
    )]
    public function systemArchitectureJsonAction(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        SystemArchitectureQueryRequest $systemArchitectureQueryRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->systemArchitecturePopularityCalculator->findSystemArchitecturesPopularity(
                    $statisticsRangeRequest,
                    $paginationRequest,
                    $systemArchitectureQueryRequest
                )
            )
        );
    }
}
