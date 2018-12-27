<?php

namespace App\Controller;

use App\Request\PkgstatsRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PostPackageListController extends AbstractController
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/post", methods={"POST"}, defaults={"_format": "text"}, requirements={"_format": "text"})
     * @param PkgstatsRequest $pkgstatsRequest
     * @return Response
     */
    public function postAction(PkgstatsRequest $pkgstatsRequest): Response
    {
        $user = $pkgstatsRequest->getUser();
        $packages = $pkgstatsRequest->getPackages();
        $modules = $pkgstatsRequest->getModules();

        $this->entityManager->transactional(
            function (EntityManagerInterface $entityManager) use ($user, $packages, $modules) {
                $entityManager->persist($user);

                foreach ($packages as $package) {
                    $entityManager->merge($package);
                }

                foreach ($modules as $module) {
                    $entityManager->merge($module);
                }
            }
        );

        if (!$pkgstatsRequest->isQuiet()) {
            $body = 'Thanks for your submission. :-)' . "\n" . 'See results at '
                . $this->generateUrl('app_start_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . "\n";
        } else {
            $body = '';
        }

        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
