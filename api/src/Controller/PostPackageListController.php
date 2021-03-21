<?php

namespace App\Controller;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Request\PkgstatsRequest;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
     * @param PkgstatsRequest $pkgstatsRequest
     * @param Request $request
     * @return Response
     * @deprecated
     * @Route(
     *     "/post",
     *      methods={"POST"},
     *      defaults={"_format": "text"},
     *      requirements={"_format": "text"},
     *      name="app_pkgstats_post"
     * )
     */
    public function postAction(PkgstatsRequest $pkgstatsRequest, Request $request): Response
    {
        $this->persistSubmission($pkgstatsRequest);

        return $this->render('post.text.twig', ['quiet' => $request->get('quiet') === 'true']);
    }

    /**
     * @Route(
     *     "/api/submit",
     *      methods={"POST"},
     *      condition="request.headers.get('Content-Type') === 'application/json'",
     *      name="app_api_submit"
     * )
     *
     * @OA\Tag(name="pkgstats")
     * @OA\Post(
     *     description="POST endpoint for the pkgstats cli tool",
     *     @OA\Response(
     *         response=204,
     *         description="Submission was successful"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed"
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit was reached"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="version",
     *                 type="string",
     *                 example="3"
     *             ),
     *             @OA\Property (
     *                 type="object",
     *                 property="system",
     *                 @OA\Property(
     *                     property="architecture",
     *                     type="string",
     *                     description="Architecture of the CPU",
     *                     example="x86_64"
     *                 ),
     *             ),
     *             @OA\Property (
     *                 type="object",
     *                 property="os",
     *                 @OA\Property(
     *                     property="architecture",
     *                     type="string",
     *                     description="Architecture of the distribution",
     *                     example="x86_64"
     *                 ),
     *             ),
     *             @OA\Property (
     *                 type="object",
     *                 property="pacman",
     *                 @OA\Property(
     *                     property="mirror",
     *                     type="string",
     *                     description="Package mirror",
     *                     example="https://mirror.pkgbuild.com/"
     *                 ),
     *                 @OA\Property(
     *                     property="packages",
     *                     type="array",
     *                     items={"type"="string", "minLength"=1, "maxLength"=191, "minimum"=1, "maximum"=10000},
     *                     description="List of package names",
     *                     example={"pacman", "linux", "pkgstats"}
     *                 )
     *             )
     *         )
     *     )
     * )
     *
     * @param PkgstatsRequest $pkgstatsRequest
     * @return Response
     */
    public function submitAction(PkgstatsRequest $pkgstatsRequest): Response
    {
        $this->persistSubmission($pkgstatsRequest);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param PkgstatsRequest $pkgstatsRequest
     */
    private function persistSubmission(PkgstatsRequest $pkgstatsRequest): void
    {
        $this->entityManager->transactional(
            function (EntityManager $entityManager) use ($pkgstatsRequest) {
                $this->persistPackages($entityManager, $pkgstatsRequest->getPackages());
                $this->persistCountry($entityManager, $pkgstatsRequest->getCountry());
                $this->persistMirror($entityManager, $pkgstatsRequest->getMirror());
                $this->persistOperatingSystemArchitecture(
                    $entityManager,
                    $pkgstatsRequest->getOperatingSystemArchitecture()
                );
                $this->persistSystemArchitecture($entityManager, $pkgstatsRequest->getSystemArchitecture());
            }
        );
    }

    /**
     * @param EntityManager $entityManager
     * @param Package[] $packages
     */
    private function persistPackages(EntityManager $entityManager, array $packages): void
    {
        foreach ($packages as $package) {
            /** @var Package|null $persistedPackage */
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

    /**
     * @param EntityManager $entityManager
     * @param Country|null $country
     */
    private function persistCountry(EntityManager $entityManager, ?Country $country): void
    {
        if (!$country) {
            return;
        }

        /** @var Country|null $persistedCountry */
        $persistedCountry = $entityManager->find(
            Country::class,
            ['code' => $country->getCode(), 'month' => $country->getMonth()],
            LockMode::PESSIMISTIC_WRITE
        );
        if ($persistedCountry) {
            $persistedCountry->incrementCount();
            $country = $persistedCountry;
        }
        $entityManager->persist($country);
    }

    /**
     * @param EntityManager $entityManager
     * @param Mirror|null $mirror
     */
    private function persistMirror(EntityManager $entityManager, ?Mirror $mirror): void
    {
        if (!$mirror) {
            return;
        }

        /** @var Mirror|null $persistedMirror */
        $persistedMirror = $entityManager->find(
            Mirror::class,
            ['url' => $mirror->getUrl(), 'month' => $mirror->getMonth()],
            LockMode::PESSIMISTIC_WRITE
        );
        if ($persistedMirror) {
            $persistedMirror->incrementCount();
            $mirror = $persistedMirror;
        }
        $entityManager->persist($mirror);
    }

    /**
     * @param EntityManager $entityManager
     * @param OperatingSystemArchitecture $operatingSystemArchitecture
     */
    private function persistOperatingSystemArchitecture(
        EntityManager $entityManager,
        OperatingSystemArchitecture $operatingSystemArchitecture
    ): void {
        /** @var OperatingSystemArchitecture|null $persistedOperatingSystemArchitecture */
        $persistedOperatingSystemArchitecture = $entityManager->find(
            OperatingSystemArchitecture::class,
            ['name' => $operatingSystemArchitecture->getName(), 'month' => $operatingSystemArchitecture->getMonth()],
            LockMode::PESSIMISTIC_WRITE
        );
        if ($persistedOperatingSystemArchitecture) {
            $persistedOperatingSystemArchitecture->incrementCount();
            $operatingSystemArchitecture = $persistedOperatingSystemArchitecture;
        }
        $entityManager->persist($operatingSystemArchitecture);
    }

    /**
     * @param EntityManager $entityManager
     * @param SystemArchitecture $systemArchitecture
     */
    private function persistSystemArchitecture(
        EntityManager $entityManager,
        SystemArchitecture $systemArchitecture
    ): void {
        /** @var SystemArchitecture|null $persistedSystemArchitecture */
        $persistedSystemArchitecture = $entityManager->find(
            SystemArchitecture::class,
            ['name' => $systemArchitecture->getName(), 'month' => $systemArchitecture->getMonth()],
            LockMode::PESSIMISTIC_WRITE
        );
        if ($persistedSystemArchitecture) {
            $persistedSystemArchitecture->incrementCount();
            $systemArchitecture = $persistedSystemArchitecture;
        }
        $entityManager->persist($systemArchitecture);
    }
}
