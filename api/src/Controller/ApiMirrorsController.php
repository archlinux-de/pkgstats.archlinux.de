<?php

namespace App\Controller;

use App\Entity\Month;
use App\Entity\Mirror;
use App\Entity\MirrorPopularity;
use App\Entity\MirrorPopularityList;
use App\Request\MirrorQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\MirrorPopularityCalculator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiMirrorsController extends AbstractController
{
    public function __construct(
        private readonly MirrorPopularityCalculator $mirrorPopularityCalculator,
        private readonly string $environment
    ) {
    }

    #[Route(
        path: '/api/mirrors/{url}',
        name: 'app_api_mirror',
        requirements: ['url' => Mirror::URL_REGEXP],
        methods: ['GET'],
        condition: 'not (params["url"] ends with "/series")',
        priority: 1
    )]
    #[OA\Tag(name: 'mirrors')]
    #[OA\Parameter(
        name: 'url',
        description: 'URL of the mirror',
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
        description: 'Returns popularity of given mirror',
        content: new OA\JsonContent(ref: new Model(type: MirrorPopularity::class))
    )]
    public function mirrorAction(string $url, StatisticsRangeRequest $statisticsRangeRequest): Response
    {
        return $this->applyCacheHeaders(
            $this->json(
                $this->mirrorPopularityCalculator->getMirrorPopularity($url, $statisticsRangeRequest)
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
        path: '/api/mirrors/{url}/series',
        name: 'app_api_mirror_series',
        requirements: ['url' => Mirror::URL_REGEXP],
        methods: ['GET'],
        priority: 2
    )]
    #[OA\Tag(name: 'mirrors')]
    #[OA\Parameter(
        name: 'url',
        description: 'URL of the mirror',
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
        description: 'Returns popularities of given mirror in a monthly series',
        content: new OA\JsonContent(ref: new Model(type: MirrorPopularityList::class))
    )]
    public function mirrorSeriesAction(
        string $url,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->mirrorPopularityCalculator->getMirrorPopularitySeries(
                    $url,
                    $statisticsRangeRequest,
                    $paginationRequest
                )
            )
        );
    }

    #[Route(
        path: '/api/mirrors',
        name: 'app_api_mirrors',
        methods: ['GET']
    )]
    #[OA\Tag(name: 'mirrors')]
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
        description: 'Search by mirror url',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', maxLength: 191)
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of mirror popularities',
        content: new OA\JsonContent(ref: new Model(type: MirrorPopularityList::class))
    )]
    public function mirrorJsonAction(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        MirrorQueryRequest $mirrorQueryRequest
    ): Response {
        return $this->applyCacheHeaders(
            $this->json(
                $this->mirrorPopularityCalculator->findMirrorsPopularity(
                    $statisticsRangeRequest,
                    $paginationRequest,
                    $mirrorQueryRequest
                )
            )
        );
    }
}
