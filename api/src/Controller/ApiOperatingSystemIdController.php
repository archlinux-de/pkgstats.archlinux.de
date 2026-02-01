<?php

namespace App\Controller;

use App\Entity\Month;
use App\Entity\OperatingSystemId;
use App\Entity\OperatingSystemIdPopularity;
use App\Entity\OperatingSystemIdPopularityList;
use App\Request\OperatingSystemIdQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\OperatingSystemIdPopularityCalculator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiOperatingSystemIdController extends AbstractController
{
    public function __construct(
        private readonly OperatingSystemIdPopularityCalculator $operatingSystemIdPopularityCalculator,
        private readonly string $environment
    ) {
    }

    #[Route(
        path: '/api/operating-systems/{id}',
        name: 'app_api_operating_system_id',
        requirements: ['id' => OperatingSystemId::ID_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'operating-systems')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID of the operating system',
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
        description: 'Returns popularity of given operating system ID',
        content: new OA\JsonContent(ref: new Model(type: OperatingSystemIdPopularity::class))
    )]
    public function operatingSystemIdAction(string $id, StatisticsRangeRequest $statisticsRangeRequest): Response
    {
        return $this->applyCacheHeaders(
            $this->json(
                $this
                    ->operatingSystemIdPopularityCalculator
                    ->getOperatingSystemIdPopularity($id, $statisticsRangeRequest)
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
        path: '/api/operating-systems/{id}/series',
        name: 'app_api_operating_system_id_series',
        requirements: ['id' => OperatingSystemId::ID_REGEXP],
        methods: ['GET']
    )]
    #[OA\Tag(name: 'operating-systems')]
    #[OA\Parameter(
        name: 'id',
        description: 'ID of the operating system',
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
        description: 'Returns popularities of given operating system ID in a monthly series',
        content: new OA\JsonContent(ref: new Model(type: OperatingSystemIdPopularityList::class))
    )]
    public function operatingSystemIdSeriesAction(
        string $id,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->operatingSystemIdPopularityCalculator->getOperatingSystemIdPopularitySeries(
                    $id,
                    $statisticsRangeRequest,
                    $paginationRequest
                )
            )
        );
    }

    #[Route(
        path: '/api/operating-systems',
        name: 'app_api_operating_system_ids',
        methods: ['GET']
    )]
    #[OA\Tag(name: 'operating-systems')]
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
        description: 'Search by operating system ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', maxLength: 50)
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of operating system ID popularities',
        content: new OA\JsonContent(ref: new Model(type: OperatingSystemIdPopularityList::class))
    )]
    public function operatingSystemIdJsonAction(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        OperatingSystemIdQueryRequest $operatingSystemIdQueryRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->operatingSystemIdPopularityCalculator->findOperatingSystemIdsPopularity(
                    $statisticsRangeRequest,
                    $paginationRequest,
                    $operatingSystemIdQueryRequest
                )
            )
        );
    }
}
