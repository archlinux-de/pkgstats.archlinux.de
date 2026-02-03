<?php

namespace App\Service;

use App\DTO\Anomaly\AnomalyThresholds;
use App\DTO\Anomaly\BasePackageResult;
use App\DTO\Anomaly\CountCorrelation;
use App\DTO\Anomaly\DetectionResult;
use App\DTO\Anomaly\GrowthAnomaly;
use App\DTO\Anomaly\PackageRatio;
use App\DTO\Anomaly\Spike;
use Doctrine\DBAL\Connection;

readonly class AnomalyDetectionService
{
    /** @param list<string> $expectedPackages */
    public function __construct(
        private Connection $connection,
        private AnomalyThresholds $thresholds = new AnomalyThresholds(),
        private array $expectedPackages = []
    ) {
    }

    public function getThresholds(): AnomalyThresholds
    {
        return $this->thresholds;
    }

    public function detect(int $targetMonth, int $baselineStart, int $baselineEnd): DetectionResult
    {
        return new DetectionResult(
            countCorrelations: $this->detectCountCorrelations($targetMonth, $baselineEnd),
            newPackageSpikes: $this->detectNewSpikes('package', 'name', $targetMonth, $baselineStart),
            mirrorAnomalies: $this->detectGrowthAnomalies('mirror', 'url', $targetMonth, $baselineStart, $baselineEnd),
            newMirrorSpikes: $this->detectNewSpikes('mirror', 'url', $targetMonth, $baselineStart),
            systemArchAnomalies: $this->detectGrowthAnomalies(
                'system_architecture',
                'name',
                $targetMonth,
                $baselineStart,
                $baselineEnd
            ),
            osArchAnomalies: $this->detectGrowthAnomalies(
                'operating_system_architecture',
                'name',
                $targetMonth,
                $baselineStart,
                $baselineEnd
            ),
            basePackageResult: $this->detectBasePackageAnomalies($targetMonth)
        );
    }

    /** @return list<CountCorrelation> */
    private function detectCountCorrelations(int $targetMonth, int $previousMonth): array
    {
        $sql = <<<'SQL'
            WITH deltas AS (
                SELECT
                    curr.name,
                    curr.count - COALESCE(prev.count, 0) as delta
                FROM package curr
                LEFT JOIN package prev ON curr.name = prev.name AND prev.month = :previousMonth
                WHERE curr.month = :targetMonth
                  AND curr.count - COALESCE(prev.count, 0) >= :minDelta
            )
            SELECT delta, GROUP_CONCAT(name ORDER BY name SEPARATOR ',') as packages, COUNT(*) as num_packages
            FROM deltas
            GROUP BY delta
            HAVING COUNT(*) >= 3
            ORDER BY delta DESC
            LIMIT 50
            SQL;

        /** @var list<array{delta: string, packages: string, num_packages: string}> $results */
        $results = $this->connection->executeQuery($sql, [
            'targetMonth' => $targetMonth,
            'previousMonth' => $previousMonth,
            'minDelta' => $this->thresholds->minCorrelationCount,
        ])->fetchAllAssociative();

        return array_map(
            fn(array $row): CountCorrelation => new CountCorrelation(
                (int)$row['delta'],
                (int)$row['num_packages'],
                explode(',', $row['packages'])
            ),
            $results
        );
    }

    /** @return list<Spike> */
    private function detectNewSpikes(string $table, string $idColumn, int $targetMonth, int $baselineStart): array
    {
        $sql = <<<SQL
            SELECT t.{$idColumn} as identifier, t.count
            FROM {$table} t
            WHERE t.month = :targetMonth
              AND t.count >= :minCount
              AND NOT EXISTS (
                  SELECT 1 FROM {$table} t2
                  WHERE t2.{$idColumn} = t.{$idColumn} AND t2.month >= :baselineStart AND t2.month < :targetMonth
              )
            ORDER BY t.count DESC
            LIMIT 50
            SQL;

        /** @var list<array{identifier: string, count: string}> $results */
        $results = $this->connection->executeQuery($sql, [
            'targetMonth' => $targetMonth,
            'baselineStart' => $baselineStart,
            'minCount' => $this->thresholds->minCorrelationCount,
        ])->fetchAllAssociative();

        return array_map(
            fn(array $row): Spike => new Spike($row['identifier'], (int)$row['count']),
            $results
        );
    }

    /** @return list<GrowthAnomaly> */
    private function detectGrowthAnomalies(
        string $table,
        string $idColumn,
        int $targetMonth,
        int $baselineStart,
        int $baselineEnd
    ): array {
        $sql = <<<SQL
            WITH baseline AS (
                SELECT {$idColumn}, AVG(count) as avg_count
                FROM {$table}
                WHERE month >= :baselineStart AND month <= :baselineEnd
                GROUP BY {$idColumn}
                HAVING COUNT(*) >= 3
            ),
            target AS (
                SELECT {$idColumn}, count FROM {$table} WHERE month = :targetMonth
            )
            SELECT
                t.{$idColumn} as identifier,
                t.count as target_count,
                b.avg_count as baseline_avg,
                ((t.count - b.avg_count) / b.avg_count) * 100 as growth_percent
            FROM target t
            JOIN baseline b ON t.{$idColumn} = b.{$idColumn}
            WHERE b.avg_count >= :minBaseline
              AND ((t.count - b.avg_count) / b.avg_count) * 100 > :growthThreshold
            ORDER BY growth_percent DESC
            LIMIT 50
            SQL;

        /** @var list<array{identifier: string, target_count: string, baseline_avg: string, growth_percent: string}> $results */
        $results = $this->connection->executeQuery($sql, [
            'baselineStart' => $baselineStart,
            'baselineEnd' => $baselineEnd,
            'targetMonth' => $targetMonth,
            'minBaseline' => $this->thresholds->minBaselineCount,
            'growthThreshold' => $this->thresholds->growthThreshold,
        ])->fetchAllAssociative();

        return array_map(
            fn(array $row): GrowthAnomaly => new GrowthAnomaly(
                $row['identifier'],
                (int)$row['target_count'],
                round((float)$row['baseline_avg'], 2),
                round((float)$row['growth_percent'], 2)
            ),
            $results
        );
    }

    private function detectBasePackageAnomalies(int $targetMonth): BasePackageResult
    {
        $emptyResult = new BasePackageResult(0, [], []);

        if (empty($this->expectedPackages)) {
            return $emptyResult;
        }

        $baseCounts = $this->fetchExpectedPackageCounts($targetMonth);
        if (empty($baseCounts)) {
            return $emptyResult;
        }

        $median = $this->calculateMedian(array_values($baseCounts));
        $threshold = $median * $this->thresholds->basePackageDeviationThreshold;

        return new BasePackageResult(
            $median,
            $this->findBasePackageOutliers($baseCounts, $median, $threshold),
            $this->findPackagesAboveBaseThreshold($targetMonth, $median, $threshold)
        );
    }

    /** @return array<string, int> */
    private function fetchExpectedPackageCounts(int $targetMonth): array
    {
        $placeholders = implode(',', array_fill(0, count($this->expectedPackages), '?'));
        $sql = "SELECT name, count FROM package WHERE month = ? AND name IN ({$placeholders})";

        /** @var list<array{name: string, count: string}> $results */
        $results = $this->connection->executeQuery(
            $sql,
            array_merge([$targetMonth], $this->expectedPackages)
        )->fetchAllAssociative();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['name']] = (int)$row['count'];
        }

        return $counts;
    }

    /** @param list<int> $values */
    private function calculateMedian(array $values): int
    {
        sort($values);
        $count = count($values);
        $mid = (int)floor($count / 2);

        return $count % 2 === 0
            ? (int)(($values[$mid - 1] + $values[$mid]) / 2)
            : $values[$mid];
    }

    /**
     * @param array<string, int> $baseCounts
     * @return list<PackageRatio>
     */
    private function findBasePackageOutliers(array $baseCounts, int $median, float $threshold): array
    {
        $outliers = [];
        foreach ($baseCounts as $name => $count) {
            if ($count > $threshold) {
                $outliers[] = new PackageRatio($name, $count, round($count / $median, 2));
            }
        }
        usort($outliers, fn(PackageRatio $a, PackageRatio $b): int => $b->count <=> $a->count);

        return $outliers;
    }

    /** @return list<PackageRatio> */
    private function findPackagesAboveBaseThreshold(int $targetMonth, int $median, float $threshold): array
    {
        $placeholders = implode(',', array_fill(0, count($this->expectedPackages), '?'));
        $sql = <<<SQL
            SELECT name, count
            FROM package
            WHERE month = ? AND count > ? AND name NOT IN ({$placeholders})
            ORDER BY count DESC
            LIMIT 50
            SQL;

        /** @var list<array{name: string, count: string}> $results */
        $results = $this->connection->executeQuery(
            $sql,
            array_merge([$targetMonth, $threshold], $this->expectedPackages)
        )->fetchAllAssociative();

        return array_map(
            fn(array $row): PackageRatio => new PackageRatio(
                $row['name'],
                (int)$row['count'],
                round((int)$row['count'] / $median, 2)
            ),
            $results
        );
    }
}
