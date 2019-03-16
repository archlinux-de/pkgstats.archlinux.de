<?php

namespace App\Controller;

use App\Request\PkgstatsRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

        $this->entityManager->transactional(
            function (EntityManagerInterface $entityManager) use ($user, $packages) {
                $entityManager->persist($user);

                foreach ($packages as $package) {
                    $entityManager->merge($package);
                }
            }
        );

        return $this->render('post.text.twig', ['quiet' => $pkgstatsRequest->isQuiet()]);
    }
}
