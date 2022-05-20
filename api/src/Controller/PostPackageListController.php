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
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PostPackageListController extends AbstractController
{
    /**
     * @param EntityManager $entityManager
     */
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route(
        path: '/api/submit',
        name: 'app_api_submit',
        methods: ['POST']
    )]
    #[OA\Tag(name: 'pkgstats')]
    #[OA\Post(
        description: 'POST endpoint for the pkgstats cli tool',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'version', type: 'string', example: '3'),
                    new OA\Property(
                        property: 'system',
                        properties: [
                            new OA\Property(
                                property: 'architecture',
                                description: 'Architecture of the CPU',
                                type: 'string',
                                example: 'x86_64'
                            )
                        ],
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'os',
                        properties: [
                            new OA\Property(
                                property: 'architecture',
                                description: 'Architecture of the distribution',
                                type: 'string',
                                example: 'x86_64'
                            )
                        ],
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'pacman',
                        properties: [
                            new OA\Property(
                                property: 'mirror',
                                description: 'Package mirror',
                                type: 'string',
                                example: 'https://mirror.pkgbuild.com/'
                            ),
                            new OA\Property(
                                property: 'packages',
                                description: 'List of package names',
                                type: 'array',
                                items: new OA\Items(type: 'string', maxLength: 191, minLength: 1),
                                maxItems: 10000,
                                minItems: 1,
                                example: ['pacman', 'linux', 'pkgstats']
                            )
                        ],
                        type: 'object'
                    )
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Submission was successful'),
            new OA\Response(response: 400, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Rate limit was reached'),
        ]
    )]
    public function submitAction(PkgstatsRequest $pkgstatsRequest): Response
    {
        $this->persistSubmission($pkgstatsRequest);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function persistSubmission(PkgstatsRequest $pkgstatsRequest): void
    {
        $this->entityManager->wrapInTransaction(
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
