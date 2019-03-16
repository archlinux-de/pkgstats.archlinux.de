<?php

namespace App\Controller;

use App\Request\PackageQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\PackagePopularityCalculator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiPackageStatisticsController extends AbstractController
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
     * @Route("/api/packages/{name}", methods={"GET"}, requirements={"name"="^([^-]+\S*){1,255}$"})
     * @Cache(smaxage="86400")
     * @param string $name
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @return Response
     */
    public function packageAction(string $name, StatisticsRangeRequest $statisticsRangeRequest): Response
    {
        return $this->json(
            $this->packagePopularityCalculator->getPackagePopularity($name, $statisticsRangeRequest)
        );
    }

    /**
     * @Route("/api/packages", methods={"GET"})
     * @Cache(smaxage="86400")
     * @param StatisticsRangeRequest $statisticsRangeRequest
     * @param PaginationRequest $paginationRequest
     * @param PackageQueryRequest $packageQueryRequest
     * @return Response
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
