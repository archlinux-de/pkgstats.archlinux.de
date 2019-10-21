<?php

namespace App\Tests\Repository;

use App\Entity\Package;
use App\Repository\PackageRepository;
use SymfonyDatabaseTest\DatabaseTestCase;

class PackageRepositoryTest extends DatabaseTestCase
{
    public function testInitialCount()
    {
        $package = (new Package())->setName('a')->setMonth(201812);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($package);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        /** @var Package $persistedPackage */
        $persistedPackage = $packageRepository->findOneBy(['name' => 'a', 'month' => 201812]);

        $this->assertEquals(1, $persistedPackage->getCount());
    }

    public function testCountIncreases()
    {
        $packageA = (new Package())->setName('a')->setMonth(201812);
        $packageB = (new Package())->setName('a')->setMonth(201812);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        /** @var Package $persistedPackage */
        $persistedPackage = $packageRepository->findOneBy(['name' => 'a', 'month' => 201812]);

        $this->assertEquals(2, $persistedPackage->getCount());
    }

    /**
     * @dataProvider provideMonthRange
     * @param int $startMonth
     * @param int $endMonth
     */
    public function testGetCountByNameAndRange(int $startMonth, int $endMonth)
    {
        $packageA = (new Package())->setName('a')->setMonth($startMonth);
        $packageB = (new Package())->setName('a')->setMonth($startMonth);
        $packageC = (new Package())->setName('a')->setMonth($endMonth + 1);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->merge($packageC);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getCountByNameAndRange('a', $startMonth, $endMonth);

        $this->assertEquals(2, $count);
    }

    /**
     * @dataProvider provideMonthRange
     * @param int $startMonth
     * @param int $endMonth
     */
    public function testGetCountByNameAndRangeOfUnknownPackage(int $startMonth, int $endMonth)
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getCountByNameAndRange('a', $startMonth, $endMonth);

        $this->assertEquals(0, $count);
    }

    /**
     * @dataProvider provideMonthRange
     * @param int $startMonth
     * @param int $endMonth
     */
    public function testFindPackagesCountByRange(int $startMonth, int $endMonth)
    {
        $packageA = (new Package())->setName('a')->setMonth($startMonth);
        $packageB = (new Package())->setName('a')->setMonth($startMonth);
        $packageC = (new Package())->setName('aa')->setMonth($endMonth);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->merge($packageC);
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
                        'count' => 1
                    ]
                ]
            ],
            $count
        );
    }

    /**
     * @dataProvider provideMonthRange
     * @param int $startMonth
     * @param int $endMonth
     */
    public function testGetMaximumCountByRange(int $startMonth, int $endMonth)
    {
        $packageA = (new Package())->setName('a')->setMonth($startMonth - 1);
        $packageB = (new Package())->setName('a')->setMonth($startMonth);
        $packageC = (new Package())->setName('a')->setMonth($endMonth);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->merge($packageC);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getMaximumCountByRange($startMonth, $endMonth);

        $this->assertEquals(2, $count);
    }

    /**
     * @dataProvider provideMonthRange
     * @param int $startMonth
     * @param int $endMonth
     */
    public function testGetMaximumCountByRangeIsInitiallyZero(int $startMonth, int $endMonth)
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getMaximumCountByRange($startMonth, $endMonth);

        $this->assertEquals(0, $count);
    }

    public function testFindMonthlyByNameAndRange()
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('a')->setMonth(201811);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
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

    public function testGetMonthlyMaximumCountByRange()
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('a')->setMonth(201810);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $monthlyCount = $packageRepository->getMonthlyMaximumCountByRange(201810, 201811);
        $this->assertEquals([['count' => 2, 'month' => 201810]], $monthlyCount);
    }

    /**
     * @return array
     */
    public function provideMonthRange(): array
    {
        return [
            [201810, 201811],
            [201810, 201810],
            [201811, 201811]
        ];
    }
}
