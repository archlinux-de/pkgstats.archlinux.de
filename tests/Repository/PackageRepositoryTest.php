<?php

namespace App\Tests\Repository;

use App\Entity\Package;
use App\Repository\PackageRepository;
use App\Tests\Util\DatabaseTestCase;

class PackageRepositoryTest extends DatabaseTestCase
{
    public function testGetCountByNameSince()
    {
        $packageA = (new Package())->setPkgname('a')->setMonth(201810);
        $packageB = (new Package())->setPkgname('a')->setMonth(201811);
        $packageC = (new Package())->setPkgname('a')->setMonth(201812);
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
        $count = $packageRepository->getCountByNameSince('a', 201811);

        $this->assertEquals(2, $count);
    }

    public function testGetCountOfUnknownPackage()
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getCountByNameSince('a', 201811);

        $this->assertEquals(0, $count);
    }

    public function testInitialCount()
    {
        $package = (new Package())->setPkgname('a')->setMonth(201812);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($package);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        /** @var Package $persistedPackage */
        $persistedPackage = $packageRepository->findOneBy(['pkgname' => 'a', 'month' => 201812]);

        $this->assertEquals(1, $persistedPackage->getCount());
    }

    public function testCountIncreases()
    {
        $packageA = (new Package())->setPkgname('a')->setMonth(201812);
        $packageB = (new Package())->setPkgname('a')->setMonth(201812);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($packageA);
        $entityManager->flush();
        $entityManager->merge($packageB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        /** @var Package $persistedPackage */
        $persistedPackage = $packageRepository->findOneBy(['pkgname' => 'a', 'month' => 201812]);

        $this->assertEquals(2, $persistedPackage->getCount());
    }

    public function testGetCountByNameAndRange()
    {
        $packageA = (new Package())->setPkgname('a')->setMonth(201810);
        $packageB = (new Package())->setPkgname('a')->setMonth(201811);
        $packageC = (new Package())->setPkgname('a')->setMonth(201812);
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
        $packageA = (new Package())->setPkgname('a')->setMonth(201810);
        $packageB = (new Package())->setPkgname('a')->setMonth(201811);
        $packageC = (new Package())->setPkgname('aa')->setMonth(201812);
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
}
