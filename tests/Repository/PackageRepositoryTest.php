<?php

namespace App\Tests\Repository;

use App\Entity\Package;
use App\Repository\PackageRepository;
use SymfonyDatabaseTest\DatabaseTestCase;

class PackageRepositoryTest extends DatabaseTestCase
{
    /**
     * @dataProvider provideMonthRange
     * @param int $startMonth
     * @param int $endMonth
     */
    public function testGetCountByNameAndRange(int $startMonth, int $endMonth): void
    {
        $package = (new Package())->setName('a')->setMonth($startMonth)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($package);
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
    public function testGetCountByNameAndRangeOfUnknownPackage(int $startMonth, int $endMonth): void
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
    public function testFindPackagesCountByRange(int $startMonth, int $endMonth): void
    {
        $packageA = (new Package())->setName('a')->setMonth($startMonth)->incrementCount();
        $packageAA = (new Package())->setName('aa')->setMonth($endMonth);
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
    public function testGetMaximumCountByRange(int $startMonth, int $endMonth): void
    {
        $packageA = (new Package())->setName('a')->setMonth($startMonth - 1);
        $packageB = (new Package())->setName('a')->setMonth($startMonth);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($packageA);
        $entityManager->flush();

        if ($startMonth == $endMonth) {
            $packageB->incrementCount();
        } else {
            $packageC = (new Package())->setName('a')->setMonth($endMonth);
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

    /**
     * @dataProvider provideMonthRange
     * @param int $startMonth
     * @param int $endMonth
     */
    public function testGetMaximumCountByRangeIsInitiallyZero(int $startMonth, int $endMonth): void
    {
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $count = $packageRepository->getMaximumCountByRange($startMonth, $endMonth);

        $this->assertEquals(0, $count);
    }

    public function testFindMonthlyByNameAndRange(): void
    {
        $packageA = (new Package())->setName('a')->setMonth(201810);
        $packageB = (new Package())->setName('a')->setMonth(201811);
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
        $package = (new Package())->setName('a')->setMonth(201810)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($package);
        $entityManager->flush();
        $entityManager->clear();

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getRepository(Package::class);
        $monthlyCount = $packageRepository->getMonthlyMaximumCountByRange(201810, 201811);
        $this->assertEquals([['count' => 2, 'month' => 201810]], $monthlyCount);
    }

    /**
     * @return array<array>
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
