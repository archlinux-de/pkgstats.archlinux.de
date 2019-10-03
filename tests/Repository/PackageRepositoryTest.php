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

    public function testGetCountByNameAndRange()
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('a')->setMonth(201811);
        $packageC = (new Package())->setName('a')->setMonth(201812);
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
        $count = $packageRepository->getCountByNameAndRange('a', 201811, 201812);

        $this->assertEquals(2, $count);
    }

    public function testGetCountByNameAndRangeOfUnknownPackage()
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getCountByNameAndRange('a', 201811, 201812);

        $this->assertEquals(0, $count);
    }

    public function testFindPackagesCountByRange()
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('a')->setMonth(201811);
        $packageC = (new Package())->setName('aa')->setMonth(201812);
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
        $count = $packageRepository->findPackagesCountByRange('a', 201810, 201812, 1, 1);

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

    public function testGetMaximumCountByRange()
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('a')->setMonth(201811);
        $packageC = (new Package())->setName('a')->setMonth(201812);
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
        $count = $packageRepository->getMaximumCountByRange(201811, 201812);

        $this->assertEquals(2, $count);
    }

    public function testGetMaximumCountByRangeIsInitiallyZero()
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getMaximumCountByRange(201811, 201812);

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

    public function testGetFirstMonthByName()
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
        $this->assertEquals(201810, $packageRepository->getFirstMonthByName('a'));
    }

    public function testGetLatestMonthByName()
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
        $this->assertEquals(201811, $packageRepository->getLatestMonthByName('a'));
    }

    public function testGetFirstMonth()
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('b')->setMonth(201811);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $this->assertEquals(201810, $packageRepository->getFirstMonth());
    }

    public function testGetLatestMonth()
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('b')->setMonth(201811);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $this->assertEquals(201811, $packageRepository->getLatestMonth());
    }
}
