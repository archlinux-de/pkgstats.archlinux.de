<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PackageController extends AbstractController
{
    /** @var PackageRepository */
    private $packageRepository;

    /**
     * @param PackageRepository $packageRepository
     */
    public function __construct(PackageRepository $packageRepository)
    {
        $this->packageRepository = $packageRepository;
    }

    /**
     * @Route("/packages", methods={"GET"}, name="app_packages")
     * @Cache(smaxage="+1 hour", maxage="+5 minutes")
     * @return Response
     */
    public function packagesAction(): Response
    {
        $lastMonth = $this->packageRepository->getLatestMonth() - 1;
        return $this->render(
            'packages.html.twig',
            [
                'startMonth' => $lastMonth,
                'endMonth' => $lastMonth,
                'limit' => 20
            ]
        );
    }

    /**
     * @Route(path="/packages/{package}", methods={"GET"}, name="app_package")
     * @Cache(smaxage="+1 hour", maxage="+5 minutes")
     * @param string $package
     * @return Response
     */
    public function packageAction(string $package): Response
    {
        $startMonth = $this->packageRepository->getFirstMonthByName($package);
        if (!$startMonth) {
            throw $this->createNotFoundException(sprintf('Package %s was not found', $package));
        }
        $endMonth = max($startMonth, $this->packageRepository->getLatestMonthByName($package) - 1);

        return $this->render(
            'package.html.twig',
            [
                'package' => $package,
                'startMonth' => $startMonth,
                'endMonth' => $endMonth,
                'limit' => 0
            ]
        );
    }

    /**
     * @Route(path="/compare/packages", methods={"GET"}, name="app_compare_packages")
     * @Cache(smaxage="+1 hour", maxage="+5 minutes")
     * @return Response
     */
    public function compareAction(): Response
    {
        $startMonth = $this->packageRepository->getFirstMonth();
        if (!$startMonth) {
            throw $this->createNotFoundException('No packages were found');
        }
        $endMonth = max($startMonth, $this->packageRepository->getLatestMonth() - 1);

        return $this->render(
            'compare.html.twig',
            [
                'startMonth' => $startMonth,
                'endMonth' => $endMonth,
                'limit' => 0
            ]
        );
    }
}
