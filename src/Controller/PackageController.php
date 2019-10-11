<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PackageController extends AbstractController
{
    /**
     * @Route("/packages", methods={"GET"}, name="app_packages")
     * @Cache(smaxage="+1 hour", maxage="+5 minutes")
     * @return Response
     */
    public function packagesAction(): Response
    {
        return $this->render('packages.html.twig');
    }

    /**
     * @Route(path="/packages/{package}", methods={"GET"}, name="app_package")
     * @Cache(smaxage="+1 hour", maxage="+5 minutes")
     * @param string $package
     * @return Response
     */
    public function packageAction(string $package): Response
    {
        return $this->render('package.html.twig', ['package' => $package]);
    }

    /**
     * @Route(path="/compare/packages", methods={"GET"}, name="app_compare_packages")
     * @Cache(smaxage="+1 hour", maxage="+5 minutes")
     * @return Response
     */
    public function compareAction(): Response
    {
        return $this->render('compare.html.twig');
    }
}
