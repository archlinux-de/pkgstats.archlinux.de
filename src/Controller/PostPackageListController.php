<?php

namespace App\Controller;

use App\Entity\Package;
use App\Request\PkgstatsRequest;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Swagger\Annotations as SWG;
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
     * @Route(
     *     "/post",
     *      methods={"POST"},
     *      defaults={"_format": "text"},
     *      requirements={"_format": "text"},
     *      name="app_pkgstats_post"
     * )
     * @param PkgstatsRequest $pkgstatsRequest
     * @return Response
     *
     * @SWG\Tag(name="pkgstats")
     * @SWG\Post(
     *     description="POST endpoint for the pkgstats cli tool",
     *     produces={"text/plain"},
     *     @SWG\Response(
     *         response=200,
     *         description="Displays a success message"
     *     ),
     *     @SWG\Response(
     *         response=400,
     *         description="Validation failed"
     *     ),
     *     @SWG\Response(
     *         response=403,
     *         description="Rate limit was reached"
     *     ),
     *     @SWG\Parameter(
     *         in="header",
     *         name="User-Agent",
     *         description="",
     *         type="string",
     *         required=true,
     *         enum={"pkgstats/2.3", "pkgstats/2.4"}
     *     ),
     *     @SWG\Parameter(
     *         in="formData",
     *         name="arch",
     *         description="Architecture of the distribution",
     *         type="string",
     *         required=true,
     *         enum={"x86_64"}
     *     ),
     *     @SWG\Parameter(
     *         in="formData",
     *         name="cpuarch",
     *         description="Architecture of the CPU",
     *         type="string",
     *         enum={"x86_64"}
     *     ),
     *     @SWG\Parameter(
     *         in="formData",
     *         name="mirror",
     *         description="Package mirror",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         in="formData",
     *         name="quiet",
     *         description="Suppress the response",
     *         type="boolean",
     *         default="false"
     *     ),
     *     @SWG\Parameter(
     *         in="formData",
     *         name="packages",
     *         description="List of package names",
     *         type="array",
     *         required=true,
     *         items={"type"="string", "minLength"=1, "maxLength"=255, "minimum"=1, "maximum"=10000}
     *     )
     * )
     */
    public function postAction(PkgstatsRequest $pkgstatsRequest): Response
    {
        $user = $pkgstatsRequest->getUser();
        $packages = $pkgstatsRequest->getPackages();

        $this->entityManager->transactional(
            function (EntityManager $entityManager) use ($user, $packages) {
                $entityManager->persist($user);

                foreach ($packages as $package) {
                    $persistedPackage = $entityManager->find(
                        Package::class,
                        ['name' => $package->getName(), 'month' => $package->getMonth()],
                        LockMode::PESSIMISTIC_WRITE
                    );
                    if ($persistedPackage) {
                        $persistedPackage->incrementCount();
                        $package = $persistedPackage;
                    }
                    $entityManager->persist($package);
                }
            }
        );

        return $this->render('post.text.twig', ['quiet' => $pkgstatsRequest->isQuiet()]);
    }
}
