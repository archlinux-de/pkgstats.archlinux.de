<?php

namespace App\Tests\Service;

use App\Repository\PackageRepository;
use App\Repository\UserRepository;
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

    /** @var UserRepository|MockObject */
    private $userRepository;

    /** @var PackagePopularityCalculator */
    private $packagePopularityCalculator;

    public function setUp(): void
    {
        $this->packageRepository = $this->createMock(PackageRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->packagePopularityCalculator = new PackagePopularityCalculator(
            $this->packageRepository,
            $this->userRepository
        );
    }

    public function testGetPackagePopularity()
    {
        $this
            ->packageRepository
            ->expects($this->once())
            ->method('getCountByNameAndRange')
            ->with('foo', 201801, 201812)
            ->willReturn(42);
        $this
            ->userRepository
            ->expects($this->once())
            ->method('getCountByRange')
            ->with(1514764800, 1546300799)
            ->willReturn(12);

        $packagePopularity = $this->packagePopularityCalculator->getPackagePopularity(
            'foo',
            new StatisticsRangeRequest(201801, 201812)
        );

        $this->assertEquals('foo', $packagePopularity->getName());
        $this->assertEquals(42, $packagePopularity->getCount());
        $this->assertEquals(12, $packagePopularity->getSamples());
    }

    public function testFindPackagesPopularity()
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
}
