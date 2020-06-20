<?php

namespace App\Tests\Service;

use App\Repository\PackageRepository;
use App\Request\PackageQueryRequest;
use App\Request\PaginationRequest;
use App\Request\StatisticsRangeRequest;
use App\Service\PackagePopularityCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PackagePopularityCalculatorTest extends TestCase
{
    /** @var PackageRepository|MockObject */
    private $packageRepository;

    /** @var PackagePopularityCalculator */
    private $packagePopularityCalculator;

    public function setUp(): void
    {
        $this->packageRepository = $this->createMock(PackageRepository::class);
        $this->packagePopularityCalculator = new PackagePopularityCalculator($this->packageRepository);
    }

    public function testGetPackagePopularity(): void
    {
        $this
            ->packageRepository
            ->expects($this->once())
            ->method('getCountByNameAndRange')
            ->with('foo', 201801, 201812)
            ->willReturn(42);
        $this
            ->packageRepository
            ->expects($this->once())
            ->method('getMaximumCountByRange')
            ->with(201801, 201812)
            ->willReturn(12);

        $packagePopularity = $this->packagePopularityCalculator->getPackagePopularity(
            'foo',
            new StatisticsRangeRequest(201801, 201812)
        );

        $this->assertEquals('foo', $packagePopularity->getName());
        $this->assertEquals(42, $packagePopularity->getCount());
        $this->assertEquals(12, $packagePopularity->getSamples());
    }

    public function testFindPackagesPopularity(): void
    {
        $this
            ->packageRepository
            ->expects($this->once())
            ->method('findPackagesCountByRange')
            ->with('foo', 201801, 201812, 2, 12)
            ->willReturn([
                'packages' => [
                    [
                        'name' => 'foo',
                        'count' => 43
                    ]
                ],
                'total' => 13
            ]);

        $packagePopularityList = $this->packagePopularityCalculator->findPackagesPopularity(
            new StatisticsRangeRequest(201801, 201812),
            new PaginationRequest(2, 12),
            new PackageQueryRequest('foo')
        );

        $this->assertEquals(1, $packagePopularityList->getCount());
    }

    public function testGetPackagePopularitySeries(): void
    {
        $this
            ->packageRepository
            ->expects($this->once())
            ->method('getMonthlyMaximumCountByRange')
            ->with(201801, 201812)
            ->willReturn([
                [
                    'month' => 201801,
                    'count' => 1
                ]
            ]);

        $this
            ->packageRepository
            ->expects($this->once())
            ->method('findMonthlyByNameAndRange')
            ->with('foo', 201801, 201812, 2, 12)
            ->willReturn([
                'packages' => [
                    [
                        'name' => 'foo',
                        'count' => 43,
                        'month' => 201801
                    ]
                ],
                'total' => 13
            ]);

        $packagePopularitySeries = $this->packagePopularityCalculator->getPackagePopularitySeries(
            'foo',
            new StatisticsRangeRequest(201801, 201812),
            new PaginationRequest(2, 12)
        );

        $this->assertEquals(1, $packagePopularitySeries->getCount());
    }
}
