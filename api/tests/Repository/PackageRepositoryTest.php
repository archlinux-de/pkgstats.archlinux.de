<?php

namespace App\Tests\Repository;

use App\Entity\Package;
use App\Repository\PackageRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use SymfonyDatabaseTest\DatabaseTestCase;

class PackageRepositoryTest extends DatabaseTestCase
{
    #[DataProvider('provideMonthRange')]
    public function testGetCountByNameAndRange(int $startMonth, int $endMonth): void
    {
        $package = new Package()->setName('a')->setMonth($startMonth)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($package);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getCountByNameAndRange('a', $startMonth, $endMonth);

        $this->assertEquals(2, $count);
    }

    #[DataProvider('provideMonthRange')]
    public function testGetCountByNameAndRangeOfUnknownPackage(int $startMonth, int $endMonth): void
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getCountByNameAndRange('a', $startMonth, $endMonth);

        $this->assertEquals(0, $count);
    }

    #[DataProvider('provideMonthRange')]
    public function testFindPackagesCountByRange(int $startMonth, int $endMonth): void
    {
        $packageA = $this->createPopularPackage('a', $startMonth);
        $packageAA = $this->createPopularPackage('aa', $endMonth);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($packageA);
        $entityManager->flush();
        $entityManager->persist($packageAA);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->findPackagesCountByRange('a', $startMonth, $endMonth, 1, 1);

        $this->assertEquals(
            [
                'total' => 2,
                'packages' => [
                    [
                        'name' => 'aa',
                        'count' => PackageRepository::MIN_POPULARITY
                    ]
                ]
            ],
            $count
        );
    }

    #[DataProvider('provideMonthRange')]
    public function testGetMaximumCountByRange(int $startMonth, int $endMonth): void
    {
        $packageA = new Package()->setName('a')->setMonth($startMonth - 1);
        $packageB = new Package()->setName('a')->setMonth($startMonth);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($packageA);
        $entityManager->flush();

        if ($startMonth === $endMonth) {
            $packageB->incrementCount();
        } else {
            $packageC = new Package()->setName('a')->setMonth($endMonth);
            $entityManager->persist($packageC);
            $entityManager->flush();
        }

        $entityManager->persist($packageB);
        $entityManager->flush();

        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getMaximumCountByRange($startMonth, $endMonth);

        $this->assertEquals(2, $count);
    }

    #[DataProvider('provideMonthRange')]
    public function testGetMaximumCountByRangeIsInitiallyZero(int $startMonth, int $endMonth): void
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getMaximumCountByRange($startMonth, $endMonth);

        $this->assertEquals(0, $count);
    }

    public function testFindMonthlyByNameAndRange(): void
    {
        $packageA = new Package()->setName('a')->setMonth(201810);
        $packageB = new Package()->setName('a')->setMonth(201811);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($packageA);
        $entityManager->flush();
        $entityManager->persist($packageB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->findMonthlyByNameAndRange('a', 201810, 201812, 1, 1);

        $this->assertEquals(
            [
                'total' => 2,
                'packages' => [
                    [
                        'name' => 'a',
                        'count' => 1,
                        'month' => 201811
                    ]
                ]
            ],
            $count
        );
    }

    public function testGetMonthlyMaximumCountByRange(): void
    {
        $package = new Package()->setName('a')->setMonth(201810)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($package);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $monthlyCount = $packageRepository->getMonthlyMaximumCountByRange(201810, 201811);
        $this->assertEquals([['count' => 2, 'month' => 201810]], $monthlyCount);
    }

    public function testFindPackagesCountByRangeFiltersUnpopular(): void
    {
        $startMonth = 202501;
        $endMonth = 202501;

        $popularPackage = $this->createPopularPackage('popular', $startMonth);

        $unpopularPackage = new Package()->setName('unpopular')->setMonth($startMonth);
        for ($i = 1; $i < PackageRepository::MIN_POPULARITY - 1; $i++) {
            $unpopularPackage->incrementCount();
        }

        $entityManager = $this->getEntityManager();
        $entityManager->persist($popularPackage);
        $entityManager->persist($unpopularPackage);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $result = $packageRepository->findPackagesCountByRange('', $startMonth, $endMonth, 0, 10);

        $this->assertEquals(
            [
                'total' => 1,
                'packages' => [
                    [
                        'name' => 'popular',
                        'count' => PackageRepository::MIN_POPULARITY
                    ]
                ]
            ],
            $result
        );
    }

    private function createPopularPackage(string $name, int $month): Package
    {
        $package = new Package()
            ->setName($name)
            ->setMonth($month);
        for ($i = 1; $i < PackageRepository::MIN_POPULARITY; $i++) {
            $package->incrementCount();
        }

        return $package;
    }

    /**
     * @return list<int[]>
     */
    public static function provideMonthRange(): array
    {
        return [
            [201810, 201811],
            [201810, 201810],
            [201811, 201811]
        ];
    }
}
