<?php

namespace App\Controller;

use App\Entity\PackagePopularity;
use App\Entity\PackagePopularityList;
use App\Request\PackageQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\PackagePopularityCalculator;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;

/**
 * @Cache(smaxage="first day of next month", maxage="+5 minutes")
 */
class ApiPackagesController extends AbstractController
{
    /** @var PackagePopularityCalculator */
    private $packagePopularityCalculator;

    /**
     * @param PackagePopularityCalculator $packagePopularityCalculator
     */
    public function __construct(PackagePopularityCalculator $packagePopularityCalculator)
    {
        $this->packagePopularityCalculator = $packagePopularityCalculator;
    }

    /**
     * @Route(
     *     "/api/packages/{name}",
     *      methods={"GET"},
     *      requirements={"name"="^[^-/]{1}[^/\s]{0,190}$"},
     *      name="app_api_package"
     * )
     * @param string $name
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @return Response
     *
     * @OA\Tag(name="packages")
     * @OA\Response(
     *     description="Returns popularity of given package",
     *     response=200,
     *     @Model(type=PackagePopularity::class)
     * )
     * @OA\Parameter(
     *     in="path",
     *     name="name",
     *     description="Name of the package",
     *     @OA\Schema(
     *         type="string"
     *     )
     * )
     * @OA\Parameter(
     *     name="startMonth",
     *     required=false,
     *     in="query",
     *     description="Specify start month in the form of 'Ym', e.g. 201901. Defaults to last month.",
     *     @OA\Schema(
     *         type="integer"
     *     )
     * )
     * @OA\Parameter(
     *     name="endMonth",
     *     required=false,
     *     in="query",
     *     description="Specify end month in the format of 'Ym', e.g. 201901. Defaults to last month.",
     *     @OA\Schema(
     *         type="integer"
     *     )
     * )
     */
    public function packageAction(string $name, StatisticsRangeRequest $statisticsRangeRequest): Response
    {
        return $this->json(
            $this->packagePopularityCalculator->getPackagePopularity($name, $statisticsRangeRequest)
        );
    }

    /**
     * @Route(
     *     "/api/packages/{name}/series",
     *      methods={"GET"},
     *      requirements={"name"="^[^-/]{1}[^/\s]{0,190}$"},
     *      name="app_api_package_series"
     * )
     * @param string $name
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @param PaginationRequest $paginationRequest
     * @return Response
     *
     * @OA\Tag(name="packages")
     * @OA\Response(
     *     description="Returns popularities of given package in a monthly series",
     *     response=200,
     *     @Model(type=PackagePopularityList::class)
     * )
     * @OA\Parameter(
     *     in="path",
     *     name="name",
     *     description="Name of the package",
     *     @OA\Schema(
     *         type="string"
     *     )
     * )
     * @OA\Parameter(
     *     name="startMonth",
     *     required=false,
     *     in="query",
     *     description="Specify start month in the form of 'Ym', e.g. 201901. Defaults to last month.",
     *     @OA\Schema(
     *         type="integer"
     *     )
     * )
     * @OA\Parameter(
     *     name="endMonth",
     *     required=false,
     *     in="query",
     *     description="Specify end month in the format of 'Ym', e.g. 201901. Defaults to last month.",
     *     @OA\Schema(
     *         type="integer"
     *     )
     * )
     * @OA\Parameter(
     *     name="limit",
     *     required=false,
     *     in="query",
     *     description="Limit the result set",
     *     @OA\Schema(
     *         type="integer",
     *         default=100,
     *         minimum=1,
     *         maximum=10000
     *     )
     * )
     * @OA\Parameter(
     *     name="offset",
     *     required=false,
     *     in="query",
     *     description="Offset the result set",
     *     @OA\Schema(
     *         type="integer",
     *         default=0,
     *         minimum=0,
     *         maximum=100000
     *     )
     * )
     */
    public function packageSeriesAction(
        string $name,
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest
    ): Response {
        return $this->json(
            $this->packagePopularityCalculator->getPackagePopularitySeries(
                $name,
                $statisticsRangeRequest,
                $paginationRequest
            )
        );
    }

    /**
     * @Route(
     *     "/api/packages",
     *      methods={"GET"},
     *      name="app_api_packages"
     * )
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @param PaginationRequest $paginationRequest
     * @param PackageQueryRequest $packageQueryRequest
     * @return Response
     *
     * @OA\Tag(name="packages")
     * @OA\Response(
     *     description="Returns list of package popularities",
     *     response=200,
     *     @Model(type=PackagePopularityList::class)
     * )
     * @OA\Parameter(
     *     name="startMonth",
     *     required=false,
     *     in="query",
     *     description="Specify start month in the format of 'Ym', e.g. 201901. Defaults to last month.",
     *     @OA\Schema(
     *         type="integer",
     *         format="Ym"
     *     )
     * )
     * @OA\Parameter(
     *     name="endMonth",
     *     required=false,
     *     in="query",
     *     description="Specify end month in the format of 'Ym', e.g. 201901. Defaults to last month.",
     *     @OA\Schema(
     *         type="integer"
     *     )
     * )
     * @OA\Parameter(
     *     name="limit",
     *     required=false,
     *     in="query",
     *     description="Limit the result set",
     *     @OA\Schema(
     *         type="integer",
     *         default=100,
     *         minimum=1,
     *         maximum=10000
     *     )
     * )
     * @OA\Parameter(
     *     name="offset",
     *     required=false,
     *     in="query",
     *     description="Offset the result set",
     *     @OA\Schema(
     *         type="integer",
     *         default=0,
     *         minimum=0,
     *         maximum=100000
     *     )
     * )
     * @OA\Parameter(
     *     name="query",
     *     required=false,
     *     in="query",
     *     description="Search by package name",
     *     @OA\Schema(
     *         type="string",
     *         maxLength=191
     *     )
     * )
     */
    public function packageJsonAction(
        StatisticsRangeRequest $statisticsRangeRequest,
        PaginationRequest $paginationRequest,
        PackageQueryRequest $packageQueryRequest
    ): Response {
        return $this->json(
            $this->packagePopularityCalculator->findPackagesPopularity(
                $statisticsRangeRequest,
                $paginationRequest,
                $packageQueryRequest
            )
        );
    }
}
