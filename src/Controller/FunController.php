<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FunController extends AbstractController
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
     * @Route("/fun", methods={"GET"}, name="app_fun")
     * @Cache(smaxage="+1 day", maxage="+5 minutes")
     * @return Response
     */
    public function funAction(): Response
    {
        $lastMonth = $this->packageRepository->getLatestMonth() - 1;

        return $this->render(
            'fun.html.twig',
            [
                'startMonth' => $lastMonth,
                'endMonth' => $lastMonth
            ]
        );
    }
}
