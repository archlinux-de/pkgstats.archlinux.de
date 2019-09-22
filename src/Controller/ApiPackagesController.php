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
use Swagger\Annotations as SWG;

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
     *      requirements={"name"="^[^-/]{1}[^/\s]{1,255}$"},
     *      name="app_api_package"
     * )
     * @Cache(smaxage="86400")
     * @param string $name
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @return Response
     *
     * @SWG\Tag(name="packages")
     * @SWG\Response(
     *     description="Returns popularity of given package",
     *     response=200,
     *     @Model(type=PackagePopularity::class)
     * )
     * @SWG\Parameter(
     *     in="path",
     *     name="name",
     *     description="Name of the package",
     *     type="string"
     * )
     * @SWG\Parameter(
     *     name="startMonth",
     *     required=false,
     *     in="query",
     *     description="Specify start month in the form of 'Ym', e.g. 201901. Defaults to a range of 3 months.",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="endMonth",
     *     required=false,
     *     in="query",
     *     description="Specify end month in the format of 'Ym', e.g. 201901. Defaults to current month.",
     *     type="integer"
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
     *      requirements={"name"="^[^-/]{1}[^/\s]{1,255}$"},
     *      name="app_api_package_series"
     * )
     * @Cache(smaxage="86400")
     * @param string $name
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @param PaginationRequest $paginationRequest
     * @return Response
     *
     * @SWG\Tag(name="packages")
     * @SWG\Response(
     *     description="Returns popularities of given package in a monthly series",
     *     response=200,
     *     @Model(type=PackagePopularityList::class)
     * )
     * @SWG\Parameter(
     *     in="path",
     *     name="name",
     *     description="Name of the package",
     *     type="string"
     * )
     * @SWG\Parameter(
     *     name="startMonth",
     *     required=false,
     *     in="query",
     *     description="Specify start month in the form of 'Ym', e.g. 201901. Defaults to a range of 3 months.",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="endMonth",
     *     required=false,
     *     in="query",
     *     description="Specify end month in the format of 'Ym', e.g. 201901. Defaults to current month.",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="limit",
     *     required=false,
     *     default=100,
     *     minimum=1,
     *     maximum=10000,
     *     in="query",
     *     description="Limit the result set",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="offset",
     *     required=false,
     *     default=0,
     *     minimum=0,
     *     maximum=100000,
     *     in="query",
     *     description="Offset the result set",
     *     type="integer"
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
     * @Cache(smaxage="86400")
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @param PaginationRequest $paginationRequest
     * @param PackageQueryRequest $packageQueryRequest
     * @return Response
     *
     * @SWG\Tag(name="packages")
     * @SWG\Response(
     *     description="Returns list of package popularities",
     *     response=200,
     *     @Model(type=PackagePopularityList::class)
     * )
     * @SWG\Parameter(
     *     name="startMonth",
     *     required=false,
     *     in="query",
     *     description="Specify start month in the format of 'Ym', e.g. 201901. Defaults to a range of 3 months.",
     *     format="Ym",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="endMonth",
     *     required=false,
     *     in="query",
     *     description="Specify end month in the format of 'Ym', e.g. 201901. Defaults to current month.",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="limit",
     *     required=false,
     *     default=100,
     *     minimum=1,
     *     maximum=10000,
     *     in="query",
     *     description="Limit the result set",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="offset",
     *     required=false,
     *     default=0,
     *     minimum=0,
     *     maximum=100000,
     *     in="query",
     *     description="Offset the result set",
     *     type="integer"
     * )
     * @SWG\Parameter(
     *     name="query",
     *     required=false,
     *     maxLength=255,
     *     in="query",
     *     description="Search by package name",
     *     type="string"
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
