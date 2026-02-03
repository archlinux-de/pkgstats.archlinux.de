<?php

namespace App\Tests\Service;

use App\DTO\Anomaly\AnomalyThresholds;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Service\AnomalyDetectionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use SymfonyDatabaseTest\DatabaseTestCase;

#[Group('mariadb')]
class AnomalyDetectionServiceTest extends DatabaseTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = self::getEntityManager()->getConnection();
    }

    public function testDetectMirrorGrowthAnomalies(): void
    {
        $baselineStart = 202401;
        $baselineEnd = 202406;
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        for ($month = $baselineStart; $month <= $baselineEnd; $month++) {
            $mirror = $this->createMirrorWithCount('https://mirror.example.com/', $month, 100);
            $entityManager->persist($mirror);
        }

        $mirror = $this->createMirrorWithCount('https://mirror.example.com/', $targetMonth, 500);
        $entityManager->persist($mirror);

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 100,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds);
        $result = $service->detect($targetMonth, $baselineStart, $baselineEnd);

        $this->assertCount(1, $result->mirrorAnomalies);
        $this->assertEquals('https://mirror.example.com/', $result->mirrorAnomalies[0]->identifier);
        $this->assertEquals(500, $result->mirrorAnomalies[0]->count);
        $this->assertEquals(100.0, $result->mirrorAnomalies[0]->baselineAvg);
        $this->assertEquals(400.0, $result->mirrorAnomalies[0]->growthPercent);
    }

    public function testDetectNewMirrorSpikes(): void
    {
        $baselineStart = 202401;
        $baselineEnd = 202406;
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        $newMirror = $this->createMirrorWithCount('https://new-mirror.example.com/', $targetMonth, 5000);
        $entityManager->persist($newMirror);

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 1000,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds);
        $result = $service->detect($targetMonth, $baselineStart, $baselineEnd);

        $this->assertCount(1, $result->newMirrorSpikes);
        $this->assertEquals('https://new-mirror.example.com/', $result->newMirrorSpikes[0]->identifier);
        $this->assertEquals(5000, $result->newMirrorSpikes[0]->count);
    }

    public function testDetectCountCorrelations(): void
    {
        $baselineEnd = 202406;
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        $packages = ['suspicious-pkg-a', 'suspicious-pkg-b', 'suspicious-pkg-c'];
        foreach ($packages as $name) {
            $prev = $this->createPackageWithCount($name, $baselineEnd, 100);
            $entityManager->persist($prev);
            $curr = $this->createPackageWithCount($name, $targetMonth, 2100);
            $entityManager->persist($curr);
        }

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 1000,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds);
        $result = $service->detect($targetMonth, 202401, $baselineEnd);

        $this->assertCount(1, $result->countCorrelations);
        $this->assertEquals(2000, $result->countCorrelations[0]->delta);
        $this->assertEquals(3, $result->countCorrelations[0]->packageCount);
    }

    public function testDetectBasePackageAnomalies(): void
    {
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        $pkgstats = $this->createPackageWithCount('pkgstats', $targetMonth, 1000);
        $entityManager->persist($pkgstats);

        $pacman = $this->createPackageWithCount('pacman', $targetMonth, 1000);
        $entityManager->persist($pacman);

        $suspicious = $this->createPackageWithCount('suspicious-package', $targetMonth, 2000);
        $entityManager->persist($suspicious);

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 1000,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds, ['pkgstats', 'pacman']);
        $result = $service->detect($targetMonth, 202401, 202406);

        $this->assertEquals(1000, $result->basePackageResult->median);
        $this->assertCount(0, $result->basePackageResult->outliers);
        $this->assertCount(1, $result->basePackageResult->packagesAboveThreshold);
        $this->assertEquals('suspicious-package', $result->basePackageResult->packagesAboveThreshold[0]->name);
        $this->assertEquals(2000, $result->basePackageResult->packagesAboveThreshold[0]->count);
    }

    public function testDetectBasePackageOutliers(): void
    {
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        $pkgstats = $this->createPackageWithCount('pkgstats', $targetMonth, 4000);
        $entityManager->persist($pkgstats);

        $pacman = $this->createPackageWithCount('pacman', $targetMonth, 1000);
        $entityManager->persist($pacman);

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 1000,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds, ['pkgstats', 'pacman']);
        $result = $service->detect($targetMonth, 202401, 202406);

        $this->assertEquals(2500, $result->basePackageResult->median);
        $this->assertCount(1, $result->basePackageResult->outliers);
        $this->assertEquals('pkgstats', $result->basePackageResult->outliers[0]->name);
    }

    public function testDetectArchitectureAnomalies(): void
    {
        $baselineStart = 202401;
        $baselineEnd = 202406;
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        for ($month = $baselineStart; $month <= $baselineEnd; $month++) {
            $arch = $this->createSystemArchitectureWithCount('x86_64', $month, 100);
            $entityManager->persist($arch);
        }

        $arch = $this->createSystemArchitectureWithCount('x86_64', $targetMonth, 500);
        $entityManager->persist($arch);

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 100,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds);
        $result = $service->detect($targetMonth, $baselineStart, $baselineEnd);

        $this->assertCount(1, $result->systemArchAnomalies);
        $this->assertEquals('x86_64', $result->systemArchAnomalies[0]->identifier);
        $this->assertEquals(400.0, $result->systemArchAnomalies[0]->growthPercent);
    }

    public function testDetectOsArchitectureAnomalies(): void
    {
        $baselineStart = 202401;
        $baselineEnd = 202406;
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        for ($month = $baselineStart; $month <= $baselineEnd; $month++) {
            $arch = $this->createOsArchitectureWithCount('x86_64', $month, 100);
            $entityManager->persist($arch);
        }

        $arch = $this->createOsArchitectureWithCount('x86_64', $targetMonth, 500);
        $entityManager->persist($arch);

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 100,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds);
        $result = $service->detect($targetMonth, $baselineStart, $baselineEnd);

        $this->assertCount(1, $result->osArchAnomalies);
        $this->assertEquals('x86_64', $result->osArchAnomalies[0]->identifier);
        $this->assertEquals(400.0, $result->osArchAnomalies[0]->growthPercent);
    }

    public function testNoFalsePositivesWithNormalData(): void
    {
        $baselineStart = 202401;
        $baselineEnd = 202406;
        $targetMonth = 202407;

        $entityManager = $this->getEntityManager();

        for ($month = $baselineStart; $month <= $baselineEnd; $month++) {
            $mirror = $this->createMirrorWithCount('https://mirror.example.com/', $month, 100);
            $entityManager->persist($mirror);

            $arch = $this->createSystemArchitectureWithCount('x86_64', $month, 1000);
            $entityManager->persist($arch);

            $pkgstatsBaseline = $this->createPackageWithCount('pkgstats', $month, 1000);
            $entityManager->persist($pkgstatsBaseline);

            $pacmanBaseline = $this->createPackageWithCount('pacman', $month, 1000);
            $entityManager->persist($pacmanBaseline);
        }

        $mirror = $this->createMirrorWithCount('https://mirror.example.com/', $targetMonth, 120);
        $entityManager->persist($mirror);

        $arch = $this->createSystemArchitectureWithCount('x86_64', $targetMonth, 1100);
        $entityManager->persist($arch);

        $pkgstats = $this->createPackageWithCount('pkgstats', $targetMonth, 1000);
        $entityManager->persist($pkgstats);

        $pacman = $this->createPackageWithCount('pacman', $targetMonth, 1000);
        $entityManager->persist($pacman);

        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds(
            lookbackMonths: 6,
            minBaselineCount: 50,
            minCorrelationCount: 1000,
            growthThreshold: 300.0,
            extremeGrowthThreshold: 1000.0,
            basePackageDeviationThreshold: 1.5
        );

        $service = new AnomalyDetectionService($this->connection, $thresholds, ['pkgstats', 'pacman']);
        $result = $service->detect($targetMonth, $baselineStart, $baselineEnd);

        $this->assertCount(0, $result->mirrorAnomalies);
        $this->assertCount(0, $result->newMirrorSpikes);
        $this->assertCount(0, $result->systemArchAnomalies);
        $this->assertCount(0, $result->countCorrelations);
        $this->assertCount(0, $result->newPackageSpikes);
        $this->assertCount(0, $result->basePackageResult->outliers);
        $this->assertCount(0, $result->basePackageResult->packagesAboveThreshold);
    }

    public function testGetThresholds(): void
    {
        $thresholds = new AnomalyThresholds(lookbackMonths: 12);
        $service = new AnomalyDetectionService($this->connection, $thresholds);

        $this->assertEquals(12, $service->getThresholds()->lookbackMonths);
    }

    public function testEmptyExpectedPackagesReturnsEmptyBasePackageResult(): void
    {
        $entityManager = $this->getEntityManager();

        $package = $this->createPackageWithCount('some-package', 202407, 5000);
        $entityManager->persist($package);
        $entityManager->flush();
        $entityManager->clear();

        $thresholds = new AnomalyThresholds();
        $service = new AnomalyDetectionService($this->connection, $thresholds, []);
        $result = $service->detect(202407, 202401, 202406);

        $this->assertEquals(0, $result->basePackageResult->median);
        $this->assertCount(0, $result->basePackageResult->outliers);
        $this->assertCount(0, $result->basePackageResult->packagesAboveThreshold);
    }

    private function createMirrorWithCount(string $url, int $month, int $count): Mirror
    {
        $mirror = new Mirror($url);
        $mirror->setMonth($month);
        for ($i = 1; $i < $count; $i++) {
            $mirror->incrementCount();
        }

        return $mirror;
    }

    private function createPackageWithCount(string $name, int $month, int $count): Package
    {
        $package = new Package();
        $package->setName($name)->setMonth($month);
        for ($i = 1; $i < $count; $i++) {
            $package->incrementCount();
        }

        return $package;
    }

    private function createSystemArchitectureWithCount(string $name, int $month, int $count): SystemArchitecture
    {
        $arch = new SystemArchitecture($name);
        $arch->setMonth($month);
        for ($i = 1; $i < $count; $i++) {
            $arch->incrementCount();
        }

        return $arch;
    }

    private function createOsArchitectureWithCount(string $name, int $month, int $count): OperatingSystemArchitecture
    {
        $arch = new OperatingSystemArchitecture($name);
        $arch->setMonth($month);
        for ($i = 1; $i < $count; $i++) {
            $arch->incrementCount();
        }

        return $arch;
    }
}
